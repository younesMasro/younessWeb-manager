<?php
/**
 * REST API — réception des leads depuis le site Next.js (younessweb.com).
 * v2.2 addition
 *
 * Endpoint : POST /wp-json/vendbase/v1/leads
 * Auth     : header  X-VB-Secret: <clé partagée>
 *
 * La clé est stockée dans l'option `vb_lead_api_secret` (générée
 * automatiquement à l'activation) et doit être copiée dans la variable
 * d'environnement WP_LEADS_SECRET du projet Next.js.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Retourne la clé partagée, en la générant au premier appel. */
function vb_get_lead_api_secret() {
    $secret = get_option( 'vb_lead_api_secret' );
    if ( ! $secret ) {
        $secret = wp_generate_password( 48, false, false );
        add_option( 'vb_lead_api_secret', $secret, '', false );
    }
    return $secret;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'vendbase/v1', '/leads', [
        'methods'             => 'POST',
        'callback'            => 'vb_rest_create_lead',
        'permission_callback' => 'vb_rest_check_secret',
    ] );
} );

/** Vérifie la clé partagée en comparaison à temps constant. */
function vb_rest_check_secret( WP_REST_Request $request ) {
    $provided = $request->get_header( 'x_vb_secret' );
    if ( ! $provided ) {
        return new WP_Error( 'vb_no_secret', 'Missing secret', [ 'status' => 401 ] );
    }
    if ( ! hash_equals( vb_get_lead_api_secret(), $provided ) ) {
        return new WP_Error( 'vb_bad_secret', 'Invalid secret', [ 'status' => 403 ] );
    }
    return true;
}

/** Crée un lead à partir du payload du formulaire Next.js. */
function vb_rest_create_lead( WP_REST_Request $request ) {
    $body = $request->get_json_params();
    if ( ! is_array( $body ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid body' ], 400 );
    }

    // Honeypot : le site le filtre déjà, on double la sécurité côté WP.
    if ( ! empty( $body['company_website'] ) ) {
        return new WP_REST_Response( [ 'success' => true, 'skipped' => 'honeypot' ], 200 );
    }

    $full_name = trim( (string) ( $body['fullName'] ?? '' ) );
    $phone     = trim( (string) ( $body['phone'] ?? '' ) );
    if ( $full_name === '' || $phone === '' ) {
        return new WP_REST_Response( [ 'error' => 'fullName and phone are required' ], 400 );
    }

    $result = vb_insert_lead( [
        'full_name'        => $full_name,
        'phone'            => $phone,
        'email'            => $body['email'] ?? '',
        'website_type'     => $body['websiteType'] ?? '',
        'package_interest' => $body['packageInterest'] ?? '',
        'domain_status'    => $body['domainStatus'] ?? '',
        'message'          => $body['message'] ?? '',
        'locale'           => $body['locale'] ?? '',
        'source'           => $body['source'] ?? 'website_form',
        'utm_source'       => $body['utmSource'] ?? '',
        'utm_medium'       => $body['utmMedium'] ?? '',
        'utm_campaign'     => $body['utmCampaign'] ?? '',
        'referer'          => $body['referer'] ?? '',
        'ip'               => $body['ip'] ?? '',
    ] );

    if ( $result === 'duplicate' ) {
        return new WP_REST_Response( [ 'success' => true, 'duplicate' => true ], 200 );
    }
    if ( ! $result ) {
        return new WP_REST_Response( [ 'error' => 'Could not save lead' ], 500 );
    }

    /**
     * Permet de brancher des notifications (email, WhatsApp, Slack…)
     * sans toucher à ce fichier.
     */
    do_action( 'vb_lead_received', $result, $body );

    return new WP_REST_Response( [ 'success' => true, 'id' => $result ], 201 );
}
