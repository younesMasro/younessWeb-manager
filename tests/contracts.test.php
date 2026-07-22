<?php
/**
 * Module Contrats — tests de bout en bout.
 *
 * On appelle les VRAIES fonctions du plugin (includes/contracts.php et
 * includes/contract-templates.php) sur une base SQLite en mémoire. Aucune
 * logique n'est réimplémentée ici : si un test passe, c'est le code de
 * production qui a produit le résultat.
 *
 *   php tests/contracts.test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/contracts.php';
require_once __DIR__ . '/../includes/contract-templates.php';

vb_create_tables();
vb_create_contracts_table();

/** Projet de référence : un client réel, avec suivi mensuel actif. */
function make_project( array $o = [] ) {
    return vb_save_project( array_merge( [
        'client_name'  => 'Cutt Salons',
        'client_phone' => '0708-432216',
        'client_email' => 'contact@cuttsalons.com',
        'client_city'  => 'Casablanca',
        'site_type'    => 'Vitrine',
        'site_url'     => 'https://cuttsalons.com/',
        'prix'         => 4700,
        'avance'       => 1500,
        'status'       => 'in_progress',
        'start_date'   => '',
        'tracking_enabled'    => 1,
        'tracking_price'      => 400,
        'tracking_start_date' => '2026-03-10',
    ], $o ) );
}


echo "\n\033[1m1. Numérotation\033[0m\n";

is_eq( vb_next_contract_number( 2026 ), 'CTR-2026-001', 'premier numéro de l\'année' );

$c1 = vb_save_contract( [ 'client_name' => 'Client A', 'number' => 'CTR-2026-001', 'amount_total' => 1000 ] );
$c2 = vb_save_contract( [ 'client_name' => 'Client B', 'number' => 'CTR-2026-002', 'amount_total' => 2000 ] );
is_eq( vb_next_contract_number( 2026 ), 'CTR-2026-003', 'le numéro suit le plus grand émis' );

// Le point qui distingue un contrat d'une ligne de base : supprimer le dernier
// ne doit JAMAIS réattribuer son numéro, il existe sur un papier signé.
vb_delete_contract( $c2 );
is_eq( vb_next_contract_number( 2026 ), 'CTR-2026-003', 'après suppression, le numéro n\'est pas réutilisé' );

is_eq( vb_next_contract_number( 2027 ), 'CTR-2027-001', 'la numérotation repart à 1 chaque année' );


echo "\n\033[1m2. Garde-fous à l'enregistrement\033[0m\n";

is_eq( vb_save_contract( [ 'client_name' => '', 'amount_total' => 500 ] ), false, 'un contrat sans client est refusé' );

$id = vb_save_contract( [ 'client_name' => 'Test Acompte', 'amount_total' => 1000, 'deposit_amount' => 5000 ] );
$c  = vb_get_contract( $id );
is_eq( (float) $c->deposit_amount, 1000.0, 'un acompte supérieur au total est ramené au total' );

$id = vb_save_contract( [ 'client_name' => 'Statut', 'status' => 'n-importe-quoi' ] );
is_eq( vb_get_contract( $id )->status, 'draft', 'un statut inconnu retombe sur brouillon' );

$id = vb_save_contract( [ 'client_name' => 'Auto' ] );
$c  = vb_get_contract( $id );
ok( preg_match( '/^CTR-\d{4}-\d{3}$/', $c->number ) === 1, 'un numéro est attribué automatiquement' );
is_eq( $c->issue_date, date( 'Y-m-d' ), 'la date d\'émission par défaut est aujourd\'hui' );


echo "\n\033[1m3. Pré-remplissage depuis un projet (Projets + Clients + Paiements + Maintenance)\033[0m\n";

$pid  = make_project();
$data = vb_contract_prefill_from_project( $pid, 'creation' );

ok( $data !== false, 'le pré-remplissage trouve le projet' );
is_eq( $data['client_name'],  'Cutt Salons',            'le client est repris' );
is_eq( $data['client_phone'], '0708-432216',            'le téléphone est repris' );
is_eq( $data['site_url'],     'https://cuttsalons.com/','l\'URL du site est reprise' );
is_eq( (float) $data['amount_total'], 4700.0,           'le prix du projet devient le montant du contrat' );

// L'avance réellement encaissée prime sur le pourcentage théorique du modèle.
is_eq( (float) $data['deposit_amount'], 1500.0, 'l\'avance déjà encaissée sert d\'acompte' );

// Le suivi mensuel déjà actif force la clause de maintenance, même sur le
// modèle « création » qui ne la prévoit pas.
is_eq( (int) $data['maintenance_enabled'], 1,      'le suivi actif sur le projet active la clause' );
is_eq( (float) $data['maintenance_price'], 400.0,  'le prix du suivi est repris' );

