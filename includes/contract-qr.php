<?php
/**
 * QR Code — encodeur autonome — v2.8
 *
 * Pourquoi réécrire un encodeur plutôt que tirer une image d'une API ou
 * embarquer une bibliothèque :
 *
 *  1. Un contrat part chez un client et se réimprime des années plus tard.
 *     Un QR servi par un service tiers devient un carré vide le jour où ce
 *     service ferme, change d'URL ou bloque le referer.
 *  2. Aucune dépendance externe, aucun appel réseau au moment du rendu, et
 *     un SVG net à l'impression quelle que soit la résolution.
 *
 * Périmètre volontairement réduit : mode octet, correction M, versions 1 à 10
 * (jusqu'à 213 octets). Une URL de vérification tient très largement dedans.
 * Au-delà, vb_contract_qr_svg() renvoie une chaîne vide — jamais un QR faux.
 *
 * Conforme ISO/IEC 18004. Le résultat est comparé matrice par matrice à une
 * implémentation de référence dans tests/contract-qr.test.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   TABLES (mode octet, correction M, versions 1-10)
============================================================ */

/**
 * Structure de chaque version : capacité utile, nombre de codewords de
 * correction par bloc, et découpage en blocs (groupe 1 + groupe 2).
 *
 * [ octets utiles, ecc/bloc, blocs g1, data/bloc g1, blocs g2, data/bloc g2 ]
 */
function vb_qr_versions() {
    return [
        1  => [  14, 10, 1, 16, 0,  0 ],
        2  => [  26, 16, 1, 28, 0,  0 ],
        3  => [  42, 26, 1, 44, 0,  0 ],
        4  => [  62, 18, 2, 32, 0,  0 ],
        5  => [  84, 24, 2, 43, 0,  0 ],
        6  => [ 106, 16, 4, 27, 0,  0 ],
        7  => [ 122, 18, 4, 31, 0,  0 ],
        8  => [ 152, 22, 2, 38, 2, 39 ],
        9  => [ 180, 22, 3, 36, 2, 37 ],
        10 => [ 213, 26, 4, 43, 1, 44 ],
    ];
}

/** Centres des motifs d'alignement, par version. */
function vb_qr_alignment_centers( $version ) {
    $t = [
        1 => [], 2 => [ 6, 18 ], 3 => [ 6, 22 ], 4 => [ 6, 26 ], 5 => [ 6, 30 ],
        6 => [ 6, 34 ], 7 => [ 6, 22, 38 ], 8 => [ 6, 24, 42 ],
        9 => [ 6, 26, 46 ], 10 => [ 6, 28, 50 ],
    ];
    return $t[ $version ] ?? [];
}

/* ============================================================
   GF(256) ET REED-SOLOMON
============================================================ */

/** Tables exp/log du corps de Galois GF(256), polynôme primitif 0x11D. */
function vb_qr_gf_tables() {
    static $tables = null;
    if ( $tables !== null ) return $tables;

    $exp = array_fill( 0, 512, 0 );
    $log = array_fill( 0, 256, 0 );
    $x   = 1;
    for ( $i = 0; $i < 255; $i++ ) {
        $exp[ $i ] = $x;
        $log[ $x ] = $i;
        $x <<= 1;
        if ( $x & 0x100 ) $x ^= 0x11D;
    }
    for ( $i = 255; $i < 512; $i++ ) $exp[ $i ] = $exp[ $i - 255 ];

    return $tables = [ $exp, $log ];
}

function vb_qr_gf_mul( $a, $b ) {
    if ( $a === 0 || $b === 0 ) return 0;
    list( $exp, $log ) = vb_qr_gf_tables();
    return $exp[ $log[ $a ] + $log[ $b ] ];
}

/**
 * Polynôme générateur de degré $n : (x - α⁰)(x - α¹)…(x - αⁿ⁻¹).
 *
 * Indice 0 = coefficient de plus haut degré. Multiplier par (x + αⁱ) revient
 * donc à décaler les coefficients existants (× x) et à ajouter le produit par
 * αⁱ un cran plus loin. Le polynôme reste unitaire, ce dont dépend
 * vb_qr_rs_encode() pour annuler le terme de tête à chaque étape.
 */
