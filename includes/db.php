<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function vb_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'vb_projects';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        client_name   VARCHAR(200) NOT NULL,
        client_phone  VARCHAR(50)  DEFAULT '',
        client_email  VARCHAR(200) DEFAULT '',
        client_city   VARCHAR(100) DEFAULT '',
        site_type     VARCHAR(100) DEFAULT '',
        site_url      VARCHAR(500) DEFAULT '',
        admin_url     VARCHAR(500) DEFAULT '',
        admin_user    VARCHAR(200) DEFAULT '',
        admin_pass    VARCHAR(200) DEFAULT '',
        hosting       TINYINT(1)   DEFAULT 0,
        hosting_provider VARCHAR(100) DEFAULT '',
        hosting_price DECIMAL(10,2) DEFAULT 0,
        hosting_expiry DATE         DEFAULT NULL,
        domain        VARCHAR(200) DEFAULT '',
        domain_price  DECIMAL(10,2) DEFAULT 0,
        domain_expiry DATE         DEFAULT NULL,
        prix          DECIMAL(10,2) DEFAULT 0,
        avance        DECIMAL(10,2) DEFAULT 0,
        reste         DECIMAL(10,2) GENERATED ALWAYS AS (prix - avance) STORED,
        status        VARCHAR(50)  DEFAULT 'in_progress',
        start_date    DATE         DEFAULT NULL,
        delivery_date DATE         DEFAULT NULL,
        notes         TEXT         DEFAULT '',
        tags          VARCHAR(500) DEFAULT '',
        tracking_enabled    TINYINT(1)   DEFAULT 0,
        tracking_price      DECIMAL(10,2) DEFAULT 0,
        tracking_start_date DATE         DEFAULT NULL,
        tracking_note       VARCHAR(255) DEFAULT '',
        created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Safety net: garantit que les colonnes "tracking" existent bien,
    // même si dbDelta n'a pas pu les ajouter pour une raison quelconque.
    // N'affecte aucune autre colonne existante.
    $existing_cols = $wpdb->get_col("DESC $table", 0);
    $needed_cols = [
        'tracking_enabled'    => "ALTER TABLE $table ADD COLUMN tracking_enabled TINYINT(1) DEFAULT 0",
        'tracking_price'      => "ALTER TABLE $table ADD COLUMN tracking_price DECIMAL(10,2) DEFAULT 0",
        'tracking_start_date' => "ALTER TABLE $table ADD COLUMN tracking_start_date DATE DEFAULT NULL",
        'tracking_note'       => "ALTER TABLE $table ADD COLUMN tracking_note VARCHAR(255) DEFAULT ''",
    ];
    foreach ( $needed_cols as $col => $alter_sql ) {
        if ( ! in_array( $col, $existing_cols, true ) ) {
            $wpdb->query( $alter_sql );
        }
    }
}

function vb_get_projects( $args = [] ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';

    $defaults = [
        'status'     => '',
        'month'      => '',
        'year'       => '',
        'search'     => '',
        'site_type'  => '',
        'tracking'   => '',
        'orderby'    => 'created_at',
        'order'      => 'DESC',
        'limit'      => 50,
        'offset'     => 0,
    ];
    $args = wp_parse_args( $args, $defaults );

    $where  = [];
    $params = [];

    if ( $args['status'] )    { $where[] = 'status = %s';                   $params[] = $args['status']; }
    if ( $args['site_type'] ) { $where[] = 'site_type = %s';                $params[] = $args['site_type']; }
    if ( $args['tracking'] !== '' ) { $where[] = 'tracking_enabled = %d';    $params[] = intval($args['tracking']); }
    if ( $args['month'] && $args['year'] ) {
        $where[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d';
        $params[] = intval($args['month']);
        $params[] = intval($args['year']);
    } elseif ( $args['year'] ) {
        $where[] = 'YEAR(created_at) = %d';
        $params[] = intval($args['year']);
    }
    if ( $args['search'] ) {
        $where[] = '(client_name LIKE %s OR client_phone LIKE %s OR site_url LIKE %s OR client_email LIKE %s)';
        $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $order_sql = sprintf('ORDER BY %s %s', sanitize_sql_orderby($args['orderby']) ?: 'created_at', $args['order'] === 'ASC' ? 'ASC' : 'DESC');
    $limit_sql = sprintf('LIMIT %d OFFSET %d', intval($args['limit']), intval($args['offset']));

    $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
    return $params ? $wpdb->get_results( $wpdb->prepare($sql, $params) ) : $wpdb->get_results($sql);
}

function vb_get_project( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    return $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)) );
}

