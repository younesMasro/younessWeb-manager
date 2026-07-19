<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'vb_register_menus' );
function vb_register_menus() {
    add_menu_page(
        'YounessWeb Manager', 'YounessWeb', 'manage_options',
        'vendbase', 'vb_page_dashboard', 'dashicons-chart-area', 3
    );
    add_submenu_page( 'vendbase', 'Dashboard',        'Dashboard',         'manage_options', 'vendbase',          'vb_page_dashboard' );
    add_submenu_page( 'vendbase', 'Leads CRM',        vb_leads_menu_label(), 'manage_options', 'vendbase-leads',  'vb_page_leads'     );
    add_submenu_page( 'vendbase', 'Projets',          'Projets',           'manage_options', 'vendbase-projects', 'vb_page_projects'  );
    add_submenu_page( 'vendbase', 'Nouveau projet',   'Nouveau',           'manage_options', 'vendbase-new',      'vb_page_form'      );
    add_submenu_page( 'vendbase', 'Editer',           '',                  'manage_options', 'vendbase-edit',     'vb_page_form'      );
    add_submenu_page( 'vendbase', 'Statistiques',     'Statistiques',      'manage_options', 'vendbase-stats',    'vb_page_stats'     );
    add_submenu_page( 'vendbase', 'Depenses',         'Depenses & Pub',    'manage_options', 'vendbase-expenses', 'vb_page_expenses'  );
    add_submenu_page( 'vendbase', 'Rentabilite pub',  '📈 Rentabilite ROI','manage_options', 'vendbase-roi',      'vb_page_roi'       );
    add_submenu_page( 'vendbase', 'Suivi mensuel',    '🔔 Suivi clients',  'manage_options', 'vendbase-tracking', 'vb_page_tracking'  );
    add_submenu_page( 'vendbase', 'Calendrier',       'Calendrier',        'manage_options', 'vendbase-calendar', 'vb_page_calendar'  );
    add_submenu_page( 'vendbase', 'Factures',         'Factures & Devis',  'manage_options', 'vendbase-invoices', 'vb_page_invoices'  );
    add_submenu_page( 'vendbase', 'Sauvegarde',       vb_backup_menu_label(), 'manage_options', 'vendbase-backup', 'vb_page_backup'   );
}

/**
 * Libellé du menu "Sauvegarde", avec une pastille d'alerte si aucune
 * sauvegarde n'a été faite depuis plus de 7 jours.
 */
function vb_backup_menu_label() {
    $label = '💾 Sauvegarde';
    $last  = get_option( 'vb_last_backup_at' );
    $stale = ! $last || ( current_time( 'timestamp' ) - strtotime( $last ) ) > 7 * DAY_IN_SECONDS;
    if ( $stale ) {
        $label .= ' <span class="awaiting-mod"><span class="pending-count">!</span></span>';
    }
    return $label;
}

/**
 * Libellé du menu "Leads CRM" avec une pastille rouge indiquant
 * le nombre de leads jamais traités (statut = new, non archivés).
 */
function vb_leads_menu_label() {
    global $wpdb;
    $table = $wpdb->prefix . 'vb_leads';
    $label = '📥 Leads CRM';

    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
        return $label;
    }
    $has_archived = in_array( 'archived', $wpdb->get_col( "DESC $table", 0 ), true );
    $new = intval( $wpdb->get_var(
        "SELECT COUNT(*) FROM $table WHERE status = 'new'" . ( $has_archived ? " AND archived = 0" : "" )
    ) );
    if ( $new > 0 ) {
        $label .= ' <span class="awaiting-mod"><span class="pending-count">' . $new . '</span></span>';
    }
    return $label;
}

function vb_page_dashboard() { require VB_PLUGIN_DIR . 'templates/dashboard.php'; }
function vb_page_leads()     { require VB_PLUGIN_DIR . 'templates/leads.php';      }
function vb_page_projects()  { require VB_PLUGIN_DIR . 'templates/projects.php';  }
function vb_page_form()      { require VB_PLUGIN_DIR . 'templates/form.php';       }
function vb_page_stats()     { require VB_PLUGIN_DIR . 'templates/stats.php';      }
function vb_page_expenses()  { require VB_PLUGIN_DIR . 'templates/expenses.php';   }
function vb_page_roi()       { require VB_PLUGIN_DIR . 'templates/roi.php';        }
function vb_page_tracking()  { require VB_PLUGIN_DIR . 'templates/tracking.php';   }
function vb_page_calendar()  { require VB_PLUGIN_DIR . 'templates/calendar.php';   }
function vb_page_invoices()  { require VB_PLUGIN_DIR . 'templates/invoices.php';   }
function vb_page_backup()    { require VB_PLUGIN_DIR . 'templates/backup.php';     }
