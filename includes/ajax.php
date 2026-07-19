<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Save project ── */
add_action('wp_ajax_vb_save_project', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

    global $wpdb;
    $id  = intval($_POST['id'] ?? 0);
    $pid = vb_save_project($_POST, $id);

    if ( $wpdb->last_error ) {
        wp_send_json_error('Erreur base de données: ' . $wpdb->last_error);
    }
    if ( ! $pid ) {
        wp_send_json_error('Impossible d enregistrer le projet.');
    }
    wp_send_json_success(['id' => $pid]);
});

/* ── Delete project ── */
add_action('wp_ajax_vb_delete_project', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    vb_delete_project(intval($_POST['id']));
    wp_send_json_success();
});

/* ── Get project (for modal edit) ── */
add_action('wp_ajax_vb_get_project', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    $project = vb_get_project(intval($_GET['id']));
    $project ? wp_send_json_success($project) : wp_send_json_error('Not found');
});

/* ── Stats data ── */
add_action('wp_ajax_vb_get_stats', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    $month = sanitize_text_field($_GET['month'] ?? '');
    $year  = sanitize_text_field($_GET['year']  ?? date('Y'));
    wp_send_json_success([
        'stats'   => vb_get_stats($month, $year),
        'monthly' => vb_get_monthly_chart($year),
        'types'   => vb_get_site_types_chart($year),
    ]);
});

/* ── Update status quick ── */
add_action('wp_ajax_vb_update_status', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    $wpdb->update($table,
        ['status' => sanitize_text_field($_POST['status'])],
        ['id'     => intval($_POST['id'])]
    );
    wp_send_json_success();
});

/* ── Update avance quick ── */
add_action('wp_ajax_vb_update_avance', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    $wpdb->update($table,
        ['avance' => floatval($_POST['avance'])],
        ['id'     => intval($_POST['id'])]
    );
    wp_send_json_success();
});

/* ── Expenses handlers — v2 addition ── */
add_action('wp_ajax_vb_save_expense', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id = intval($_POST['id'] ?? 0);
    $saved_id = vb_save_expense($_POST, $id);

    if ( ! $saved_id ) {
        global $wpdb;
        wp_send_json_error($wpdb->last_error ?: 'Impossible d enregistrer la depense.');
    }

    wp_send_json_success(['id' => $saved_id]);
});

add_action('wp_ajax_vb_delete_expense', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    vb_delete_expense(intval($_POST['id']));
    wp_send_json_success();
});

add_action('wp_ajax_vb_get_expense', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}vb_expenses WHERE id = %d",
        intval($_GET['id'])
    ));
    $row ? wp_send_json_success($row) : wp_send_json_error('Not found');
});

/* ── Tracking (Suivi mensuel) quick toggle — v2.1 addition ── */
add_action('wp_ajax_vb_toggle_tracking', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    global $wpdb;
    $table = $wpdb->prefix . 'vb_projects';
    $id     = intval($_POST['id']);
    $enable = intval($_POST['enabled']);
    $data = ['tracking_enabled' => $enable];
    if ( $enable && ! empty($_POST['tracking_price']) ) {
        $data['tracking_price'] = floatval($_POST['tracking_price']);
    }
    if ( $enable && empty($wpdb->get_var($wpdb->prepare("SELECT tracking_start_date FROM $table WHERE id=%d", $id))) ) {
        $data['tracking_start_date'] = current_time('Y-m-d');
    }
    $wpdb->update($table, $data, ['id' => $id]);
    wp_send_json_success();
});

/* ============================================================
   LEADS / DEMANDES CLIENTS — v2.2 addition
============================================================ */

/* ── Changer le statut d'un lead (pipeline) ── */
add_action('wp_ajax_vb_update_lead_status', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

    $id     = intval($_POST['id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');

    if ( ! array_key_exists($status, vb_lead_statuses()) ) {
        wp_send_json_error('Statut invalide');
    }
    $data = ['status' => $status];
    if ( isset($_POST['lost_reason']) ) $data['lost_reason'] = sanitize_text_field($_POST['lost_reason']);

    vb_update_lead($id, $data) ? wp_send_json_success() : wp_send_json_error('Mise à jour impossible');
});

/* ── Enregistrer le prix du devis proposé ── */
add_action('wp_ajax_vb_update_lead_quote', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

    $id    = intval($_POST['id'] ?? 0);
    $price = floatval($_POST['quoted_price'] ?? 0);
    $data  = ['quoted_price' => $price];

    // Proposer un prix fait automatiquement avancer le lead au statut "devis".
    $lead = vb_get_lead($id);
    if ( $lead && in_array($lead->status, ['new', 'contacted'], true) && $price > 0 ) {
        $data['status'] = 'quoted';
    }
    vb_update_lead($id, $data) ? wp_send_json_success(['status' => $data['status'] ?? null]) : wp_send_json_error('Erreur');
});

/* ── Note interne sur un lead ── */
add_action('wp_ajax_vb_update_lead_note', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id = intval($_POST['id'] ?? 0);
    vb_update_lead($id, ['internal_note' => wp_unslash($_POST['internal_note'] ?? '')])
        ? wp_send_json_success() : wp_send_json_error('Erreur');
});

/* ── Convertir un lead en projet ── */
add_action('wp_ajax_vb_convert_lead', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');

    $id         = intval($_POST['id'] ?? 0);
    $project_id = vb_convert_lead_to_project($id);

    if ( ! $project_id ) wp_send_json_error('Conversion impossible');
    wp_send_json_success([
        'project_id'  => $project_id,
        'edit_url'    => admin_url('admin.php?page=vendbase-edit&id=' . $project_id),
    ]);
});

/* ── Supprimer un lead ── */
add_action('wp_ajax_vb_delete_lead', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    vb_delete_lead(intval($_POST['id'] ?? 0));
    wp_send_json_success();
});

/* ── Régénérer la clé API des leads ── */
add_action('wp_ajax_vb_regenerate_lead_secret', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $secret = wp_generate_password(48, false, false);
    update_option('vb_lead_api_secret', $secret, false);
    wp_send_json_success(['secret' => $secret]);
});

/* ── CRM Leads : priorité (v2.5) ── */
add_action('wp_ajax_vb_update_lead_priority', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id       = intval($_POST['id'] ?? 0);
    $priority = sanitize_text_field($_POST['priority'] ?? '');
    if ( ! array_key_exists($priority, vb_lead_priorities()) ) wp_send_json_error('Priorité invalide');
    vb_update_lead($id, ['priority' => $priority]) ? wp_send_json_success() : wp_send_json_error('Erreur');
});

/* ── CRM Leads : assignation (future) ── */
add_action('wp_ajax_vb_update_lead_assignee', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id = intval($_POST['id'] ?? 0);
    vb_update_lead($id, ['assigned_to' => wp_unslash($_POST['assigned_to'] ?? '')])
        ? wp_send_json_success() : wp_send_json_error('Erreur');
});

/* ── CRM Leads : archiver / désarchiver ── */
add_action('wp_ajax_vb_archive_lead', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $id       = intval($_POST['id'] ?? 0);
    $archived = ! empty($_POST['archived']) ? 1 : 0;
    vb_update_lead($id, ['archived' => $archived]) ? wp_send_json_success() : wp_send_json_error('Erreur');
});

/* ── CRM Leads : régénérer la clé API WhatsApp ── */
add_action('wp_ajax_vb_regenerate_whatsapp_secret', function() {
    check_ajax_referer('vb_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    $secret = wp_generate_password(48, false, false);
    update_option('vb_whatsapp_api_secret', $secret, false);
    wp_send_json_success(['secret' => $secret]);
});