function vb_get_stats( $month = '', $year = '' ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'vb_projects';
    $where  = [];
    $params = [];

    if ( $month && $year ) {
        $where[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d';
        $params[] = intval($month); $params[] = intval($year);
    } elseif ( $year ) {
        $where[] = 'YEAR(created_at) = %d';
        $params[] = intval($year);
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT
        COUNT(*) as total_projects,
        SUM(prix) as total_revenue,
        SUM(avance) as total_received,
        SUM(prix - avance) as total_pending,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status='paused' THEN 1 ELSE 0 END) as paused,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) as cancelled,
        AVG(prix) as avg_price
        FROM $table $where_sql";

    return $params ? $wpdb->get_row( $wpdb->prepare($sql, $params) ) : $wpdb->get_row($sql);
}

function vb_get_monthly_chart( $year = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    $year  = $year ?: date('Y');
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT MONTH(created_at) as month, COUNT(*) as projects, SUM(prix) as revenue, SUM(avance) as received
         FROM $table WHERE YEAR(created_at) = %d GROUP BY MONTH(created_at) ORDER BY month ASC",
        intval($year)
    ));
}

function vb_get_site_types_chart( $year = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    $year  = $year ?: date('Y');
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT site_type, COUNT(*) as count FROM $table WHERE YEAR(created_at) = %d AND site_type != '' GROUP BY site_type ORDER BY count DESC LIMIT 10",
        intval($year)
    ));
}

function vb_save_project( $data, $id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';

    $fields = [
        'client_name'       => sanitize_text_field($data['client_name'] ?? ''),
        'client_phone'      => sanitize_text_field($data['client_phone'] ?? ''),
        'client_email'      => sanitize_email($data['client_email'] ?? ''),
        'client_city'       => sanitize_text_field($data['client_city'] ?? ''),
        'site_type'         => sanitize_text_field($data['site_type'] ?? ''),
        'site_url'          => esc_url_raw($data['site_url'] ?? ''),
        'admin_url'         => esc_url_raw($data['admin_url'] ?? ''),
        'admin_user'        => sanitize_text_field($data['admin_user'] ?? ''),
        'admin_pass'        => sanitize_text_field($data['admin_pass'] ?? ''),
        'hosting'           => intval($data['hosting'] ?? 0),
        'hosting_provider'  => sanitize_text_field($data['hosting_provider'] ?? ''),
        'hosting_price'     => floatval($data['hosting_price'] ?? 0),
        'hosting_expiry'    => $data['hosting_expiry'] ?: null,
        'domain'            => sanitize_text_field($data['domain'] ?? ''),
        'domain_price'      => floatval($data['domain_price'] ?? 0),
        'domain_expiry'     => $data['domain_expiry'] ?: null,
        'prix'              => floatval($data['prix'] ?? 0),
        'avance'            => floatval($data['avance'] ?? 0),
        'status'            => sanitize_text_field($data['status'] ?? 'in_progress'),
        'start_date'        => $data['start_date'] ?: null,
        'delivery_date'     => $data['delivery_date'] ?: null,
        'notes'             => sanitize_textarea_field($data['notes'] ?? ''),
        'tags'              => sanitize_text_field($data['tags'] ?? ''),
        'tracking_enabled'      => intval($data['tracking_enabled'] ?? 0),
        'tracking_price'        => floatval($data['tracking_price'] ?? 0),
        'tracking_start_date'   => $data['tracking_start_date'] ?: null,
        'tracking_note'         => sanitize_text_field($data['tracking_note'] ?? ''),
    ];

    if ( $id ) {
        $result = $wpdb->update($table, $fields, ['id' => $id]);
        return ( $result === false ) ? false : $id;
    } else {
        $result = $wpdb->insert($table, $fields);
        return ( $result === false ) ? false : $wpdb->insert_id;
    }
}

function vb_delete_project( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    return $wpdb->delete($table, ['id' => intval($id)]);
}

