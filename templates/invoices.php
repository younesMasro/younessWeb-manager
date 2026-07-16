<?php
if ( ! defined('ABSPATH') ) exit;

global $wpdb;
$table_inv = $wpdb->prefix . 'vb_invoices';

// Ensure table exists
$wpdb->query("CREATE TABLE IF NOT EXISTS $table_inv (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    type         VARCHAR(10) NOT NULL DEFAULT 'invoice',
    number       VARCHAR(50) NOT NULL,
    project_id   BIGINT(20) UNSIGNED DEFAULT 0,
    client_name  VARCHAR(200) NOT NULL,
    client_phone VARCHAR(50)  DEFAULT '',
    client_email VARCHAR(200) DEFAULT '',
    client_city  VARCHAR(100) DEFAULT '',
    items        LONGTEXT     DEFAULT '',
    subtotal     DECIMAL(10,2) DEFAULT 0,
    tva          DECIMAL(5,2)  DEFAULT 0,
    total        DECIMAL(10,2) DEFAULT 0,
    status       VARCHAR(30)  DEFAULT 'draft',
    issue_date   DATE         DEFAULT NULL,
    due_date     DATE         DEFAULT NULL,
    notes        TEXT         DEFAULT '',
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) " . $wpdb->get_charset_collate() . ";");

$action = sanitize_text_field($_GET['action'] ?? 'list');
$inv_id = intval($_GET['inv_id'] ?? 0);

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vb_inv_nonce'])) {
    if (!wp_verify_nonce($_POST['vb_inv_nonce'], 'vb_invoice_save')) wp_die('Security check failed');

    $items_raw = $_POST['items'] ?? [];
    $items_clean = [];
    $subtotal = 0;
    foreach ($items_raw as $item) {
        $qty   = floatval($item['qty'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $line  = $qty * $price;
        $subtotal += $line;
        $items_clean[] = [
            'desc'  => sanitize_text_field($item['desc'] ?? ''),
            'qty'   => $qty,
            'price' => $price,
            'total' => $line,
        ];
    }
    $tva   = floatval($_POST['tva'] ?? 0);
    $total = $subtotal + ($subtotal * $tva / 100);

    $data = [
        'type'        => sanitize_text_field($_POST['type'] ?? 'invoice'),
        'number'      => sanitize_text_field($_POST['number'] ?? ''),
        'project_id'  => intval($_POST['project_id'] ?? 0),
        'client_name' => sanitize_text_field($_POST['client_name'] ?? ''),
        'client_phone'=> sanitize_text_field($_POST['client_phone'] ?? ''),
        'client_email'=> sanitize_email($_POST['client_email'] ?? ''),
        'client_city' => sanitize_text_field($_POST['client_city'] ?? ''),
        'items'       => json_encode($items_clean),
        'subtotal'    => $subtotal,
        'tva'         => $tva,
        'total'       => $total,
        'status'      => sanitize_text_field($_POST['status'] ?? 'draft'),
        'issue_date'  => sanitize_text_field($_POST['issue_date'] ?? ''),
        'due_date'    => sanitize_text_field($_POST['due_date'] ?? ''),
        'notes'       => sanitize_textarea_field($_POST['notes'] ?? ''),
    ];

    $edit_id = intval($_POST['inv_id'] ?? 0);
    if ($edit_id) {
        $wpdb->update($table_inv, $data, ['id' => $edit_id]);
        $inv_id = $edit_id;
    } else {
        $wpdb->insert($table_inv, $data);
        $inv_id = $wpdb->insert_id;
    }
    wp_redirect(admin_url('admin.php?page=vendbase-invoices&action=view&inv_id=' . $inv_id . '&saved=1'));
    exit;
}

// Handle delete
if ($action === 'delete' && $inv_id) {
    check_admin_referer('vb_inv_delete_' . $inv_id);
    $wpdb->delete($table_inv, ['id' => $inv_id]);
    wp_redirect(admin_url('admin.php?page=vendbase-invoices&deleted=1'));
    exit;
}

// Load invoice for edit/view
$inv = null;
if (($action === 'edit' || $action === 'view') && $inv_id) {
    $inv = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_inv WHERE id = %d", $inv_id));
}

// Load projects for dropdown
$projects = vb_get_projects(['limit' => 200]);

// Generate next number
function vb_next_number($type) {
    global $wpdb;
    $table_inv = $wpdb->prefix . 'vb_invoices';
    $prefix = $type === 'devis' ? 'DEV' : 'FAC';
    $year   = date('Y');
    $count  = intval($wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_inv WHERE type=%s AND YEAR(created_at)=%d", $type, $year
    )));
    return $prefix . '-' . $year . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

