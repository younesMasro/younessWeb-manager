<?php
/**
 * Sauvegarde & restauration — v2.3
 *
 * Objectif : en un clic, télécharger sur son ordinateur TOUT ce que contient
 * ce plugin (code + données), de façon à pouvoir tout remonter même si
 * l'hébergement est mort, non renouvelé, ou le site inaccessible.
 *
 * Le ZIP produit contient :
 *   data/*.json  — sauvegarde exacte, sert à la restauration
 *   data/*.csv   — mêmes données, ouvrables dans Excel / Google Sheets
 *   plugin/      — le code complet du plugin (réinstallable tel quel)
 *   LISEZMOI.txt — la marche à suivre pour tout restaurer
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Tables gérées par le plugin, dans l'ordre de restauration.
 *
 * ⚠️ Toute table ajoutée au plugin DOIT être déclarée ici, sinon elle est
 * silencieusement absente des sauvegardes. C'était le cas des factures
 * (`vb_invoices`) jusqu'à la v2.7 : la table était créée par le template mais
 * jamais exportée. Corrigé en même temps que l'arrivée des contrats.
 */
function vb_backup_tables() {
    global $wpdb;
    return [
        'projects'  => $wpdb->prefix . 'vb_projects',
        'leads'     => $wpdb->prefix . 'vb_leads',
        'expenses'  => $wpdb->prefix . 'vb_expenses',
        'contracts' => $wpdb->prefix . 'vb_contracts',
        'invoices'  => $wpdb->prefix . 'vb_invoices',
    ];
}

/**
 * Colonnes sensibles : identifiants d'accès aux sites des clients.
 * Elles sont exclues du backup si l'utilisateur décoche l'option.
 */
function vb_backup_sensitive_columns() {
    return [ 'admin_pass', 'admin_user', 'admin_url' ];
}

/** Construit le contenu complet de la sauvegarde sous forme de tableau. */
function vb_build_backup_data( $include_credentials = true ) {
    global $wpdb;

    $data = [
        'meta' => [
            'plugin'         => 'YounessWeb Manager',
            'plugin_version' => VB_VERSION,
            'exported_at'    => current_time( 'mysql' ),
            'site_url'       => get_site_url(),
            'wp_version'     => get_bloginfo( 'version' ),
            'db_prefix'      => $wpdb->prefix,
            'has_credentials'=> (bool) $include_credentials,
        ],
        'tables'  => [],
        'options' => [
            // La clé API du formulaire : sans elle, le site Next.js ne peut
            // plus envoyer de demandes après une restauration.
            'vb_lead_api_secret'     => get_option( 'vb_lead_api_secret' ),
            // Idem pour le canal WhatsApp : sans elle, le Cloudflare Worker
            // ne peut plus déposer de leads après une restauration.
            'vb_whatsapp_api_secret' => get_option( 'vb_whatsapp_api_secret' ),
            // Modèles de premier message WhatsApp personnalisés.
            'vb_wa_templates'        => get_option( 'vb_wa_templates' ),
            'vb_wa_fallback_lang'    => get_option( 'vb_wa_fallback_lang' ),
            // Contrats : modèles réécrits et coordonnées du prestataire.
            'vb_contract_templates'  => get_option( 'vb_contract_templates' ),
            'vb_contract_provider'   => get_option( 'vb_contract_provider' ),
        ],
    ];

    foreach ( vb_backup_tables() as $key => $table ) {
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
            $data['tables'][ $key ] = [];
            continue;
        }

        $rows = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A );
        if ( ! $include_credentials ) {
            $sensitive = vb_backup_sensitive_columns();
            foreach ( $rows as &$row ) {
                foreach ( $sensitive as $col ) {
                    if ( array_key_exists( $col, $row ) ) $row[ $col ] = '';
                }
            }
            unset( $row );
        }
        $data['tables'][ $key ] = $rows;
    }

    return $data;
}