/* ============================================================
   EXPENSES TABLE — v2 addition
============================================================ */
function vb_create_expenses_table() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'vb_expenses';
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        project_id   BIGINT(20) UNSIGNED DEFAULT NULL,
        month        TINYINT(2)    DEFAULT NULL,
        year         SMALLINT(4)   DEFAULT NULL,
        category     VARCHAR(50)   NOT NULL DEFAULT 'ads_facebook',
        label        VARCHAR(255)  DEFAULT '',
        amount       DECIMAL(10,2) NOT NULL DEFAULT 0,
        note         TEXT          DEFAULT NULL,
        expense_date DATE          DEFAULT NULL,
        created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

function vb_save_expense( $data, $id = 0 ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'vb_expenses';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        vb_create_expenses_table();
    }

    $fields = [
        'project_id'   => !empty($data['project_id']) ? intval($data['project_id']) : null,
        'month'        => !empty($data['month'])  ? intval($data['month'])  : null,
        'year'         => !empty($data['year'])   ? intval($data['year'])   : intval(date('Y')),
        'category'     => sanitize_text_field($data['category']     ?? 'ads_facebook'),
        'label'        => sanitize_text_field($data['label']        ?? ''),
        'amount'       => floatval($data['amount']  ?? 0),
        'note'         => sanitize_textarea_field($data['note']     ?? ''),
        'expense_date' => !empty($data['expense_date']) ? sanitize_text_field($data['expense_date']) : null,
    ];

    if ( $id ) {
        $updated = $wpdb->update($table, $fields, ['id' => $id]);
        return $updated === false ? false : $id;
    }

    $inserted = $wpdb->insert($table, $fields);
    return $inserted === false ? false : $wpdb->insert_id;
}

function vb_delete_expense( $id ) {
    global $wpdb;
    return $wpdb->delete( $wpdb->prefix . 'vb_expenses', ['id' => intval($id)] );
}

function vb_get_expenses( $args = [] ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'vb_expenses';
    $where  = []; $params = [];
    if ( !empty($args['year']) )       { $where[] = 'year = %d';       $params[] = intval($args['year']); }
    if ( !empty($args['month']) )      { $where[] = 'month = %d';      $params[] = intval($args['month']); }
    if ( !empty($args['project_id']) ) { $where[] = 'project_id = %d'; $params[] = intval($args['project_id']); }
    if ( !empty($args['category']) )   { $where[] = 'category = %s';   $params[] = $args['category']; }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT * FROM $table $where_sql ORDER BY year DESC, month DESC, id DESC";
    return $params ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
}

function vb_get_expenses_totals( $month = '', $year = '' ) {
    global $wpdb;
    $table  = $wpdb->prefix . 'vb_expenses';
    $where  = []; $params = [];
    if ( $month && $year ) { $where[] = 'month = %d AND year = %d'; $params[] = intval($month); $params[] = intval($year); }
    elseif ( $year )       { $where[] = 'year = %d'; $params[] = intval($year); }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT
        SUM(amount) as total,
        SUM(CASE WHEN category = 'ads_facebook'  THEN amount ELSE 0 END) as ads_facebook,
        SUM(CASE WHEN category = 'ads_instagram' THEN amount ELSE 0 END) as ads_instagram,
        SUM(CASE WHEN category = 'ads_google'    THEN amount ELSE 0 END) as ads_google,
        SUM(CASE WHEN category = 'ads_tiktok'    THEN amount ELSE 0 END) as ads_tiktok,
        SUM(CASE WHEN category = 'tools'         THEN amount ELSE 0 END) as tools,
        SUM(CASE WHEN category = 'hosting'       THEN amount ELSE 0 END) as hosting_exp,
        SUM(CASE WHEN category = 'freelance'     THEN amount ELSE 0 END) as freelance,
        SUM(CASE WHEN category = 'other'         THEN amount ELSE 0 END) as other_exp,
        SUM(CASE WHEN category LIKE 'ads_%'      THEN amount ELSE 0 END) as total_ads
        FROM $table $where_sql";
    return $params ? $wpdb->get_row($wpdb->prepare($sql, $params)) : $wpdb->get_row($sql);
}

