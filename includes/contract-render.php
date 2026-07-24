<?php
/**
 * Rendu HTML du contrat — v2.8
 *
 * Le corps d'un contrat reste du TEXTE (includes/contract-templates.php) :
 * c'est ce que l'utilisateur édite, ce que les tests vérifient, et ce qui
 * garantit qu'un modèle personnalisé continue de fonctionner. Ce fichier
 * n'ajoute qu'une couche de MISE EN FORME au-dessus de ce texte :
 *
 *   — les titres « ARTICLE n — … » deviennent de vrais <h2> ;
 *   — les lignes « — … » deviennent de vraies listes ;
 *   — les livrables et l'échéancier deviennent des tableaux et des listes
 *     à cocher, parce qu'un client lit un tableau et survole un paragraphe.
 *
 * Aucune donnée n'est recalculée ici. Tout vient du contrat, et un champ vide
 * ne produit AUCUNE ligne — jamais un libellé suivi d'un tiret.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Marqueurs internes substitués aux placeholders qui deviennent des blocs
 * HTML. Des caractères de contrôle : impossibles à saisir dans un modèle,
 * donc impossibles à confondre avec du contenu réel.
 */
const VB_CT_TOKEN_SCHEDULE = "\x02vb:schedule\x03";
const VB_CT_TOKEN_SCOPE    = "\x02vb:scope\x03";

/* ============================================================
   BLOCS D'IDENTITÉ — PRESTATAIRE ET CLIENT
============================================================ */

/**
 * Lignes d'identité du prestataire. Un freelance n'a ni RC ni ICE : ces
 * lignes n'existent tout simplement pas tant qu'elles ne sont pas remplies.
 *
 * @return array<int,array{label:string,value:string,strong:bool}>
 */
function vb_contract_provider_block( $provider = null ) {
    $p     = $provider ?: vb_contract_provider();
    $lines = [];

    $push = function ( $value, $label = '', $strong = false ) use ( &$lines ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return;                    // champ vide = ligne supprimée
        $lines[] = [ 'label' => $label, 'value' => $value, 'strong' => $strong ];
    };

    $push( $p['name'], '', true );
    $push( $p['legal'], 'Représenté par' );
    foreach ( vb_contract_provider_legal_lines( $p ) as $label => $value ) {
        $push( $value, $label );
    }
    $push( $p['address'] );
    $push( $p['city'] );
    $push( $p['phone'], 'Tél.' );
    $push( $p['email'], 'Email' );

    return $lines;
}

/**
 * Lignes d'identité du client, dans l'ordre de lecture d'un en-tête de
 * courrier : qui, où, comment le joindre, puis les identifiants légaux.
 *
 * @return array<int,array{label:string,value:string,strong:bool}>
 */
function vb_contract_client_block( $contract ) {
    $c     = (object) $contract;
    $lines = [];

    $push = function ( $value, $label = '', $strong = false ) use ( &$lines ) {
        $value = trim( (string) $value );
        if ( $value === '' ) return;
        $lines[] = [ 'label' => $label, 'value' => $value, 'strong' => $strong ];
    };

    $push( $c->client_name ?? '', '', true );
    $push( $c->client_company ?? '', 'Société' );
    $push( $c->client_address ?? '' );

    // Ville et pays sur une seule ligne : « Casablanca, Maroc ».
    $place = array_filter( [ trim( (string) ( $c->client_city ?? '' ) ), trim( (string) ( $c->client_country ?? '' ) ) ] );
    $push( implode( ', ', $place ) );

    $push( $c->client_phone ?? '', 'Tél.' );
    $push( $c->client_email ?? '', 'Email' );
    $push( $c->client_legal_id ?? '', 'CIN / ICE' );
    $push( $c->client_ice ?? '', 'ICE' );
    $push( $c->client_rc ?? '', 'RC' );

    return $lines;
}

/** Rend un bloc d'identité en HTML. */
function vb_contract_identity_html( array $lines ) {
    $html = '';
    foreach ( $lines as $l ) {
        $value = $l['strong']
            ? '<strong class="vb-ct-party-name">' . esc_html( $l['value'] ) . '</strong>'
            : esc_html( $l['value'] );
        $label = $l['label'] !== ''
            ? '<span class="vb-ct-party-key">' . esc_html( $l['label'] ) . '</span> '
            : '';
        $html .= '<div class="vb-ct-party-line">' . $label . $value . '</div>';
    }
    return $html;
}

/* ============================================================
   RÉCAPITULATIF FINANCIER
============================================================ */

