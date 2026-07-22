<?php
/**
 * Contrats — v2.7
 *
 * Quatre écrans dans un seul template, comme templates/invoices.php :
 *   list      → la liste + les compteurs + les projets sans contrat
 *   new/edit  → le formulaire (pré-rempli depuis un projet si ?project_id=)
 *   view      → le contrat rendu, prêt à imprimer / exporter en PDF
 *   templates → l'éditeur des modèles et des coordonnées du prestataire
 */

if ( ! defined('ABSPATH') ) exit;

vb_create_contracts_table();

$action      = sanitize_text_field( $_GET['action'] ?? 'list' );
$contract_id = intval( $_GET['id'] ?? 0 );
$statuses    = vb_contract_statuses();
$templates   = vb_contract_templates();
$provider    = vb_contract_provider();

/* ════════════════════════════════════════════════════════════
   ENREGISTREMENT
════════════════════════════════════════════════════════════ */
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['vb_contract_nonce'] ) ) {
    if ( ! wp_verify_nonce( $_POST['vb_contract_nonce'], 'vb_contract_save' ) ) wp_die( 'Security check failed' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Accès refusé.' );

    $edit_id  = intval( $_POST['contract_id'] ?? 0 );
    $existing = $edit_id ? vb_get_contract( $edit_id ) : null;

    // Un contrat signé est verrouillé : le formulaire ne peut pas le réécrire.
    if ( $existing && vb_contract_is_locked( $existing ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&action=view&id=' . $edit_id . '&vb_error=locked' ) );
        exit;
    }

    // Échéancier : lignes du formulaire, ou reconstruit si aucune n'est saisie.
    $schedule = [];
    foreach ( (array) ( $_POST['schedule'] ?? [] ) as $row ) {
        if ( trim( (string) ( $row['label'] ?? '' ) ) === '' ) continue;
        $schedule[] = [
            'label'  => wp_unslash( $row['label'] ),
            'amount' => $row['amount'] ?? 0,
            'due'    => wp_unslash( $row['due'] ?? '' ),
            'paid'   => ! empty( $row['paid'] ) ? 1 : 0,
        ];
    }
    if ( ! $schedule ) {
        $schedule = vb_contract_default_schedule(
            $_POST['amount_total'] ?? 0,
            $_POST['deposit_amount'] ?? 0,
            $_POST['template_key'] ?? 'creation'
        );
    }

    $payload = array_map( fn( $v ) => is_string( $v ) ? wp_unslash( $v ) : $v, $_POST );
    $payload['payment_schedule'] = $schedule;

    $saved_id = vb_save_contract( $payload, $edit_id );

    if ( ! $saved_id ) {
        wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&action=' . ( $edit_id ? 'edit&id=' . $edit_id : 'new' ) . '&vb_error=save' ) );
        exit;
    }
    wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&action=view&id=' . $saved_id . '&saved=1' ) );
    exit;
}

/* ── Suppression ── */
if ( $action === 'delete' && $contract_id ) {
    check_admin_referer( 'vb_contract_delete_' . $contract_id );
    $c = vb_get_contract( $contract_id );
    if ( $c && vb_contract_is_locked( $c ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&vb_error=locked_delete' ) );
        exit;
    }
    vb_delete_contract( $contract_id );
    wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&deleted=1' ) );
    exit;
}

/* ── Signature ── */
if ( $action === 'sign' && $contract_id ) {
    check_admin_referer( 'vb_contract_sign_' . $contract_id );
    vb_sign_contract( $contract_id, sanitize_text_field( $_GET['date'] ?? '' ) );
    wp_safe_redirect( admin_url( 'admin.php?page=vendbase-contracts&action=view&id=' . $contract_id . '&signed=1' ) );
    exit;
}

$contract = ( $contract_id && in_array( $action, [ 'edit', 'view' ], true ) ) ? vb_get_contract( $contract_id ) : null;
if ( in_array( $action, [ 'edit', 'view' ], true ) && ! $contract ) $action = 'list';

$errors = [
    'locked'        => 'Ce contrat est signé : repassez-le en brouillon pour le modifier.',
    'locked_delete' => 'Un contrat signé ne peut pas être supprimé. Annulez-le d\'abord.',
    'save'          => 'Enregistrement impossible — le nom du client est obligatoire.',
];
$error_key = sanitize_text_field( $_GET['vb_error'] ?? '' );

/** Badge de statut réutilisé partout dans la page. */
function vb_contract_badge( $status ) {
    $s = vb_contract_statuses()[ $status ] ?? [ 'label' => $status, 'color' => 'blue', 'icon' => '' ];
    return sprintf( '<span class="vb-badge vb-badge-%s">%s %s</span>',
        esc_attr( $s['color'] ), $s['icon'], esc_html( $s['label'] ) );
}
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">📜 Contrats</h1>
            <span class="vb-page-sub">Documents signés avec tes clients — création, maintenance, échéanciers</span>
        </div>
    </div>
    <div class="vb-header-right" style="gap:8px">
        <a href="<?= admin_url('admin.php?page=vendbase-contracts&action=templates') ?>" class="vb-btn vb-btn-ghost">
            <span class="dashicons dashicons-edit-page"></span> Modèles
        </a>
        <a href="<?= admin_url('admin.php?page=vendbase-contracts&action=new') ?>" class="vb-btn vb-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> Nouveau contrat
        </a>
    </div>
</div>

<?php if ( isset($_GET['saved']) ): ?>
<div class="vb-notice vb-notice-success">✅ Contrat enregistré.</div>
<?php endif; ?>
<?php if ( isset($_GET['signed']) ): ?>
<div class="vb-notice vb-notice-success">✍️ Contrat signé — le projet a été mis à jour.</div>
<?php endif; ?>
<?php if ( isset($_GET['deleted']) ): ?>
<div class="vb-notice vb-notice-success">🗑️ Contrat supprimé.</div>
<?php endif; ?>
<?php if ( $error_key && isset($errors[$error_key]) ): ?>
<div class="vb-notice" style="border-left:4px solid #dc2626">⚠️ <?= esc_html($errors[$error_key]) ?></div>
<?php endif; ?>


<?php if ( $action === 'list' ): /* ══════════════ LISTE ══════════════ */
$stats     = vb_get_contracts_stats();
$rows      = vb_get_contracts([
    'status' => sanitize_text_field($_GET['status'] ?? ''),
    'search' => sanitize_text_field($_GET['s'] ?? ''),
]);
$uncovered = vb_get_projects_without_contract( 12 );
?>

<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-media-text"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats->total) ?></div>
            <div class="vb-stat-label">Contrats</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format((float)$stats->signed_value, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">MAD sous contrat signé</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-clock"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format((float)$stats->pending_value, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">MAD en attente de signature</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-update"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format((float)$stats->mrr_contracted, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">MAD/mois de maintenance contractée</div>
        </div>
    </div>
</div>

<?php if ( $uncovered ): ?>
<div class="vb-card" style="margin-top:16px;border-left:4px solid #f59e0b">
    <div class="vb-card-header"><span>⚠️ Projets sans contrat (<?= count($uncovered) ?>)</span></div>
    <div style="padding:12px 16px">
        <p class="vb-sub" style="margin-top:0">
            Un chantier démarré sans document signé, c'est un litige sans preuve.
            Un clic crée le contrat pré-rempli à partir du projet.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach ( $uncovered as $p ): ?>
            <a class="vb-btn vb-btn-ghost vb-btn-sm"
               href="<?= admin_url('admin.php?page=vendbase-contracts&action=new&project_id=' . $p->id) ?>">
                <?= esc_html($p->client_name) ?>
                <span class="vb-sub">· <?= number_format((float)$p->prix, 0, ',', ' ') ?> MAD</span>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header">
        <span>Tous les contrats</span>
        <form method="get" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="page" value="vendbase-contracts">
            <input type="search" name="s" class="vb-input" placeholder="Client, numéro…"
                   value="<?= esc_attr($_GET['s'] ?? '') ?>" style="width:180px">
            <select name="status" class="vb-select" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <?php foreach ( $statuses as $k => $s ): ?>
                <option value="<?= esc_attr($k) ?>" <?= selected($_GET['status'] ?? '', $k, false) ?>><?= $s['icon'] ?> <?= esc_html($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

<table class="vb-table vb-table-full">
    <thead>
        <tr>
            <th>Numéro</th><th>Client</th><th>Modèle</th><th>Montant</th>
            <th>Échéancier</th><th>Statut</th><th>Émis le</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ( $rows ): foreach ( $rows as $c ):
        $tpl   = $templates[$c->template_key] ?? [ 'label' => $c->template_key, 'icon' => '📄' ];
        $sched = vb_contract_schedule_totals( $c );
    ?>
    <tr>
        <td><strong><?= esc_html($c->number) ?></strong></td>
        <td>
            <strong><?= esc_html($c->client_name) ?></strong>
            <?php if ($c->project_id): ?>
            <div class="vb-sub">
                <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . $c->project_id) ?>">Projet #<?= intval($c->project_id) ?></a>
            </div>
            <?php else: ?>
            <div class="vb-sub">— hors projet</div>
            <?php endif; ?>
        </td>
        <td><?= $tpl['icon'] ?> <?= esc_html($tpl['label']) ?></td>
        <td>
            <strong><?= number_format((float)$c->amount_total, 2, ',', ' ') ?> MAD</strong>
            <?php if ( !empty($c->maintenance_enabled) && (float)$c->maintenance_price > 0 ): ?>
            <div class="vb-sub">+ <?= number_format((float)$c->maintenance_price, 0, ',', ' ') ?> MAD/mois</div>
            <?php endif; ?>
        </td>
        <td>
            <?php if ( $sched['lines'] ): ?>
                <span class="<?= $sched['paid'] >= $sched['scheduled'] ? 'green' : '' ?>">
                    <?= number_format($sched['paid'], 0, ',', ' ') ?> / <?= number_format($sched['scheduled'], 0, ',', ' ') ?>
                </span>
                <?php if ( ! $sched['balanced'] ): ?>
                <div class="vb-sub" style="color:#dc2626">⚠️ écart <?= number_format($sched['difference'], 2, ',', ' ') ?></div>
                <?php endif; ?>
            <?php else: ?><span class="vb-sub">—</span><?php endif; ?>
        </td>
        <td><?= vb_contract_badge($c->status) ?></td>
        <td><?= $c->issue_date ? date('d/m/Y', strtotime($c->issue_date)) : '—' ?></td>
        <td class="vb-actions-cell">
            <a href="<?= admin_url('admin.php?page=vendbase-contracts&action=view&id=' . $c->id) ?>" class="vb-action-btn" title="Voir / Imprimer"><span class="dashicons dashicons-visibility"></span></a>
            <?php if ( ! vb_contract_is_locked($c) ): ?>
            <a href="<?= admin_url('admin.php?page=vendbase-contracts&action=edit&id=' . $c->id) ?>" class="vb-action-btn vb-btn-edit" title="Modifier"><span class="dashicons dashicons-edit"></span></a>
            <a href="<?= wp_nonce_url(admin_url('admin.php?page=vendbase-contracts&action=delete&id=' . $c->id), 'vb_contract_delete_' . $c->id) ?>"
               class="vb-action-btn vb-btn-delete" title="Supprimer"
               onclick="return confirm('Supprimer ce contrat ?')"><span class="dashicons dashicons-trash"></span></a>
            <?php else: ?>
            <span class="vb-action-btn" title="Contrat signé — verrouillé" style="opacity:.4;cursor:not-allowed"><span class="dashicons dashicons-lock"></span></span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="8" class="vb-empty">Aucun contrat. Crée le premier depuis un projet →</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>


<?php elseif ( $action === 'new' || $action === 'edit' ): /* ══════ FORMULAIRE ══════ */
$projects = vb_get_projects([ 'limit' => 300, 'orderby' => 'client_name', 'order' => 'ASC' ]);

if ( $contract ) {
    $f = $contract;
} else {
    // Pré-remplissage depuis un projet si on arrive avec ?project_id=
    $pid      = intval( $_GET['project_id'] ?? 0 );
    $tpl_key  = sanitize_text_field( $_GET['template'] ?? 'creation' );
    $prefill  = $pid ? vb_contract_prefill_from_project( $pid, $tpl_key ) : null;

    if ( ! $prefill ) {
        $tpl = $templates[$tpl_key] ?? $templates['creation'];
        $prefill = [
            'number' => vb_next_contract_number(), 'project_id' => 0, 'template_key' => $tpl_key,
            'title' => $tpl['title'], 'client_name' => '', 'client_phone' => '', 'client_email' => '',
            'client_city' => '', 'client_legal_id' => '', 'site_type' => '', 'site_url' => '',
            'scope' => $tpl['scope'], 'delivery_days' => $tpl['delivery_days'],
            'revisions_included' => $tpl['revisions'], 'warranty_months' => $tpl['warranty_months'],
            'amount_total' => 0, 'deposit_amount' => 0,
            'payment_schedule' => [], 'late_penalty_percent' => $tpl['late_penalty_percent'],
            'maintenance_enabled' => $tpl['maintenance'] ? 1 : 0,
            'maintenance_price' => $tpl['maintenance_price'], 'maintenance_months' => $tpl['maintenance_months'],
            'maintenance_start' => date('Y-m-d'), 'jurisdiction' => $provider['city'], 'ip_transfer' => 1,
            'custom_clauses' => '', 'status' => 'draft', 'issue_date' => date('Y-m-d'), 'notes' => '',
        ];
    }
    $f = (object) $prefill;
    if ( is_array( $f->payment_schedule ) ) $f->payment_schedule = wp_json_encode( $f->payment_schedule );
}

$schedule_rows = vb_contract_schedule( $f );
if ( ! $schedule_rows ) $schedule_rows = vb_contract_default_schedule( $f->amount_total, $f->deposit_amount, $f->template_key );
?>
<form method="post" id="vb-contract-form">
    <?= wp_nonce_field('vb_contract_save', 'vb_contract_nonce', true, false) ?>
    <input type="hidden" name="contract_id" value="<?= intval($f->id ?? 0) ?>">

<div class="vb-form-grid">
<div class="vb-form-col">

    <div class="vb-card">
        <div class="vb-card-header"><span>📄 Document</span></div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Modèle</label>
                <select name="template_key" class="vb-select" id="vb-ct-template">
                    <?php foreach ( $templates as $k => $t ): ?>
                    <option value="<?= esc_attr($k) ?>" <?= selected($f->template_key, $k, false) ?>
                            data-maintenance="<?= intval($t['maintenance']) ?>"
                            data-deposit="<?= intval($t['deposit_percent']) ?>">
                        <?= $t['icon'] ?> <?= esc_html($t['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Numéro</label>
                <input type="text" name="number" class="vb-input" value="<?= esc_attr($f->number) ?>" required>
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Titre du contrat</label>
            <input type="text" name="title" class="vb-input" value="<?= esc_attr($f->title) ?>">
        </div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Date d'émission</label>
                <input type="date" name="issue_date" class="vb-input" value="<?= esc_attr($f->issue_date) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Statut</label>
                <select name="status" class="vb-select">
                    <?php foreach ( $statuses as $k => $s ): ?>
                    <option value="<?= esc_attr($k) ?>" <?= selected($f->status, $k, false) ?>><?= $s['icon'] ?> <?= esc_html($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Projet lié</label>
            <select name="project_id" class="vb-select" id="vb-ct-project">
                <option value="0">— Aucun (contrat hors projet) —</option>
                <?php foreach ( $projects as $pr ): ?>
                <option value="<?= $pr->id ?>" <?= selected($f->project_id, $pr->id, false) ?>
                    data-name="<?= esc_attr($pr->client_name) ?>" data-phone="<?= esc_attr($pr->client_phone) ?>"
                    data-email="<?= esc_attr($pr->client_email) ?>" data-city="<?= esc_attr($pr->client_city) ?>"
                    data-type="<?= esc_attr($pr->site_type) ?>"  data-url="<?= esc_attr($pr->site_url) ?>"
                    data-prix="<?= esc_attr($pr->prix) ?>"       data-avance="<?= esc_attr($pr->avance) ?>"
                    data-track="<?= intval($pr->tracking_enabled) ?>" data-track-price="<?= esc_attr($pr->tracking_price) ?>">
                    #<?= $pr->id ?> — <?= esc_html($pr->client_name) ?> (<?= number_format((float)$pr->prix, 0, ',', ' ') ?> MAD)
                </option>
                <?php endforeach; ?>
            </select>
            <p class="vb-sub">Choisir un projet recopie le client, le prix et la maintenance dans le contrat.</p>
        </div>
    </div>

    <div class="vb-card" style="margin-top:16px">
        <div class="vb-card-header"><span>👤 Client (figé dans le contrat)</span></div>
        <div class="vb-form-group">
            <label class="vb-label">Nom / Raison sociale *</label>
            <input type="text" name="client_name" id="vb-ct-name" class="vb-input" value="<?= esc_attr($f->client_name) ?>" required>
        </div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Téléphone</label>
                <input type="tel" name="client_phone" id="vb-ct-phone" class="vb-input" value="<?= esc_attr($f->client_phone) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Email</label>
                <input type="email" name="client_email" id="vb-ct-email" class="vb-input" value="<?= esc_attr($f->client_email) ?>">
            </div>
        </div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Ville</label>
                <input type="text" name="client_city" id="vb-ct-city" class="vb-input" value="<?= esc_attr($f->client_city) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">CIN / ICE / RC</label>
                <input type="text" name="client_legal_id" class="vb-input" value="<?= esc_attr($f->client_legal_id) ?>">
            </div>
        </div>
    </div>

    <div class="vb-card" style="margin-top:16px">
        <div class="vb-card-header"><span>🛠️ Prestation</span></div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Type de site</label>
                <input type="text" name="site_type" id="vb-ct-type" class="vb-input" value="<?= esc_attr($f->site_type) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">URL du site</label>
                <input type="text" name="site_url" id="vb-ct-url" class="vb-input" value="<?= esc_attr($f->site_url) ?>">
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Livrables — une ligne par élément</label>
            <textarea name="scope" class="vb-input vb-textarea" rows="6"><?= esc_textarea($f->scope) ?></textarea>
        </div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Délai (jours ouvrables)</label>
                <input type="number" name="delivery_days" class="vb-input" value="<?= esc_attr($f->delivery_days) ?>" min="0">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Séries de révisions</label>
                <input type="number" name="revisions_included" class="vb-input" value="<?= esc_attr($f->revisions_included) ?>" min="0">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Garantie (mois)</label>
                <input type="number" name="warranty_months" class="vb-input" value="<?= esc_attr($f->warranty_months) ?>" min="0">
            </div>
        </div>
    </div>

</div><!-- col A -->

<div class="vb-form-col">

    <div class="vb-card">
        <div class="vb-card-header"><span>💰 Montant & échéancier</span></div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Montant total (MAD)</label>
                <input type="number" name="amount_total" id="vb-ct-total" class="vb-input" value="<?= esc_attr($f->amount_total) ?>" min="0" step="0.01">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Acompte (MAD)</label>
                <input type="number" name="deposit_amount" id="vb-ct-deposit" class="vb-input" value="<?= esc_attr($f->deposit_amount) ?>" min="0" step="0.01">
            </div>
        </div>
        <div id="vb-ct-words" class="vb-sub" style="margin:-4px 0 12px"></div>

        <table class="vb-table" id="vb-ct-schedule">
            <thead><tr>
                <th style="width:38%">Échéance</th><th style="width:20%">Montant</th>
                <th style="width:30%">Exigible</th><th style="width:6%">Payé</th><th style="width:6%"></th>
            </tr></thead>
            <tbody id="vb-ct-schedule-body">
            <?php foreach ( $schedule_rows as $i => $row ): ?>
            <tr class="vb-ct-sched-row">
                <td><input type="text" name="schedule[<?= $i ?>][label]" class="vb-input" value="<?= esc_attr($row['label'] ?? '') ?>"></td>
                <td><input type="number" name="schedule[<?= $i ?>][amount]" class="vb-input vb-ct-sched-amount" value="<?= esc_attr($row['amount'] ?? 0) ?>" step="0.01" min="0"></td>
                <td><input type="text" name="schedule[<?= $i ?>][due]" class="vb-input" value="<?= esc_attr($row['due'] ?? '') ?>"></td>
                <td style="text-align:center"><input type="checkbox" name="schedule[<?= $i ?>][paid]" value="1" <?= checked(!empty($row['paid']), true, false) ?>></td>
                <td><button type="button" class="vb-action-btn vb-btn-delete" onclick="vbCtRemoveRow(this)"><span class="dashicons dashicons-trash"></span></button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="display:flex;gap:8px;align-items:center;padding:10px 0">
            <button type="button" class="vb-btn vb-btn-secondary vb-btn-sm" onclick="vbCtAddRow()">+ Ligne</button>
            <button type="button" class="vb-btn vb-btn-ghost vb-btn-sm" onclick="vbCtRebuild()">↻ Reconstruire (acompte + solde)</button>
            <span id="vb-ct-sched-check" class="vb-sub" style="margin-left:auto"></span>
        </div>

        <div class="vb-form-group">
            <label class="vb-label">Pénalité de retard (% / mois) — 0 = clause masquée</label>
            <input type="number" name="late_penalty_percent" class="vb-input" value="<?= esc_attr($f->late_penalty_percent) ?>" min="0" max="100" step="0.5">
        </div>
    </div>

    <div class="vb-card" style="margin-top:16px">
        <div class="vb-card-header"><span>🔧 Maintenance</span></div>
        <div class="vb-form-group">
            <label class="vb-toggle-wrap">
                <input type="checkbox" name="maintenance_enabled" id="vb-ct-maint" value="1" <?= checked(!empty($f->maintenance_enabled), true, false) ?>>
                <span><strong>Inclure une clause de maintenance</strong>
                    <div class="vb-sub">À la signature, le suivi mensuel du projet sera activé automatiquement.</div>
                </span>
            </label>
        </div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Prix mensuel (MAD)</label>
                <input type="number" name="maintenance_price" class="vb-input" value="<?= esc_attr($f->maintenance_price) ?>" min="0" step="0.01">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Durée (mois)</label>
                <input type="number" name="maintenance_months" class="vb-input" value="<?= esc_attr($f->maintenance_months) ?>" min="0">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Début</label>
                <input type="date" name="maintenance_start" class="vb-input" value="<?= esc_attr($f->maintenance_start) ?>">
            </div>
        </div>
    </div>

    <div class="vb-card" style="margin-top:16px">
        <div class="vb-card-header"><span>⚖️ Juridique</span></div>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Tribunaux compétents</label>
                <input type="text" name="jurisdiction" class="vb-input" value="<?= esc_attr($f->jurisdiction) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">&nbsp;</label>
                <label class="vb-toggle-wrap">
                    <input type="checkbox" name="ip_transfer" value="1" <?= checked(!empty($f->ip_transfer), true, false) ?>>
                    <span>Cession des droits au client</span>
                </label>
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Dispositions particulières — 0 caractère = article masqué</label>
            <textarea name="custom_clauses" class="vb-input vb-textarea" rows="4"><?= esc_textarea($f->custom_clauses) ?></textarea>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Note interne (n'apparaît pas dans le contrat)</label>
            <textarea name="notes" class="vb-input vb-textarea" rows="2"><?= esc_textarea($f->notes) ?></textarea>
        </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px">
        <button type="submit" class="vb-btn vb-btn-primary vb-btn-lg">
            <span class="dashicons dashicons-saved"></span> Enregistrer
        </button>
        <a href="<?= admin_url('admin.php?page=vendbase-contracts') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
    </div>

</div><!-- col B -->
</div><!-- grid -->
</form>

<script>
(function(){
    var idx = <?= count($schedule_rows) ?>;

    function money(n){ return (n||0).toLocaleString('fr-MA', {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function val(id){ return parseFloat(document.getElementById(id)?.value) || 0; }

    window.vbCtAddRow = function(){
        var i = idx++;
        document.getElementById('vb-ct-schedule-body').insertAdjacentHTML('beforeend',
            '<tr class="vb-ct-sched-row">'
          + '<td><input type="text" name="schedule['+i+'][label]" class="vb-input"></td>'
          + '<td><input type="number" name="schedule['+i+'][amount]" class="vb-input vb-ct-sched-amount" value="0" step="0.01" min="0"></td>'
          + '<td><input type="text" name="schedule['+i+'][due]" class="vb-input"></td>'
          + '<td style="text-align:center"><input type="checkbox" name="schedule['+i+'][paid]" value="1"></td>'
          + '<td><button type="button" class="vb-action-btn vb-btn-delete" onclick="vbCtRemoveRow(this)"><span class="dashicons dashicons-trash"></span></button></td>'
          + '</tr>');
        bind();
    };

    window.vbCtRemoveRow = function(btn){ btn.closest('tr').remove(); check(); };

    // Reconstruit acompte + solde. Le solde est TOUJOURS une soustraction,
    // jamais un pourcentage : la somme doit tomber juste au centime.
    window.vbCtRebuild = function(){
        var total = val('vb-ct-total'), dep = Math.min(val('vb-ct-deposit'), total);
        var body  = document.getElementById('vb-ct-schedule-body');
        body.innerHTML = ''; idx = 0;
        if (dep > 0)         addPreset('Acompte à la signature', dep, 'À la signature du présent contrat');
        if (total - dep > 0) addPreset('Solde à la livraison', +(total - dep).toFixed(2), 'À la mise en ligne du site');
        check();
    };

    function addPreset(label, amount, due){
        var i = idx++;
        document.getElementById('vb-ct-schedule-body').insertAdjacentHTML('beforeend',
            '<tr class="vb-ct-sched-row">'
          + '<td><input type="text" name="schedule['+i+'][label]" class="vb-input" value="'+label+'"></td>'
          + '<td><input type="number" name="schedule['+i+'][amount]" class="vb-input vb-ct-sched-amount" value="'+amount+'" step="0.01" min="0"></td>'
          + '<td><input type="text" name="schedule['+i+'][due]" class="vb-input" value="'+due+'"></td>'
          + '<td style="text-align:center"><input type="checkbox" name="schedule['+i+'][paid]" value="1"></td>'
          + '<td><button type="button" class="vb-action-btn vb-btn-delete" onclick="vbCtRemoveRow(this)"><span class="dashicons dashicons-trash"></span></button></td>'
          + '</tr>');
        bind();
    }

    // Contrôle permanent : un échéancier qui ne tombe pas juste ne doit
    // jamais partir chez un client.
    function check(){
        var total = val('vb-ct-total'), sum = 0;
        document.querySelectorAll('.vb-ct-sched-amount').forEach(function(el){ sum += parseFloat(el.value) || 0; });
        var el = document.getElementById('vb-ct-sched-check');
        if (!el) return;
        var diff = +(sum - total).toFixed(2);
        el.innerHTML = Math.abs(diff) < 0.01
            ? '<span class="green">✓ Échéancier équilibré (' + money(sum) + ' MAD)</span>'
            : '<span style="color:#dc2626">⚠️ Écart de ' + money(diff) + ' MAD avec le total</span>';
    }

    function bind(){
        document.querySelectorAll('.vb-ct-sched-amount').forEach(function(el){ el.oninput = check; });
    }

    // Choisir un projet recopie client + prix + maintenance.
    var projectSel = document.getElementById('vb-ct-project');
    if (projectSel) projectSel.addEventListener('change', function(){
        var o = this.selectedOptions[0];
        if (!o || this.value === '0') return;
        var set = function(id, v){ var e = document.getElementById(id); if (e && v) e.value = v; };
        set('vb-ct-name',  o.dataset.name);
        set('vb-ct-phone', o.dataset.phone);
        set('vb-ct-email', o.dataset.email);
        set('vb-ct-city',  o.dataset.city);
        set('vb-ct-type',  o.dataset.type);
        set('vb-ct-url',   o.dataset.url);
        set('vb-ct-total', o.dataset.prix);
        set('vb-ct-deposit', o.dataset.avance);
        if (o.dataset.track === '1') {
            var m = document.getElementById('vb-ct-maint');
            if (m) m.checked = true;
        }
        vbCtRebuild();
    });

    ['vb-ct-total','vb-ct-deposit'].forEach(function(id){
        var e = document.getElementById(id);
        if (e) e.addEventListener('input', check);
    });

    bind(); check();
})();
</script>


<?php elseif ( $action === 'view' ): /* ══════════════ APERÇU / PDF ══════════════ */
$body   = vb_contract_render( $contract );
$sched  = vb_contract_schedule_totals( $contract );
$recon  = vb_contract_reconciliation( $contract );
$locked = vb_contract_is_locked( $contract );
?>
<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?php if ( ! $locked ): ?>
    <a href="<?= admin_url('admin.php?page=vendbase-contracts&action=edit&id=' . $contract->id) ?>" class="vb-btn vb-btn-secondary"><span class="dashicons dashicons-edit"></span> Modifier</a>
    <a href="<?= wp_nonce_url(admin_url('admin.php?page=vendbase-contracts&action=sign&id=' . $contract->id), 'vb_contract_sign_' . $contract->id) ?>"
       class="vb-btn vb-btn-primary"
       onclick="return confirm('Marquer ce contrat comme signé ?\n\nLe projet lié sera mis à jour (date de démarrage, maintenance) et le contrat ne sera plus modifiable.')">
        <span class="dashicons dashicons-yes"></span> Marquer comme signé
    </a>
    <?php endif; ?>
    <button onclick="vbPrintContract()" class="vb-btn vb-btn-primary"><span class="dashicons dashicons-printer"></span> Imprimer / PDF</button>
    <a href="<?= admin_url('admin.php?page=vendbase-contracts') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
    <?= vb_contract_badge($contract->status) ?>
    <?php if ( $locked ): ?><span class="vb-sub">🔒 Verrouillé — document signé</span><?php endif; ?>
</div>

<?php if ( ! $sched['balanced'] && $sched['lines'] ): ?>
<div class="vb-notice" style="border-left:4px solid #dc2626;margin-bottom:12px">
    ⚠️ L'échéancier totalise <?= number_format($sched['scheduled'], 2, ',', ' ') ?> MAD pour un contrat de
    <?= number_format($sched['contract'], 2, ',', ' ') ?> MAD — écart de <?= number_format($sched['difference'], 2, ',', ' ') ?> MAD.
</div>
<?php endif; ?>

<?php if ( $recon && ! $recon['in_sync'] ): ?>
<div class="vb-card" style="margin-bottom:12px;border-left:4px solid #f59e0b">
    <div class="vb-card-header"><span>🔗 Rapprochement avec le projet #<?= intval($contract->project_id) ?></span></div>
    <div style="padding:12px 16px">
        <table class="vb-table">
            <tr><td>Montant</td>
                <td>contrat <strong><?= number_format($recon['contract_total'], 2, ',', ' ') ?></strong></td>
                <td>projet <strong><?= number_format($recon['project_total'], 2, ',', ' ') ?></strong></td>
                <td class="<?= abs($recon['total_diff']) < 0.01 ? 'green' : 'red' ?>"><?= number_format($recon['total_diff'], 2, ',', ' ') ?></td></tr>
            <tr><td>Maintenance / mois</td>
                <td>contrat <strong><?= number_format($recon['contract_maint'], 2, ',', ' ') ?></strong></td>
                <td>projet <strong><?= number_format($recon['project_maint'], 2, ',', ' ') ?></strong></td>
                <td class="<?= abs($recon['maint_diff']) < 0.01 ? 'green' : 'red' ?>"><?= number_format($recon['maint_diff'], 2, ',', ' ') ?></td></tr>
            <tr><td>Encaissé</td>
                <td>acompte contrat <strong><?= number_format($recon['contract_deposit'], 2, ',', ' ') ?></strong></td>
                <td>avance projet <strong><?= number_format($recon['project_paid'], 2, ',', ' ') ?></strong></td>
                <td>reste <strong><?= number_format($recon['remaining'], 2, ',', ' ') ?></strong></td></tr>
        </table>
        <p class="vb-sub">
            Un écart n'est pas forcément une erreur (avenant, geste commercial).
            Le bouton ci-dessous aligne le projet sur le contrat — jamais l'inverse, et jamais tout seul.
        </p>
        <button class="vb-btn vb-btn-secondary vb-btn-sm" id="vb-ct-apply" data-id="<?= intval($contract->id) ?>">
            Aligner le projet sur le contrat
        </button>
    </div>
</div>
<?php endif; ?>

<div class="vb-contract-paper" id="vb-contract-print">
    <div class="vb-ct-head">
        <div class="vb-ct-logo-block">
            <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-ct-logo" alt="">
            <div class="vb-ct-company">
                <strong><?= esc_html($provider['name']) ?></strong>
                <span><?= esc_html($provider['phone']) ?></span>
                <span><?= esc_html($provider['email']) ?></span>
                <span><?= esc_html($provider['website']) ?></span>
            </div>
        </div>
        <div class="vb-ct-meta">
            <div class="vb-ct-doc-type">CONTRAT</div>
            <table>
                <tr><td>Numéro</td><td><strong><?= esc_html($contract->number) ?></strong></td></tr>
                <tr><td>Date</td><td><?= $contract->issue_date ? date('d/m/Y', strtotime($contract->issue_date)) : '—' ?></td></tr>
                <?php if ( $contract->signed_date ): ?>
                <tr><td>Signé le</td><td><?= date('d/m/Y', strtotime($contract->signed_date)) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <h1 class="vb-ct-title"><?= esc_html($contract->title ?: 'Contrat de prestation') ?></h1>

    <div class="vb-ct-parties">
        <div>
            <div class="vb-ct-party-label">ENTRE LES SOUSSIGNÉS — LE PRESTATAIRE</div>
            <strong><?= esc_html($provider['name']) ?></strong><br>
            <?php if ($provider['legal']): ?>Représenté par <?= esc_html($provider['legal']) ?><br><?php endif; ?>
            <?php if ($provider['legal_id']): ?>ICE / RC : <?= esc_html($provider['legal_id']) ?><br><?php endif; ?>
            <?php if ($provider['address']): ?><?= esc_html($provider['address']) ?><br><?php endif; ?>
            <?= esc_html($provider['city']) ?><br>
            <?= esc_html($provider['phone']) ?><br>
            <?= esc_html($provider['email']) ?>
        </div>
        <div>
            <div class="vb-ct-party-label">ET — LE CLIENT</div>
            <strong><?= esc_html($contract->client_name) ?></strong><br>
            <?php if ($contract->client_legal_id): ?>CIN / ICE : <?= esc_html($contract->client_legal_id) ?><br><?php endif; ?>
            <?php if ($contract->client_city): ?><?= esc_html($contract->client_city) ?><br><?php endif; ?>
            <?php if ($contract->client_phone): ?><?= esc_html($contract->client_phone) ?><br><?php endif; ?>
            <?php if ($contract->client_email): ?><?= esc_html($contract->client_email) ?><?php endif; ?>
        </div>
    </div>

    <div class="vb-ct-body"><?= nl2br( esc_html( $body ) ) ?></div>

    <div class="vb-ct-signatures">
        <div class="vb-ct-sign-box">
            <div class="vb-ct-sign-label">LE PRESTATAIRE</div>
            <div class="vb-ct-sign-name"><?= esc_html($provider['legal'] ?: $provider['name']) ?></div>
            <div class="vb-ct-sign-hint">Lu et approuvé, signature</div>
            <div class="vb-ct-sign-space"></div>
        </div>
        <div class="vb-ct-sign-box">
            <div class="vb-ct-sign-label">LE CLIENT</div>
            <div class="vb-ct-sign-name"><?= esc_html($contract->client_name) ?></div>
            <div class="vb-ct-sign-hint">Lu et approuvé, signature</div>
            <div class="vb-ct-sign-space"></div>
        </div>
    </div>

    <div class="vb-ct-foot">
        Fait à <?= esc_html($contract->client_city ?: $provider['city']) ?>,
        le <?= $contract->issue_date ? date('d/m/Y', strtotime($contract->issue_date)) : date('d/m/Y') ?>,
        en deux exemplaires originaux.
        &nbsp;·&nbsp; <?= esc_html($contract->number) ?>
    </div>
</div>

<script>
function vbPrintContract() {
    var content = document.getElementById('vb-contract-print').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title><?= esc_js('Contrat ' . $contract->number . ' — ' . $contract->client_name) ?></title><style>'
        + '@page{size:A4;margin:18mm 16mm}'
        + 'body{font-family:Georgia,"Times New Roman",serif;font-size:11.5px;line-height:1.65;color:#1e293b;max-width:820px;margin:0 auto;padding:20px}'
        + '.vb-ct-head{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:14px;border-bottom:2px solid #6366f1;margin-bottom:22px}'
        + '.vb-ct-logo{height:44px;object-fit:contain}'
        + '.vb-ct-logo-block{display:flex;align-items:center;gap:14px}'
        + '.vb-ct-company{display:flex;flex-direction:column;font-size:10px;color:#64748b}'
        + '.vb-ct-company strong{color:#1e293b;font-size:13px}'
        + '.vb-ct-doc-type{font-size:20px;font-weight:700;color:#6366f1;letter-spacing:3px;margin-bottom:8px;text-align:right}'
        + '.vb-ct-meta table{font-size:10px}.vb-ct-meta td{padding:2px 6px}.vb-ct-meta td:first-child{color:#64748b}'
        + '.vb-ct-title{font-size:17px;text-align:center;margin:0 0 22px;text-transform:uppercase;letter-spacing:1px}'
        + '.vb-ct-parties{display:flex;gap:32px;margin-bottom:24px;font-size:11px;line-height:1.6}'
        + '.vb-ct-parties>div{flex:1;background:#f8fafc;border-left:3px solid #6366f1;padding:10px 14px}'
        + '.vb-ct-party-label{font-size:8.5px;font-weight:700;color:#6366f1;letter-spacing:1.5px;margin-bottom:6px}'
        + '.vb-ct-body{text-align:justify;white-space:normal}'
        + '.vb-ct-signatures{display:flex;gap:40px;margin-top:36px;page-break-inside:avoid}'
        + '.vb-ct-sign-box{flex:1;border:1px solid #cbd5e1;border-radius:6px;padding:12px 14px}'
        + '.vb-ct-sign-label{font-size:8.5px;font-weight:700;color:#6366f1;letter-spacing:1.5px}'
        + '.vb-ct-sign-name{font-size:12px;font-weight:700;margin:4px 0}'
        + '.vb-ct-sign-hint{font-size:9px;color:#94a3b8}'
        + '.vb-ct-sign-space{height:70px}'
        + '.vb-ct-foot{margin-top:22px;font-size:9.5px;color:#94a3b8;text-align:center;border-top:1px solid #e2e8f0;padding-top:10px}'
        + '</style></head><body>' + content + '</body></html>');
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); }, 500);
}

jQuery(function($){
    $('#vb-ct-apply').on('click', function(){
        var $b = $(this);
        if (!confirm('Aligner le prix et la maintenance du projet sur ce contrat ?')) return;
        $b.prop('disabled', true).text('…');
        $.post(VB.ajax, { action:'vb_contract_apply_to_project', nonce:VB.nonce, id:$b.data('id') }, function(r){
            if (r.success) location.reload();
            else { alert(r.data || 'Erreur'); $b.prop('disabled', false).text('Aligner le projet sur le contrat'); }
        });
    });
});
</script>


<?php elseif ( $action === 'templates' ): /* ══════════════ MODÈLES ══════════════ */ ?>
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>🏢 Tes coordonnées (partie « PRESTATAIRE »)</span></div>
    <div style="padding:16px" class="vb-form-row">
        <?php foreach ( [ 'name'=>'Nom commercial','legal'=>'Représentant légal','legal_id'=>'ICE / RC','address'=>'Adresse','city'=>'Ville','phone'=>'Téléphone','email'=>'Email','website'=>'Site web' ] as $k => $lbl ): ?>
        <div class="vb-form-group">
            <label class="vb-label"><?= esc_html($lbl) ?></label>
            <input type="text" class="vb-input vb-ct-provider" data-key="<?= esc_attr($k) ?>" value="<?= esc_attr($provider[$k]) ?>">
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php foreach ( $templates as $key => $tpl ): ?>
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span><?= $tpl['icon'] ?> <?= esc_html($tpl['label']) ?></span></div>
    <div style="padding:16px">
        <div class="vb-form-group">
            <label class="vb-label">Titre</label>
            <input type="text" class="vb-input vb-ct-tpl" data-key="<?= esc_attr($key) ?>" data-field="title" value="<?= esc_attr($tpl['title']) ?>">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Livrables par défaut</label>
            <textarea class="vb-input vb-textarea vb-ct-tpl" data-key="<?= esc_attr($key) ?>" data-field="scope" rows="6"><?= esc_textarea($tpl['scope']) ?></textarea>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Corps du contrat</label>
            <textarea class="vb-input vb-textarea vb-ct-tpl" data-key="<?= esc_attr($key) ?>" data-field="body" rows="18" style="font-family:monospace;font-size:12px"><?= esc_textarea($tpl['body']) ?></textarea>
            <p class="vb-sub">Vider un champ restaure le modèle d'usine.</p>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>🏷️ Marqueurs disponibles</span></div>
    <div style="padding:16px">
        <p class="vb-sub">
            Cliquer pour copier. Les blocs <code>{{#maintenance}}…{{/maintenance}}</code>,
            <code>{{#late_penalty}}…{{/late_penalty}}</code> et
            <code>{{#custom_clauses}}…{{/custom_clauses}}</code> ne s'affichent que si la clause est active.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
        <?php foreach ( vb_contract_placeholder_help() as $ph ): ?>
            <code class="vb-ct-ph" style="cursor:pointer;background:#f1f5f9;padding:3px 8px;border-radius:4px;font-size:11px"><?= esc_html($ph) ?></code>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<div style="margin-top:20px;display:flex;gap:12px">
    <button class="vb-btn vb-btn-primary vb-btn-lg" id="vb-ct-save-tpl"><span class="dashicons dashicons-saved"></span> Enregistrer les modèles</button>
    <button class="vb-btn vb-btn-ghost" id="vb-ct-reset-tpl">↺ Restaurer les modèles d'usine</button>
    <a href="<?= admin_url('admin.php?page=vendbase-contracts') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
</div>

<script>
jQuery(function($){
    $('.vb-ct-ph').on('click', function(){
        navigator.clipboard && navigator.clipboard.writeText($(this).text());
        $(this).css('background', '#d1fae5');
        setTimeout(function(el){ $(el).css('background', '#f1f5f9'); }, 600, this);
    });

    $('#vb-ct-save-tpl').on('click', function(){
        var $b = $(this).prop('disabled', true), data = { action:'vb_save_contract_templates', nonce:VB.nonce, templates:{}, provider:{} };
        $('.vb-ct-tpl').each(function(){
            var k = $(this).data('key'), f = $(this).data('field');
            data.templates[k] = data.templates[k] || {};
            data.templates[k][f] = $(this).val();
        });
        $('.vb-ct-provider').each(function(){ data.provider[$(this).data('key')] = $(this).val(); });

        $.post(VB.ajax, data, function(r){
            $b.prop('disabled', false);
            if (r.success) { alert('✅ Modèles enregistrés.'); location.reload(); }
            else alert(r.data || 'Erreur');
        });
    });

    $('#vb-ct-reset-tpl').on('click', function(){
        if (!confirm('Restaurer les modèles d\'usine ?\n\nTes réécritures seront perdues. Tes coordonnées sont conservées.')) return;
        $.post(VB.ajax, { action:'vb_reset_contract_templates', nonce:VB.nonce }, function(){ location.reload(); });
    });
});
</script>

<?php endif; ?>

</div><!-- .vb-wrap -->