function vb_get_monthly_expenses_chart( $year = '' ) {
    global $wpdb;
    $year  = $year ?: date('Y');
    $table = $wpdb->prefix . 'vb_expenses';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT month, SUM(amount) as total,
            SUM(CASE WHEN category LIKE 'ads_%' THEN amount ELSE 0 END) as ads,
            SUM(CASE WHEN category NOT LIKE 'ads_%' THEN amount ELSE 0 END) as other
         FROM $table WHERE year = %d AND month IS NOT NULL
         GROUP BY month ORDER BY month ASC",
        intval($year)
    ));
}

/* ============================================================
   TRACKING / SUIVI MENSUEL — v2.1 addition
   Clients qui payent un abonnement mensuel pour le suivi du site.
============================================================ */
function vb_get_tracking_projects( $args = [] ) {
    $args['tracking'] = 1;
    if ( empty($args['orderby']) ) $args['orderby'] = 'client_name';
    if ( empty($args['order']) )   $args['order']   = 'ASC';
    if ( empty($args['limit']) )   $args['limit']   = 200;
    return vb_get_projects( $args );
}

function vb_get_tracking_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    return $wpdb->get_row(
        "SELECT
            COUNT(*) as active_count,
            SUM(tracking_price) as mrr
         FROM $table WHERE tracking_enabled = 1"
    );
}

/* ============================================================
   LEADS / PROSPECTS — v2.2 addition
   Demandes reçues via le formulaire de contact de younessweb.com
   (site Next.js). Un lead devient un projet une fois signé.
============================================================ */

function vb_create_leads_table() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'vb_leads';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        reference         VARCHAR(50)  DEFAULT '',
        full_name         VARCHAR(200) NOT NULL,
        phone             VARCHAR(50)  DEFAULT '',
        email             VARCHAR(200) DEFAULT '',
        website_type      VARCHAR(50)  DEFAULT '',
        project_type      VARCHAR(20)  DEFAULT '',
        products_count    VARCHAR(20)  DEFAULT '',
        package_interest  VARCHAR(50)  DEFAULT '',
        domain_status     VARCHAR(50)  DEFAULT '',
        content_ready     VARCHAR(20)  DEFAULT '',
        maintenance       TINYINT(1)   DEFAULT 0,
        preferred_contact VARCHAR(20)  DEFAULT '',
        priority          VARCHAR(10)  DEFAULT 'medium',
        message           TEXT         DEFAULT NULL,
        locale            VARCHAR(5)   DEFAULT '',
        status            VARCHAR(20)  DEFAULT 'new',
        quoted_price      DECIMAL(10,2) DEFAULT 0,
        lost_reason       VARCHAR(255) DEFAULT '',
        internal_note     TEXT         DEFAULT NULL,
        assigned_to       VARCHAR(100) DEFAULT '',
        archived          TINYINT(1)   DEFAULT 0,
        project_id        BIGINT(20) UNSIGNED DEFAULT NULL,
        source            VARCHAR(50)  DEFAULT 'website_form',
        utm_source        VARCHAR(100) DEFAULT '',
        utm_medium        VARCHAR(100) DEFAULT '',
        utm_campaign      VARCHAR(100) DEFAULT '',
        referer           VARCHAR(500) DEFAULT '',
        ip                VARCHAR(45)  DEFAULT '',
        first_contact_at  DATETIME     DEFAULT NULL,
        created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_status (status),
        KEY idx_created (created_at),
        KEY idx_project (project_id),
        KEY idx_source (source),
        KEY idx_archived (archived)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Filet de sécurité : ajoute les colonnes du CRM (v2.5) sur les installs
    // existantes où la table date d'une version antérieure. N'altère rien d'autre.
    $existing = $wpdb->get_col( "DESC $table", 0 );
    $new_cols = [
        'reference'         => "ALTER TABLE $table ADD COLUMN reference VARCHAR(50) DEFAULT '' AFTER id",
        'project_type'      => "ALTER TABLE $table ADD COLUMN project_type VARCHAR(20) DEFAULT '' AFTER website_type",
        'products_count'    => "ALTER TABLE $table ADD COLUMN products_count VARCHAR(20) DEFAULT '' AFTER project_type",
        'content_ready'     => "ALTER TABLE $table ADD COLUMN content_ready VARCHAR(20) DEFAULT '' AFTER domain_status",
        'maintenance'       => "ALTER TABLE $table ADD COLUMN maintenance TINYINT(1) DEFAULT 0 AFTER content_ready",
        'preferred_contact' => "ALTER TABLE $table ADD COLUMN preferred_contact VARCHAR(20) DEFAULT '' AFTER maintenance",
        'priority'          => "ALTER TABLE $table ADD COLUMN priority VARCHAR(10) DEFAULT 'medium' AFTER preferred_contact",
        'assigned_to'       => "ALTER TABLE $table ADD COLUMN assigned_to VARCHAR(100) DEFAULT '' AFTER internal_note",
        'archived'          => "ALTER TABLE $table ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER assigned_to",
    ];
    foreach ( $new_cols as $col => $alter ) {
        if ( ! in_array( $col, $existing, true ) ) $wpdb->query( $alter );
    }
}

