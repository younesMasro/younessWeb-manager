<?php
/**
 * Modèles de contrats — v2.7
 *
 * Même principe que les modèles WhatsApp (includes/whatsapp-templates.php) :
 * des modèles d'usine dans le code, surchargeables et stockés dans l'option
 * `vb_contract_templates`. Un modèle vidé revient automatiquement à l'usine :
 * on ne peut pas se retrouver sans contrat imprimable.
 *
 * Le corps d'un modèle est du texte avec des marqueurs {{placeholder}}.
 * Les blocs conditionnels {{#maintenance}} … {{/maintenance}} n'apparaissent
 * que si la clause est active : un contrat sans maintenance ne doit pas
 * afficher un article vide.
 *
 * ⚠️ Ces textes sont des modèles de travail, pas un avis juridique. À faire
 * relire par un juriste avant usage sur des montants importants.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   LE PRESTATAIRE (partie « DE » du contrat)
============================================================ */

/** Coordonnées de l'agence, surchargeables depuis la page Contrats. */
function vb_contract_provider() {
    $defaults = [
        'name'     => 'YounessWeb',
        'legal'    => 'Youness Masroure',
        'legal_id' => '',
        'address'  => '',
        'city'     => 'Casablanca',
        'phone'    => '+212 774-654464',
        'email'    => 'younes.masroure@gmail.com',
        'website'  => 'younessweb.me',
    ];
    $saved = get_option( 'vb_contract_provider', [] );
    if ( ! is_array( $saved ) ) $saved = [];

    $out = [];
    foreach ( $defaults as $k => $v ) {
        $custom = isset( $saved[ $k ] ) ? trim( (string) $saved[ $k ] ) : '';
        $out[ $k ] = $custom !== '' ? $custom : $v;
    }
    return $out;
}

function vb_contract_save_provider( array $data ) {
    $clean = [];
    foreach ( array_keys( vb_contract_provider() ) as $k ) {
        if ( isset( $data[ $k ] ) ) $clean[ $k ] = sanitize_text_field( $data[ $k ] );
    }
    update_option( 'vb_contract_provider', $clean, false );
    return true;
}

/* ============================================================
   MODÈLES D'USINE
============================================================ */

function vb_contract_default_templates() {
    return [

        /* ─────────────────────────────────────────────────────────── */
        'creation' => [
            'label'                => 'Création de site web',
            'icon'                 => '🌐',
            'title'                => 'Contrat de création de site web',
            'deposit_percent'      => 50,
            'delivery_days'        => 21,
            'revisions'            => 2,
            'warranty_months'      => 3,
            'late_penalty_percent' => 0,
            'maintenance'          => false,
            'maintenance_price'    => 0,
            'maintenance_months'   => 12,
            'scope'                => "Conception graphique de la maquette\n"
                                    . "Intégration et développement du site\n"
                                    . "Version mobile et tablette (responsive)\n"
                                    . "Mise en ligne sur l'hébergement du CLIENT\n"
                                    . "Formation à la prise en main (1 séance)",
            'body'                 => vb_contract_body_creation(),
        ],

        /* ─────────────────────────────────────────────────────────── */
        'maintenance' => [
            'label'                => 'Maintenance / Suivi mensuel',
            'icon'                 => '🔧',
            'title'                => 'Contrat de maintenance et de suivi',
            'deposit_percent'      => 0,
            'delivery_days'        => 0,
            'revisions'            => 0,
            'warranty_months'      => 0,
            'late_penalty_percent' => 0,
            'maintenance'          => true,
            'maintenance_price'    => 300,
            'maintenance_months'   => 12,
            'scope'                => "Mises à jour du CMS, du thème et des extensions\n"
                                    . "Sauvegarde hebdomadaire du site\n"
                                    . "Surveillance de la disponibilité\n"
                                    . "Correction des anomalies techniques\n"
                                    . "Petites modifications de contenu (1 h / mois)\n"
                                    . "Assistance par WhatsApp et email",
            'body'                 => vb_contract_body_maintenance(),
        ],

        /* ─────────────────────────────────────────────────────────── */
        'full' => [
            'label'                => 'Création + maintenance',
            'icon'                 => '📦',
            'title'                => 'Contrat de création de site web et de maintenance',
            'deposit_percent'      => 50,
            'delivery_days'        => 21,
            'revisions'            => 2,
            'warranty_months'      => 3,
            'late_penalty_percent' => 0,
            'maintenance'          => true,
            'maintenance_price'    => 300,
            'maintenance_months'   => 12,
            'scope'                => "Conception graphique de la maquette\n"
                                    . "Intégration et développement du site\n"
                                    . "Version mobile et tablette (responsive)\n"
                                    . "Mise en ligne sur l'hébergement du CLIENT\n"
                                    . "Formation à la prise en main (1 séance)\n"
                                    . "Maintenance et suivi mensuel après livraison",
            'body'                 => vb_contract_body_creation(),
        ],
    ];
}

