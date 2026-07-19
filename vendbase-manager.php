<?php
/**
 * Plugin Name: YounessWeb Manager
 * Plugin URI:  https://younesweb.ma
 * Description: إدارة مشاريع يونس ويب — تتبع العملاء، المشاريع، الإحصائيات والدépenses
 * Version:     2.5.0
 * Author:      Younes Web
 * Text Domain: vendbase
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VB_VERSION',    '2.5.0' );
define( 'VB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VB_PLUGIN_DIR . 'includes/db.php';
require_once VB_PLUGIN_DIR . 'includes/post-type.php';
require_once VB_PLUGIN_DIR . 'includes/meta-fields.php';
require_once VB_PLUGIN_DIR . 'includes/admin-menu.php';
require_once VB_PLUGIN_DIR . 'includes/ajax.php';
require_once VB_PLUGIN_DIR . 'includes/rest.php';
require_once VB_PLUGIN_DIR . 'includes/roi.php';
require_once VB_PLUGIN_DIR . 'includes/backup.php';
require_once VB_PLUGIN_DIR . 'includes/updater.php';
require_once VB_PLUGIN_DIR . 'includes/enqueue.php';

register_activation_hook( __FILE__, 'vb_activate' );
function vb_activate() {
    vb_create_tables();
    vb_create_expenses_table();
    vb_create_leads_table();
    vb_get_lead_api_secret();      // clé API du site (formulaire)
    vb_get_whatsapp_api_secret();  // clé API du canal WhatsApp
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'vb_deactivate' );
function vb_deactivate() {
    // Ne jamais laisser tourner la sauvegarde auto d'un plugin désactivé.
    $scheduled = wp_next_scheduled( 'vb_weekly_backup_event' );
    if ( $scheduled ) wp_unschedule_event( $scheduled, 'vb_weekly_backup_event' );
}

add_action( 'plugins_loaded', function() {
    vb_create_tables();
    vb_create_expenses_table();
    vb_create_leads_table();
} );