/** Source d'un lead : normalisée en 'website' ou 'whatsapp'. */
function vb_lead_sources() {
    return [
        'website'  => [ 'label' => 'Site web',  'icon' => '🌐', 'color' => 'blue' ],
        'whatsapp' => [ 'label' => 'WhatsApp',  'icon' => '💬', 'color' => 'green' ],
    ];
}

function vb_lead_priorities() {
    return [
        'high'   => [ 'label' => 'Haute',   'color' => 'red' ],
        'medium' => [ 'label' => 'Moyenne', 'color' => 'orange' ],
        'low'    => [ 'label' => 'Basse',   'color' => 'blue' ],
    ];
}

function vb_lead_languages() {
    return [
        'ar' => 'العربية',
        'fr' => 'Français',
        'en' => 'English',
    ];
}

function vb_lead_project_types() {
    return [
        'new'      => 'Nouveau site',
        'redesign' => 'Refonte',
    ];
}

function vb_lead_content_readiness() {
    return [
        'yes'     => 'Prêt',
        'partial' => 'Partiel',
        'no'      => 'Pas prêt',
    ];
}

function vb_lead_contact_prefs() {
    return [
        'whatsapp' => 'WhatsApp',
        'phone'    => 'Téléphone',
        'meeting'  => 'Rendez-vous',
    ];
}

/** Normalise une source brute (website_form, whatsapp…) vers 'website'|'whatsapp'. */
function vb_normalize_lead_source( $raw ) {
    return ( stripos( (string) $raw, 'whatsapp' ) !== false || stripos( (string) $raw, 'wa' ) === 0 )
        ? 'whatsapp' : 'website';
}

/** Statuts du pipeline commercial, dans l'ordre. */
function vb_lead_statuses() {
    return [
        'new'       => ['label' => 'Nouveau',   'color' => 'blue'],
        'contacted' => ['label' => 'Contacté',  'color' => 'purple'],
        'quoted'    => ['label' => 'Devis',     'color' => 'orange'],
        'won'       => ['label' => 'Gagné',     'color' => 'green'],
        'lost'      => ['label' => 'Perdu',     'color' => 'red'],
    ];
}

function vb_lead_website_types() {
    return [
        'business'  => 'Site vitrine / entreprise',
        'ecommerce' => 'E-commerce',
        'landing'   => 'Landing page (pub)',
        'portfolio' => 'Portfolio',
        'booking'   => 'Réservation / contact',
        'redesign'  => 'Refonte de site',
        'other'     => 'Autre',
    ];
}

function vb_lead_packages() {
    return [
        'essentiel' => 'Essentiel',
        'premium'   => 'Premium sur-mesure',
        'notSure'   => 'Pas encore sûr',
    ];
}

function vb_lead_domain_statuses() {
    return [
        'yes'     => 'A déjà un domaine',
        'no'      => 'Pas de domaine',
        'notSure' => 'Ne sait pas',
    ];
}

