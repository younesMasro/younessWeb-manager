<?php
/**
 * Test : message WhatsApp pré-rempli sur les liens wa.me de la page Leads.
 *
 *   php tests/whatsapp-prefill.test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/whatsapp-templates.php';

function esc_textarea( $v ) { return htmlspecialchars( (string) $v ); }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }

vb_create_leads_table();

/** Crée un lead et renvoie l'objet. */
function make_lead( array $data ) {
    $id = vb_insert_lead( array_merge( [ 'full_name' => 'Test', 'phone' => '+212600000000' ], $data ) );
    return vb_get_lead( is_int( $id ) ? $id : 0 );
}

/** Décode le paramètre ?text= d'un lien wa.me. */
function text_of( $url ) {
    parse_str( parse_url( $url, PHP_URL_QUERY ) ?? '', $q );
    return $q['text'] ?? '';
}

$defaults = vb_wa_default_templates();

echo "\n\033[1m1. Le lien n'est plus une conversation vide\033[0m\n";

$lead = make_lead( [ 'reference' => 'L-1', 'phone' => '+212661234567', 'locale' => 'fr' ] );
$url  = vb_wa_link_for_lead( $lead );

ok( strpos( $url, 'https://wa.me/212661234567?text=' ) === 0, 'format https://wa.me/{phone}?text={message}' );
ok( text_of( $url ) !== '', 'un message est bien pré-rempli' );


echo "\n\033[1m2. La langue du lead choisit le modèle\033[0m\n";

foreach ( [ 'ar', 'fr', 'en' ] as $lang ) {
    $l = make_lead( [ 'reference' => "L-$lang", 'phone' => '+21266000' . rand( 100, 999 ), 'locale' => $lang ] );
    is_eq( text_of( vb_wa_link_for_lead( $l ) ), $defaults[ $lang ], "locale=$lang -> modèle $lang" );
}

// Le texte arabe doit survivre à l'encodage sans être mutilé.
$ar = make_lead( [ 'reference' => 'L-ar2', 'phone' => '+212600111222', 'locale' => 'ar' ] );
$t  = text_of( vb_wa_link_for_lead( $ar ) );
ok( strpos( $t, 'مرحباً' ) === 0, 'arabe : le message commence bien par مرحباً' );
ok( strpos( $t, 'YounessWeb' ) !== false, 'arabe : la marque YounessWeb est préservée' );
ok( mb_check_encoding( $t, 'UTF-8' ), 'arabe : UTF-8 intact après aller-retour d\'encodage' );


echo "\n\033[1m3. Encodage de l'URL\033[0m\n";

$fr  = make_lead( [ 'reference' => 'L-fr2', 'phone' => '+212600222333', 'locale' => 'fr' ] );
$raw = substr( vb_wa_link_for_lead( $fr ), strpos( vb_wa_link_for_lead( $fr ), 'text=' ) + 5 );

ok( strpos( $raw, '%0A' ) !== false, 'les retours à la ligne sont encodés (%0A)' );
ok( strpos( $raw, ' ' ) === false, 'aucun espace brut dans l\'URL' );
ok( strpos( $raw, '+' ) === false, 'espaces encodés en %20 et non en "+" (rawurlencode)' );
ok( strpos( text_of( vb_wa_link_for_lead( $fr ) ), "\n" ) !== false, 'les sauts de ligne survivent au décodage' );
is_eq( text_of( vb_wa_link_for_lead( $fr ) ), $defaults['fr'], 'message décodé identique au modèle' );


echo "\n\033[1m4. Langue absente ou inconnue -> langue par défaut\033[0m\n";

$none = make_lead( [ 'reference' => 'L-none', 'phone' => '+212600333444', 'locale' => '' ] );
is_eq( text_of( vb_wa_link_for_lead( $none ) ), $defaults['fr'], 'locale vide -> français (défaut)' );

$es = make_lead( [ 'reference' => 'L-es', 'phone' => '+212600444555', 'locale' => 'es' ] );
is_eq( text_of( vb_wa_link_for_lead( $es ) ), $defaults['fr'], 'locale inconnue (es) -> français' );