/** Convertit des lignes en CSV (avec BOM UTF-8 pour Excel + accents). */
function vb_rows_to_csv( $rows ) {
    if ( empty( $rows ) ) return '';

    $out = fopen( 'php://temp', 'r+' );
    fwrite( $out, "\xEF\xBB\xBF" ); // BOM : sinon Excel casse les accents
    fputcsv( $out, array_keys( $rows[0] ) );
    foreach ( $rows as $row ) {
        fputcsv( $out, array_map( function ( $v ) {
            return is_null( $v ) ? '' : (string) $v;
        }, $row ) );
    }
    rewind( $out );
    $csv = stream_get_contents( $out );
    fclose( $out );
    return $csv;
}

/** Ajoute récursivement les fichiers du plugin dans le ZIP. */
function vb_zip_add_plugin_source( ZipArchive $zip ) {
    $root  = rtrim( VB_PLUGIN_DIR, '/\\' );
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ( $files as $file ) {
        if ( $file->isDir() ) continue;
        $path = $file->getRealPath();
        $rel  = ltrim( str_replace( $root, '', $path ), '/\\' );

        // On n'embarque pas les sauvegardes déjà générées ni les fichiers système.
        if ( strpos( $rel, 'backups/' ) === 0 ) continue;
        if ( basename( $rel ) === '.DS_Store' ) continue;

        $zip->addFile( $path, 'plugin/younessWeb-manager/' . $rel );
    }
}

/** Texte d'instructions inclus dans le ZIP. */
function vb_backup_readme( $meta ) {
    $creds = $meta['has_credentials']
        ? "OUI — ce fichier contient les identifiants d'accès aux sites clients.\n   Garde-le en lieu sûr, ne l'envoie a personne, ne le mets pas sur Drive public."
        : "NON — les identifiants des sites clients ont ete exclus de cette sauvegarde.";

    return <<<TXT
SAUVEGARDE YOUNESSWEB MANAGER
=============================
Date d'export : {$meta['exported_at']}
Site d'origine : {$meta['site_url']}
Version plugin : {$meta['plugin_version']}
Prefixe base   : {$meta['db_prefix']}

Identifiants clients inclus : {$creds}


CE QUE CONTIENT CE FICHIER
--------------------------
data/backup.json     La sauvegarde complete. C'est CE fichier qu'il faut
                     re-importer pour tout restaurer. Ne pas le modifier.
data/projects.csv    Tes projets, ouvrables dans Excel / Google Sheets.
data/leads.csv       Les demandes recues via le formulaire du site.
data/expenses.csv    Tes depenses et budgets pub.
data/contracts.csv   Tes contrats clients (texte, montants, signatures).
data/invoices.csv    Tes factures et devis.
plugin/              Le code complet du plugin, pret a etre reinstalle.


SI LE SITE EST MORT (hebergement non renouvele, panne, piratage)
----------------------------------------------------------------
1. Installe un WordPress neuf (n'importe ou, meme en local).
2. Copie le dossier  plugin/younessWeb-manager  dans  wp-content/plugins/
   puis active "YounessWeb Manager" dans les extensions.
3. Va dans  YounessWeb > Sauvegarde  et importe le fichier  data/backup.json
4. Tout revient : projets, demandes, depenses, et la cle API du formulaire.

Si tu n'as meme plus WordPress sous la main, les fichiers .csv restent
lisibles dans Excel : tes donnees ne sont jamais prisonnieres du plugin.


LA CLE API DU FORMULAIRE
------------------------
La sauvegarde contient la cle qui relie younessweb.com a ce plugin.
Apres une restauration sur une NOUVELLE adresse, pense a mettre a jour
WP_LEADS_ENDPOINT dans Vercel avec la nouvelle URL du site.
TXT;
}

/* ============================================================
   TELECHARGEMENT DU ZIP  (admin-post)
============================================================ */
add_action( 'admin_post_vb_export_backup', 'vb_handle_export_backup' );
function vb_handle_export_backup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'vb_export_backup' );

    $include_credentials = ! empty( $_GET['credentials'] );
    $data = vb_build_backup_data( $include_credentials );

    $stamp    = date( 'Y-m-d_H-i' );
    $filename = "younessweb-backup_{$stamp}.zip";

    if ( ! class_exists( 'ZipArchive' ) ) {
        // Repli : pas de ZIP sur ce serveur, on sert le JSON brut.
        // Mieux vaut une sauvegarde moins pratique que pas de sauvegarde.
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="younessweb-backup_' . $stamp . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    $tmp = wp_tempnam( $filename );
    $zip = new ZipArchive();
    if ( $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
        wp_die( 'Impossible de créer le fichier ZIP.' );
    }

    $zip->addFromString( 'data/backup.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    foreach ( $data['tables'] as $key => $rows ) {
        $csv = vb_rows_to_csv( $rows );
        if ( $csv !== '' ) $zip->addFromString( "data/{$key}.csv", $csv );
    }
    $zip->addFromString( 'LISEZMOI.txt', vb_backup_readme( $data['meta'] ) );
    vb_zip_add_plugin_source( $zip );
    $zip->close();

    update_option( 'vb_last_backup_at', current_time( 'mysql' ), false );

    nocache_headers();
    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . filesize( $tmp ) );
    readfile( $tmp );
    @unlink( $tmp );
    exit;
}