function vb_get_leads( $args = [] ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';

    $defaults = [
        'status'            => '',
        'website_type'      => '',
        'package'           => '',
        'source'            => '',   // 'website' | 'whatsapp'
        'priority'          => '',   // 'high' | 'medium' | 'low'
        'language'          => '',   // locale : ar | fr | en
        'maintenance'       => '',   // '0' | '1'
        'preferred_contact' => '',   // whatsapp | phone | meeting
        'archived'          => '0',  // '0' masque les archivés, '1' ne montre qu'eux, 'all' tout
        'date_from'         => '',
        'date_to'           => '',
        'search'            => '',
        'month'             => '',
        'year'              => '',
        'orderby'           => 'created_at',
        'order'             => 'DESC',
        'limit'             => 200,
        'offset'            => 0,
    ];
    $args = wp_parse_args( $args, $defaults );

    $where = []; $params = [];

    if ( $args['status'] )       { $where[] = 'status = %s';           $params[] = $args['status']; }
    if ( $args['website_type'] ) { $where[] = 'website_type = %s';     $params[] = $args['website_type']; }
    if ( $args['package'] )      { $where[] = 'package_interest = %s'; $params[] = $args['package']; }
    if ( $args['priority'] )     { $where[] = 'priority = %s';         $params[] = $args['priority']; }
    if ( $args['language'] )     { $where[] = 'locale = %s';           $params[] = $args['language']; }
    if ( $args['preferred_contact'] ) { $where[] = 'preferred_contact = %s'; $params[] = $args['preferred_contact']; }
    if ( $args['maintenance'] !== '' ) { $where[] = 'maintenance = %d'; $params[] = intval($args['maintenance']); }

    // Source : normalisée. 'whatsapp' = source contient whatsapp, 'website' = le reste.
    if ( $args['source'] === 'whatsapp' ) {
        $where[] = "source LIKE %s"; $params[] = '%whatsapp%';
    } elseif ( $args['source'] === 'website' ) {
        $where[] = "source NOT LIKE %s"; $params[] = '%whatsapp%';
    }

    // Archivage.
    if ( $args['archived'] === '1' ) {
        $where[] = 'archived = 1';
    } elseif ( $args['archived'] !== 'all' ) {
        $where[] = 'archived = 0';
    }

    // Plage de dates (prioritaire sur month/year si fournie).
    if ( $args['date_from'] ) { $where[] = 'created_at >= %s'; $params[] = $args['date_from'] . ' 00:00:00'; }
    if ( $args['date_to'] )   { $where[] = 'created_at <= %s'; $params[] = $args['date_to'] . ' 23:59:59'; }

    if ( ! $args['date_from'] && ! $args['date_to'] ) {
        if ( $args['month'] && $args['year'] ) {
            $where[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d';
            $params[] = intval($args['month']); $params[] = intval($args['year']);
        } elseif ( $args['year'] ) {
            $where[] = 'YEAR(created_at) = %d'; $params[] = intval($args['year']);
        }
    }

    if ( $args['search'] ) {
        $where[] = '(full_name LIKE %s OR phone LIKE %s OR email LIKE %s OR reference LIKE %s OR message LIKE %s)';
        $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $order_sql = sprintf('ORDER BY %s %s',
        sanitize_sql_orderby($args['orderby']) ?: 'created_at',
        $args['order'] === 'ASC' ? 'ASC' : 'DESC'
    );
    $limit_sql = sprintf('LIMIT %d OFFSET %d', intval($args['limit']), intval($args['offset']));

    $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
    return $params ? $wpdb->get_results( $wpdb->prepare($sql, $params) ) : $wpdb->get_results($sql);
}

function vb_get_lead( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';
    return $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id)) );
}

/**
 * Insère un lead venant du formulaire du site.
 * Déduplique sur (téléphone|email) dans les dernières 24h pour éviter
 * les doubles soumissions (double-clic, retry réseau).
 *
 * @return int|false|'duplicate'
 */