function vb_qr_rs_generator( $n ) {
    list( $exp, ) = vb_qr_gf_tables();
    $g = [ 1 ];
    for ( $i = 0; $i < $n; $i++ ) {
        $next = array_fill( 0, count( $g ) + 1, 0 );
        foreach ( $g as $j => $coef ) {
            $next[ $j ]     ^= $coef;                                // × x
            $next[ $j + 1 ] ^= vb_qr_gf_mul( $coef, $exp[ $i ] );    // × αⁱ
        }
        $g = $next;
    }
    return $g;
}

/** Codewords de correction d'un bloc de données. */
function vb_qr_rs_encode( array $data, $ecc_len ) {
    $gen = vb_qr_rs_generator( $ecc_len );
    $res = array_merge( $data, array_fill( 0, $ecc_len, 0 ) );

    for ( $i = 0; $i < count( $data ); $i++ ) {
        $factor = $res[ $i ];
        if ( $factor === 0 ) continue;
        foreach ( $gen as $j => $coef ) {
            $res[ $i + $j ] ^= vb_qr_gf_mul( $coef, $factor );
        }
    }
    return array_slice( $res, count( $data ) );
}

/* ============================================================
   BCH — informations de format et de version
============================================================ */

/** 15 bits de format : niveau de correction M (00) + masque. */
function vb_qr_format_bits( $mask ) {
    $data = ( 0b00 << 3 ) | $mask;   // M = 00
    $rem  = $data << 10;
    for ( $i = 14; $i >= 10; $i-- ) {
        if ( $rem & ( 1 << $i ) ) $rem ^= 0x537 << ( $i - 10 );
    }
    return ( ( ( $data << 10 ) | $rem ) ^ 0x5412 ) & 0x7FFF;
}

/** 18 bits de version (versions 7 et plus uniquement). */
function vb_qr_version_bits( $version ) {
    $rem = $version << 12;
    for ( $i = 17; $i >= 12; $i-- ) {
        if ( $rem & ( 1 << $i ) ) $rem ^= 0x1F25 << ( $i - 12 );
    }
    return ( ( $version << 12 ) | $rem ) & 0x3FFFF;
}

/* ============================================================
   ENCODAGE DES DONNÉES
============================================================ */

/**
 * Flux binaire complet (données + correction, blocs entrelacés).
 *
 * @return int[]|false codewords, ou false si le texte ne tient pas.
 */
function vb_qr_codewords( $text, $version ) {
    $versions = vb_qr_versions();
    if ( ! isset( $versions[ $version ] ) ) return false;

    list( $capacity, $ecc_len, $g1_blocks, $g1_size, $g2_blocks, $g2_size ) = $versions[ $version ];

    $bytes = array_values( unpack( 'C*', $text ) );
    if ( count( $bytes ) > $capacity ) return false;

    // Indicateur de mode (0100 = octet) + compteur de caractères.
    $count_bits = $version < 10 ? 8 : 16;
    $bits       = '0100' . str_pad( decbin( count( $bytes ) ), $count_bits, '0', STR_PAD_LEFT );
    foreach ( $bytes as $b ) $bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );

    $total_data = $g1_blocks * $g1_size + $g2_blocks * $g2_size;
    $max_bits   = $total_data * 8;

    // Terminateur (4 bits maximum), puis alignement sur l'octet.
    $bits .= str_repeat( '0', min( 4, $max_bits - strlen( $bits ) ) );
    if ( strlen( $bits ) % 8 ) $bits .= str_repeat( '0', 8 - strlen( $bits ) % 8 );

    // Octets de remplissage alternés, imposés par la norme.
    $pad = [ 0xEC, 0x11 ];
    $i   = 0;
    $data = [];
    foreach ( str_split( $bits, 8 ) as $byte ) $data[] = bindec( $byte );
    while ( count( $data ) < $total_data ) $data[] = $pad[ $i++ % 2 ];

    // Découpage en blocs, correction bloc par bloc.
    $blocks = [];
    $eccs   = [];
    $offset = 0;
    foreach ( [ [ $g1_blocks, $g1_size ], [ $g2_blocks, $g2_size ] ] as $group ) {
        for ( $b = 0; $b < $group[0]; $b++ ) {
            $block    = array_slice( $data, $offset, $group[1] );
            $offset  += $group[1];
            $blocks[] = $block;
            $eccs[]   = vb_qr_rs_encode( $block, $ecc_len );
        }
    }

    // Entrelacement : un codeword de chaque bloc, tour à tour.
    $out = [];
    $max = max( array_map( 'count', $blocks ) );
    for ( $i = 0; $i < $max; $i++ ) {
        foreach ( $blocks as $block ) if ( isset( $block[ $i ] ) ) $out[] = $block[ $i ];
    }
    for ( $i = 0; $i < $ecc_len; $i++ ) {
        foreach ( $eccs as $ecc ) $out[] = $ecc[ $i ];
    }
    return $out;
}

