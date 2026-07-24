<?php
/**
 * Encodeur QR — tests de conformité.
 *
 * Un QR faux ne se voit pas : il s'imprime, il part chez le client, et
 * personne ne s'en aperçoit avant que quelqu'un essaie de le scanner. On ne
 * peut donc pas se contenter de vérifier qu'« il y a un carré ».
 *
 * Les empreintes ci-dessous ont été produites par une implémentation de
 * RÉFÉRENCE indépendante (la bibliothèque python « qrcode »), matrice par
 * matrice, pour six contenus couvrant les versions 1, 3, 4, 9 et 10 et
 * plusieurs masques. Si l'encodeur dérive d'un seul module, ces tests
 * tombent.
 *
 *   php tests/contract-qr.test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/contract-qr.php';
require_once __DIR__ . '/../includes/contract-templates.php';

/** Empreinte d'une matrice, lignes concaténées par des retours à la ligne. */
function qr_fingerprint( array $matrix ) {
    return sha1( implode( "\n", array_map( fn( $row ) => implode( '', $row ), $matrix ) ) );
}

/** Matrice pour une version et un masque imposés (chemin interne, testable). */
function qr_matrix_fixed( $text, $version, $mask ) {
    $codewords = vb_qr_codewords( $text, $version );
    list( $base, $reserved ) = vb_qr_base_matrix( $version );
    return vb_qr_apply_mask( vb_qr_place_data( $base, $reserved, $codewords ), $reserved, $mask );
}


echo "\n\033[1m1. Corps de Galois et Reed-Solomon\033[0m\n";

list( $exp, $log ) = vb_qr_gf_tables();
is_eq( $exp[0], 1,   'α⁰ = 1' );
is_eq( $exp[8], 29,  'α⁸ = 29 (repliement par le polynôme 0x11D)' );
is_eq( $exp[255], 1, 'le corps boucle après 255' );
is_eq( vb_qr_gf_mul( 0, 42 ), 0, 'multiplication par zéro' );
is_eq( vb_qr_gf_mul( 1, 42 ), 42, 'multiplication par un' );

// Le générateur doit être unitaire, sinon la division polynomiale de
// vb_qr_rs_encode() n'annule pas le terme de tête et toute la correction
// est fausse — c'est exactement le bug qu'on a corrigé.
$gen = vb_qr_rs_generator( 10 );
is_eq( count( $gen ), 11, 'un générateur de degré 10 a 11 coefficients' );
is_eq( $gen[0], 1, 'le polynôme générateur est unitaire' );
is_eq( vb_qr_rs_generator( 7 ), [ 1, 127, 122, 154, 164, 11, 68, 117 ], 'générateur de degré 7 (valeurs de la norme)' );


echo "\n\033[1m2. Informations de format et de version (BCH)\033[0m\n";

// Table normalisée du niveau de correction M.
$expected_format = [
    0 => 0b101010000010010, 1 => 0b101000100100101,
    2 => 0b101111001111100, 3 => 0b101101101001011,
    4 => 0b100010111111001, 5 => 0b100000011001110,
    6 => 0b100111110010111, 7 => 0b100101010100000,
];
foreach ( $expected_format as $mask => $bits ) {
    is_eq( vb_qr_format_bits( $mask ), $bits, "format, niveau M, masque $mask" );
}

$expected_version = [
    7 => 0b000111110010010100, 8 => 0b001000010110111100,
    9 => 0b001001101010011001, 10 => 0b001010010011010011,
];
foreach ( $expected_version as $v => $bits ) {
    is_eq( vb_qr_version_bits( $v ), $bits, "information de version $v" );
}


echo "\n\033[1m3. Matrices — comparaison à une implémentation de référence\033[0m\n";

$reference = [
    [ 'A',                                            1,  3, '66a90bf2fd844de9a1fe95cc9167ef7109763511' ],
    [ 'HELLO WORLD',                                  1,  2, 'b088e97721f374bb3c27b7a26fc439cfb88ca06c' ],
    [ 'https://younessweb.me/contrat/CTR-2026-001',   3,  2, '56496319bf06ecaed0d0b5cc2f63c938ff3b15a3' ],
    [ 'Contrat CTR-2026-042 - Cutt Salons - 4700 MAD', 4, 5, '4bde885bfb04aa20eeeafc72958cb6191d7d2fe7' ],
    [ str_repeat( 'x', 180 ),                         9,  1, '5b03d9644e3997db280a98f2399c5f59ab9d0219' ],
    [ str_repeat( 'y', 213 ),                        10,  0, 'c76237e8007ac89d8be6956b25496c45ced1cb26' ],
    // Les quatre derniers masques ont leur propre mot de format : on les
    // couvre explicitement, c'est là que se cachent les erreurs de table.
    [ 'CTR-2026-001',                                 1,  4, 'e55d44542e3dc897182eb60acb36c3fc9e758af4' ],
    [ 'CTR-2026-001',                                 1,  6, '3ef8c65c0ccb5606a08c0f0c8bc7678f01ca82e5' ],
    [ 'CTR-2026-001',                                 1,  7, 'd5d22380a900aa1a094e36cf02e44506c84d4604' ],
];