/**
 * Sections du récapitulatif financier, selon le TYPE RÉEL du contrat.
 * Un contrat de maintenance n'affiche pas de solde de livraison ; un contrat
 * de création n'affiche pas d'abonnement mensuel.
 *
 * @return array<int,array{title:string,rows:array,total:?array}>
 */
function vb_contract_summary_sections( $contract ) {
    $c    = (object) $contract;
    $type = vb_contract_type( $c );

    $total   = round( floatval( $c->amount_total ?? 0 ), 2 );
    $deposit = round( min( floatval( $c->deposit_amount ?? 0 ), $total ), 2 );
    $balance = round( $total - $deposit, 2 );

    $m_price  = round( floatval( $c->maintenance_price ?? 0 ), 2 );
    $m_months = intval( $c->maintenance_months ?? 0 );

    $sections = [];

    if ( $type !== 'maintenance' && $total > 0 ) {
        $label = trim( (string) ( $c->site_type ?? '' ) ) !== ''
            ? 'Création du site web — ' . $c->site_type
            : 'Création du site web';

        $rows = [ [ 'label' => $label, 'value' => vb_contract_money( $total ) ] ];
        if ( $deposit > 0 ) $rows[] = [ 'label' => 'Acompte à la signature', 'value' => vb_contract_money( $deposit ) ];
        if ( $balance > 0 ) $rows[] = [ 'label' => 'Solde restant dû',       'value' => vb_contract_money( $balance ) ];
        $rows[] = [ 'label' => 'Mode de règlement', 'value' => 'Virement, versement ou espèces' ];

        $lines = count( vb_contract_schedule( $c ) );
        if ( $lines ) {
            $rows[] = [ 'label' => 'Échéancier', 'value' => $lines . ' échéance' . ( $lines > 1 ? 's' : '' ) ];
        }

        $sections[] = [
            'title' => 'Prestation de création',
            'rows'  => $rows,
            'total' => [ 'label' => 'Total de la prestation', 'value' => vb_contract_money( $total ) ],
        ];
    }

    if ( $type !== 'creation' && $m_price > 0 ) {
        $rows = [ [ 'label' => 'Maintenance et suivi mensuel', 'value' => vb_contract_money( $m_price ) . ' / mois' ] ];
        if ( $m_months > 0 ) $rows[] = [ 'label' => 'Durée de l\'engagement', 'value' => $m_months . ' mois' ];
        if ( ! empty( $c->maintenance_start ) ) {
            $rows[] = [ 'label' => 'Prise d\'effet', 'value' => date( 'd/m/Y', strtotime( $c->maintenance_start ) ) ];
        }

        $sections[] = [
            'title' => 'Abonnement de maintenance',
            'rows'  => $rows,
            'total' => $m_months > 0
                ? [ 'label' => 'Total sur ' . $m_months . ' mois', 'value' => vb_contract_money( $m_price * $m_months ) ]
                : null,
        ];
    }

    return $sections;
}

/** Récapitulatif financier en HTML. Chaîne vide si le contrat n'a aucun montant. */
function vb_contract_summary_html( $contract ) {
    $sections = vb_contract_summary_sections( $contract );
    if ( ! $sections ) return '';

    $html = '';
    foreach ( $sections as $s ) {
        $html .= '<table class="vb-ct-table vb-ct-table-money">'
               . '<caption class="vb-ct-table-caption">' . esc_html( $s['title'] ) . '</caption>'
               . '<thead><tr><th scope="col">Désignation</th><th scope="col" class="vb-ct-num">Montant</th></tr></thead><tbody>';

        foreach ( $s['rows'] as $r ) {
            $html .= '<tr><td>' . esc_html( $r['label'] ) . '</td>'
                   . '<td class="vb-ct-num">' . esc_html( $r['value'] ) . '</td></tr>';
        }
        $html .= '</tbody>';

        if ( $s['total'] ) {
            $html .= '<tfoot><tr class="vb-ct-total-row">'
                   . '<th scope="row">' . esc_html( $s['total']['label'] ) . '</th>'
                   . '<td class="vb-ct-num">' . esc_html( $s['total']['value'] ) . '</td>'
                   . '</tr></tfoot>';
        }
        $html .= '</table>';
    }
    return $html;
}

/* ============================================================
   ÉCHÉANCIER
============================================================ */

/**
 * Lignes de l'échéancier enrichies pour l'affichage : rang, statut, et solde
 * restant après chaque versement — la colonne que le client cherche en
 * premier.
 */
