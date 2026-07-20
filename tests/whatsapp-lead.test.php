<?php
/**
 * Test end-to-end : agent WhatsApp (Cloudflare Worker) -> Leads CRM.
 *
 * On appelle le vrai callback REST enregistré par le plugin, avec un
 * WP_REST_Request identique à celui que WordPress construirait.
 *
 *   php tests/whatsapp-lead.test.php
 */

require_once __DIR__ . '/bootstrap.php';

vb_create_leads_table();
$KEY = vb_get_whatsapp_api_secret();

/** Simule un POST /wp-json/younessweb/v1/whatsapp-lead. */
function post_whatsapp( array $payload, $key = null ) {
    global $KEY;
    $req  = new WP_REST_Request( $payload, [ 'X-API-Key' => $key ?? $KEY ] );
    $auth = vb_rest_check_whatsapp_key( $req );
    if ( $auth instanceof WP_Error ) return new WP_REST_Response( [ 'error' => $auth->message ], $auth->get_status() );
    return vb_rest_create_whatsapp_lead( $req );
}

/** Payload d'une qualification WhatsApp complète. */
function qualification( array $overrides = [] ) {
    return array_merge( [
        'source'            => 'whatsapp',
        'reference'         => 'WA-2026-0042',
        'language'          => 'ar',
        'website_type'      => 'ecommerce',
        'project_type'      => 'new',
        'products_count'    => '11-50',
        'has_domain'        => true,
        'content_ready'     => 'partial',
        'maintenance'       => true,
        'preferred_contact' => 'whatsapp',
        'customer_name'     => 'Ahmed Benali',
        'phone'             => '+212661234567',
        'priority'          => 'high',
        'message'           => 'Je veux une boutique en ligne pour mes produits cosmétiques.',
    ], $overrides );
}

echo "\n\033[1m1. Authentification\033[0m\n";

$r = post_whatsapp( qualification(), 'mauvaise-cle' );
is_eq( $r->status, 403, 'clé API invalide -> 403' );

$req = new WP_REST_Request( qualification(), [] );
$auth = vb_rest_check_whatsapp_key( $req );
ok( $auth instanceof WP_Error && $auth->get_status() === 401, 'clé API absente -> 401' );


echo "\n\033[1m2. Création du lead depuis une qualification WhatsApp\033[0m\n";

reset_actions();
$r = post_whatsapp( qualification() );
is_eq( $r->status, 201, 'qualification valide -> 201 Created' );
is_eq( $r->data['success'], true, 'réponse success:true' );
is_eq( $r->data['created'], true, 'réponse created:true' );
is_eq( $r->data['reference'], 'WA-2026-0042', 'référence renvoyée au Worker' );

$id   = $r->data['id'];
$lead = vb_get_lead( $id );

ok( $lead !== null, 'le lead existe dans wp_vb_leads' );
is_eq( $lead->source, 'whatsapp', 'source = whatsapp' );
is_eq( $lead->status, 'new', 'status = new' );
is_eq( $lead->project_id, null, 'AUCUN projet créé automatiquement' );

echo "\n  Réponses de qualification enregistrées :\n";
is_eq( $lead->reference, 'WA-2026-0042', '  reference' );
is_eq( $lead->full_name, 'Ahmed Benali', '  customer_name -> full_name' );
is_eq( $lead->phone, '+212661234567', '  phone' );
is_eq( $lead->locale, 'ar', '  language -> locale' );
is_eq( $lead->website_type, 'ecommerce', '  website_type' );
is_eq( $lead->project_type, 'new', '  project_type' );
is_eq( $lead->products_count, '11-50', '  products_count' );
is_eq( $lead->domain_status, 'yes', '  has_domain:true -> domain_status=yes' );
is_eq( $lead->content_ready, 'partial', '  content_ready' );
is_eq( (int) $lead->maintenance, 1, '  maintenance' );
is_eq( $lead->preferred_contact, 'whatsapp', '  preferred_contact' );
is_eq( $lead->priority, 'high', '  priority' );

is_eq( count( fired_actions( 'vb_whatsapp_lead_received' ) ), 1, 'hook vb_whatsapp_lead_received déclenché' );


echo "\n\033[1m3. Anti-doublon : même référence renvoyée\033[0m\n";

reset_actions();
$r2 = post_whatsapp( qualification( [
    'content_ready' => 'yes',          // le client a fini de préparer son contenu
    'priority'      => 'medium',
    'products_count'=> '51-200',
] ) );

is_eq( $r2->status, 200, 'référence connue -> 200 (pas 201)' );
is_eq( $r2->data['updated'], true, 'réponse updated:true' );
is_eq( $r2->data['id'], $id, 'même id de lead — aucun doublon' );

global $wpdb;
$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}vb_leads WHERE reference = 'WA-2026-0042'" );
is_eq( $count, 1, 'une seule ligne en base pour cette référence' );

$lead = vb_get_lead( $id );
is_eq( $lead->content_ready, 'yes', 'champ mis à jour (content_ready)' );
is_eq( $lead->products_count, '51-200', 'champ mis à jour (products_count)' );
is_eq( $lead->priority, 'medium', 'champ mis à jour (priority)' );
is_eq( count( fired_actions( 'vb_whatsapp_lead_received' ) ), 0, 'pas de re-notification sur mise à jour' );


echo "\n\033[1m4. La mise à jour ne piétine pas le travail commercial\033[0m\n";