$status_labels = [
    'draft'   => ['label'=>'Brouillon', 'class'=>'vb-badge-blue'],
    'sent'    => ['label'=>'Envoyé',    'class'=>'vb-badge-orange'],
    'paid'    => ['label'=>'Payé',      'class'=>'vb-badge-green'],
    'cancelled'=>['label'=>'Annulé',    'class'=>'vb-badge-red'],
    'accepted'=> ['label'=>'Accepté',   'class'=>'vb-badge-green'],
    'refused' => ['label'=>'Refusé',    'class'=>'vb-badge-red'],
];
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">Factures & Devis</h1>
            <span class="vb-page-sub">Gestion des documents commerciaux</span>
        </div>
    </div>
    <div class="vb-header-right" style="gap:8px">
        <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=new&type=devis') ?>" class="vb-btn vb-btn-secondary">
            <span class="dashicons dashicons-media-text"></span> Nouveau Devis
        </a>
        <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=new&type=invoice') ?>" class="vb-btn vb-btn-primary">
            <span class="dashicons dashicons-media-document"></span> Nouvelle Facture
        </a>
    </div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="vb-notice vb-notice-success">✅ Document enregistré avec succès.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="vb-notice vb-notice-success">🗑️ Document supprimé.</div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- ══ LIST ══ -->
<?php
$all = $wpdb->get_results("SELECT * FROM $table_inv ORDER BY created_at DESC LIMIT 200");
?>
<div class="vb-card" style="margin-top:16px">
<table class="vb-table vb-table-full">
    <thead>
        <tr>
            <th>#</th>
            <th>Type</th>
            <th>Numéro</th>
            <th>Client</th>
            <th>Total</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($all): foreach ($all as $row):
        $type_label = $row->type === 'devis' ? '📋 Devis' : '🧾 Facture';
        $sl = $status_labels[$row->status] ?? ['label'=>$row->status,'class'=>'vb-badge-blue'];
    ?>
    <tr>
        <td><?= $row->id ?></td>
        <td><span class="vb-type-badge"><?= $type_label ?></span></td>
        <td><strong><?= esc_html($row->number) ?></strong></td>
        <td>
            <strong><?= esc_html($row->client_name) ?></strong>
            <?php if ($row->client_phone): ?><div class="vb-sub"><?= esc_html($row->client_phone) ?></div><?php endif; ?>
        </td>
        <td><strong><?= number_format($row->total, 2, ',', ' ') ?> MAD</strong></td>
        <td><span class="vb-badge <?= esc_attr($sl['class']) ?>"><?= esc_html($sl['label']) ?></span></td>
        <td><?= date('d/m/Y', strtotime($row->created_at)) ?></td>
        <td class="vb-actions-cell">
            <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=view&inv_id='.$row->id) ?>" class="vb-action-btn" title="Voir"><span class="dashicons dashicons-visibility"></span></a>
            <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=edit&inv_id='.$row->id) ?>" class="vb-action-btn vb-btn-edit" title="Modifier"><span class="dashicons dashicons-edit"></span></a>
            <a href="<?= wp_nonce_url(admin_url('admin.php?page=vendbase-invoices&action=delete&inv_id='.$row->id), 'vb_inv_delete_'.$row->id) ?>" class="vb-action-btn vb-btn-delete" title="Supprimer" onclick="return confirm('Supprimer ce document?')"><span class="dashicons dashicons-trash"></span></a>
        </td>
    </tr>
    <?php endforeach; else: ?>
    <tr><td colspan="8" class="vb-empty">Aucun document. Créez votre première facture ou devis →</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
<!-- ══ FORM ══ -->
<?php
$type_default = sanitize_text_field($_GET['type'] ?? 'invoice');
$f = $inv ? $inv : (object)[
    'id'=>'','type'=>$type_default,'number'=>vb_next_number($type_default),
    'project_id'=>0,'client_name'=>'','client_phone'=>'','client_email'=>'','client_city'=>'',
    'items'=>'[]','subtotal'=>0,'tva'=>0,'total'=>0,'status'=>'draft',
    'issue_date'=>date('Y-m-d'),'due_date'=>'','notes'=>'',
];
$items_arr = json_decode($f->items ?: '[]', true) ?: [['desc'=>'','qty'=>1,'price'=>0,'total'=>0]];
?>
<form method="post" id="vb-inv-form">
    <?= wp_nonce_field('vb_invoice_save', 'vb_inv_nonce', true, false) ?>
    <input type="hidden" name="inv_id" value="<?= intval($f->id) ?>">