is_eq( vb_contract_prefill_from_project( 999999 ), false, 'projet inexistant -> false' );

// Sans avance, on retombe sur le pourcentage d'acompte du modèle (50 %).
$pid2  = make_project( [ 'client_name' => 'Sans avance', 'prix' => 3000, 'avance' => 0, 'tracking_enabled' => 0 ] );
$data2 = vb_contract_prefill_from_project( $pid2, 'creation' );
is_eq( (float) $data2['deposit_amount'], 1500.0, 'sans avance, acompte = 50 % du modèle' );
is_eq( (int) $data2['maintenance_enabled'], 0,   'sans suivi ni modèle, pas de clause de maintenance' );


echo "\n\033[1m4. Échéancier\033[0m\n";

$sched = vb_contract_default_schedule( 4700, 1500 );
is_eq( count( $sched ), 2, 'acompte + solde' );
is_eq( (float) $sched[0]['amount'], 1500.0, 'ligne 1 = acompte' );
is_eq( (float) $sched[1]['amount'], 3200.0, 'ligne 2 = solde par soustraction' );

// Le solde est calculé par soustraction : la somme doit tomber au centime,
// même sur un montant qui se divise mal.
$s = vb_contract_default_schedule( 1000, 333.33 );
is_eq( round( $s[0]['amount'] + $s[1]['amount'], 2 ), 1000.0, 'la somme des lignes tombe juste au centime' );

is_eq( count( vb_contract_default_schedule( 2000, 0 ) ), 1, 'sans acompte : une seule ligne' );
is_eq( count( vb_contract_default_schedule( 2000, 2000 ) ), 1, 'payé d\'avance : une seule ligne' );

$id = vb_save_contract( [
    'client_name' => 'Échéancier', 'amount_total' => 4700, 'deposit_amount' => 1500,
    'payment_schedule' => vb_contract_default_schedule( 4700, 1500 ),
] );
$c = vb_get_contract( $id );

$t = vb_contract_schedule_totals( $c );
is_eq( $t['scheduled'], 4700.0, 'total de l\'échéancier' );
is_eq( $t['paid'], 0.0,         'rien de payé au départ' );
ok( $t['balanced'],             'échéancier équilibré' );

// Un échéancier qui ne tombe pas juste doit être détecté avant l'envoi client.
$rows = vb_contract_schedule( $c );
$rows[0]['amount'] = 999;
vb_save_contract( [
    'client_name' => 'Échéancier', 'amount_total' => 4700,
    'payment_schedule' => $rows,
], $id );
$t = vb_contract_schedule_totals( vb_get_contract( $id ) );
ok( ! $t['balanced'], 'un échéancier déséquilibré est signalé' );
is_eq( $t['difference'], -501.0, 'l\'écart est chiffré' );

// Une ligne sans libellé est du bruit, elle ne doit pas entrer en base.
$decoded = json_decode( vb_contract_encode_schedule( [
    [ 'label' => 'Vrai', 'amount' => 100 ],
    [ 'label' => '',     'amount' => 50  ],
] ), true );
is_eq( count( $decoded ), 1, 'les lignes sans libellé sont écartées' );

is_eq( vb_contract_schedule( (object) [ 'payment_schedule' => 'pas du json' ] ), [], 'un JSON illisible donne un tableau vide' );


echo "\n\033[1m5. Signature — la propagation vers le projet\033[0m\n";

$pid = make_project( [ 'client_name' => 'Signature', 'tracking_enabled' => 0, 'tracking_price' => 0, 'tracking_start_date' => '' ] );
$id  = vb_save_contract( array_merge(
    vb_contract_prefill_from_project( $pid, 'full' ),
    [ 'maintenance_enabled' => 1, 'maintenance_price' => 350, 'maintenance_start' => '2026-08-01' ]
) );

is_eq( vb_get_project( $pid )->start_date, null, 'le projet n\'a pas de date de démarrage avant signature' );

ok( vb_sign_contract( $id, '2026-07-22', 'Younes' ), 'la signature réussit' );

$c = vb_get_contract( $id );
is_eq( $c->status, 'signed',        'statut = signé' );
is_eq( $c->signed_date, '2026-07-22', 'date de signature enregistrée' );
is_eq( $c->signed_by, 'Younes',     'signataire enregistré' );