/* ============================================================
   CORPS DES CONTRATS
============================================================ */

function vb_contract_body_creation() {
    return <<<'TXT'
ARTICLE 1 — OBJET DU CONTRAT
Le présent contrat a pour objet la réalisation, par le PRESTATAIRE et pour le
compte du CLIENT, d'un site web de type « {{site_type}} ».
Les prestations comprises sont détaillées à l'article 2.

ARTICLE 2 — PRESTATIONS COMPRISES
{{scope}}

Toute prestation non listée ci-dessus fait l'objet d'un devis complémentaire
accepté par écrit avant exécution.

ARTICLE 3 — PRIX ET MODALITÉS DE PAIEMENT
Le prix total de la prestation est fixé à {{amount_total}} ({{amount_words}}).

{{payment_schedule}}

Le paiement s'effectue par virement bancaire, versement ou espèces.
{{#late_penalty}}
En cas de retard de paiement, une pénalité de {{late_penalty_percent}} % par mois
de retard est applicable de plein droit, sans mise en demeure préalable.
{{/late_penalty}}

ARTICLE 4 — DÉLAI DE LIVRAISON
Le PRESTATAIRE s'engage à livrer le site dans un délai de {{delivery_days}} jours
ouvrables à compter de la signature du contrat et de l'encaissement de l'acompte.

Ce délai est suspendu tant que le CLIENT n'a pas fourni l'ensemble des éléments
nécessaires (textes, images, logo, accès techniques). Le retard de fourniture de
ces éléments par le CLIENT reporte la date de livraison d'autant.

ARTICLE 5 — OBLIGATIONS DU CLIENT
Le CLIENT s'engage à :
— fournir l'ensemble des contenus (textes, images, logo) dans un délai raisonnable ;
— désigner un interlocuteur unique pour la validation ;
— garantir qu'il détient les droits sur les contenus qu'il transmet ;
— répondre aux demandes de validation dans un délai de 7 jours.

ARTICLE 6 — MODIFICATIONS ET RÉVISIONS
Le prix comprend {{revisions_included}} série(s) de modifications après
présentation de la maquette. Toute demande supplémentaire, ou toute demande
formulée après la mise en ligne, fait l'objet d'une facturation séparée.

ARTICLE 7 — GARANTIE
Le PRESTATAIRE garantit le bon fonctionnement du site pendant
{{warranty_months}} mois à compter de la mise en ligne. Cette garantie couvre la
correction des anomalies techniques imputables au PRESTATAIRE.

Sont exclus de la garantie : les modifications effectuées par le CLIENT ou un
tiers, les défaillances de l'hébergement, les mises à jour non demandées au
PRESTATAIRE, et toute évolution fonctionnelle.

ARTICLE 8 — HÉBERGEMENT ET NOM DE DOMAINE
Sauf mention contraire, l'hébergement et le nom de domaine sont souscrits au nom
et aux frais du CLIENT. Leur renouvellement relève de sa seule responsabilité.
Le PRESTATAIRE ne peut être tenu responsable d'une interruption de service liée
au non-renouvellement de ces services.

ARTICLE 9 — PROPRIÉTÉ INTELLECTUELLE
{{ip_clause}}

Le PRESTATAIRE se réserve le droit de citer le site réalisé et d'en présenter des
visuels dans ses références commerciales, sauf refus écrit du CLIENT.

ARTICLE 10 — CONFIDENTIALITÉ
Chaque partie s'engage à ne pas divulguer les informations confidentielles
portées à sa connaissance à l'occasion de l'exécution du présent contrat.

ARTICLE 11 — RÉSILIATION
En cas de résiliation par le CLIENT après le démarrage des travaux, l'acompte
versé reste acquis au PRESTATAIRE à titre d'indemnité, et les travaux déjà
réalisés sont dus au prorata de leur avancement.

{{#maintenance}}
ARTICLE 12 — MAINTENANCE ET SUIVI
À l'issue de la livraison, le CLIENT souscrit à un service de maintenance et de
suivi d'un montant de {{maintenance_price}} par mois, pour une durée de
{{maintenance_months}} mois à compter du {{maintenance_start}}.

Ce service comprend les mises à jour techniques, la sauvegarde régulière, la
surveillance de la disponibilité et l'assistance du CLIENT.

Il est reconductible tacitement et résiliable par l'une ou l'autre des parties
moyennant un préavis d'un mois notifié par écrit.
{{/maintenance}}

ARTICLE 13 — LITIGES
Le présent contrat est soumis au droit marocain. En cas de litige, et à défaut
d'accord amiable, compétence expresse est attribuée aux tribunaux de
{{jurisdiction}}.

{{#custom_clauses}}
ARTICLE 14 — DISPOSITIONS PARTICULIÈRES
{{custom_clauses}}
{{/custom_clauses}}
TXT;
}

function vb_contract_body_maintenance() {
    return <<<'TXT'
ARTICLE 1 — OBJET DU CONTRAT
Le présent contrat a pour objet la maintenance et le suivi technique du site
{{site_url}}, propriété du CLIENT, par le PRESTATAIRE.

ARTICLE 2 — PRESTATIONS COMPRISES
{{scope}}

Toute intervention non listée ci-dessus (refonte, nouvelle fonctionnalité,
création de page, campagne publicitaire) fait l'objet d'un devis séparé.

ARTICLE 3 — PRIX ET DURÉE
Le montant de l'abonnement est de {{maintenance_price}} par mois, soit
{{maintenance_yearly}} par an.

Le contrat prend effet le {{maintenance_start}} pour une durée de
{{maintenance_months}} mois. Il est reconductible tacitement pour la même durée.

Le paiement est mensuel et s'effectue en début de période.

ARTICLE 4 — DÉLAI D'INTERVENTION
Le PRESTATAIRE s'engage à répondre à toute demande sous 48 heures ouvrables.
En cas d'indisponibilité totale du site, l'intervention est engagée dans les
plus brefs délais après signalement.

ARTICLE 5 — LIMITES DU SERVICE
Le service ne couvre pas :
— les défaillances de l'hébergement ou du nom de domaine ;
— les dommages résultant de modifications effectuées par le CLIENT ou un tiers ;
— la restauration de données consécutive à un piratage lié à des identifiants
  communiqués par le CLIENT à des tiers ;
— le développement de nouvelles fonctionnalités.

ARTICLE 6 — OBLIGATIONS DU CLIENT
Le CLIENT s'engage à maintenir actifs son hébergement et son nom de domaine, et
à communiquer au PRESTATAIRE les accès nécessaires à l'exécution du service.

ARTICLE 7 — RÉSILIATION
Le contrat est résiliable par l'une ou l'autre des parties moyennant un préavis
d'un mois notifié par écrit. Les sommes dues au titre du mois en cours restent
exigibles.

ARTICLE 8 — LITIGES
Le présent contrat est soumis au droit marocain. En cas de litige, et à défaut
d'accord amiable, compétence expresse est attribuée aux tribunaux de
{{jurisdiction}}.

{{#custom_clauses}}
ARTICLE 9 — DISPOSITIONS PARTICULIÈRES
{{custom_clauses}}
{{/custom_clauses}}
TXT;
}

/* ============================================================
   MODÈLES EFFECTIFS
============================================================ */

/**
 * Modèles réellement utilisés : ceux d'usine, dont le corps et le titre
 * peuvent avoir été personnalisés. Un champ vidé revient à l'usine.
 */
function vb_contract_templates() {
    $defaults = vb_contract_default_templates();
    $saved    = get_option( 'vb_contract_templates', [] );
    if ( ! is_array( $saved ) ) $saved = [];

    $out = [];
    foreach ( $defaults as $key => $tpl ) {
        $custom = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : [];
        foreach ( [ 'title', 'body', 'scope' ] as $field ) {
            $v = isset( $custom[ $field ] ) ? trim( (string) $custom[ $field ] ) : '';
            if ( $v !== '' ) $tpl[ $field ] = $v;
        }
        $out[ $key ] = $tpl;
    }
    return $out;
}

/** Enregistre les modèles personnalisés. Un champ vide restaure l'usine. */
function vb_contract_save_templates( array $templates ) {
    $allowed = array_keys( vb_contract_default_templates() );
    $clean   = [];

    foreach ( $allowed as $key ) {
        if ( ! isset( $templates[ $key ] ) || ! is_array( $templates[ $key ] ) ) continue;
        $row = [];
        foreach ( [ 'title', 'body', 'scope' ] as $field ) {
            if ( ! isset( $templates[ $key ][ $field ] ) ) continue;
            // sanitize_textarea_field préserve les retours à la ligne : la mise
            // en forme des articles du contrat en dépend entièrement.
            $row[ $field ] = sanitize_textarea_field( $templates[ $key ][ $field ] );
        }
        if ( $row ) $clean[ $key ] = $row;
    }

    update_option( 'vb_contract_templates', $clean, false );
    return true;
}

/* ============================================================
   MONTANT EN TOUTES LETTRES
============================================================ */

/**
 * Convertit un montant en toutes lettres françaises, comme l'exige la
 * pratique sur un document contractuel : « deux mille cinq cents dirhams ».
 * Gère les particularités françaises (soixante-dix, quatre-vingts, cent/cents).
 */
function vb_number_to_words_fr( $n ) {
    $n = (int) $n;
    if ( $n === 0 ) return 'zéro';
    if ( $n < 0 )   return 'moins ' . vb_number_to_words_fr( -$n );

    $units = [
        0 => '', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre', 5 => 'cinq',
        6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf', 10 => 'dix',
        11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze',
        15 => 'quinze', 16 => 'seize',
    ];
    $tens = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante',
        5 => 'cinquante', 6 => 'soixante',
    ];

    if ( $n < 17 ) return $units[ $n ];

    if ( $n < 100 ) {
        $t = intdiv( $n, 10 );
        $u = $n % 10;

        // 70-79 et 90-99 se disent « soixante-dix… » et « quatre-vingt-dix… ».
        if ( $t === 7 || $t === 9 ) {
            $base = $t === 7 ? 'soixante' : 'quatre-vingt';
            $rest = vb_number_to_words_fr( 10 + $u );
            return $base . ( $u === 1 && $t === 7 ? ' et ' : '-' ) . $rest;
        }
        if ( $t === 8 ) {
            // « quatre-vingts » prend un s, sauf suivi d'un autre nombre.
            return $u === 0 ? 'quatre-vingts' : 'quatre-vingt-' . $units[ $u ];
        }
        if ( $u === 0 ) return $tens[ $t ];
        if ( $u === 1 ) return $tens[ $t ] . ' et un';
        return $tens[ $t ] . '-' . $units[ $u ];
    }

    if ( $n < 1000 ) {
        $h    = intdiv( $n, 100 );
        $rest = $n % 100;
        // « cent » prend un s au pluriel, sauf s'il est suivi d'un autre nombre.
        $prefix = $h === 1 ? 'cent' : $units[ $h ] . ' cent' . ( $rest === 0 ? 's' : '' );
        return $rest === 0 ? $prefix : $prefix . ' ' . vb_number_to_words_fr( $rest );
    }

    if ( $n < 1000000 ) {
        $th   = intdiv( $n, 1000 );
        $rest = $n % 1000;
        // « mille » est toujours invariable, et « un mille » ne se dit pas.
        $prefix = $th === 1 ? 'mille' : vb_number_to_words_fr( $th ) . ' mille';
        return $rest === 0 ? $prefix : $prefix . ' ' . vb_number_to_words_fr( $rest );
    }

    $m    = intdiv( $n, 1000000 );
    $rest = $n % 1000000;
    $prefix = $m === 1 ? 'un million' : vb_number_to_words_fr( $m ) . ' millions';
    return $rest === 0 ? $prefix : $prefix . ' ' . vb_number_to_words_fr( $rest );
}

/** Montant en toutes lettres, centimes compris : « … dirhams et 50 centimes ». */
function vb_amount_in_words( $amount, $currency = 'dirhams' ) {
    $amount   = round( floatval( $amount ), 2 );
    $whole    = (int) floor( $amount );
    $cents    = (int) round( ( $amount - $whole ) * 100 );

    $words = vb_number_to_words_fr( $whole ) . ' ' . $currency;
    if ( $cents > 0 ) $words .= ' et ' . vb_number_to_words_fr( $cents ) . ' centimes';

    return $words;
}

/** Formatage monétaire commun à tout le module. */
function vb_contract_money( $amount ) {
    return number_format( floatval( $amount ), 2, ',', ' ' ) . ' MAD';
}

/* ============================================================
   RENDU
============================================================ */

/** Échéancier mis en forme pour le corps du contrat. */
function vb_contract_render_schedule( $contract ) {
    $rows = vb_contract_schedule( $contract );
    if ( ! $rows ) {
        return 'Le règlement s\'effectue en totalité à la signature du présent contrat.';
    }

    $out = [];
    foreach ( $rows as $i => $r ) {
        $line = sprintf( '%d. %s : %s',
            $i + 1,
            $r['label'] ?? '',
            vb_contract_money( $r['amount'] ?? 0 )
        );
        if ( ! empty( $r['due'] ) ) $line .= ' — ' . $r['due'];
        $out[] = $line;
    }
    return implode( "\n", $out );
}

/** Clause de propriété intellectuelle, selon que les droits sont cédés ou non. */
function vb_contract_ip_clause( $contract ) {
    $transfer = is_object( $contract ) ? ! empty( $contract->ip_transfer ) : ! empty( $contract['ip_transfer'] );

    return $transfer
        ? "L'intégralité des droits de propriété sur le site livré (design, contenus "
        . "intégrés, code spécifique) est cédée au CLIENT à compter du paiement "
        . "intégral du prix. Avant complet paiement, le PRESTATAIRE demeure "
        . "propriétaire de l'ensemble des livrables."
        : "Le PRESTATAIRE concède au CLIENT un droit d'usage du site livré. Les "
        . "droits de propriété intellectuelle sur le code et les développements "
        . "spécifiques demeurent la propriété du PRESTATAIRE.";
}

/**
 * Table de substitution d'un contrat. Toutes les valeurs sont des chaînes
 * prêtes à afficher — le rendu final ne fait plus aucun calcul.
 */
function vb_contract_placeholders( $contract ) {
    $c = (object) $contract;
    $p = vb_contract_provider();

    $maint_price  = floatval( $c->maintenance_price ?? 0 );
    $maint_months = intval( $c->maintenance_months ?? 0 );

    $fmt_date = function ( $d ) {
        return $d ? date( 'd/m/Y', strtotime( $d ) ) : '—';
    };

    return [
        // Prestataire
        '{{provider_name}}'     => $p['name'],
        '{{provider_legal}}'    => $p['legal'],
        '{{provider_legal_id}}' => $p['legal_id'],
        '{{provider_address}}'  => $p['address'],
        '{{provider_city}}'     => $p['city'],
        '{{provider_phone}}'    => $p['phone'],
        '{{provider_email}}'    => $p['email'],
        '{{provider_website}}'  => $p['website'],

        // Client
        '{{client_name}}'     => $c->client_name ?? '',
        '{{client_phone}}'    => $c->client_phone ?? '',
        '{{client_email}}'    => $c->client_email ?? '',
        '{{client_city}}'     => $c->client_city ?? '',
        '{{client_legal_id}}' => $c->client_legal_id ?? '',

        // Document
        '{{contract_number}}' => $c->number ?? '',
        '{{contract_title}}'  => $c->title ?? '',
        '{{issue_date}}'      => $fmt_date( $c->issue_date ?? '' ),
        '{{signed_date}}'     => $fmt_date( $c->signed_date ?? '' ),
        '{{today}}'           => date( 'd/m/Y' ),

        // Prestation
        // ?? '' systématique : le rendu doit tenir sur un contrat partiel
        // (aperçu d'un modèle, brouillon jamais enregistré).
        '{{site_type}}'          => ( $c->site_type ?? '' ) ?: 'site web',
        '{{site_url}}'           => ( $c->site_url ?? '' ) ?: '—',
        '{{scope}}'              => vb_contract_format_scope( $c->scope ?? '' ),
        '{{delivery_days}}'      => intval( $c->delivery_days ?? 0 ),
        '{{revisions_included}}' => intval( $c->revisions_included ?? 0 ),
        '{{warranty_months}}'    => intval( $c->warranty_months ?? 0 ),

        // Argent
        '{{amount_total}}'         => vb_contract_money( $c->amount_total ?? 0 ),
        '{{amount_words}}'         => vb_amount_in_words( $c->amount_total ?? 0 ),
        '{{deposit_amount}}'       => vb_contract_money( $c->deposit_amount ?? 0 ),
        '{{balance_amount}}'       => vb_contract_money( floatval( $c->amount_total ?? 0 ) - floatval( $c->deposit_amount ?? 0 ) ),
        '{{payment_schedule}}'     => vb_contract_render_schedule( $c ),
        '{{late_penalty_percent}}' => rtrim( rtrim( number_format( floatval( $c->late_penalty_percent ?? 0 ), 2, ',', '' ), '0' ), ',' ),

        // Maintenance
        '{{maintenance_price}}'  => vb_contract_money( $maint_price ),
        '{{maintenance_yearly}}' => vb_contract_money( $maint_price * 12 ),
        '{{maintenance_months}}' => $maint_months,
        '{{maintenance_start}}'  => $fmt_date( $c->maintenance_start ?? '' ),

        // Juridique
        '{{jurisdiction}}'   => ( $c->jurisdiction ?? '' ) ?: $p['city'],
        '{{ip_clause}}'      => vb_contract_ip_clause( $c ),
        '{{custom_clauses}}' => trim( (string) ( $c->custom_clauses ?? '' ) ),
    ];
}

/** Les livrables saisis une ligne par ligne deviennent une liste à puces. */
function vb_contract_format_scope( $scope ) {
    $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $scope ) ) );
    if ( ! $lines ) return '—';
    return implode( "\n", array_map( fn( $l ) => '— ' . ltrim( $l, "-— \t" ), $lines ) );
}

/**
 * Rend le corps d'un contrat : blocs conditionnels puis substitution.
 *
 * L'ordre compte. Les blocs sont résolus AVANT les placeholders, sinon un
 * {{maintenance_price}} injecté à l'intérieur d'un bloc désactivé resterait
 * visible dans le document final.
 */
function vb_contract_render( $contract, $body = null ) {
    $c = (object) $contract;

    if ( $body === null ) {
        $templates = vb_contract_templates();
        $key       = $c->template_key ?? 'creation';
        $tpl       = $templates[ $key ] ?? $templates['creation'];
        $body      = $tpl['body'];
    }

    // Conditions d'affichage des blocs {{#nom}} … {{/nom}}.
    $flags = [
        'maintenance'    => ! empty( $c->maintenance_enabled ) && floatval( $c->maintenance_price ?? 0 ) > 0,
        'late_penalty'   => floatval( $c->late_penalty_percent ?? 0 ) > 0,
        'custom_clauses' => trim( (string) ( $c->custom_clauses ?? '' ) ) !== '',
    ];

    foreach ( $flags as $name => $enabled ) {
        $pattern = '/\{\{#' . preg_quote( $name, '/' ) . '\}\}(.*?)\{\{\/' . preg_quote( $name, '/' ) . '\}\}/s';
        $body    = preg_replace_callback( $pattern, function ( $m ) use ( $enabled ) {
            return $enabled ? trim( $m[1] ) : '';
        }, $body );
    }

    $body = strtr( $body, vb_contract_placeholders( $c ) );

    // Un bloc retiré laisse un trou de plusieurs lignes vides : on referme.
    $body = preg_replace( "/\n{3,}/", "\n\n", $body );

    return trim( $body );
}

/** Liste des marqueurs disponibles, pour l'aide de l'éditeur de modèles. */
function vb_contract_placeholder_help() {
    $sample = (object) [
        'client_name' => '', 'amount_total' => 0, 'maintenance_price' => 0,
        'ip_transfer' => 1, 'scope' => '', 'jurisdiction' => '',
    ];
    return array_keys( vb_contract_placeholders( $sample ) );
}
