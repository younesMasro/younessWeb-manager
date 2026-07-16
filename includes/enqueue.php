<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_enqueue_scripts', function($hook) {
    if ( strpos($hook, 'vendbase') === false ) return;

    wp_enqueue_style('vb-main',   VB_PLUGIN_URL . 'assets/css/main.css',  [], VB_VERSION);

    wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], null, true);
    wp_enqueue_script('vb-main',  VB_PLUGIN_URL . 'assets/js/main.js',    ['jquery', 'chart-js'], VB_VERSION, true);

    /* Original VB object — DO NOT change keys, used everywhere in existing JS */
    wp_localize_script('vb-main', 'VB', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vb_nonce'),
        'year'  => date('Y'),
    ]);

    /* Expenses JS — loaded on all vendbase pages, guards itself internally */
    wp_enqueue_script('vb-expenses', VB_PLUGIN_URL . 'assets/js/expenses.js', ['jquery', 'chart-js'], VB_VERSION, true);
    wp_localize_script('vb-expenses', 'VB_EXP', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vb_nonce'),
        'today'   => date('Y-m-d'),
        'month'   => date('m'),
        'year'    => date('Y'),
    ]);
});