foreach ( $reference as list( $text, $version, $mask, $hash ) ) {
    $label = strlen( $text ) > 34 ? substr( $text, 0, 31 ) . '…' : $text;
    is_eq( qr_fingerprint( qr_matrix_fixed( $text, $version, $mask ) ), $hash,
        "version $version, masque $mask — « $label »" );
}


echo "\n\033[1m4. Structure de la matrice\033[0m\n";

$m = vb_qr_matrix( 'https://younessweb.me/contrat/CTR-2026-001' );
ok( is_array( $m ), 'une matrice est produite' );
is_eq( count( $m ), 29, 'version 3 -> 29 modules de côté' );
is_eq( count( $m[0] ), 29, 'la matrice est carrée' );

// Les trois motifs de détection : sans eux, aucun lecteur ne trouve le code.
foreach ( [ [ 0, 0 ], [ 0, 22 ], [ 22, 0 ] ] as $o ) {
    $ok = $m[ $o[0] ][ $o[1] ] === 1 && $m[ $o[0] + 6 ][ $o[1] + 6 ] === 1
        && $m[ $o[0] + 1 ][ $o[1] + 1 ] === 0 && $m[ $o[0] + 3 ][ $o[1] + 3 ] === 1;
    ok( $ok, "motif de détection en ({$o[0]},{$o[1]})" );
}

// Ligne de synchronisation : alternance stricte.
$timing_ok = true;
for ( $i = 8; $i < 21; $i++ ) {
    if ( $m[6][ $i ] !== ( $i % 2 === 0 ? 1 : 0 ) ) { $timing_ok = false; break; }
}
ok( $timing_ok, 'la ligne de synchronisation alterne correctement' );

// Module toujours noir.
is_eq( $m[ count( $m ) - 8 ][8], 1, 'le module fixe est bien noir' );


echo "\n\033[1m5. Choix de version et refus propre\033[0m\n";

is_eq( count( vb_qr_matrix( str_repeat( 'a', 14 ) ) ),  21, '14 octets tiennent en version 1' );
is_eq( count( vb_qr_matrix( str_repeat( 'a', 15 ) ) ),  25, '15 octets passent en version 2' );
is_eq( count( vb_qr_matrix( str_repeat( 'a', 213 ) ) ), 57, '213 octets tiennent en version 10' );
is_eq( vb_qr_matrix( str_repeat( 'a', 214 ) ), false, 'au-delà, on refuse plutôt que de tronquer' );
is_eq( vb_qr_matrix( '' ), false, 'un contenu vide ne produit pas de QR' );
is_eq( vb_qr_codewords( 'test', 99 ), false, 'une version inexistante est refusée' );

// L'accentuation est fréquente sur un contrat français : l'encodage doit
// compter des OCTETS, pas des caractères.
$accents = 'Contrat de création — 4 700,00 MAD';
ok( vb_qr_matrix( $accents ) !== false, 'un contenu accentué s\'encode' );
is_eq( count( vb_qr_codewords( $accents, 3 ) ), 70, 'le nombre de codewords suit la version, pas le texte' );


echo "\n\033[1m6. Sortie SVG\033[0m\n";

$svg = vb_contract_qr_svg( 'https://younessweb.me/contrat/CTR-2026-001', 96 );
ok( strpos( $svg, '<svg' ) === 0, 'la sortie commence par une balise svg' );
ok( strpos( $svg, 'width="96" height="96"' ) !== false, 'la taille demandée est respectée' );
ok( strpos( $svg, 'viewBox="0 0 37 37"' ) !== false, 'la zone de silence de 4 modules est incluse' );
ok( strpos( $svg, 'shape-rendering="crispEdges"' ) !== false, 'les modules restent nets à l\'impression' );
ok( strpos( $svg, 'role="img"' ) !== false && strpos( $svg, 'aria-label' ) !== false,
    'le QR est annoncé aux lecteurs d\'écran' );
ok( substr_count( $svg, '<path' ) === 1, 'un seul chemin pour tous les modules — fichier minimal' );
ok( strpos( $svg, 'http://www.w3.org/2000/svg' ) !== false, 'espace de noms SVG présent' );
ok( strpos( $svg, '<image' ) === false && strpos( $svg, 'xlink:href' ) === false,
    'aucune ressource externe : le QR reste lisible hors ligne, pour toujours' );


echo "\n\033[1m7. Intégration au contrat\033[0m\n";

delete_option( 'vb_contract_qr_enabled' );
is_eq( vb_contract_qr_enabled(), false, 'désactivé tant qu\'on ne l\'a pas demandé' );

update_option( 'vb_contract_provider', [ 'website' => 'younessweb.me' ] );
delete_option( 'vb_contract_qr_base_url' );
is_eq( vb_contract_qr_payload( (object) [ 'number' => 'CTR-2026-001' ] ),
    'https://younessweb.me/contrat/CTR-2026-001', 'URL déduite du site du prestataire' );

update_option( 'vb_contract_qr_base_url', 'https://portail.younessweb.me/verify/' );
is_eq( vb_contract_qr_payload( (object) [ 'number' => 'CTR-2026-001' ] ),
    'https://portail.younessweb.me/verify/CTR-2026-001', 'une URL de vérification personnalisée est respectée' );

summary();
