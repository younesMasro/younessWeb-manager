<?php
/**
 * Module Contrats — v2.7
 *
 * Un contrat est un DOCUMENT JURIDIQUE, pas une vue sur le projet. Ça dicte
 * toute l'architecture de ce fichier :
 *
 *  1. SNAPSHOT, PAS DE JOINTURE. Les coordonnées du client, le prix, le délai
 *     et les conditions sont COPIÉS dans la ligne du contrat au moment où on
 *     le rédige. Si le client change de téléphone six mois plus tard, le
 *     contrat signé ne doit pas changer — sinon ce n'est plus une preuve.
 *     `project_id` ne sert qu'au rattachement et au pré-remplissage.
 *
 *  2. UN CONTRAT SIGNÉ NE SE MODIFIE PLUS. `vb_contract_is_locked()` verrouille
 *     les documents signés ou terminés ; il faut les repasser en brouillon
 *     (traçable) pour les corriger.
 *
 *  3. LA SIGNATURE EST L'ÉVÉNEMENT MÉTIER. C'est elle — et rien d'autre — qui
 *     propage l'information vers le projet : date de démarrage et abonnement
 *     de maintenance. Voir vb_sign_contract().
 *
 * Intégrations :
 *   Projets      → project_id + pré-remplissage (vb_contract_prefill_from_project)
 *   Clients      → snapshot des coordonnées portées par le projet
 *   Paiements    → échéancier JSON + rapprochement avec prix/avance du projet
 *   Maintenance  → clause d'abonnement synchronisée avec tracking_* du projet
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   RÉFÉRENTIELS
============================================================ */

/** Cycle de vie d'un contrat, dans l'ordre. */
function vb_contract_statuses() {
    return [
        'draft'     => [ 'label' => 'Brouillon', 'color' => 'blue',   'icon' => '📝' ],
        'sent'      => [ 'label' => 'Envoyé',    'color' => 'orange', 'icon' => '📤' ],
        'signed'    => [ 'label' => 'Signé',     'color' => 'green',  'icon' => '✍️' ],
        'completed' => [ 'label' => 'Terminé',   'color' => 'purple', 'icon' => '✅' ],
        'cancelled' => [ 'label' => 'Annulé',    'color' => 'red',    'icon' => '✖️' ],
    ];
}

/**
 * Un contrat signé ou terminé est verrouillé : le modifier réécrirait un
 * document qui fait foi. On le repasse en brouillon pour le corriger.
 */
function vb_contract_is_locked( $contract ) {
    $status = is_object( $contract ) ? $contract->status : ( $contract['status'] ?? '' );
    return in_array( $status, [ 'signed', 'completed' ], true );
}

/* ============================================================
   TABLE
============================================================ */

function vb_create_contracts_table() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = $wpdb->prefix . 'vb_contracts';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        number               VARCHAR(50)  NOT NULL,
        project_id           BIGINT(20) UNSIGNED DEFAULT NULL,
        template_key         VARCHAR(50)  DEFAULT 'creation',
        title                VARCHAR(255) DEFAULT '',
        client_name          VARCHAR(200) NOT NULL,
        client_phone         VARCHAR(50)  DEFAULT '',
        client_email         VARCHAR(200) DEFAULT '',
        client_city          VARCHAR(100) DEFAULT '',
        client_legal_id      VARCHAR(100) DEFAULT '',
        site_type            VARCHAR(100) DEFAULT '',
        site_url             VARCHAR(500) DEFAULT '',
        scope                LONGTEXT     DEFAULT NULL,
        delivery_days        INT          DEFAULT 21,
        revisions_included   INT          DEFAULT 2,
        warranty_months      INT          DEFAULT 3,
        amount_total         DECIMAL(10,2) DEFAULT 0,
        deposit_amount       DECIMAL(10,2) DEFAULT 0,
        payment_schedule     LONGTEXT     DEFAULT NULL,
        late_penalty_percent DECIMAL(5,2) DEFAULT 0,
        maintenance_enabled  TINYINT(1)   DEFAULT 0,
        maintenance_price    DECIMAL(10,2) DEFAULT 0,
        maintenance_months   INT          DEFAULT 12,
        maintenance_start    DATE         DEFAULT NULL,
        jurisdiction         VARCHAR(100) DEFAULT '',
        ip_transfer          TINYINT(1)   DEFAULT 1,
        custom_clauses       LONGTEXT     DEFAULT NULL,
        status               VARCHAR(30)  DEFAULT 'draft',
        issue_date           DATE         DEFAULT NULL,
        signed_date          DATE         DEFAULT NULL,
        signed_by            VARCHAR(200) DEFAULT '',
        notes                TEXT         DEFAULT NULL,
        created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP,
        updated_at           DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_project (project_id),
        KEY idx_status (status),
        KEY idx_number (number)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* ============================================================
   NUMÉROTATION