function vb_contract_schedule_view_rows( $contract ) {
    $rows   = vb_contract_schedule( $contract );
    $totals = vb_contract_schedule_totals( $contract );
    $left   = $totals['scheduled'];
    $out    = [];

    foreach ( $rows as $i => $r ) {
        $amount = round( floatval( $r['amount'] ?? 0 ), 2 );
        $left   = round( $left - $amount, 2 );
        $out[]  = [
            'rank'    => $i + 1,
            'label'   => (string) ( $r['label'] ?? '' ),
            'due'     => (string) ( $r['due'] ?? '' ),
            'amount'  => $amount,
            'paid'    => ! empty( $r['paid'] ),
            'balance' => $left,
        ];
    }
    return $out;
}

/** Échéancier en tableau HTML. Repli textuel si aucune échéance n'est saisie. */
function vb_contract_schedule_html( $contract ) {
    $rows = vb_contract_schedule_view_rows( $contract );
    if ( ! $rows ) {
        return '<p class="vb-ct-p">Le règlement s\'effectue en totalité à la signature du présent contrat.</p>';
    }

    $totals = vb_contract_schedule_totals( $contract );

    $html = '<table class="vb-ct-table vb-ct-table-schedule"><thead><tr>'
          . '<th scope="col" class="vb-ct-rank">N°</th>'
          . '<th scope="col">Échéance</th>'
          . '<th scope="col">Exigibilité</th>'
          . '<th scope="col" class="vb-ct-num">Montant</th>'
          . '<th scope="col">Statut</th>'
          . '<th scope="col" class="vb-ct-num">Solde</th>'
          . '</tr></thead><tbody>';

    foreach ( $rows as $r ) {
        $html .= '<tr>'
               . '<td class="vb-ct-rank">' . intval( $r['rank'] ) . '</td>'
               . '<td>' . esc_html( $r['label'] ) . '</td>'
               . '<td>' . ( $r['due'] !== '' ? esc_html( $r['due'] ) : '<span class="vb-ct-muted">—</span>' ) . '</td>'
               . '<td class="vb-ct-num">' . esc_html( vb_contract_money( $r['amount'] ) ) . '</td>'
               . '<td>' . ( $r['paid']
                    ? '<span class="vb-ct-status vb-ct-status-paid">Réglée</span>'
                    : '<span class="vb-ct-status">À échoir</span>' ) . '</td>'
               . '<td class="vb-ct-num">' . esc_html( vb_contract_money( $r['balance'] ) ) . '</td>'
               . '</tr>';
    }

    $html .= '</tbody><tfoot><tr class="vb-ct-total-row">'
           . '<th scope="row" colspan="3">Total de l\'échéancier</th>'
           . '<td class="vb-ct-num">' . esc_html( vb_contract_money( $totals['scheduled'] ) ) . '</td>'
           . '<td>' . ( $totals['paid'] > 0 ? esc_html( vb_contract_money( $totals['paid'] ) . ' réglés' ) : '' ) . '</td>'
           . '<td class="vb-ct-num">' . esc_html( vb_contract_money( $totals['unpaid'] ) ) . '</td>'
           . '</tr></tfoot></table>';

    return $html;
}

/* ============================================================
   LIVRABLES — LISTE À COCHER
============================================================ */

/** Livrables d'un contrat, une entrée par ligne saisie. */
function vb_contract_scope_items( $contract ) {
    $c     = (object) $contract;
    $lines = preg_split( '/\R/u', (string) ( $c->scope ?? '' ) );
    $out   = [];
    foreach ( (array) $lines as $l ) {
        $l = trim( ltrim( trim( $l ), "-—•*\t " ) );
        if ( $l !== '' ) $out[] = $l;
    }
    return $out;
}

/**
 * Les livrables en liste à cocher. Une liste cochée se lit comme une promesse
 * tenue ; le même contenu en paragraphe se lit comme des conditions.
 */
function vb_contract_checklist_html( array $items ) {
    if ( ! $items ) return '<p class="vb-ct-p vb-ct-muted">Prestations détaillées en annexe.</p>';

    $html = '<ul class="vb-ct-check">';
    foreach ( $items as $item ) {
        $html .= '<li><span class="vb-ct-check-mark" aria-hidden="true">✓</span> '
               . '<span>' . esc_html( $item ) . '</span></li>';
    }
    return $html . '</ul>';
}

/* ============================================================
   CORPS DU CONTRAT EN HTML
============================================================ */

