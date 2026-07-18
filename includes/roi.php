<?php
/**
 * Rentabilité publicitaire (ROI / ROAS) — v2.4
 *
 * Croise trois sources déjà présentes dans le plugin :
 *   - vb_projects  → le chiffre d'affaires (prix facturé, avance encaissée)
 *   - vb_expenses  → les dépenses publicitaires par canal
 *   - vb_leads     → l'origine des demandes (utm_source), et leur conversion
 *
 * Deux niveaux de lecture :
 *   1. VUE D'ENSEMBLE (blended) — CA total vs dépenses totales. Toujours fiable,
 *      même sans tracking : « j'ai gagné X, dépensé Y, il me reste Z ».
 *   2. PAR CANAL (attribution) — se remplit dès que les liens publicitaires
 *      portent un ?utm_source=facebook|instagram|google|tiktok. Donne le coût
 *      par lead, le coût par client signé et le ROAS de chaque canal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Définition des canaux publicitaires et de leurs alias utm_source. */
function vb_roi_channels() {
    return [
        'facebook'  => [
            'label'    => 'Facebook',
            'category' => 'ads_facebook',
            'utm'      => [ 'facebook', 'fb', 'meta', 'facebook_ads', 'fb_ads' ],
            'color'    => 'blue',
            'icon'     => 'facebook',
        ],
        'instagram' => [
            'label'    => 'Instagram',
            'category' => 'ads_instagram',
            'utm'      => [ 'instagram', 'ig', 'insta', 'instagram_ads' ],
            'color'    => 'purple',
            'icon'     => 'instagram',
        ],
        'google'    => [
            'label'    => 'Google',
            'category' => 'ads_google',
            'utm'      => [ 'google', 'adwords', 'google_ads', 'gads', 'sem' ],
            'color'    => 'green',
            'icon'     => 'google',
        ],
        'tiktok'    => [
            'label'    => 'TikTok',
            'category' => 'ads_tiktok',
            'utm'      => [ 'tiktok', 'tt', 'tiktok_ads' ],
            'color'    => 'red',
            'icon'     => 'video-alt3',
        ],
    ];
}

/** Liste SQL des catégories publicitaires, échappée. */
function vb_roi_ad_categories_sql() {
    $cats = array_column( vb_roi_channels(), 'category' );
    return "'" . implode( "','", array_map( 'esc_sql', $cats ) ) . "'";
}

/**
 * Vue d'ensemble (blended) sur une période.
 * Utilise TOUS les projets et TOUTES les dépenses, sans attribution.
 */