============================================================ */

/** Rang extrait d'un numéro de contrat, 0 si le format ne correspond pas. */
function vb_contract_number_rank( $number, $year ) {
    $pattern = '/^CTR-' . preg_quote( (string) $year, '/' ) . '-(\d+)$/';
    return preg_match( $pattern, (string) $number, $m ) ? (int) $m[1] : 0;
}

/**
 * Numéro suivant : CTR-2026-001.
 *
 * Le compteur est un point d'eau haute (`vb_contract_seq_<année>`) qui ne
 * redescend JAMAIS, combiné au plus grand numéro encore présent en base.
 *
 * Pourquoi pas un simple COUNT, ni même un MAX sur la table : supprimer le
 * dernier contrat ferait réattribuer son numéro à un nouveau document. Or ce
 * numéro-là existe peut-être déjà sur un papier signé, dans un email, dans la
 * comptabilité du client. Deux contrats différents portant le même numéro,
 * c'est précisément ce qu'un contrat ne doit jamais permettre.
 */
function vb_next_contract_number( $year = null ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_contracts';
    $year  = $year ?: date( 'Y' );

    $max = intval( get_option( 'vb_contract_seq_' . $year, 0 ) );

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table ) {
        $numbers = $wpdb->get_col( $wpdb->prepare(
            "SELECT number FROM $table WHERE number LIKE %s",
            'CTR-' . $year . '-%'
        ) );
        foreach ( $numbers as $n ) {
            $max = max( $max, vb_contract_number_rank( $n, $year ) );
        }
    }

    return sprintf( 'CTR-%s-%03d', $year, $max + 1 );
}

/**
 * Tous les compteurs de numérotation présents en base (clés vb_contract_seq_*).
 * Utilisé par la sauvegarde : ces options doivent voyager avec les contrats.
 *
 * @return array<string,int> clé d'option => rang
 */
function vb_contract_seq_options() {
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT option_name, option_value FROM {$wpdb->options}
         WHERE option_name LIKE 'vb_contract_seq_%'",
        ARRAY_A
    );
    $out = [];
    foreach ( (array) $rows as $r ) {
        $out[ $r['option_name'] ] = intval( $r['option_value'] );
    }
    return $out;
}

/**
 * Enregistre qu'un numéro a été consommé. Appelé à chaque insertion, y compris
 * quand l'utilisateur a saisi le numéro à la main : c'est ce qui garantit que
 * le compteur ne redescend pas après une suppression.
 */
function vb_contract_mark_number_used( $number ) {
    if ( ! preg_match( '/^CTR-(\d{4})-(\d+)$/', (string) $number, $m ) ) return;
    $year = $m[1];
    $rank = (int) $m[2];
    $key  = 'vb_contract_seq_' . $year;
    if ( $rank > intval( get_option( $key, 0 ) ) ) {
        update_option( $key, $rank, false );
    }
}

/* ============================================================
   LECTURE
============================================================ */