function vb_insert_lead( $data ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';

    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) ) !== $table ) {
        vb_create_leads_table();
    }

    $phone = sanitize_text_field($data['phone'] ?? '');
    $email = sanitize_email($data['email'] ?? '');

    // Anti-doublon : même téléphone OU même email dans les 24 dernières heures.
    if ( $phone || $email ) {
        $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND ( (%s <> '' AND phone = %s) OR (%s <> '' AND email = %s) )
             LIMIT 1",
            $phone, $phone, $email, $email
        ) );
        if ( $dup ) return 'duplicate';
    }

    $priority = strtolower( sanitize_text_field($data['priority'] ?? 'medium') );
    if ( ! array_key_exists( $priority, vb_lead_priorities() ) ) $priority = 'medium';

    $fields = [
        'reference'         => sanitize_text_field($data['reference'] ?? ''),
        'full_name'         => sanitize_text_field($data['full_name'] ?? ''),
        'phone'             => $phone,
        'email'             => $email,
        'website_type'      => sanitize_text_field($data['website_type'] ?? ''),
        'project_type'      => sanitize_text_field($data['project_type'] ?? ''),
        'products_count'    => sanitize_text_field($data['products_count'] ?? ''),
        'package_interest'  => sanitize_text_field($data['package_interest'] ?? ''),
        'domain_status'     => sanitize_text_field($data['domain_status'] ?? ''),
        'content_ready'     => sanitize_text_field($data['content_ready'] ?? ''),
        'maintenance'       => ! empty($data['maintenance']) ? 1 : 0,
        'preferred_contact' => sanitize_text_field($data['preferred_contact'] ?? ''),
        'priority'          => $priority,
        'message'           => sanitize_textarea_field($data['message'] ?? ''),
        'locale'            => sanitize_text_field($data['locale'] ?? ''),
        'status'            => 'new',
        'source'            => sanitize_text_field($data['source'] ?? 'website_form'),
        'utm_source'        => sanitize_text_field($data['utm_source'] ?? ''),
        'utm_medium'        => sanitize_text_field($data['utm_medium'] ?? ''),
        'utm_campaign'      => sanitize_text_field($data['utm_campaign'] ?? ''),
        'referer'           => esc_url_raw($data['referer'] ?? ''),
        'ip'                => sanitize_text_field($data['ip'] ?? ''),
    ];

    $inserted = $wpdb->insert($table, $fields);
    if ( $inserted === false ) return false;

    $id = $wpdb->insert_id;

    // Référence lisible et cherchable si aucune n'a été fournie par la source.
    if ( $fields['reference'] === '' ) {
        $wpdb->update( $table, [ 'reference' => sprintf( 'LEAD-%05d', $id ) ], [ 'id' => $id ] );
    }

    return $id;
}

function vb_update_lead( $id, $data ) {
    global $wpdb;
    $table   = $wpdb->prefix . 'vb_leads';
    $allowed = [
        'full_name', 'phone', 'email', 'website_type', 'project_type',
        'products_count', 'package_interest', 'domain_status', 'content_ready',
        'maintenance', 'preferred_contact', 'priority', 'message', 'status',
        'quoted_price', 'lost_reason', 'internal_note', 'assigned_to',
        'archived', 'project_id', 'first_contact_at',
    ];

    $fields = [];
    foreach ( $allowed as $key ) {
        if ( ! array_key_exists($key, $data) ) continue;
        switch ( $key ) {
            case 'email':
                $fields[$key] = sanitize_email($data[$key]); break;
            case 'quoted_price':
                $fields[$key] = floatval($data[$key]); break;
            case 'maintenance':
            case 'archived':
                $fields[$key] = ! empty($data[$key]) ? 1 : 0; break;
            case 'project_id':
                $fields[$key] = $data[$key] ? intval($data[$key]) : null; break;
            case 'message':
            case 'internal_note':
                $fields[$key] = sanitize_textarea_field($data[$key]); break;
            case 'first_contact_at':
                $fields[$key] = $data[$key] ?: null; break;
            default:
                $fields[$key] = sanitize_text_field($data[$key]);
        }
    }
    if ( ! $fields ) return false;

    // Horodate le premier contact automatiquement.
    if ( isset($fields['status']) && $fields['status'] !== 'new' ) {
        $current = vb_get_lead($id);
        if ( $current && empty($current->first_contact_at) ) {
            $fields['first_contact_at'] = current_time('mysql');
        }
    }

    $updated = $wpdb->update($table, $fields, ['id' => intval($id)]);
    return $updated === false ? false : $id;
}

function vb_delete_lead( $id ) {
    global $wpdb;
    return $wpdb->delete( $wpdb->prefix . 'vb_leads', ['id' => intval($id)] );
}

/**
 * Convertit un lead en projet : crée la ligne dans vb_projects,
 * marque le lead comme "gagné" et lie les deux.
 *
 * @return int|false ID du projet créé.
 */