$p = vb_get_project( $pid );
is_eq( $p->start_date, '2026-07-22',   'le projet démarre à la date de signature' );
is_eq( (int) $p->tracking_enabled, 1,  'la clause de maintenance active le suivi mensuel' );
is_eq( (float) $p->tracking_price, 350.0, 'le prix du suivi vient du contrat' );
is_eq( $p->tracking_start_date, '2026-08-01', 'la date de début du suivi vient du contrat' );

// Un contrat signé ne se modifie plus : c'est une preuve, pas un brouillon.
ok( vb_contract_is_locked( vb_get_contract( $id ) ), 'un contrat signé est verrouillé' );
ok( ! vb_contract_is_locked( (object) [ 'status' => 'draft' ] ), 'un brouillon reste modifiable' );

// La signature ne touche JAMAIS à l'argent encaissé : la trésorerie ne se
// déduit pas d'un document.
is_eq( (float) $p->avance, 1500.0, 'la signature ne modifie pas l\'avance encaissée' );

// Une date de démarrage déjà posée ne doit pas être écrasée.
$pid3 = make_project( [ 'client_name' => 'Déjà démarré', 'start_date' => '2026-01-05', 'tracking_enabled' => 0 ] );
$id3  = vb_save_contract( [ 'client_name' => 'Déjà démarré', 'project_id' => $pid3, 'amount_total' => 1000 ] );
vb_sign_contract( $id3, '2026-07-22' );
is_eq( vb_get_project( $pid3 )->start_date, '2026-01-05', 'une date de démarrage existante est préservée' );

is_eq( vb_sign_contract( 999999 ), false, 'signer un contrat inexistant -> false' );

// Un contrat sans projet doit se signer sans erreur.
$orphan = vb_save_contract( [ 'client_name' => 'Hors projet', 'amount_total' => 800 ] );
ok( vb_sign_contract( $orphan, '2026-07-22' ), 'un contrat hors projet se signe sans planter' );


echo "\n\033[1m6. Rapprochement contrat ↔ projet (Paiements)\033[0m\n";

$pid = make_project( [ 'client_name' => 'Rapprochement', 'prix' => 4700, 'avance' => 1500, 'tracking_price' => 400 ] );
$id  = vb_save_contract( [
    'client_name' => 'Rapprochement', 'project_id' => $pid,
    'amount_total' => 5000, 'deposit_amount' => 1500,
    'maintenance_enabled' => 1, 'maintenance_price' => 400,
] );

$r = vb_contract_reconciliation( $id );
is_eq( $r['total_diff'], 300.0,  'l\'écart de montant est chiffré' );
is_eq( $r['remaining'], 3500.0,  'le reste à encaisser = contrat - avance projet' );
is_eq( $r['maint_diff'], 0.0,    'la maintenance est alignée' );
ok( ! $r['in_sync'],             'la divergence est signalée' );

ok( vb_contract_apply_to_project( $id ), 'l\'alignement du projet réussit' );
is_eq( (float) vb_get_project( $pid )->prix, 5000.0, 'le prix du projet suit le contrat' );
ok( vb_contract_reconciliation( $id )['in_sync'], 'après alignement, tout concorde' );

// L'alignement ne touche pas à l'encaissé.
is_eq( (float) vb_get_project( $pid )->avance, 1500.0, 'l\'alignement ne modifie pas l\'avance' );

$orphan = vb_save_contract( [ 'client_name' => 'Sans projet', 'amount_total' => 100 ] );
is_eq( vb_contract_reconciliation( $orphan ), null, 'contrat sans projet -> pas de rapprochement' );
is_eq( vb_contract_apply_to_project( $orphan ), false, 'rien à aligner sans projet' );


echo "\n\033[1m7. Montant en toutes lettres\033[0m\n";

is_eq( vb_number_to_words_fr( 0 ),    'zéro',                  '0' );
is_eq( vb_number_to_words_fr( 16 ),   'seize',                 '16' );
is_eq( vb_number_to_words_fr( 21 ),   'vingt et un',           '21 (liaison « et »)' );
is_eq( vb_number_to_words_fr( 71 ),   'soixante et onze',      '71 (le piège classique)' );
is_eq( vb_number_to_words_fr( 80 ),   'quatre-vingts',         '80 prend un s' );
is_eq( vb_number_to_words_fr( 81 ),   'quatre-vingt-un',       '81 perd le s' );
is_eq( vb_number_to_words_fr( 95 ),   'quatre-vingt-quinze',   '95' );
is_eq( vb_number_to_words_fr( 100 ),  'cent',                  '100' );
is_eq( vb_number_to_words_fr( 200 ),  'deux cents',            '200 prend un s' );
is_eq( vb_number_to_words_fr( 201 ),  'deux cent un',          '201 perd le s' );
is_eq( vb_number_to_words_fr( 1000 ), 'mille',                 '1000 (jamais « un mille »)' );
is_eq( vb_number_to_words_fr( 2500 ), 'deux mille cinq cents', '2500 — le panier moyen' );
is_eq( vb_number_to_words_fr( 4700 ), 'quatre mille sept cents', '4700 — Cutt Salons' );
is_eq( vb_number_to_words_fr( 87900 ), 'quatre-vingt-sept mille neuf cents', '87 900 — le CA total' );