/* ============================================================
   MATRICE
============================================================ */

/**
 * Trame de la matrice : motifs fixes posés, modules réservés marqués.
 *
 * @return array [ matrice (0/1), réservé (bool) ]
 */
function vb_qr_base_matrix( $version ) {
    $size     = 17 + 4 * $version;
    $m        = array_fill( 0, $size, array_fill( 0, $size, 0 ) );
    $reserved = array_fill( 0, $size, array_fill( 0, $size, false ) );

    $put = function ( $r, $c, $v ) use ( &$m, &$reserved, $size ) {
        if ( $r < 0 || $c < 0 || $r >= $size || $c >= $size ) return;
        $m[ $r ][ $c ]        = $v;
        $reserved[ $r ][ $c ] = true;
    };

    // Motifs de détection de position + séparateurs.
    foreach ( [ [ 0, 0 ], [ 0, $size - 7 ], [ $size - 7, 0 ] ] as $o ) {
        for ( $r = -1; $r <= 7; $r++ ) {
            for ( $c = -1; $c <= 7; $c++ ) {
                $inside = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6;
                $dark   = $inside && ( $r === 0 || $r === 6 || $c === 0 || $c === 6
                                    || ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 ) );
                $put( $o[0] + $r, $o[1] + $c, $dark ? 1 : 0 );
            }
        }
    }

    // Motifs d'alignement — jamais par-dessus un motif de position.
    $centers = vb_qr_alignment_centers( $version );
    foreach ( $centers as $cr ) {
        foreach ( $centers as $cc ) {
            if ( ( $cr <= 8 && $cc <= 8 ) || ( $cr <= 8 && $cc >= $size - 9 )
              || ( $cr >= $size - 9 && $cc <= 8 ) ) continue;
            for ( $r = -2; $r <= 2; $r++ ) {
                for ( $c = -2; $c <= 2; $c++ ) {
                    $dark = abs( $r ) === 2 || abs( $c ) === 2 || ( $r === 0 && $c === 0 );
                    $put( $cr + $r, $cc + $c, $dark ? 1 : 0 );
                }
            }
        }
    }

    // Motifs de synchronisation.
    for ( $i = 8; $i < $size - 8; $i++ ) {
        $put( 6, $i, $i % 2 === 0 ? 1 : 0 );
        $put( $i, 6, $i % 2 === 0 ? 1 : 0 );
    }

    // Module toujours noir + zones réservées aux informations de format.
    $put( $size - 8, 8, 1 );
    for ( $i = 0; $i <= 8; $i++ ) {
        if ( ! $reserved[8][ $i ] )        $put( 8, $i, 0 );
        if ( ! $reserved[ $i ][8] )        $put( $i, 8, 0 );
    }
    for ( $i = 0; $i < 8; $i++ ) {
        if ( ! $reserved[8][ $size - 1 - $i ] ) $put( 8, $size - 1 - $i, 0 );
        if ( ! $reserved[ $size - 1 - $i ][8] ) $put( $size - 1 - $i, 8, 0 );
    }

    // Informations de version (7 et plus).
    if ( $version >= 7 ) {
        $bits = vb_qr_version_bits( $version );
        for ( $i = 0; $i < 18; $i++ ) {
            $bit = ( $bits >> $i ) & 1;
            $put( intdiv( $i, 3 ), $size - 11 + $i % 3, $bit );
            $put( $size - 11 + $i % 3, intdiv( $i, 3 ), $bit );
        }
    }

    return [ $m, $reserved ];
}

/** Place les codewords en zigzag, en sautant les modules réservés. */
function vb_qr_place_data( array $m, array $reserved, array $codewords ) {
    $size = count( $m );
    $bits = '';
    foreach ( $codewords as $cw ) $bits .= str_pad( decbin( $cw ), 8, '0', STR_PAD_LEFT );

    $i  = 0;
    $up = true;
    for ( $col = $size - 1; $col > 0; $col -= 2 ) {
        if ( $col === 6 ) $col--;   // la colonne de synchronisation ne compte pas
        for ( $n = 0; $n < $size; $n++ ) {
            $row = $up ? $size - 1 - $n : $n;
            foreach ( [ $col, $col - 1 ] as $c ) {
                if ( $reserved[ $row ][ $c ] ) continue;
                $m[ $row ][ $c ] = isset( $bits[ $i ] ) ? (int) $bits[ $i ] : 0;
                $i++;
            }
        }
        $up = ! $up;
    }
    return $m;
}