function vb_convert_lead_to_project( $lead_id ) {
    $lead = vb_get_lead( $lead_id );
    if ( ! $lead ) return false;
    if ( $lead->project_id ) return intval($lead->project_id); // déjà converti

    $type_labels    = vb_lead_website_types();
    $ptype_labels   = vb_lead_project_types();
    $content_labels = vb_lead_content_readiness();

    // Récapitulatif de qualification injecté dans les notes du projet.
    $qualif = [];
    if ( ! empty($lead->reference) )      $qualif[] = "Référence : {$lead->reference}";
    if ( ! empty($lead->project_type) )   $qualif[] = "Type de projet : " . ( $ptype_labels[$lead->project_type] ?? $lead->project_type );
    if ( ! empty($lead->products_count) ) $qualif[] = "Produits/services : {$lead->products_count}";
    if ( ! empty($lead->content_ready) )  $qualif[] = "Contenu : " . ( $content_labels[$lead->content_ready] ?? $lead->content_ready );
    if ( ! empty($lead->maintenance) )    $qualif[] = "Maintenance souhaitée : oui";
    $qualif_txt = $qualif ? implode("\n", $qualif) . "\n" : '';

    $project_id = vb_save_project([
        'client_name'  => $lead->full_name,
        'client_phone' => $lead->phone,
        'client_email' => $lead->email,
        'site_type'    => $type_labels[$lead->website_type] ?? $lead->website_type,
        'prix'         => floatval($lead->quoted_price),
        'status'       => 'in_progress',
        'start_date'   => current_time('Y-m-d'),
        'notes'        => trim(
            "Lead {$lead->reference} (" . ucfirst( vb_normalize_lead_source($lead->source) ) . ") reçu le " . date('d/m/Y', strtotime($lead->created_at)) . "\n" .
            $qualif_txt .
            ( $lead->message ? "\nDemande du client :\n{$lead->message}\n" : '' ) .
            ( $lead->internal_note ? "\nNote interne :\n{$lead->internal_note}" : '' )
        ),
        'tags'         => $lead->package_interest,
    ]);

    if ( ! $project_id ) return false;

    vb_update_lead( $lead_id, [ 'status' => 'won', 'project_id' => $project_id ] );
    return $project_id;
}

/** Stats du pipeline : compteurs par statut + taux de conversion. */
function vb_get_leads_stats( $month = '', $year = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';

    $where = []; $params = [];
    if ( $month && $year ) {
        $where[] = 'MONTH(created_at) = %d AND YEAR(created_at) = %d';
        $params[] = intval($month); $params[] = intval($year);
    } elseif ( $year ) {
        $where[] = 'YEAR(created_at) = %d'; $params[] = intval($year);
    }
    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Les leads archivés ne comptent pas dans le tableau de bord.
    $where[] = 'archived = 0';
    $where_sql = 'WHERE ' . implode(' AND ', $where);

    $sql = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='new'       THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN status='contacted' THEN 1 ELSE 0 END) as contacted_count,
        SUM(CASE WHEN status='quoted'    THEN 1 ELSE 0 END) as quoted_count,
        SUM(CASE WHEN status='won'       THEN 1 ELSE 0 END) as won_count,
        SUM(CASE WHEN status='lost'      THEN 1 ELSE 0 END) as lost_count,
        SUM(CASE WHEN INSTR(source,'whatsapp') > 0 THEN 1 ELSE 0 END) as whatsapp_count,
        SUM(CASE WHEN INSTR(source,'whatsapp') = 0 THEN 1 ELSE 0 END) as website_count,
        SUM(CASE WHEN priority='high' AND status IN ('new','contacted') THEN 1 ELSE 0 END) as high_priority_open,
        SUM(CASE WHEN status='quoted' THEN quoted_price ELSE 0 END) as pipeline_value,
        AVG(CASE WHEN first_contact_at IS NOT NULL
                 THEN TIMESTAMPDIFF(HOUR, created_at, first_contact_at) END) as avg_response_hours
        FROM $table $where_sql";

    return $params ? $wpdb->get_row($wpdb->prepare($sql, $params)) : $wpdb->get_row($sql);
}

/** Répartition des leads par type de site demandé (pour graphique). */
function vb_get_leads_by_type( $year = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';
    $year  = $year ?: date('Y');
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT website_type, COUNT(*) as count,
                SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as won
         FROM $table WHERE YEAR(created_at) = %d AND website_type != ''
         GROUP BY website_type ORDER BY count DESC",
        intval($year)
    ));
}