/* ============================================================
   RESTAURATION
============================================================ */

/**
 * Restaure une sauvegarde.
 *
 * @param array  $data Contenu de backup.json décodé.
 * @param string $mode 'replace' (vide les tables d'abord) ou 'merge' (ajoute).
 * @return array|WP_Error Compteurs par table.
 */
function vb_restore_backup( $data, $mode = 'merge' ) {
    global $wpdb;

    if ( empty( $data['tables'] ) || ! is_array( $data['tables'] ) ) {
        return new WP_Error( 'vb_bad_backup', 'Fichier de sauvegarde invalide ou illisible.' );
    }

    // Les tables doivent exister avant toute insertion.
    vb_create_tables();
    vb_create_expenses_table();
    vb_create_leads_table();
    vb_create_contracts_table();
    vb_create_invoices_table();

    $tables   = vb_backup_tables();
    $imported = [];

    foreach ( $tables as $key => $table ) {
        if ( ! isset( $data['tables'][ $key ] ) ) continue;
        $rows = $data['tables'][ $key ];

        if ( $mode === 'replace' ) {
            $wpdb->query( "DELETE FROM $table" );
        }

        // Colonnes réellement présentes : protège contre un backup fait avec
        // une version différente du plugin (colonne ajoutée ou retirée).
        $columns = $wpdb->get_col( "DESC $table", 0 );
        $count   = 0;

        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) continue;

            $clean = [];
            foreach ( $row as $col => $val ) {
                if ( ! in_array( $col, $columns, true ) ) continue;
                // 'reste' est une colonne calculée par MySQL : l'écrire ferait échouer l'insert.
                if ( $col === 'reste' ) continue;
                if ( $mode === 'merge' && $col === 'id' ) continue; // laisse MySQL attribuer un id neuf
                $clean[ $col ] = $val;
            }
            if ( empty( $clean ) ) continue;

            if ( $wpdb->insert( $table, $clean ) !== false ) $count++;
        }
        $imported[ $key ] = $count;
    }

    // Restaure les clés API pour que le formulaire du site et le Worker
    // WhatsApp refonctionnent, ainsi que les modèles de messages.
    foreach ( [ 'vb_lead_api_secret', 'vb_whatsapp_api_secret', 'vb_wa_templates', 'vb_wa_fallback_lang',
                'vb_contract_templates', 'vb_contract_provider' ] as $opt ) {
        if ( ! empty( $data['options'][ $opt ] ) ) {
            update_option( $opt, $data['options'][ $opt ], false );
        }
    }

    return $imported;
}