/** Les huit masques normalisés. */
function vb_qr_mask_test( $mask, $r, $c ) {
    switch ( $mask ) {
        case 0: return ( $r + $c ) % 2 === 0;
        case 1: return $r % 2 === 0;
        case 2: return $c % 3 === 0;
        case 3: return ( $r + $c ) % 3 === 0;
        case 4: return ( intdiv( $r, 2 ) + intdiv( $c, 3 ) ) % 2 === 0;
        case 5: return ( ( $r * $c ) % 2 + ( $r * $c ) % 3 ) === 0;
        case 6: return ( ( ( $r * $c ) % 2 + ( $r * $c ) % 3 ) % 2 ) === 0;
        default: return ( ( ( $r + $c ) % 2 + ( $r * $c ) % 3 ) % 2 ) === 0;
    }
}

/** Applique un masque et inscrit les informations de format correspondantes. */
function vb_qr_apply_mask( array $m, array $reserved, $mask ) {
    $size = count( $m );

    for ( $r = 0; $r < $size; $r++ ) {
        for ( $c = 0; $c < $size; $c++ ) {
            if ( $reserved[ $r ][ $c ] ) continue;
            if ( vb_qr_mask_test( $mask, $r, $c ) ) $m[ $r ][ $c ] ^= 1;
        }
    }

    $bits = vb_qr_format_bits( $mask );
    for ( $i = 0; $i < 15; $i++ ) {
        $bit = ( $bits >> $i ) & 1;

        // Copie 1 — autour du motif haut-gauche.
        if ( $i < 6 )       $m[ $i ][8] = $bit;
        elseif ( $i === 6 ) $m[7][8]    = $bit;
        elseif ( $i === 7 ) $m[8][8]    = $bit;
        elseif ( $i === 8 ) $m[8][7]    = $bit;
        else                $m[8][ 14 - $i ] = $bit;

        // Copie 2 — le long des deux autres motifs.
        if ( $i < 8 ) $m[8][ $size - 1 - $i ] = $bit;
        else          $m[ $size - 15 + $i ][8] = $bit;
    }
    $m[ $size - 8 ][8] = 1;   // module toujours noir

    return $m;
}

/** Pénalité d'un masque : les quatre règles de la norme. */
function vb_qr_penalty( array $m ) {
    $size    = count( $m );
    $penalty = 0;

    // Règle 1 — suites de 5 modules identiques ou plus.
    for ( $pass = 0; $pass < 2; $pass++ ) {
        for ( $a = 0; $a < $size; $a++ ) {
            $run = 1;
            for ( $b = 1; $b < $size; $b++ ) {
                $prev = $pass ? $m[ $b - 1 ][ $a ] : $m[ $a ][ $b - 1 ];
                $cur  = $pass ? $m[ $b ][ $a ]     : $m[ $a ][ $b ];
                if ( $cur === $prev ) {
                    $run++;
                } else {
                    if ( $run >= 5 ) $penalty += 3 + $run - 5;
                    $run = 1;
                }
            }
            if ( $run >= 5 ) $penalty += 3 + $run - 5;
        }
    }

    // Règle 2 — blocs 2×2 de même couleur.
    for ( $r = 0; $r < $size - 1; $r++ ) {
        for ( $c = 0; $c < $size - 1; $c++ ) {
            $v = $m[ $r ][ $c ];
            if ( $v === $m[ $r ][ $c + 1 ] && $v === $m[ $r + 1 ][ $c ] && $v === $m[ $r + 1 ][ $c + 1 ] ) {
                $penalty += 3;
            }
        }
    }

    // Règle 3 — motif 1:1:3:1:1 précédé ou suivi de quatre modules clairs.
    $p1 = [ 1,0,1,1,1,0,1,0,0,0,0 ];
    $p2 = [ 0,0,0,0,1,0,1,1,1,0,1 ];
    for ( $r = 0; $r < $size; $r++ ) {
        for ( $c = 0; $c <= $size - 11; $c++ ) {
            foreach ( [ $p1, $p2 ] as $pattern ) {
                $okh = $okv = true;
                for ( $k = 0; $k < 11; $k++ ) {
                    if ( $m[ $r ][ $c + $k ] !== $pattern[ $k ] ) $okh = false;
                    if ( $m[ $c + $k ][ $r ] !== $pattern[ $k ] ) $okv = false;
                }
                if ( $okh ) $penalty += 40;
                if ( $okv ) $penalty += 40;
            }
        }
    }

    // Règle 4 — déséquilibre clair / foncé.
    $dark = 0;
    foreach ( $m as $row ) $dark += array_sum( $row );
    $ratio    = $dark * 100 / ( $size * $size );
    $penalty += intval( abs( $ratio - 50 ) / 5 ) * 10;

    return $penalty;
}