<div class="vb-form-grid">
<div class="vb-form-col">

<!-- Type & Number -->
<div class="vb-card">
    <div class="vb-card-header"><span>📄 Document</span></div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Type</label>
            <select name="type" class="vb-select" id="vb-inv-type" onchange="updateInvNumber(this.value)">
                <option value="invoice" <?= selected($f->type,'invoice',false) ?>>🧾 Facture</option>
                <option value="devis"   <?= selected($f->type,'devis',false) ?>>📋 Devis</option>
            </select>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Numéro</label>
            <input type="text" name="number" id="vb-inv-number" class="vb-input" value="<?= esc_attr($f->number) ?>" required>
        </div>
    </div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Date d'émission</label>
            <input type="date" name="issue_date" class="vb-input" value="<?= esc_attr($f->issue_date) ?>">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Date d'échéance</label>
            <input type="date" name="due_date" class="vb-input" value="<?= esc_attr($f->due_date) ?>">
        </div>
    </div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Statut</label>
            <select name="status" class="vb-select">
                <?php
                $inv_statuses = $f->type === 'devis'
                    ? ['draft'=>'Brouillon','sent'=>'Envoyé','accepted'=>'Accepté','refused'=>'Refusé']
                    : ['draft'=>'Brouillon','sent'=>'Envoyé','paid'=>'Payé','cancelled'=>'Annulé'];
                foreach ($inv_statuses as $sk => $sv): ?>
                <option value="<?= $sk ?>" <?= selected($f->status,$sk,false) ?>><?= $sv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Projet lié</label>
            <select name="project_id" class="vb-select" onchange="fillClientFromProject(this.value)">
                <option value="0">— Aucun —</option>
                <?php foreach ($projects as $pr): ?>
                <option value="<?= $pr->id ?>" <?= selected($f->project_id,$pr->id,false) ?>
                    data-name="<?= esc_attr($pr->client_name) ?>"
                    data-phone="<?= esc_attr($pr->client_phone) ?>"
                    data-email="<?= esc_attr($pr->client_email) ?>"
                    data-city="<?= esc_attr($pr->client_city) ?>">
                    #<?= $pr->id ?> — <?= esc_html($pr->client_name) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Client -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>👤 Client</span></div>
    <div class="vb-form-group">
        <label class="vb-label">Nom *</label>
        <input type="text" name="client_name" id="vb-inv-client-name" class="vb-input" value="<?= esc_attr($f->client_name) ?>" required>
    </div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Téléphone</label>
            <input type="tel" name="client_phone" id="vb-inv-client-phone" class="vb-input" value="<?= esc_attr($f->client_phone) ?>">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Email</label>
            <input type="email" name="client_email" id="vb-inv-client-email" class="vb-input" value="<?= esc_attr($f->client_email) ?>">
        </div>
    </div>
    <div class="vb-form-group">
        <label class="vb-label">Ville</label>
        <input type="text" name="client_city" id="vb-inv-client-city" class="vb-input" value="<?= esc_attr($f->client_city) ?>">
    </div>
</div>

<!-- Notes -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>📝 Notes</span></div>
    <textarea name="notes" class="vb-input vb-textarea" rows="4" placeholder="Conditions de paiement, remarques..."><?= esc_textarea($f->notes) ?></textarea>
</div>

</div><!-- col A -->

<div class="vb-form-col">

