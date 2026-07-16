<?php
/**
 * Mise à jour automatique depuis GitHub — v2.3
 *
 * Permet de développer le plugin sur son ordinateur, publier une "release"
 * sur GitHub, et voir apparaître le bouton "Mettre à jour" habituel de
 * WordPress sur younessweb.me — comme n'importe quel plugin du dépôt officiel.
 *
 * Fonctionne avec un dépôt PRIVÉ : le token GitHub est stocké en base
 * (option `vb_github_token`), jamais dans le code.
 *
 * Publier une mise à jour :
 *   1. Changer "Version:" dans vendbase-manager.php (ex: 2.3.0 -> 2.3.1)
 *   2. gh release create v2.3.1 younessWeb-manager.zip
 *   3. Sur WordPress : Extensions > Mettre à jour
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VB_GITHUB_REPO', 'younesMasro/younessWeb-manager' );

function vb_updater_slug()     { return 'younessWeb-manager'; }
function vb_updater_basename() { return vb_updater_slug() . '/vendbase-manager.php'; }

/** Entêtes d'appel à l'API GitHub (avec token si dépôt privé). */
function vb_github_headers( $accept = 'application/vnd.github+json' ) {
    $headers = [
        'Accept'               => $accept,
        'User-Agent'           => 'YounessWeb-Manager/' . VB_VERSION,
        'X-GitHub-Api-Version' => '2022-11-28',
    ];
    $token = get_option( 'vb_github_token' );
    if ( $token ) $headers['Authorization'] = 'Bearer ' . $token;
    return $headers;
}

/**
 * Récupère la dernière release publiée sur GitHub.
 * Résultat mis en cache 6h pour ne pas ralentir l'admin ni épuiser le quota.
 *
 * @param bool $force Ignore le cache (bouton "Vérifier maintenant").
 * @return array|WP_Error
 */
function vb_get_latest_release( $force = false ) {
    $cache_key = 'vb_latest_release';

    if ( ! $force ) {
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) return $cached;
    }

    $response = wp_remote_get(
        'https://api.github.com/repos/' . VB_GITHUB_REPO . '/releases/latest',
        [ 'headers' => vb_github_headers(), 'timeout' => 10 ]
    );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code === 404 ) {
        return new WP_Error( 'vb_no_release', 'Aucune release publiée sur GitHub (ou dépôt privé sans token valide).' );
    }
    if ( $code === 401 || $code === 403 ) {
        return new WP_Error( 'vb_bad_token', 'GitHub refuse le token (expiré, ou sans accès au dépôt).' );
    }
    if ( $code !== 200 ) {
        return new WP_Error( 'vb_github_error', 'GitHub a répondu HTTP ' . $code );
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
        return new WP_Error( 'vb_bad_response', 'Réponse GitHub illisible.' );
    }

    // "v2.3.1" -> "2.3.1"
    $release = [
        'version'    => ltrim( $body['tag_name'], 'vV' ),
        'changelog'  => $body['body'] ?? '',
        'published'  => $body['published_at'] ?? '',
        'zip_url'    => '',
        'asset_id'   => 0,
        'html_url'   => $body['html_url'] ?? '',
    ];

    // On privilégie le .zip attaché à la release (le zipball GitHub ajoute
    // un dossier parent au nom aléatoire qui casserait l'installation).
    foreach ( $body['assets'] ?? [] as $asset ) {
        if ( substr( $asset['name'] ?? '', -4 ) === '.zip' ) {
            $release['zip_url']  = $asset['url']; // URL API : marche aussi en privé
            $release['asset_id'] = $asset['id'];
            break;
        }
    }
    if ( ! $release['zip_url'] ) {
        $release['zip_url'] = $body['zipball_url'] ?? '';
    }

    set_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );
    return $release;
}

/** Signale à WordPress qu'une mise à jour est disponible. */
add_filter( 'pre_set_site_transient_update_plugins', 'vb_check_for_update' );
function vb_check_for_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $release = vb_get_latest_release();
    if ( is_wp_error( $release ) || empty( $release['version'] ) ) return $transient;

    if ( version_compare( $release['version'], VB_VERSION, '<=' ) ) return $transient;

    $transient->response[ vb_updater_basename() ] = (object) [
        'slug'        => vb_updater_slug(),
        'plugin'      => vb_updater_basename(),
        'new_version' => $release['version'],
        'package'     => $release['zip_url'],
        'url'         => $release['html_url'],
        'tested'      => get_bloginfo( 'version' ),
    ];

    return $transient;
}