/**
 * Matrice complète d'un texte : version choisie automatiquement, masque
 * optimal élu par pénalité.
 *
 * @return array[]|false matrice de 0/1, ou false si le texte est trop long.
 */
function vb_qr_matrix( $text ) {
    $text = (string) $text;
    if ( $text === '' ) return false;

    $version = 0;
    foreach ( vb_qr_versions() as $v => $spec ) {
        if ( strlen( $text ) <= $spec[0] ) { $version = $v; break; }
    }
    if ( ! $version ) return false;

    $codewords = vb_qr_codewords( $text, $version );
    if ( $codewords === false ) return false;

    list( $base, $reserved ) = vb_qr_base_matrix( $version );
    $filled = vb_qr_place_data( $base, $reserved, $codewords );

    $best = null;
    $best_score = PHP_INT_MAX;
    for ( $mask = 0; $mask < 8; $mask++ ) {
        $candidate = vb_qr_apply_mask( $filled, $reserved, $mask );
        $score     = vb_qr_penalty( $candidate );
        if ( $score < $best_score ) { $best_score = $score; $best = $candidate; }
    }
    return $best;
}

/* ============================================================
   SORTIE SVG
============================================================ */

/**
 * QR au format SVG, prêt à être injecté dans le document.
 *
 * SVG et non PNG : le contrat s'imprime, et un QR pixellisé se scanne mal.
 * Un seul <path> pour tous les modules noirs — le fichier reste minuscule.
 *
 * @param string $text    contenu encodé
 * @param int    $size_px côté du carré rendu
 * @return string SVG, ou chaîne vide si l'encodage est impossible
 */
function vb_contract_qr_svg( $text, $size_px = 96 ) {
    $matrix = vb_qr_matrix( $text );
    if ( ! $matrix ) return '';

    $n      = count( $matrix );
    $quiet  = 4;                    // zone de silence imposée par la norme
    $total  = $n + 2 * $quiet;
    $path   = '';

    foreach ( $matrix as $r => $row ) {
        foreach ( $row as $c => $v ) {
            if ( $v ) $path .= 'M' . ( $c + $quiet ) . ' ' . ( $r + $quiet ) . 'h1v1h-1z';
        }
    }

    return sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%2$d" height="%2$d" '
      . 'shape-rendering="crispEdges" role="img" aria-label="%3$s">'
      . '<rect width="%1$d" height="%1$d" fill="#ffffff"/>'
      . '<path d="%4$s" fill="#000000"/></svg>',
        $total,
        intval( $size_px ),
        esc_attr( 'Code QR de vérification du contrat' ),
        $path
    );
}

/* ============================================================
   INTÉGRATION CONTRAT
============================================================ */

/** Le QR est une option, désactivée tant qu'elle n'a pas été demandée. */
function vb_contract_qr_enabled() {
    return (bool) get_option( 'vb_contract_qr_enabled', 0 );
}

/**
 * Contenu encodé dans le QR d'un contrat.
 *
 * Aujourd'hui : l'URL de vérification (site du prestataire + numéro).
 * Demain : espace client, téléchargement de l'exemplaire signé. Le contenu
 * est filtrable pour que ces usages n'imposent aucune migration.
 */
function vb_contract_qr_payload( $contract ) {
    $c    = (object) $contract;
    $base = trim( (string) get_option( 'vb_contract_qr_base_url', '' ) );

    if ( $base === '' ) {
        $site = vb_contract_provider()['website'];
        $base = ( preg_match( '#^https?://#i', $site ) ? $site : 'https://' . $site ) . '/contrat/';
    }

    $url = rtrim( $base, '/' ) . '/' . rawurlencode( $c->number ?? '' );

    return apply_filters( 'vb_contract_qr_payload', $url, $c );
}