is_eq( vb_amount_in_words( 2500 ),   'deux mille cinq cents dirhams', 'montant entier' );
is_eq( vb_amount_in_words( 1500.50 ), 'mille cinq cents dirhams et cinquante centimes', 'montant avec centimes' );


echo "\n\033[1m8. Modèles et rendu du contrat\033[0m\n";

$templates = vb_contract_templates();
is_eq( count( $templates ), 3, 'trois modèles d\'usine' );
foreach ( [ 'creation', 'maintenance', 'full' ] as $k ) {
    ok( isset( $templates[ $k ]['body'] ) && $templates[ $k ]['body'] !== '', "modèle « $k » a un corps" );
}

$pid = make_project( [ 'client_name' => 'Rendu SARL', 'prix' => 2500, 'avance' => 1250, 'tracking_enabled' => 0 ] );
$id  = vb_save_contract( vb_contract_prefill_from_project( $pid, 'creation' ) );
$out = vb_contract_render( vb_get_contract( $id ) );

ok( strpos( $out, '{{' ) === false, 'aucun marqueur {{…}} ne subsiste dans le rendu' );
ok( strpos( $out, '2 500,00 MAD' ) !== false, 'le montant est formaté' );
ok( strpos( $out, 'deux mille cinq cents dirhams' ) !== false, 'le montant en lettres apparaît' );
ok( strpos( $out, 'Acompte à la signature' ) !== false, 'l\'échéancier est injecté' );
ok( strpos( $out, 'ARTICLE 1' ) !== false, 'les articles sont présents' );

// Blocs conditionnels : le point le plus facile à rater, et le plus visible
// sur un document qui part chez un client.
ok( strpos( $out, 'ARTICLE 12' ) === false, 'sans maintenance, l\'article maintenance disparaît' );
ok( strpos( $out, 'pénalité' ) === false,   'sans pénalité, la clause disparaît' );
ok( strpos( $out, 'DISPOSITIONS PARTICULIÈRES' ) === false, 'sans clause libre, l\'article disparaît' );

$id2 = vb_save_contract( [
    'client_name' => 'Avec options', 'amount_total' => 3000,
    'maintenance_enabled' => 1, 'maintenance_price' => 300, 'maintenance_months' => 12,
    'maintenance_start' => '2026-09-01', 'late_penalty_percent' => 2,
    'custom_clauses' => 'Le client fournit les photos avant le 1er septembre.',
] );
$out2 = vb_contract_render( vb_get_contract( $id2 ) );

ok( strpos( $out2, 'ARTICLE 12' ) !== false,        'avec maintenance, l\'article apparaît' );
ok( strpos( $out2, '300,00 MAD par mois' ) !== false, 'le prix mensuel est rendu' );
ok( strpos( $out2, '01/09/2026' ) !== false,        'la date de début est formatée en jj/mm/aaaa' );
ok( strpos( $out2, 'pénalité de 2 %' ) !== false,   'la pénalité de retard apparaît' );
ok( strpos( $out2, 'photos avant le 1er septembre' ) !== false, 'la clause libre apparaît' );
ok( strpos( $out2, '{{' ) === false,                'toujours aucun marqueur résiduel' );

// Maintenance cochée mais prix à 0 : la clause serait vide de sens.
$id3  = vb_save_contract( [ 'client_name' => 'Maint 0', 'amount_total' => 1000, 'maintenance_enabled' => 1, 'maintenance_price' => 0 ] );
$out3 = vb_contract_render( vb_get_contract( $id3 ) );
ok( strpos( $out3, 'ARTICLE 12' ) === false, 'maintenance à 0 MAD : la clause reste masquée' );

// Cession des droits : deux textes opposés, jamais les deux.
$ceded = vb_contract_render( (object) [ 'template_key' => 'creation', 'ip_transfer' => 1, 'amount_total' => 100, 'client_name' => 'X' ] );
$kept  = vb_contract_render( (object) [ 'template_key' => 'creation', 'ip_transfer' => 0, 'amount_total' => 100, 'client_name' => 'X' ] );
ok( strpos( $ceded, 'est cédée au CLIENT' ) !== false,   'droits cédés : clause de cession' );
ok( strpos( $kept,  'demeurent la propriété du PRESTATAIRE' ) !== false, 'droits conservés : clause inverse' );

