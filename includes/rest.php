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

/* ============================================================
   WHATSAPP AI AGENT — endpoint dédié (v2.5)

   Endpoint : POST /wp-json/younessweb/v1/whatsapp-lead
   Auth     : header  X-API-Key: <clé WhatsApp>

   Le Cloudflare Worker envoie le payload une fois la qualification
   WhatsApp terminée. On crée UNIQUEMENT un lead (jamais de projet).
============================================================ */

/** Clé API du canal WhatsApp (distincte de celle du site), générée à la volée. */
function vb_get_whatsapp_api_secret() {
    $secret = get_option( 'vb_whatsapp_api_secret' );
    if ( ! $secret ) {
        $secret = wp_generate_password( 48, false, false );
        add_option( 'vb_whatsapp_api_secret', $secret, '', false );
    }
    return $secret;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'younessweb/v1', '/whatsapp-lead', [
        'methods'             => 'POST',
        'callback'            => 'vb_rest_create_whatsapp_lead',
        'permission_callback' => 'vb_rest_check_whatsapp_key',
    ] );
} );

/** Vérifie la clé API WhatsApp (comparaison à temps constant). */
function vb_rest_check_whatsapp_key( WP_REST_Request $request ) {
    // Accepte X-API-Key (recommandé) ou Authorization: Bearer <clé>.
    $provided = $request->get_header( 'x_api_key' );
    if ( ! $provided ) {
        $auth = $request->get_header( 'authorization' );
        if ( $auth && stripos( $auth, 'bearer ' ) === 0 ) {
            $provided = trim( substr( $auth, 7 ) );
        }
    }
    if ( ! $provided ) {
        return new WP_Error( 'vb_no_key', 'Missing API key', [ 'status' => 401 ] );
    }
    if ( ! hash_equals( vb_get_whatsapp_api_secret(), $provided ) ) {
        return new WP_Error( 'vb_bad_key', 'Invalid API key', [ 'status' => 403 ] );
    }
    return true;
}

/**
 * Crée un lead à partir du payload de qualification WhatsApp.
 * Ne crée JAMAIS de projet — conversion manuelle uniquement.
 */
function vb_rest_create_whatsapp_lead( WP_REST_Request $request ) {
    $b = $request->get_json_params();
    if ( ! is_array( $b ) ) {
        return new WP_REST_Response( [ 'error' => 'Invalid body' ], 400 );
    }

    $name  = trim( (string) ( $b['customer_name'] ?? '' ) );
    $phone = trim( (string) ( $b['phone'] ?? '' ) );
    if ( $name === '' || $phone === '' ) {
        return new WP_REST_Response( [ 'error' => 'customer_name and phone are required' ], 400 );
    }

    // Domaine : le Worker envoie un booléen has_domain ; on le mappe sur la
    // même échelle que le formulaire du site (yes/no) pour un affichage unifié.
    $domain_status = '';
    if ( array_key_exists( 'has_domain', $b ) ) {
        $domain_status = ! empty( $b['has_domain'] ) ? 'yes' : 'no';
    }

    // Langue : ar / fr / en.
    $lang = strtolower( substr( (string) ( $b['language'] ?? '' ), 0, 5 ) );

    $priority = strtolower( (string) ( $b['priority'] ?? 'medium' ) );
    if ( ! in_array( $priority, [ 'high', 'medium', 'low' ], true ) ) $priority = 'medium';

    $result = vb_insert_lead( [
        'reference'         => sanitize_text_field( $b['reference'] ?? '' ),
        'full_name'         => $name,
        'phone'             => $phone,
        'email'             => $b['email'] ?? '',
        'website_type'      => $b['website_type'] ?? '',
        'project_type'      => $b['project_type'] ?? '',
        'products_count'    => $b['products_count'] ?? '',
        'domain_status'     => $domain_status,
        'content_ready'     => $b['content_ready'] ?? '',
        'maintenance'       => ! empty( $b['maintenance'] ),
        'preferred_contact' => $b['preferred_contact'] ?? '',
        'priority'          => $priority,
        'message'           => $b['message'] ?? $b['notes'] ?? '',
        'locale'            => $lang,
        'source'            => 'whatsapp',
        'utm_source'        => 'whatsapp',
        'utm_medium'        => 'chatbot',
    ] );

    if ( $result === 'duplicate' ) {
        return new WP_REST_Response( [ 'success' => true, 'duplicate' => true ], 200 );
    }
    if ( ! $result ) {
        return new WP_REST_Response( [ 'error' => 'Could not save lead' ], 500 );
    }

    $lead = vb_get_lead( $result );
    do_action( 'vb_lead_received', $result, $b );
    do_action( 'vb_whatsapp_lead_received', $result, $b );

    return new WP_REST_Response( [
        'success'   => true,
        'id'        => $result,
        'reference' => $lead ? $lead->reference : '',
    ], 201 );
}