<!-- Items -->
<div class="vb-card">
    <div class="vb-card-header">
        <span>📦 Prestations / Articles</span>
        <button type="button" class="vb-btn vb-btn-secondary" onclick="addInvItem()" style="padding:4px 10px;font-size:12px">+ Ajouter ligne</button>
    </div>
    <table class="vb-table" id="vb-items-table">
        <thead>
            <tr>
                <th style="width:45%">Description</th>
                <th style="width:12%">Qté</th>
                <th style="width:18%">Prix unit. (MAD)</th>
                <th style="width:15%">Total</th>
                <th style="width:10%"></th>
            </tr>
        </thead>
        <tbody id="vb-items-body">
        <?php foreach ($items_arr as $idx => $item): ?>
        <tr class="vb-item-row">
            <td><input type="text" name="items[<?= $idx ?>][desc]" class="vb-input" value="<?= esc_attr($item['desc']) ?>" placeholder="Création site vitrine..."></td>
            <td><input type="number" name="items[<?= $idx ?>][qty]" class="vb-input vb-item-qty" value="<?= esc_attr($item['qty']) ?>" min="1" step="0.5"></td>
            <td><input type="number" name="items[<?= $idx ?>][price]" class="vb-input vb-item-price" value="<?= esc_attr($item['price']) ?>" min="0" step="0.01"></td>
            <td class="vb-item-total green"><strong><?= number_format($item['total'], 2, ',', ' ') ?></strong></td>
            <td><button type="button" class="vb-action-btn vb-btn-delete" onclick="removeInvItem(this)" title="Supprimer"><span class="dashicons dashicons-trash"></span></button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="vb-inv-totals">
        <div class="vb-inv-total-row">
            <span>Sous-total</span>
            <strong id="vb-subtotal"><?= number_format($f->subtotal, 2, ',', ' ') ?> MAD</strong>
        </div>
        <div class="vb-inv-total-row">
            <span>TVA (%)</span>
            <input type="number" name="tva" id="vb-tva" class="vb-input" value="<?= esc_attr($f->tva) ?>" min="0" max="100" step="0.5" style="width:80px;text-align:right" oninput="calcTotals()">
        </div>
        <div class="vb-inv-total-row vb-inv-grand-total">
            <span>TOTAL TTC</span>
            <strong id="vb-grand-total"><?= number_format($f->total, 2, ',', ' ') ?> MAD</strong>
        </div>
    </div>
</div>

<!-- Save -->
<div style="margin-top:20px;display:flex;gap:12px">
    <button type="submit" class="vb-btn vb-btn-primary vb-btn-lg">
        <span class="dashicons dashicons-saved"></span> Enregistrer
    </button>
    <?php if ($inv): ?>
    <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=view&inv_id='.$inv->id) ?>" class="vb-btn vb-btn-secondary">
        <span class="dashicons dashicons-visibility"></span> Aperçu
    </a>
    <?php endif; ?>
    <a href="<?= admin_url('admin.php?page=vendbase-invoices') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
</div>

</div><!-- col B -->
</div><!-- grid -->
</form>

<?php elseif ($action === 'view' && $inv): ?>
<!-- ══ VIEW / PRINT ══ -->
<?php
$items_arr = json_decode($inv->items ?: '[]', true) ?: [];
$sl = $status_labels[$inv->status] ?? ['label'=>$inv->status,'class'=>'vb-badge-blue'];
$type_label = $inv->type === 'devis' ? 'DEVIS' : 'FACTURE';
?>
<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
    <a href="<?= admin_url('admin.php?page=vendbase-invoices&action=edit&inv_id='.$inv->id) ?>" class="vb-btn vb-btn-secondary"><span class="dashicons dashicons-edit"></span> Modifier</a>
    <button onclick="printInvoice()" class="vb-btn vb-btn-primary"><span class="dashicons dashicons-printer"></span> Imprimer / PDF</button>
    <a href="<?= admin_url('admin.php?page=vendbase-invoices') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
    <span class="vb-badge <?= esc_attr($sl['class']) ?>"><?= esc_html($sl['label']) ?></span>
</div>