// Les livrables saisis ligne par ligne deviennent une liste à puces.
$scoped = vb_contract_render( (object) [
    'template_key' => 'creation', 'client_name' => 'X', 'amount_total' => 100,
    'scope' => "Maquette\nIntégration",
] );
ok( strpos( $scoped, '— Maquette' ) !== false && strpos( $scoped, '— Intégration' ) !== false, 'les livrables deviennent une liste' );


echo "\n\033[1m9. Personnalisation des modèles\033[0m\n";

vb_contract_save_templates( [ 'creation' => [ 'title' => 'Mon contrat maison' ] ] );
is_eq( vb_contract_templates()['creation']['title'], 'Mon contrat maison', 'le titre personnalisé est pris en compte' );
ok( vb_contract_templates()['creation']['body'] !== '', 'le corps non personnalisé reste celui d\'usine' );

vb_contract_save_templates( [ 'creation' => [ 'title' => '' ] ] );
is_eq( vb_contract_templates()['creation']['title'], vb_contract_default_templates()['creation']['title'],
    'un champ vidé restaure le modèle d\'usine' );

vb_contract_save_provider( [ 'name' => 'YounessWeb SARL', 'city' => 'Rabat' ] );
is_eq( vb_contract_provider()['name'], 'YounessWeb SARL', 'les coordonnées du prestataire sont enregistrées' );
is_eq( vb_contract_provider()['phone'], '+212 774-654464', 'les champs non fournis gardent leur valeur d\'usine' );


echo "\n\033[1m10. Requêtes et statistiques\033[0m\n";

$stats = vb_get_contracts_stats();
ok( $stats->total > 0, 'les contrats sont comptés' );
ok( (float) $stats->signed_value > 0, 'la valeur signée est calculée' );

$pid = make_project( [ 'client_name' => 'Filtre', 'tracking_enabled' => 0 ] );
$a   = vb_save_contract( [ 'client_name' => 'Filtre', 'project_id' => $pid, 'amount_total' => 100 ] );
$b   = vb_save_contract( [ 'client_name' => 'Filtre', 'project_id' => $pid, 'amount_total' => 200 ] );
is_eq( count( vb_get_project_contracts( $pid ) ), 2, 'les contrats d\'un projet sont retrouvés' );

$drafts = vb_get_contracts( [ 'status' => 'draft' ] );
foreach ( $drafts as $d ) {
    if ( $d->status !== 'draft' ) { ok( false, 'le filtre par statut fuit' ); break; }
}
ok( true, 'le filtre par statut ne renvoie que le statut demandé' );

ok( count( vb_get_contracts( [ 'search' => 'Rapprochement' ] ) ) > 0, 'la recherche par nom de client fonctionne' );
is_eq( count( vb_get_contracts( [ 'search' => 'zzz-introuvable-zzz' ] ) ), 0, 'une recherche sans résultat renvoie vide' );

// Le trou de couverture juridique : un projet actif sans contrat.
$naked = make_project( [ 'client_name' => 'Sans contrat', 'status' => 'in_progress', 'tracking_enabled' => 0 ] );
$ids   = array_map( fn( $p ) => (int) $p->id, vb_get_projects_without_contract( 100 ) );
ok( in_array( $naked, $ids, true ), 'un projet sans contrat est signalé' );
ok( ! in_array( $pid, $ids, true ), 'un projet déjà couvert n\'est pas signalé' );


echo "\n\033[1m11. Non-régression : les modules existants\033[0m\n";

// Le module contrats ne doit rien avoir cassé dans ce qui existait.
$before = vb_get_stats();
ok( (int) $before->total_projects > 0, 'les statistiques projets répondent toujours' );

$p = vb_get_project( $pid );
ok( $p !== null && $p->client_name === 'Filtre', 'vb_get_project fonctionne toujours' );

vb_create_leads_table();
$lead = vb_insert_lead( [ 'full_name' => 'Test Lead', 'phone' => '0600000000', 'website_type' => 'business' ] );
ok( is_numeric( $lead ) && $lead > 0, 'la création de lead fonctionne toujours' );

$pid_conv = vb_convert_lead_to_project( $lead );
ok( is_numeric( $pid_conv ) && $pid_conv > 0, 'la conversion lead -> projet fonctionne toujours' );

// La sauvegarde doit maintenant connaître les contrats ET les factures.
require_once __DIR__ . '/../includes/db.php';
ok( function_exists( 'vb_create_invoices_table' ), 'le schéma des factures est sorti du template' );

summary();