$arma = make_lead( [ 'reference' => 'L-arma', 'phone' => '+212600555666', 'locale' => 'ar-MA' ] );
is_eq( vb_wa_lead_language( $arma ), 'ar', 'locale "ar-MA" -> ar' );

vb_wa_save_templates( [], 'en' );
is_eq( text_of( vb_wa_link_for_lead( $none ) ), $defaults['en'], 'langue par défaut configurable -> en' );
vb_wa_save_templates( [], 'fr' );


echo "\n\033[1m5. Modèles éditables depuis les réglages\033[0m\n";

vb_wa_save_templates( [ 'fr' => "Bonjour {first_name},\nSuite à votre demande {reference}." ] );
$custom = text_of( vb_wa_link_for_lead( $fr ) );
is_eq( $custom, "Bonjour Test,\nSuite à votre demande L-fr2.", 'modèle personnalisé + variables {first_name} / {reference}' );

is_eq( text_of( vb_wa_link_for_lead( $ar ) ), $defaults['ar'], 'les autres langues gardent leur modèle' );

vb_wa_save_templates( [ 'fr' => '   ' ] );
is_eq( text_of( vb_wa_link_for_lead( $fr ) ), $defaults['fr'], 'modèle vidé -> retour au modèle d\'usine' );

vb_wa_save_templates( [ 'fr' => "Ligne 1\nLigne 2\n\nLigne 4" ] );
is_eq( text_of( vb_wa_link_for_lead( $fr ) ), "Ligne 1\nLigne 2\n\nLigne 4", 'sanitisation : les retours à la ligne sont préservés' );

vb_wa_save_templates( [ 'fr' => "J'ai bien reçu votre demande" ] );
is_eq( text_of( vb_wa_link_for_lead( $fr ) ), "J'ai bien reçu votre demande", 'apostrophes et accents préservés' );

delete_option( 'vb_wa_templates' );
is_eq( text_of( vb_wa_link_for_lead( $fr ) ), $defaults['fr'], 'réinitialisation -> modèles d\'usine' );


echo "\n\033[1m6. Nom du client dans le modèle\033[0m\n";

$named = make_lead( [ 'reference' => 'L-nm', 'phone' => '+212600666777', 'locale' => 'fr', 'full_name' => 'Ahmed Ben Ali' ] );
vb_wa_save_templates( [ 'fr' => 'A: {name} | P: {first_name}' ] );
is_eq( text_of( vb_wa_link_for_lead( $named ) ), 'A: Ahmed Ben Ali | P: Ahmed', '{name} = nom complet, {first_name} = prénom' );
delete_option( 'vb_wa_templates' );


echo "\n\033[1m7. Numéros de téléphone\033[0m\n";

$local = make_lead( [ 'reference' => 'L-loc', 'phone' => '0612345678', 'locale' => 'fr' ] );
ok( strpos( vb_wa_link_for_lead( $local ), 'wa.me/212612345678?' ) !== false, '0612345678 -> 212612345678' );

$spaced = make_lead( [ 'reference' => 'L-sp', 'phone' => '+212 661 23 45 68', 'locale' => 'fr' ] );
ok( strpos( vb_wa_link_for_lead( $spaced ), 'wa.me/212661234568?' ) !== false, 'espaces retirés du numéro' );

$nophone = (object) [ 'phone' => '', 'locale' => 'fr', 'full_name' => 'X', 'reference' => 'L-np' ];
is_eq( vb_wa_link_for_lead( $nophone ), '', 'pas de numéro -> pas de lien (bouton masqué)' );


echo "\n\033[1m8. Rien n'est envoyé automatiquement\033[0m\n";

ok( strpos( vb_wa_link_for_lead( $lead ), 'wa.me' ) !== false, 'le lien ouvre WhatsApp côté utilisateur' );
ok( strpos( vb_wa_link_for_lead( $lead ), 'api.whatsapp.com/send?' ) === false
    && strpos( vb_wa_link_for_lead( $lead ), 'graph.facebook.com' ) === false,
    'aucun appel à une API d\'envoi — la conversation s\'ouvre, Youness valide' );

summary();