function vb_get_roi_overview( $year = '', $month = '' ) {
    global $wpdb;
    $pt = $wpdb->prefix . 'vb_projects';
    $et = $wpdb->prefix . 'vb_expenses';

    // Chiffre d'affaires (par date de création du projet).
    $pw = []; $pp = [];
    if ( $month && $year ) { $pw[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d'; $pp[] = intval($month); $pp[] = intval($year); }
    elseif ( $year )       { $pw[] = 'YEAR(created_at) = %d'; $pp[] = intval($year); }
    $pwsql = $pw ? 'WHERE ' . implode( ' AND ', $pw ) : '';

    $rev_sql = "SELECT
        COALESCE(SUM(prix), 0)   AS revenue,
        COALESCE(SUM(avance), 0) AS received,
        COUNT(*)                 AS projects
        FROM $pt $pwsql";
    $rev = $pp ? $wpdb->get_row( $wpdb->prepare( $rev_sql, $pp ) ) : $wpdb->get_row( $rev_sql );

    // Dépenses.
    $ew = []; $ep = [];
    if ( $month && $year ) { $ew[] = 'month = %d AND year = %d'; $ep[] = intval($month); $ep[] = intval($year); }
    elseif ( $year )       { $ew[] = 'year = %d'; $ep[] = intval($year); }
    $ewsql = $ew ? 'WHERE ' . implode( ' AND ', $ew ) : '';

    $ad_cats = vb_roi_ad_categories_sql();
    $exp_sql = "SELECT
        COALESCE(SUM(amount), 0) AS total_expenses,
        COALESCE(SUM(CASE WHEN category IN ($ad_cats) THEN amount ELSE 0 END), 0) AS ad_spend
        FROM $et $ewsql";
    $exp = $ep ? $wpdb->get_row( $wpdb->prepare( $exp_sql, $ep ) ) : $wpdb->get_row( $exp_sql );

    $revenue        = floatval( $rev->revenue ?? 0 );
    $received       = floatval( $rev->received ?? 0 );
    $total_expenses = floatval( $exp->total_expenses ?? 0 );
    $ad_spend       = floatval( $exp->ad_spend ?? 0 );

    return [
        'revenue'        => $revenue,
        'received'       => $received,
        'projects'       => intval( $rev->projects ?? 0 ),
        'total_expenses' => $total_expenses,
        'ad_spend'       => $ad_spend,
        'net_profit'     => $revenue - $total_expenses,
        // ROAS = CA généré pour 1 MAD de pub. null si aucune dépense pub.
        'blended_roas'   => $ad_spend > 0 ? $revenue / $ad_spend : null,
        // Part du CA absorbée par la pub.
        'ad_cost_ratio'  => $revenue > 0 ? ( $ad_spend / $revenue ) * 100 : null,
    ];
}

/**
 * Détail par canal, avec attribution via utm_source des leads.
 * Retourne un tableau indexé par clé de canal.
 */
function vb_get_roi_by_channel( $year = '', $month = '' ) {
    global $wpdb;
    $pt = $wpdb->prefix . 'vb_projects';
    $et = $wpdb->prefix . 'vb_expenses';
    $lt = $wpdb->prefix . 'vb_leads';

    $channels = vb_roi_channels();

    // Structure de sortie initialisée à zéro.
    $out = [];
    foreach ( $channels as $key => $c ) {
        $out[ $key ] = [
            'label'    => $c['label'],
            'color'    => $c['color'],
            'icon'     => $c['icon'],
            'spend'    => 0.0,
            'leads'    => 0,
            'won'      => 0,
            'revenue'  => 0.0,
            'received' => 0.0,
        ];
    }

    // Table de correspondance catégorie → canal, et utm → canal.
    $cat2ch = []; $utm2ch = [];
    foreach ( $channels as $key => $c ) {
        $cat2ch[ $c['category'] ] = $key;
        foreach ( $c['utm'] as $u ) $utm2ch[ $u ] = $key;
    }

    // 1) Dépenses par catégorie publicitaire.
    $ew = []; $ep = [];
    if ( $month && $year ) { $ew[] = 'month = %d AND year = %d'; $ep[] = intval($month); $ep[] = intval($year); }
    elseif ( $year )       { $ew[] = 'year = %d'; $ep[] = intval($year); }
    $ewsql   = $ew ? 'AND ' . implode( ' AND ', $ew ) : '';
    $ad_cats = vb_roi_ad_categories_sql();
    $sql = "SELECT category, COALESCE(SUM(amount),0) AS spend
            FROM $et WHERE category IN ($ad_cats) $ewsql GROUP BY category";
    $rows = $ep ? $wpdb->get_results( $wpdb->prepare( $sql, $ep ) ) : $wpdb->get_results( $sql );
    foreach ( $rows as $r ) {
        if ( isset( $cat2ch[ $r->category ] ) ) {
            $out[ $cat2ch[ $r->category ] ]['spend'] = floatval( $r->spend );
        }
    }

    // 2) Leads reçus par utm_source (+ combien gagnés).
    $lw = []; $lp = [];
    if ( $month && $year ) { $lw[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d'; $lp[] = intval($month); $lp[] = intval($year); }
    elseif ( $year )       { $lw[] = 'YEAR(created_at) = %d'; $lp[] = intval($year); }
    $lwsql = $lw ? 'AND ' . implode( ' AND ', $lw ) : '';
    $sql = "SELECT LOWER(utm_source) AS src, COUNT(*) AS leads,
                   SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) AS won
            FROM $lt WHERE utm_source <> '' $lwsql GROUP BY LOWER(utm_source)";
    $rows = $lp ? $wpdb->get_results( $wpdb->prepare( $sql, $lp ) ) : $wpdb->get_results( $sql );
    foreach ( $rows as $r ) {
        $ch = $utm2ch[ $r->src ] ?? null;
        if ( $ch ) {
            $out[ $ch ]['leads'] += intval( $r->leads );
            $out[ $ch ]['won']   += intval( $r->won );
        }
    }

    // 3) CA attribué : leads convertis en projets, revenu du projet lié.
    $rw = []; $rp = [];
    if ( $month && $year ) { $rw[] = 'MONTH(l.created_at) = %d AND YEAR(l.created_at) = %d'; $rp[] = intval($month); $rp[] = intval($year); }
    elseif ( $year )       { $rw[] = 'YEAR(l.created_at) = %d'; $rp[] = intval($year); }
    $rwsql = $rw ? 'AND ' . implode( ' AND ', $rw ) : '';
    $sql = "SELECT LOWER(l.utm_source) AS src,
                   COALESCE(SUM(p.prix),0)   AS revenue,
                   COALESCE(SUM(p.avance),0) AS received
            FROM $lt l INNER JOIN $pt p ON l.project_id = p.id
            WHERE l.utm_source <> '' AND l.project_id IS NOT NULL $rwsql
            GROUP BY LOWER(l.utm_source)";
    $rows = $rp ? $wpdb->get_results( $wpdb->prepare( $sql, $rp ) ) : $wpdb->get_results( $sql );
    foreach ( $rows as $r ) {
        $ch = $utm2ch[ $r->src ] ?? null;
        if ( $ch ) {
            $out[ $ch ]['revenue']  += floatval( $r->revenue );
            $out[ $ch ]['received'] += floatval( $r->received );
        }
    }

    // 4) Indicateurs dérivés par canal.
    foreach ( $out as $key => &$c ) {
        $c['cpl']    = $c['leads'] > 0 ? $c['spend'] / $c['leads'] : null; // coût par lead
        $c['cpa']    = $c['won']   > 0 ? $c['spend'] / $c['won']   : null; // coût par client
        $c['roas']   = $c['spend'] > 0 ? $c['revenue'] / $c['spend'] : null;
        $c['profit'] = $c['revenue'] - $c['spend'];
        $c['conv']   = $c['leads'] > 0 ? ( $c['won'] / $c['leads'] ) * 100 : null; // taux de conversion
    }
    unset( $c );

    return $out;
}

/** Combien de leads n'ont AUCUNE source utm (impossible à attribuer). */
function vb_get_roi_untracked_leads( $year = '', $month = '' ) {
    global $wpdb;
    $lt = $wpdb->prefix . 'vb_leads';
    $w = [ "(utm_source = '' OR utm_source IS NULL)" ]; $p = [];
    if ( $month && $year ) { $w[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d'; $p[] = intval($month); $p[] = intval($year); }
    elseif ( $year )       { $w[] = 'YEAR(created_at) = %d'; $p[] = intval($year); }
    $sql = "SELECT COUNT(*) FROM $lt WHERE " . implode( ' AND ', $w );
    return intval( $p ? $wpdb->get_var( $wpdb->prepare( $sql, $p ) ) : $wpdb->get_var( $sql ) );
}

/** Données mensuelles CA vs dépense pub, pour le graphique de tendance. */
function vb_get_roi_monthly( $year = '' ) {
    global $wpdb;
    $year = $year ?: date( 'Y' );
    $pt = $wpdb->prefix . 'vb_projects';
    $et = $wpdb->prefix . 'vb_expenses';

    $rev = $wpdb->get_results( $wpdb->prepare(
        "SELECT MONTH(created_at) AS m, COALESCE(SUM(prix),0) AS revenue
         FROM $pt WHERE YEAR(created_at) = %d GROUP BY MONTH(created_at)",
        intval( $year )
    ), OBJECT_K );

    $ad_cats = vb_roi_ad_categories_sql();
    $spend = $wpdb->get_results( $wpdb->prepare(
        "SELECT month AS m, COALESCE(SUM(amount),0) AS spend
         FROM $et WHERE year = %d AND month IS NOT NULL AND category IN ($ad_cats)
         GROUP BY month",
        intval( $year )
    ), OBJECT_K );

    $out = [];
    for ( $i = 1; $i <= 12; $i++ ) {
        $out[] = [
            'month'   => $i,
            'revenue' => floatval( $rev[ $i ]->revenue ?? 0 ),
            'spend'   => floatval( $spend[ $i ]->spend ?? 0 ),
        ];
    }
    return $out;
}