add_action( 'admin_post_vb_import_backup', 'vb_handle_import_backup' );
function vb_handle_import_backup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }
    check_admin_referer( 'vb_import_backup' );

    $redirect = admin_url( 'admin.php?page=vendbase-backup' );

    if ( empty( $_FILES['backup_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['backup_file']['tmp_name'] ) ) {
        wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( 'Aucun fichier reçu.' ), $redirect ) );
        exit;
    }

    $tmp_path = $_FILES['backup_file']['tmp_name'];
    $name     = strtolower( $_FILES['backup_file']['name'] );
    $json     = '';

    if ( substr( $name, -4 ) === '.zip' ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( 'ZIP non supporté sur ce serveur : importe le fichier data/backup.json directement.' ), $redirect ) );
            exit;
        }
        $zip = new ZipArchive();
        if ( $zip->open( $tmp_path ) !== true ) {
            wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( 'ZIP illisible.' ), $redirect ) );
            exit;
        }
        $json = $zip->getFromName( 'data/backup.json' );
        $zip->close();
        if ( $json === false ) {
            wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( 'data/backup.json introuvable dans le ZIP.' ), $redirect ) );
            exit;
        }
    } else {
        $json = file_get_contents( $tmp_path );
    }

    $data = json_decode( $json, true );
    if ( ! is_array( $data ) ) {
        wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( 'Fichier JSON invalide.' ), $redirect ) );
        exit;
    }

    $mode   = ( ( $_POST['mode'] ?? 'merge' ) === 'replace' ) ? 'replace' : 'merge';
    $result = vb_restore_backup( $data, $mode );

    if ( is_wp_error( $result ) ) {
        wp_safe_redirect( add_query_arg( 'vb_error', rawurlencode( $result->get_error_message() ), $redirect ) );
        exit;
    }

    $summary = sprintf(
        '%d projets, %d demandes, %d dépenses, %d contrats, %d factures restaurés.',
        $result['projects'] ?? 0,
        $result['leads'] ?? 0,
        $result['expenses'] ?? 0,
        $result['contracts'] ?? 0,
        $result['invoices'] ?? 0
    );
    wp_safe_redirect( add_query_arg( 'vb_done', rawurlencode( $summary ), $redirect ) );
    exit;
}

/* ============================================================
   SAUVEGARDE AUTOMATIQUE PAR EMAIL (hebdomadaire, optionnelle)
============================================================ */
add_action( 'vb_weekly_backup_event', 'vb_send_weekly_backup' );

function vb_send_weekly_backup() {
    if ( ! get_option( 'vb_auto_backup_enabled' ) ) return;

    $to = get_option( 'vb_auto_backup_email' );
    if ( ! is_email( $to ) ) return;

    $data  = vb_build_backup_data( false ); // jamais d'identifiants clients par email
    $stamp = date( 'Y-m-d' );
    $tmp   = trailingslashit( get_temp_dir() ) . "younessweb-backup_{$stamp}.json";

    file_put_contents( $tmp, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

    $counts = array_map( 'count', $data['tables'] );
    $body   = sprintf(
        "Sauvegarde automatique de YounessWeb Manager du %s.\n\n" .
        "Projets : %d\nDemandes : %d\nDépenses : %d\n\n" .
        "Les identifiants d'accès aux sites clients ne sont jamais envoyés par email.\n" .
        "Pour une sauvegarde complète, utilise YounessWeb > Sauvegarde.\n",
        $stamp, $counts['projects'] ?? 0, $counts['leads'] ?? 0, $counts['expenses'] ?? 0
    );

    wp_mail( $to, "Sauvegarde YounessWeb — {$stamp}", $body, [], [ $tmp ] );
    @unlink( $tmp );
    update_option( 'vb_last_auto_backup_at', current_time( 'mysql' ), false );
}

/** Active/désactive la tâche planifiée selon le réglage. */
function vb_sync_backup_schedule() {
    $enabled   = (bool) get_option( 'vb_auto_backup_enabled' );
    $scheduled = wp_next_scheduled( 'vb_weekly_backup_event' );

    if ( $enabled && ! $scheduled ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'vb_weekly_backup_event' );
    } elseif ( ! $enabled && $scheduled ) {
        wp_unschedule_event( $scheduled, 'vb_weekly_backup_event' );
    }
}

add_action( 'admin_post_vb_save_backup_settings', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );
    check_admin_referer( 'vb_backup_settings' );

    update_option( 'vb_auto_backup_enabled', ! empty( $_POST['auto_backup_enabled'] ) ? 1 : 0, false );
    update_option( 'vb_auto_backup_email', sanitize_email( $_POST['auto_backup_email'] ?? '' ), false );
    vb_sync_backup_schedule();

    wp_safe_redirect( add_query_arg( 'vb_done', rawurlencode( 'Réglages enregistrés.' ), admin_url( 'admin.php?page=vendbase-backup' ) ) );
    exit;
} );
