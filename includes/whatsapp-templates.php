<?php
/**
 * Messages WhatsApp pré-remplis — v2.6
 *
 * Cliquer sur le bouton WhatsApp d'un lead n'ouvre plus une conversation vide :
 * on pré-remplit un premier message professionnel, dans la langue choisie par
 * le client au moment de sa demande (lead.locale).
 *
 * Les modèles sont éditables depuis la page Leads CRM (bouton « Messages
 * WhatsApp ») et stockés dans l'option `vb_wa_templates`. Tant qu'ils n'ont
 * jamais été modifiés, ce sont les modèles par défaut ci-dessous qui servent.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Langues proposées pour les modèles. Aligné sur vb_lead_languages(). */
function vb_wa_template_languages() {
    return [
        'ar' => 'العربية',
        'fr' => 'Français',
        'en' => 'English',
    ];
}

/** Modèles d'usine. Servent aussi de valeur de repli si un modèle est vidé. */
function vb_wa_default_templates() {
    return [
        'ar' => "مرحباً،\n"
              . "أنا يونس من YounessWeb.\n\n"
              . "شكراً لاهتمامك بخدماتنا، لقد راجعت المعلومات التي أرسلتها وسيسعدني أن أساعدك في مشروعك.",

        'fr' => "Bonjour,\n\n"
              . "Je suis Youness de YounessWeb.\n\n"
              . "Merci pour votre demande.\n"
              . "J'ai bien reçu les informations concernant votre projet et je serai ravi d'en discuter avec vous.",

        'en' => "Hello,\n\n"
              . "I'm Youness from YounessWeb.\n\n"
              . "Thank you for your request.\n"
              . "I've reviewed the information about your project and I'd be happy to discuss it with you.",
    ];
}

/**
 * Modèles effectifs : ceux enregistrés, complétés par les modèles d'usine
 * pour toute langue absente ou laissée vide.
 */
function vb_wa_templates() {
    $defaults = vb_wa_default_templates();
    $saved    = get_option( 'vb_wa_templates', [] );
    if ( ! is_array( $saved ) ) $saved = [];

    $out = [];
    foreach ( $defaults as $lang => $default ) {
        $custom      = isset( $saved[ $lang ] ) ? trim( (string) $saved[ $lang ] ) : '';
        $out[ $lang ] = $custom !== '' ? $custom : $default;
    }
    return $out;
}

/** Langue utilisée quand le lead n'en a pas déclaré (ou une inconnue). */
function vb_wa_fallback_language() {
    $lang = get_option( 'vb_wa_fallback_lang', 'fr' );
    return array_key_exists( $lang, vb_wa_default_templates() ) ? $lang : 'fr';
}

/** Enregistre les modèles. Un champ vide restaure le modèle d'usine. */
function vb_wa_save_templates( array $templates, $fallback_lang = null ) {
    $allowed = array_keys( vb_wa_default_templates() );
    $clean   = [];
    foreach ( $allowed as $lang ) {
        if ( ! isset( $templates[ $lang ] ) ) continue;
        // sanitize_textarea_field préserve les retours à la ligne, contrairement
        // à sanitize_text_field — la mise en forme du message en dépend.
        $clean[ $lang ] = sanitize_textarea_field( $templates[ $lang ] );
    }
    update_option( 'vb_wa_templates', $clean, false );

    if ( $fallback_lang !== null && in_array( $fallback_lang, $allowed, true ) ) {
        update_option( 'vb_wa_fallback_lang', $fallback_lang, false );
    }
    return true;
}

/**
 * Normalise le numéro pour wa.me : chiffres uniquement, préfixe pays marocain
 * ajouté pour un 0X…  local (0612345678 → 212612345678).
 */
if ( ! function_exists( 'vb_lead_wa_number' ) ) {
    function vb_lead_wa_number( $phone ) {
        $n = preg_replace( '/[^0-9]/', '', (string) $phone );
        if ( strlen( $n ) === 10 && $n[0] === '0' ) $n = '212' . substr( $n, 1 );
        return $n;
    }
}

/** Langue du modèle à utiliser pour un lead donné. */
function vb_wa_lead_language( $lead ) {
    $raw  = is_object( $lead ) ? ( $lead->locale ?? '' ) : ( $lead['locale'] ?? '' );
    $lang = strtolower( substr( (string) $raw, 0, 2 ) ); // 'ar-MA' → 'ar'
    return array_key_exists( $lang, vb_wa_default_templates() ) ? $lang : vb_wa_fallback_language();
}

/**
 * Message pré-rempli pour un lead, dans sa langue.
 * Placeholders disponibles dans les modèles : {name}, {first_name}, {reference}.
 */
function vb_wa_message_for_lead( $lead ) {
    $templates = vb_wa_templates();
    $message   = $templates[ vb_wa_lead_language( $lead ) ];

    $name = trim( (string) ( is_object( $lead ) ? ( $lead->full_name ?? '' ) : ( $lead['full_name'] ?? '' ) ) );
    $ref  = trim( (string) ( is_object( $lead ) ? ( $lead->reference ?? '' ) : ( $lead['reference'] ?? '' ) ) );

    $first = $name !== '' ? preg_split( '/\s+/', $name )[0] : '';

    return strtr( $message, [
        '{name}'       => $name,
        '{first_name}' => $first,
        '{reference}'  => $ref,
    ] );
}

/**
 * Lien WhatsApp complet, message pré-rempli inclus.
 * Retourne '' si le lead n'a pas de numéro exploitable.
 *
 * rawurlencode (et non urlencode) : les espaces doivent devenir %20 et non '+',
 * sinon WhatsApp affiche des '+' au milieu du message.
 */
function vb_wa_link_for_lead( $lead ) {
    $phone = vb_lead_wa_number( is_object( $lead ) ? ( $lead->phone ?? '' ) : ( $lead['phone'] ?? '' ) );
    if ( $phone === '' ) return '';

    return 'https://wa.me/' . $phone . '?text=' . rawurlencode( vb_wa_message_for_lead( $lead ) );
}