<!-- Invoice Preview -->
<div class="vb-invoice-preview" id="vb-invoice-print">
    <div class="vb-inv-header">
        <div class="vb-inv-logo-block">
            <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-inv-logo" alt="YounessWeb">
            <div class="vb-inv-company">
                <strong>YounessWeb</strong>
                <span>+212 774-654464</span>
                <span>younes.masroure@gmail.com</span>
                <span><a href="https://younessweb.me" target="_blank">younessweb.me</a></span>
            </div>
        </div>
        <div class="vb-inv-meta">
            <div class="vb-inv-type-badge"><?= $type_label ?></div>
            <table class="vb-inv-meta-table">
                <tr><td>Numéro</td><td><strong><?= esc_html($inv->number) ?></strong></td></tr>
                <tr><td>Date</td><td><?= $inv->issue_date ? date('d/m/Y', strtotime($inv->issue_date)) : '—' ?></td></tr>
                <?php if ($inv->due_date): ?>
                <tr><td>Échéance</td><td><?= date('d/m/Y', strtotime($inv->due_date)) ?></td></tr>
                <?php endif; ?>
                <tr><td>Statut</td><td><span class="vb-badge <?= esc_attr($sl['class']) ?>"><?= esc_html($sl['label']) ?></span></td></tr>
            </table>
        </div>
    </div>

    <div class="vb-inv-parties">
        <div class="vb-inv-from">
            <div class="vb-inv-party-label">DE</div>
            <strong>YounessWeb Manager</strong><br>
            +212 774-654464<br>
            younes.masroure@gmail.com<br>
            <a href="https://younessweb.me">younessweb.me</a>
        </div>
        <div class="vb-inv-to">
            <div class="vb-inv-party-label">POUR</div>
            <strong><?= esc_html($inv->client_name) ?></strong><br>
            <?php if ($inv->client_phone): ?><?= esc_html($inv->client_phone) ?><br><?php endif; ?>
            <?php if ($inv->client_email): ?><?= esc_html($inv->client_email) ?><br><?php endif; ?>
            <?php if ($inv->client_city): ?><?= esc_html($inv->client_city) ?><?php endif; ?>
        </div>
    </div>

    <table class="vb-inv-items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align:center">Qté</th>
                <th style="text-align:right">Prix unit.</th>
                <th style="text-align:right">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items_arr as $item): ?>
        <tr>
            <td><?= esc_html($item['desc']) ?></td>
            <td style="text-align:center"><?= esc_html($item['qty']) ?></td>
            <td style="text-align:right"><?= number_format($item['price'], 2, ',', ' ') ?> MAD</td>
            <td style="text-align:right"><strong><?= number_format($item['total'], 2, ',', ' ') ?> MAD</strong></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="vb-inv-summary">
        <div class="vb-inv-notes">
            <?php if ($inv->notes): ?>
            <div class="vb-inv-notes-box">
                <strong>Notes :</strong><br>
                <?= nl2br(esc_html($inv->notes)) ?>
            </div>
            <?php endif; ?>
            <div class="vb-inv-footer-text">
                Merci pour votre confiance — <strong>YounessWeb</strong> · younessweb.me
            </div>
        </div>
        <div class="vb-inv-totals-box">
            <div class="vb-inv-total-line"><span>Sous-total</span><span><?= number_format($inv->subtotal, 2, ',', ' ') ?> MAD</span></div>
            <?php if ($inv->tva > 0): ?>
            <div class="vb-inv-total-line"><span>TVA (<?= $inv->tva ?>%)</span><span><?= number_format($inv->subtotal * $inv->tva / 100, 2, ',', ' ') ?> MAD</span></div>
            <?php endif; ?>
            <div class="vb-inv-total-line vb-inv-grand"><span>TOTAL TTC</span><span><?= number_format($inv->total, 2, ',', ' ') ?> MAD</span></div>
        </div>
    </div>
</div>

<script>
function printInvoice() {
    var content = document.getElementById('vb-invoice-print').innerHTML;
    var win = window.open('', '_blank');
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title><?= esc_js($type_label . " " . $inv->number) ?></title><style>'
        + 'body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;padding:30px;max-width:800px;margin:0 auto}'
        + '.vb-inv-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;padding-bottom:20px;border-bottom:2px solid #6366f1}'
        + '.vb-inv-logo{height:50px;object-fit:contain}'
        + '.vb-inv-logo-block{display:flex;align-items:center;gap:16px}'
        + '.vb-inv-company{display:flex;flex-direction:column;font-size:11px;color:#64748b}'
        + '.vb-inv-company strong{color:#1e293b;font-size:14px}'
        + '.vb-inv-type-badge{font-size:22px;font-weight:900;color:#6366f1;letter-spacing:2px;margin-bottom:10px}'
        + '.vb-inv-meta-table td{padding:3px 8px;font-size:11px}'
        + '.vb-inv-meta-table td:first-child{color:#64748b}'
        + '.vb-inv-parties{display:flex;gap:40px;margin-bottom:28px}'
        + '.vb-inv-party-label{font-size:9px;font-weight:700;color:#6366f1;letter-spacing:2px;margin-bottom:6px}'
        + '.vb-inv-from,.vb-inv-to{font-size:12px;line-height:1.7}'
        + '.vb-inv-items-table{width:100%;border-collapse:collapse;margin-bottom:24px}'
        + '.vb-inv-items-table th{background:#6366f1;color:#fff;padding:8px 12px;font-size:11px;text-align:left}'
        + '.vb-inv-items-table td{padding:8px 12px;border-bottom:1px solid #e2e8f0;font-size:11px}'
        + '.vb-inv-items-table tr:nth-child(even) td{background:#f8fafc}'
        + '.vb-inv-summary{display:flex;justify-content:space-between;align-items:flex-start;gap:20px}'
        + '.vb-inv-totals-box{min-width:240px}'
        + '.vb-inv-total-line{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12px}'
        + '.vb-inv-grand{font-size:15px;font-weight:700;color:#6366f1;border-top:2px solid #6366f1;margin-top:4px}'
        + '.vb-inv-notes-box{background:#f8fafc;border-left:3px solid #6366f1;padding:10px 14px;font-size:11px;margin-bottom:12px}'
        + '.vb-inv-footer-text{font-size:10px;color:#94a3b8;margin-top:8px}'
        + '.vb-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600}'
        + '.vb-badge-green{background:#d1fae5;color:#065f46}'
        + '.vb-badge-blue{background:#e0e7ff;color:#3730a3}'
        + '.vb-badge-orange{background:#fef3c7;color:#92400e}'
        + '.vb-badge-red{background:#fee2e2;color:#991b1b}'
        + 'a{color:#6366f1}'
        + '@media print{body{padding:10px}}'
        + '</style></head><body>' + content + '</body></html>');
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); }, 500);
}
</script>

