<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_enqueue_scripts', function($hook) {
    if ( strpos($hook, 'vendbase') === false ) return;

    wp_enqueue_style('vb-main',   VB_PLUGIN_URL . 'assets/css/main.css',  [], VB_VERSION);

    /* Typographie du contrat : la MÊME feuille sert à l'aperçu écran et à la
       fenêtre d'impression. C'est ce qui garantit que le document imprimé est
       exactement celui qu'on a validé à l'écran. */
    wp_enqueue_style('vb-contract', VB_PLUGIN_URL . 'assets/css/contract.css', ['vb-main'], VB_VERSION);

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