/**
 * Télécharge le ZIP depuis l'API GitHub.
 *
 * Deux entêtes sont indispensables, et WordPress ne les met pas tout seul :
 *  - Accept: octet-stream  → sinon GitHub renvoie la FICHE JSON de l'asset
 *    au lieu du binaire, et WordPress installe un "zip" corrompu.
 *    Nécessaire sur dépôt public ET privé.
 *  - Authorization         → uniquement si dépôt privé (token renseigné).
 */
add_filter( 'http_request_args', 'vb_authorize_github_download', 10, 2 );
function vb_authorize_github_download( $args, $url ) {
    if ( strpos( $url, 'api.github.com/repos/' . VB_GITHUB_REPO ) === false ) return $args;

    $args['headers'] = array_merge(
        $args['headers'] ?? [],
        [ 'User-Agent' => 'YounessWeb-Manager/' . VB_VERSION ]
    );

    // Le binaire de l'asset : vaut pour public comme privé.
    if ( strpos( $url, '/releases/assets/' ) !== false ) {
        $args['headers']['Accept'] = 'application/octet-stream';
    }

    $token = get_option( 'vb_github_token' );
    if ( $token ) {
        $args['headers']['Authorization'] = 'Bearer ' . $token;
    }

    return $args;
}

/**
 * Après extraction, GitHub nomme le dossier "repo-abc1234".
 * On le renomme pour que WordPress retrouve le plugin au bon endroit,
 * sinon la mise à jour le désactive silencieusement.
 */
add_filter( 'upgrader_source_selection', 'vb_fix_github_folder_name', 10, 4 );
function vb_fix_github_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
    if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== vb_updater_basename() ) {
        return $source;
    }

    global $wp_filesystem;
    $desired = trailingslashit( $remote_source ) . vb_updater_slug();

    if ( trailingslashit( $source ) === trailingslashit( $desired ) ) return $source;

    if ( $wp_filesystem->move( $source, $desired, true ) ) {
        return trailingslashit( $desired );
    }
    return new WP_Error( 'vb_rename_failed', 'Impossible de renommer le dossier du plugin après téléchargement.' );
}

/** Fiche détaillée affichée par WordPress ("Voir les détails"). */
add_filter( 'plugins_api', 'vb_plugin_info', 20, 3 );
function vb_plugin_info( $result, $action, $args ) {
    if ( $action !== 'plugin_information' ) return $result;
    if ( empty( $args->slug ) || $args->slug !== vb_updater_slug() ) return $result;

    $release = vb_get_latest_release();
    if ( is_wp_error( $release ) ) return $result;

    return (object) [
        'name'          => 'YounessWeb Manager',
        'slug'          => vb_updater_slug(),
        'version'       => $release['version'],
        'author'        => 'Younes Web',
        'homepage'      => $release['html_url'],
        'download_link' => $release['zip_url'],
        'last_updated'  => $release['published'],
        'sections'      => [
            'description' => 'Gestion des projets, demandes clients, dépenses et sauvegardes de YounessWeb.',
            'changelog'   => nl2br( esc_html( $release['changelog'] ) ),
        ],
    ];
}

/** Vide le cache après une mise à jour réussie. */
add_action( 'upgrader_process_complete', function ( $upgrader, $options ) {
    if ( ( $options['action'] ?? '' ) === 'update' && ( $options['type'] ?? '' ) === 'plugin' ) {
        delete_transient( 'vb_latest_release' );
    }
}, 10, 2 );

/* ── Réglages : token GitHub ── */
add_action( 'admin_post_vb_save_github_token', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );
    check_admin_referer( 'vb_github_token' );

    update_option( 'vb_github_token', sanitize_text_field( $_POST['github_token'] ?? '' ), false );
    delete_transient( 'vb_latest_release' );

    wp_safe_redirect( add_query_arg( 'vb_done', rawurlencode( 'Token GitHub enregistré.' ), admin_url( 'admin.php?page=vendbase-backup' ) ) );
    exit;
} );

/* ── Vérification manuelle (AJAX) ── */
add_action( 'wp_ajax_vb_check_update', function () {
    check_ajax_referer( 'vb_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $release = vb_get_latest_release( true );
    if ( is_wp_error( $release ) ) wp_send_json_error( $release->get_error_message() );

    // Force WordPress à re-vérifier pour afficher tout de suite le bouton.
    delete_site_transient( 'update_plugins' );

    wp_send_json_success( [
        'current'   => VB_VERSION,
        'latest'    => $release['version'],
        'available' => version_compare( $release['version'], VB_VERSION, '>' ),
        'changelog' => $release['changelog'],
        'url'       => admin_url( 'plugins.php' ),
    ] );
} );