function vb_get_contracts( $args = [] ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_contracts';

    $defaults = [
        'status'     => '',
        'project_id' => '',
        'template'   => '',
        'search'     => '',
        'year'       => '',
        'orderby'    => 'created_at',
        'order'      => 'DESC',
        'limit'      => 200,
        'offset'     => 0,
    ];
    $args = wp_parse_args( $args, $defaults );

    $where = []; $params = [];

    if ( $args['status'] )     { $where[] = 'status = %s';       $params[] = $args['status']; }
    if ( $args['template'] )   { $where[] = 'template_key = %s'; $params[] = $args['template']; }
    if ( $args['project_id'] !== '' && $args['project_id'] !== null ) {
        $where[] = 'project_id = %d'; $params[] = intval( $args['project_id'] );
    }
    if ( $args['year'] ) { $where[] = 'YEAR(created_at) = %d'; $params[] = intval( $args['year'] ); }
    if ( $args['search'] ) {
        $where[] = '(client_name LIKE %s OR number LIKE %s OR title LIKE %s)';
        $like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $order_sql = sprintf( 'ORDER BY %s %s',
        sanitize_sql_orderby( $args['orderby'] ) ?: 'created_at',
        $args['order'] === 'ASC' ? 'ASC' : 'DESC'
    );
    $limit_sql = sprintf( 'LIMIT %d OFFSET %d', intval( $args['limit'] ), intval( $args['offset'] ) );

    $sql = "SELECT * FROM $table $where_sql $order_sql $limit_sql";
    return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

function vb_get_contract( $id ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_contracts';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", intval( $id ) ) );
}

/** Contrats rattachés à un projet, du plus récent au plus ancien. */
function vb_get_project_contracts( $project_id ) {
    return vb_get_contracts( [ 'project_id' => intval( $project_id ), 'orderby' => 'id', 'order' => 'DESC' ] );
}

/* ============================================================
   ÉCRITURE
============================================================ */

function vb_save_contract( $data, $id = 0 ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_contracts';

    $statuses = vb_contract_statuses();
    $status   = sanitize_text_field( $data['status'] ?? 'draft' );
    if ( ! array_key_exists( $status, $statuses ) ) $status = 'draft';

    // L'échéancier arrive soit déjà encodé, soit sous forme de tableau.
    $schedule = $data['payment_schedule'] ?? null;
    if ( is_array( $schedule ) ) $schedule = vb_contract_encode_schedule( $schedule );

    $fields = [
        'number'               => sanitize_text_field( $data['number'] ?? '' ),
        'project_id'           => ! empty( $data['project_id'] ) ? intval( $data['project_id'] ) : null,
        'template_key'         => sanitize_text_field( $data['template_key'] ?? 'creation' ),
        'title'                => sanitize_text_field( $data['title'] ?? '' ),
        'client_name'          => sanitize_text_field( $data['client_name'] ?? '' ),
        'client_phone'         => sanitize_text_field( $data['client_phone'] ?? '' ),
        'client_email'         => sanitize_email( $data['client_email'] ?? '' ),
        'client_city'          => sanitize_text_field( $data['client_city'] ?? '' ),
        'client_legal_id'      => sanitize_text_field( $data['client_legal_id'] ?? '' ),
        'site_type'            => sanitize_text_field( $data['site_type'] ?? '' ),
        'site_url'             => esc_url_raw( $data['site_url'] ?? '' ),
        'scope'                => sanitize_textarea_field( $data['scope'] ?? '' ),
        'delivery_days'        => max( 0, intval( $data['delivery_days'] ?? 21 ) ),
        'revisions_included'   => max( 0, intval( $data['revisions_included'] ?? 2 ) ),
        'warranty_months'      => max( 0, intval( $data['warranty_months'] ?? 3 ) ),
        'amount_total'         => floatval( $data['amount_total'] ?? 0 ),
        'deposit_amount'       => floatval( $data['deposit_amount'] ?? 0 ),
        'payment_schedule'     => $schedule,
        'late_penalty_percent' => floatval( $data['late_penalty_percent'] ?? 0 ),
        'maintenance_enabled'  => ! empty( $data['maintenance_enabled'] ) ? 1 : 0,
        'maintenance_price'    => floatval( $data['maintenance_price'] ?? 0 ),
        'maintenance_months'   => max( 0, intval( $data['maintenance_months'] ?? 12 ) ),
        'maintenance_start'    => ! empty( $data['maintenance_start'] ) ? $data['maintenance_start'] : null,
        'jurisdiction'         => sanitize_text_field( $data['jurisdiction'] ?? '' ),
        'ip_transfer'          => ! empty( $data['ip_transfer'] ) ? 1 : 0,
        'custom_clauses'       => sanitize_textarea_field( $data['custom_clauses'] ?? '' ),
        'status'               => $status,
        'issue_date'           => ! empty( $data['issue_date'] ) ? $data['issue_date'] : null,
        'signed_date'          => ! empty( $data['signed_date'] ) ? $data['signed_date'] : null,
        'signed_by'            => sanitize_text_field( $data['signed_by'] ?? '' ),
        'notes'                => sanitize_textarea_field( $data['notes'] ?? '' ),
    ];

    // Un acompte ne peut pas dépasser le total : ça produirait un solde négatif
    // sur un document qui part chez le client.
    if ( $fields['deposit_amount'] > $fields['amount_total'] ) {
        $fields['deposit_amount'] = $fields['amount_total'];
    }

    if ( $fields['client_name'] === '' ) return false;

    if ( $id ) {
        $result = $wpdb->update( $table, $fields, [ 'id' => intval( $id ) ] );
        return ( $result === false ) ? false : intval( $id );
    }

    // Numéro attribué au dernier moment, jamais deviné côté client.
    if ( $fields['number'] === '' ) $fields['number'] = vb_next_contract_number();
    if ( $fields['issue_date'] === null ) $fields['issue_date'] = date( 'Y-m-d' );

    $result = $wpdb->insert( $table, $fields );
    if ( $result === false ) return false;

    vb_contract_mark_number_used( $fields['number'] );
    return $wpdb->insert_id;
}

function vb_delete_contract( $id ) {
    global $wpdb;
    return $wpdb->delete( $wpdb->prefix . 'vb_contracts', [ 'id' => intval( $id ) ] );
}

/* ============================================================
   INTÉGRATION PROJETS / CLIENTS / PAIEMENTS / MAINTENANCE
============================================================ */

/**
 * Pré-remplit un contrat à partir d'un projet : coordonnées client, prestation,
 * montants et clause de maintenance. Rien n'est écrit en base — c'est un
 * brouillon de données que le formulaire affichera et que l'utilisateur pourra
 * corriger avant enregistrement.
 *
 * @return array|false
 */
function vb_contract_prefill_from_project( $project_id, $template_key = 'creation' ) {
    $p = vb_get_project( $project_id );
    if ( ! $p ) return false;

    $templates = vb_contract_templates();
    if ( ! isset( $templates[ $template_key ] ) ) $template_key = 'creation';
    $tpl = $templates[ $template_key ];

    $total = floatval( $p->prix );

    // L'avance déjà encaissée sur le projet fait foi ; sinon on propose le
    // pourcentage d'acompte du modèle. Le vécu prime sur le théorique.
    $deposit = floatval( $p->avance ) > 0
        ? floatval( $p->avance )
        : round( $total * $tpl['deposit_percent'] / 100, 2 );

    // La maintenance suit le suivi mensuel déjà activé sur le projet.
    $maintenance = $tpl['maintenance'] || ! empty( $p->tracking_enabled );

    return [
        'number'               => vb_next_contract_number(),
        'project_id'           => intval( $p->id ),
        'template_key'         => $template_key,
        'title'                => $tpl['title'],
        'client_name'          => $p->client_name,
        'client_phone'         => $p->client_phone,
        'client_email'         => $p->client_email,
        'client_city'          => $p->client_city,
        'client_legal_id'      => '',
        'site_type'            => $p->site_type,
        'site_url'             => $p->site_url,
        'scope'                => $tpl['scope'],
        'delivery_days'        => $tpl['delivery_days'],
        'revisions_included'   => $tpl['revisions'],
        'warranty_months'      => $tpl['warranty_months'],
        'amount_total'         => $total,
        'deposit_amount'       => $deposit,
        'payment_schedule'     => vb_contract_default_schedule( $total, $deposit, $template_key ),
        'late_penalty_percent' => $tpl['late_penalty_percent'],
        'maintenance_enabled'  => $maintenance ? 1 : 0,
        'maintenance_price'    => floatval( $p->tracking_price ) ?: floatval( $tpl['maintenance_price'] ),
        'maintenance_months'   => $tpl['maintenance_months'],
        'maintenance_start'    => $p->tracking_start_date ?: ( $p->start_date ?: date( 'Y-m-d' ) ),
        'jurisdiction'         => vb_contract_provider()['city'],
        'ip_transfer'          => 1,
        'custom_clauses'       => '',
        'status'               => 'draft',
        'issue_date'           => date( 'Y-m-d' ),
        'notes'                => '',
    ];
}

/**
 * Signe un contrat. SEUL point qui propage l'information vers le projet :
 *
 *   - le projet démarre à la date de signature s'il n'avait pas de date ;
 *   - la clause de maintenance active le suivi mensuel (tracking_*).
 *
 * Ce qui n'est PAS fait ici, volontairement : écraser le prix ou l'avance du
 * projet. C'est de l'argent réellement encaissé, la trésorerie ne se déduit pas
 * d'un document. Le rapprochement est proposé à part
 * (vb_contract_reconciliation / vb_contract_apply_to_project).
 *
 * @return bool
 */
function vb_sign_contract( $id, $signed_date = null, $signed_by = '' ) {
    global $wpdb;

    $contract = vb_get_contract( $id );
    if ( ! $contract ) return false;

    $signed_date = $signed_date ?: date( 'Y-m-d' );

    $updated = $wpdb->update(
        $wpdb->prefix . 'vb_contracts',
        [
            'status'      => 'signed',
            'signed_date' => $signed_date,
            'signed_by'   => sanitize_text_field( $signed_by ?: $contract->client_name ),
        ],
        [ 'id' => intval( $id ) ]
    );
    if ( $updated === false ) return false;

    if ( ! $contract->project_id ) return true;

    $project = vb_get_project( $contract->project_id );
    if ( ! $project ) return true;

    $patch = [];

    // Le projet démarre à la signature, sauf s'il avait déjà une date.
    if ( empty( $project->start_date ) ) $patch['start_date'] = $signed_date;

    // La clause de maintenance ouvre l'abonnement de suivi mensuel.
    if ( ! empty( $contract->maintenance_enabled ) ) {
        $patch['tracking_enabled'] = 1;
        if ( floatval( $contract->maintenance_price ) > 0 ) {
            $patch['tracking_price'] = floatval( $contract->maintenance_price );
        }
        if ( empty( $project->tracking_start_date ) ) {
            $patch['tracking_start_date'] = $contract->maintenance_start ?: $signed_date;
        }
        if ( empty( $project->tracking_note ) ) {
            $patch['tracking_note'] = 'Contrat ' . $contract->number;
        }
    }

    if ( $patch ) $wpdb->update( $wpdb->prefix . 'vb_projects', $patch, [ 'id' => intval( $project->id ) ] );

    return true;
}

/**
 * Rapprochement contrat ↔ projet : ce que dit le document contre ce qu'il y a
 * réellement dans la caisse. Sert à afficher une alerte, pas à corriger tout
 * seul — un écart peut être parfaitement légitime (avenant, geste commercial).
 *
 * @return array|null null si le contrat n'est rattaché à aucun projet.
 */
function vb_contract_reconciliation( $contract ) {
    if ( is_numeric( $contract ) ) $contract = vb_get_contract( $contract );
    if ( ! $contract || ! $contract->project_id ) return null;

    $p = vb_get_project( $contract->project_id );
    if ( ! $p ) return null;

    $c_total   = floatval( $contract->amount_total );
    $c_deposit = floatval( $contract->deposit_amount );
    $p_total   = floatval( $p->prix );
    $p_paid    = floatval( $p->avance );

    $c_maint = ! empty( $contract->maintenance_enabled ) ? floatval( $contract->maintenance_price ) : 0.0;
    $p_maint = ! empty( $p->tracking_enabled ) ? floatval( $p->tracking_price ) : 0.0;

    return [
        'project'          => $p,
        'contract_total'   => $c_total,
        'project_total'    => $p_total,
        'total_diff'       => round( $c_total - $p_total, 2 ),
        'contract_deposit' => $c_deposit,
        'project_paid'     => $p_paid,
        'deposit_diff'     => round( $c_deposit - $p_paid, 2 ),
        'remaining'        => round( $c_total - $p_paid, 2 ),
        'contract_maint'   => $c_maint,
        'project_maint'    => $p_maint,
        'maint_diff'       => round( $c_maint - $p_maint, 2 ),
        'in_sync'          => abs( $c_total - $p_total ) < 0.01
                           && abs( $c_maint - $p_maint ) < 0.01,
    ];
}

/**
 * Aligne le projet sur le contrat (prix, maintenance). Action explicite,
 * jamais automatique : c'est l'utilisateur qui décide que le document fait foi.
 */
function vb_contract_apply_to_project( $contract_id ) {
    global $wpdb;

    $contract = vb_get_contract( $contract_id );
    if ( ! $contract || ! $contract->project_id ) return false;
    if ( ! vb_get_project( $contract->project_id ) ) return false;

    $patch = [ 'prix' => floatval( $contract->amount_total ) ];

    if ( ! empty( $contract->maintenance_enabled ) ) {
        $patch['tracking_enabled'] = 1;
        $patch['tracking_price']   = floatval( $contract->maintenance_price );
        if ( $contract->maintenance_start ) $patch['tracking_start_date'] = $contract->maintenance_start;
    }

    $r = $wpdb->update( $wpdb->prefix . 'vb_projects', $patch, [ 'id' => intval( $contract->project_id ) ] );
    return $r !== false;
}

/* ============================================================
   ÉCHÉANCIER DE PAIEMENT
============================================================ */

/**
 * Échéancier par défaut d'un modèle : acompte à la signature, solde à la
 * livraison. Le solde est calculé par soustraction et jamais par pourcentage,
 * pour que la somme des lignes tombe TOUJOURS juste au centime près.
 */
function vb_contract_default_schedule( $total, $deposit, $template_key = 'creation' ) {
    $total   = round( floatval( $total ), 2 );
    $deposit = round( min( floatval( $deposit ), $total ), 2 );
    $balance = round( $total - $deposit, 2 );

    $schedule = [];

    if ( $deposit > 0 ) {
        $schedule[] = [
            'label'   => 'Acompte à la signature',
            'amount'  => $deposit,
            'due'     => 'À la signature du présent contrat',
            'paid'    => 0,
        ];
    }
    if ( $balance > 0 ) {
        $schedule[] = [
            'label'   => 'Solde à la livraison',
            'amount'  => $balance,
            'due'     => 'À la mise en ligne du site',
            'paid'    => 0,
        ];
    }
    return $schedule;
}

/** Normalise et encode un échéancier pour la base. */
function vb_contract_encode_schedule( $rows ) {
    $clean = [];
    foreach ( (array) $rows as $r ) {
        if ( ! is_array( $r ) ) continue;
        $label = sanitize_text_field( $r['label'] ?? '' );
        if ( $label === '' ) continue;
        $clean[] = [
            'label'  => $label,
            'amount' => round( floatval( $r['amount'] ?? 0 ), 2 ),
            'due'    => sanitize_text_field( $r['due'] ?? '' ),
            'paid'   => ! empty( $r['paid'] ) ? 1 : 0,
        ];
    }
    return wp_json_encode( $clean );
}

/** Échéancier décodé d'un contrat. Toujours un tableau, même si la colonne est vide. */
function vb_contract_schedule( $contract ) {
    $raw = is_object( $contract ) ? ( $contract->payment_schedule ?? '' ) : ( $contract['payment_schedule'] ?? '' );
    $rows = json_decode( (string) $raw, true );
    return is_array( $rows ) ? $rows : [];
}

/**
 * Totaux de l'échéancier + contrôle de cohérence avec le montant du contrat.
 * `balanced` à false signale un échéancier qui ne tombe pas juste : c'est
 * exactement le genre d'erreur qu'on ne veut pas envoyer à un client.
 */
function vb_contract_schedule_totals( $contract ) {
    $rows      = vb_contract_schedule( $contract );
    $total     = is_object( $contract ) ? floatval( $contract->amount_total ) : floatval( $contract['amount_total'] ?? 0 );
    $scheduled = 0.0;
    $paid      = 0.0;

    foreach ( $rows as $r ) {
        $amount     = floatval( $r['amount'] ?? 0 );
        $scheduled += $amount;
        if ( ! empty( $r['paid'] ) ) $paid += $amount;
    }

    return [
        'lines'      => count( $rows ),
        'scheduled'  => round( $scheduled, 2 ),
        'paid'       => round( $paid, 2 ),
        'unpaid'     => round( $scheduled - $paid, 2 ),
        'contract'   => round( $total, 2 ),
        'difference' => round( $scheduled - $total, 2 ),
        'balanced'   => abs( $scheduled - $total ) < 0.01,
    ];
}

/* ============================================================
   STATISTIQUES
============================================================ */

/** Compteurs pour le tableau de bord et la pastille du menu. */
function vb_get_contracts_stats( $year = '' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_contracts';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        return (object) [
            'total' => 0, 'draft' => 0, 'sent' => 0, 'signed' => 0,
            'completed' => 0, 'cancelled' => 0,
            'signed_value' => 0, 'pending_value' => 0, 'mrr_contracted' => 0,
        ];
    }

    $where = ''; $params = [];
    if ( $year ) { $where = 'WHERE YEAR(created_at) = %d'; $params[] = intval( $year ); }

    $sql = "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='draft'     THEN 1 ELSE 0 END) AS draft,
        SUM(CASE WHEN status='sent'      THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status='signed'    THEN 1 ELSE 0 END) AS signed,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
        COALESCE(SUM(CASE WHEN status IN ('signed','completed') THEN amount_total ELSE 0 END),0) AS signed_value,
        COALESCE(SUM(CASE WHEN status IN ('draft','sent')       THEN amount_total ELSE 0 END),0) AS pending_value,
        COALESCE(SUM(CASE WHEN status='signed' AND maintenance_enabled=1 THEN maintenance_price ELSE 0 END),0) AS mrr_contracted
        FROM $table $where";

    return $params ? $wpdb->get_row( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_row( $sql );
}

/** Projets sans aucun contrat — le trou de couverture juridique. */
function vb_get_projects_without_contract( $limit = 50 ) {
    global $wpdb;
    $pt = $wpdb->prefix . 'vb_projects';
    $ct = $wpdb->prefix . 'vb_contracts';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $ct ) ) !== $ct ) return [];

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT p.* FROM $pt p
         WHERE p.status IN ('in_progress','completed')
           AND NOT EXISTS (
               SELECT 1 FROM $ct c
               WHERE c.project_id = p.id AND c.status <> 'cancelled'
           )
         ORDER BY p.created_at DESC LIMIT %d",
        intval( $limit )
    ) );
}