<?php endif; ?>

</div><!-- .vb-wrap -->

<script>
var vbInvItemCount = <?= count($items_arr) ?>;

function addInvItem() {
    var idx = vbInvItemCount++;
    var row = '<tr class="vb-item-row">'
        + '<td><input type="text" name="items['+idx+'][desc]" class="vb-input" placeholder="Description..."></td>'
        + '<td><input type="number" name="items['+idx+'][qty]" class="vb-input vb-item-qty" value="1" min="1" step="0.5"></td>'
        + '<td><input type="number" name="items['+idx+'][price]" class="vb-input vb-item-price" value="0" min="0" step="0.01"></td>'
        + '<td class="vb-item-total green"><strong>0,00</strong></td>'
        + '<td><button type="button" class="vb-action-btn vb-btn-delete" onclick="removeInvItem(this)"><span class="dashicons dashicons-trash"></span></button></td>'
        + '</tr>';
    document.getElementById('vb-items-body').insertAdjacentHTML('beforeend', row);
    bindItemEvents();
}

function removeInvItem(btn) {
    btn.closest('tr').remove();
    calcTotals();
}

function calcTotals() {
    var subtotal = 0;
    document.querySelectorAll('.vb-item-row').forEach(function(row) {
        var qty   = parseFloat(row.querySelector('.vb-item-qty')?.value) || 0;
        var price = parseFloat(row.querySelector('.vb-item-price')?.value) || 0;
        var total = qty * price;
        subtotal += total;
        var td = row.querySelector('.vb-item-total strong');
        if (td) td.textContent = total.toLocaleString('fr-MA', {minimumFractionDigits:2});
    });
    var tva = parseFloat(document.getElementById('vb-tva')?.value) || 0;
    var grand = subtotal + (subtotal * tva / 100);
    var stEl = document.getElementById('vb-subtotal');
    var gtEl = document.getElementById('vb-grand-total');
    if (stEl) stEl.textContent = subtotal.toLocaleString('fr-MA', {minimumFractionDigits:2}) + ' MAD';
    if (gtEl) gtEl.textContent = grand.toLocaleString('fr-MA', {minimumFractionDigits:2}) + ' MAD';
}

function bindItemEvents() {
    document.querySelectorAll('.vb-item-qty, .vb-item-price').forEach(function(el) {
        el.oninput = calcTotals;
    });
}

function fillClientFromProject(pid) {
    var sel = document.querySelector('select[name=project_id]');
    var opt = sel.querySelector('option[value="'+pid+'"]');
    if (!opt || pid == 0) return;
    var n = document.getElementById('vb-inv-client-name');
    var p = document.getElementById('vb-inv-client-phone');
    var e = document.getElementById('vb-inv-client-email');
    var c = document.getElementById('vb-inv-client-city');
    if (n && !n.value) n.value = opt.dataset.name || '';
    if (p && !p.value) p.value = opt.dataset.phone || '';
    if (e && !e.value) e.value = opt.dataset.email || '';
    if (c && !c.value) c.value = opt.dataset.city || '';
}

function updateInvNumber(type) {
    // Just update prefix hint — actual number generated server-side
    var num = document.getElementById('vb-inv-number');
    if (num && num.value) {
        num.value = num.value.replace(/^(FAC|DEV)/, type === 'devis' ? 'DEV' : 'FAC');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    bindItemEvents();
    calcTotals();
});
</script>