/**
 * Transforme le corps texte du contrat en HTML structuré.
 *
 * Le texte source est hard-wrappé à ~78 colonnes pour rester lisible dans
 * l'éditeur de modèles. Ces retours à la ligne sont un artefact d'édition,
 * pas une intention typographique : les lignes d'un même paragraphe sont donc
 * refusionnées, et c'est le navigateur qui décide où couper. C'est la
 * différence entre un document composé et un listing imprimé.
 */
function vb_contract_body_html( $contract, $body = null ) {
    $text = vb_contract_render( $contract, $body, [
        '{{payment_schedule}}' => VB_CT_TOKEN_SCHEDULE,
        '{{scope}}'            => VB_CT_TOKEN_SCOPE,
    ] );

    $html      = '';
    $paragraph = [];
    $list      = [];

    $flush = function () use ( &$html, &$paragraph, &$list ) {
        if ( $paragraph ) {
            $html .= '<p class="vb-ct-p">' . esc_html( implode( ' ', $paragraph ) ) . '</p>';
            $paragraph = [];
        }
        if ( $list ) {
            $html .= '<ul class="vb-ct-ul">';
            foreach ( $list as $item ) $html .= '<li>' . esc_html( $item ) . '</li>';
            $html .= '</ul>';
            $list = [];
        }
    };

    foreach ( preg_split( '/\R/u', $text ) as $raw ) {
        $line = trim( $raw );

        if ( $line === '' ) { $flush(); continue; }

        if ( $line === VB_CT_TOKEN_SCHEDULE ) {
            $flush();
            $html .= vb_contract_schedule_html( $contract );
            continue;
        }
        if ( $line === VB_CT_TOKEN_SCOPE ) {
            $flush();
            $html .= vb_contract_checklist_html( vb_contract_scope_items( $contract ) );
            continue;
        }

        // Titre d'article : « ARTICLE 3 — PRIX ET MODALITÉS DE PAIEMENT ».
        if ( preg_match( '/^ARTICLE\h+(\d+)\h*[—-]\h*(.+)$/u', $line, $m ) ) {
            $flush();
            // L'espace entre les deux <span> n'est pas décoratif : sans lui,
            // un lecteur d'écran annonce « Article 1OBJET DU CONTRAT », et un
            // copier-coller du contrat colle les deux morceaux.
            $html .= '<h2 class="vb-ct-article">'
                   . '<span class="vb-ct-article-n">Article ' . intval( $m[1] ) . '</span> '
                   . '<span class="vb-ct-article-t">' . esc_html( $m[2] ) . '</span>'
                   . '</h2>';
            continue;
        }

        // Puce : « — fournir l'ensemble des contenus ».
        if ( preg_match( '/^[—–-]\h+(.+)$/u', $line, $m ) ) {
            if ( $paragraph ) {
                $html .= '<p class="vb-ct-p">' . esc_html( implode( ' ', $paragraph ) ) . '</p>';
                $paragraph = [];
            }
            $list[] = $m[1];
            continue;
        }

        // Une puce sur plusieurs lignes : la suite appartient au dernier item.
        if ( $list ) { $list[ count( $list ) - 1 ] .= ' ' . $line; continue; }

        $paragraph[] = $line;
    }
    $flush();

    // Filet : un modèle personnalisé peut placer un marqueur en milieu de
    // ligne. Il ne doit jamais rester visible dans le document.
    $html = str_replace(
        [ VB_CT_TOKEN_SCHEDULE, VB_CT_TOKEN_SCOPE ],
        [ '', '' ],
        $html
    );

    return $html;
}

/* ============================================================
   QR CODE
============================================================ */

/**
 * Bloc QR de fin de document. Chaîne vide tant que l'option n'est pas
 * activée : un carré noir sur un contrat sans usage derrière, c'est du bruit.
 */
function vb_contract_qr_html( $contract, $size_px = 96 ) {
    if ( ! function_exists( 'vb_contract_qr_enabled' ) || ! vb_contract_qr_enabled() ) return '';

    $payload = vb_contract_qr_payload( $contract );
    $svg     = vb_contract_qr_svg( $payload, $size_px );
    if ( $svg === '' ) return '';

    return '<div class="vb-ct-qr">'
         . '<div class="vb-ct-qr-img">' . $svg . '</div>'
         . '<div class="vb-ct-qr-text">'
         . '<div class="vb-ct-qr-title">Vérification du document</div>'
         . '<div class="vb-ct-qr-sub">Scannez ce code pour consulter l\'exemplaire de référence de ce contrat.</div>'
         . '<div class="vb-ct-qr-url">' . esc_html( $payload ) . '</div>'
         . '</div></div>';
}