vb_update_lead( $id, [ 'status' => 'contacted', 'internal_note' => 'Rappelé lundi', 'quoted_price' => 4500 ] );
post_whatsapp( qualification( [ 'message' => 'Une précision de plus.' ] ) );

$lead = vb_get_lead( $id );
is_eq( $lead->status, 'contacted', 'status CRM préservé (pas de retour à new)' );
is_eq( $lead->internal_note, 'Rappelé lundi', 'note interne préservée' );
is_eq( (float) $lead->quoted_price, 4500.0, 'devis préservé' );
is_eq( $lead->message, 'Une précision de plus.', 'message bien mis à jour' );


echo "\n\033[1m5. Champ vide côté Worker n'écrase pas une valeur connue\033[0m\n";

post_whatsapp( [
    'reference'     => 'WA-2026-0042',
    'customer_name' => 'Ahmed Benali',
    'phone'         => '+212661234567',
    'website_type'  => '',              // le Worker n'a pas cette info dans ce renvoi
] );
$lead = vb_get_lead( $id );
is_eq( $lead->website_type, 'ecommerce', 'website_type conservé malgré un champ vide' );


echo "\n\033[1m6. Validation des entrées\033[0m\n";

is_eq( post_whatsapp( qualification( [ 'customer_name' => '' ] ) )->status, 400, 'nom manquant -> 400' );
is_eq( post_whatsapp( qualification( [ 'phone' => '' ] ) )->status, 400, 'téléphone manquant -> 400' );

$r = post_whatsapp( qualification( [ 'reference' => 'WA-PRIO-TEST', 'priority' => 'urgentissime', 'phone' => '+212600000001' ] ) );
is_eq( vb_get_lead( $r->data['id'] )->priority, 'medium', 'priorité inconnue -> medium' );

$r = post_whatsapp( qualification( [ 'reference' => 'WA-DOM-TEST', 'has_domain' => false, 'phone' => '+212600000002' ] ) );
is_eq( vb_get_lead( $r->data['id'] )->domain_status, 'no', 'has_domain:false -> domain_status=no' );


echo "\n\033[1m7. Le lead apparaît dans le Leads CRM (mêmes requêtes que la page)\033[0m\n";

$all = vb_get_leads( [] );
ok( in_array( $id, array_map( fn( $l ) => (int) $l->id, $all ), true ), 'présent dans la liste par défaut' );

$wa_only = vb_get_leads( [ 'source' => 'whatsapp' ] );
ok( count( $wa_only ) > 0, 'filtre Source=WhatsApp renvoie des résultats' );
ok( array_reduce( $wa_only, fn( $c, $l ) => $c && vb_normalize_lead_source( $l->source ) === 'whatsapp', true ),
    'filtre Source=WhatsApp ne renvoie que du WhatsApp' );

$src = vb_lead_sources();
is_eq( $src['whatsapp']['icon'] . ' ' . $src['whatsapp']['label'], '💬 WhatsApp', 'badge 💬 WhatsApp' );
is_eq( $src['website']['icon'] . ' ' . $src['website']['label'], '🌐 Site web', 'badge 🌐 Site web' );

$found = vb_get_leads( [ 'search' => 'WA-2026-0042' ] );
ok( count( $found ) === 1 && (int) $found[0]->id === $id, 'recherche par référence' );


echo "\n\033[1m8. Rétrocompatibilité : le formulaire du site est intact\033[0m\n";

$web_id = vb_insert_lead( [
    'full_name' => 'Sofia Idrissi', 'phone' => '+212677000111', 'email' => 'sofia@example.com',
    'website_type' => 'business', 'package_interest' => 'essentiel', 'source' => 'website_form',
] );
ok( is_int( $web_id ) && $web_id > 0, 'lead site web inséré' );

$web = vb_get_lead( $web_id );
is_eq( vb_normalize_lead_source( $web->source ), 'website', 'source normalisée -> website' );
is_eq( $web->reference, sprintf( 'LEAD-%05d', $web_id ), 'référence auto LEAD-xxxxx toujours générée' );
is_eq( $web->status, 'new', 'status = new' );

// L'ancienne déduplication 24h (sans référence) doit rester active.
is_eq( vb_insert_lead( [ 'full_name' => 'Sofia Idrissi', 'phone' => '+212677000111', 'source' => 'website_form' ] ),
    'duplicate', 'double soumission du formulaire toujours bloquée (24h)' );

// Mais un lead WhatsApp avec référence NE doit PAS être avalé par cette heuristique.
$r = post_whatsapp( qualification( [ 'reference' => 'WA-MEME-TEL', 'phone' => '+212677000111' ] ) );
is_eq( $r->status, 201, 'WhatsApp du même numéro qu\'un lead site -> créé quand même' );
ok( $r->data['id'] !== $web_id, 'et c\'est bien un lead distinct' );

$web_only = vb_get_leads( [ 'source' => 'website' ] );
ok( array_reduce( $web_only, fn( $c, $l ) => $c && vb_normalize_lead_source( $l->source ) === 'website', true ),
    'filtre Source=Website exclut le WhatsApp' );


echo "\n\033[1m9. Aucun projet créé, jamais\033[0m\n";

foreach ( vb_get_leads( [ 'limit' => 500 ] ) as $l ) {
    if ( $l->project_id ) { ok( false, "lead #{$l->id} a un project_id" ); break; }
}
ok( true, 'aucun lead ne porte de project_id après import WhatsApp' );

summary();
