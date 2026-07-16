<?php
if ( ! defined('ABSPATH') ) exit;

// Handle search/filter from GET
$search    = sanitize_text_field($_GET['s']      ?? '');
$status    = sanitize_text_field($_GET['status'] ?? '');
$month     = sanitize_text_field($_GET['month']  ?? '');
$year      = sanitize_text_field($_GET['year']   ?? '');
$site_type = sanitize_text_field($_GET['type']   ?? '');
$tracking  = sanitize_text_field($_GET['tracking'] ?? '');

$projects = vb_get_projects([
    'search'    => $search,
    'status'    => $status,
    'month'     => $month,
    'year'      => $year,
    'site_type' => $site_type,
    'tracking'  => $tracking,
    'limit'     => 100,
]);

$status_labels = [
    'in_progress' => ['label' => 'En cours',  'class' => 'vb-badge-blue'],
    'completed'   => ['label' => 'Terminé',   'class' => 'vb-badge-green'],
    'paused'      => ['label' => 'En pause',  'class' => 'vb-badge-orange'],
    'cancelled'   => ['label' => 'Annulé',    'class' => 'vb-badge-red'],
];

$months_fr = [''=> 'Tous','01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
    '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">Projets</h1>
            <span class="vb-page-sub"><?= count($projects) ?> projet(s) trouvé(s)</span>
        </div>
    </div>
    <div class="vb-header-right" style="gap:8px;flex-wrap:wrap;">
        <button id="vb-export-csv" class="vb-btn vb-btn-secondary" title="Exporter CSV">
            <span class="dashicons dashicons-download"></span> Export CSV
        </button>
        <button id="vb-export-pdf" class="vb-btn vb-btn-secondary" title="Rapport PDF">
            <span class="dashicons dashicons-media-document"></span> Rapport PDF
        </button>
        <label class="vb-btn vb-btn-secondary" title="Importer CSV" style="cursor:pointer;margin:0">
            <span class="dashicons dashicons-upload"></span> Import CSV
            <input type="file" id="vb-import-csv-file" accept=".csv" style="display:none">
        </label>
        <a href="<?= admin_url('admin.php?page=vendbase-new') ?>" class="vb-btn vb-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> Nouveau
        </a>
    </div>
</div>

<!-- Filters bar -->
<div class="vb-filters">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-projects">
        <div class="vb-filter-group">
            <input type="text" name="s" value="<?= esc_attr($search) ?>" placeholder="Rechercher client, URL..." class="vb-input vb-input-search">
        </div>
        <div class="vb-filter-group">
            <select name="status" class="vb-select">
                <option value="">Tous statuts</option>
                <?php foreach ($status_labels as $key => $info): ?>
                <option value="<?= $key ?>" <?= selected($status, $key, false) ?>><?= $info['label'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="month" class="vb-select">
                <?php foreach ($months_fr as $k => $v): ?>
                <option value="<?= $k ?>" <?= selected($month, $k, false) ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="year" class="vb-select">
                <option value="">Toutes années</option>
                <?php for ($y = date('Y'); $y >= 2022; $y--): ?>
                <option value="<?= $y ?>" <?= selected($year, $y, false) ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="tracking" class="vb-select">
                <option value="">🔔 Suivi: Tous</option>
                <option value="1" <?= selected($tracking, '1', false) ?>>Suivi actif</option>
                <option value="0" <?= selected($tracking, '0', false) ?>>Sans suivi</option>
            </select>
        </div>
        <div class="vb-filter-group">
            <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
            <a href="<?= admin_url('admin.php?page=vendbase-projects') ?>" class="vb-btn vb-btn-ghost">Reset</a>
        </div>
    </form>
</div>

<!-- Import notice -->
<div id="vb-import-notice" style="display:none;margin-bottom:12px" class="vb-card" >
    <div style="padding:12px 16px;display:flex;align-items:center;gap:10px">
        <span class="dashicons dashicons-update vb-spin"></span>
        <span id="vb-import-msg">Import en cours...</span>
    </div>
</div>

<!-- Projects table -->
<div class="vb-card" style="margin-top:16px">
<table id="vb-projects-table" class="vb-table vb-table-full">
    <thead>
        <tr>
            <th>#</th>
            <th>Client</th>
            <th>WhatsApp</th>
            <th>Type</th>
            <th>Site</th>
            <th>Prix</th>
            <th>Avance</th>
            <th>Reste</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($projects): ?>
    <?php foreach ($projects as $i => $p): ?>
        <tr class="vb-project-row" data-id="<?= $p->id ?>">
            <td class="vb-id"><?= $p->id ?></td>
            <td>
                <strong><?= esc_html($p->client_name) ?></strong>
                <?php if ($p->client_phone): ?>
                <div class="vb-sub">
                    <a href="tel:<?= esc_attr($p->client_phone) ?>"><?= esc_html($p->client_phone) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($p->client_email): ?>
                <div class="vb-sub"><?= esc_html($p->client_email) ?></div>
                <?php endif; ?>
                <?php if ($p->client_city): ?>
                <div class="vb-sub"><span class="dashicons dashicons-location" style="font-size:11px"></span> <?= esc_html($p->client_city) ?></div>
                <?php endif; ?>
            </td>
            <td class="vb-wa-cell">
                <?php if ($p->client_phone): ?>
                <?php
                    $wa_number = preg_replace('/[^0-9]/', '', $p->client_phone);
                    // Convert Moroccan 06/07 to international 2126/2127
                    if (strlen($wa_number) === 10 && $wa_number[0] === '0') {
                        $wa_number = '212' . substr($wa_number, 1);
                    }
                    $wa_url = 'https://wa.me/' . $wa_number;
                ?>
                <a href="<?= esc_url($wa_url) ?>" target="_blank" class="vb-wa-btn" title="WhatsApp <?= esc_attr($p->client_phone) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($p->site_type): ?>
                <span class="vb-type-badge"><?= esc_html($p->site_type) ?></span>
                <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($p->site_url): ?>
                <a href="<?= esc_url($p->site_url) ?>" target="_blank" class="vb-url-link"><?= esc_html(parse_url($p->site_url, PHP_URL_HOST) ?: $p->site_url) ?> <span class="dashicons dashicons-external" style="font-size:11px"></span></a>
                <?php endif; ?>
                <?php if ($p->admin_url): ?>
                <div class="vb-sub"><a href="<?= esc_url($p->admin_url) ?>" target="_blank" title="Admin">Admin ↗</a></div>
                <?php endif; ?>
                <?php if ($p->hosting): ?>
                <div class="vb-sub"><span class="vb-hosting-pill">🌐 Hébergement</span></div>
                <?php endif; ?>
                <?php if ($p->tracking_enabled): ?>
                <div class="vb-sub"><span class="vb-tracking-pill" title="<?= esc_attr($p->tracking_note) ?>">🔔 Suivi actif — <?= number_format($p->tracking_price, 0, ',', ' ') ?> MAD/mois</span></div>
                <?php endif; ?>
            </td>
            <td><strong><?= number_format($p->prix, 0, ',', ' ') ?> MAD</strong></td>
            <td class="green"><?= number_format($p->avance, 0, ',', ' ') ?></td>
            <td class="<?= $p->reste > 0 ? 'orange' : 'green' ?>">
                <?= number_format($p->reste, 0, ',', ' ') ?>
                <?php if ($p->reste > 0): ?>
                <button class="vb-pay-quick" data-id="<?= $p->id ?>" data-prix="<?= $p->prix ?>" data-avance="<?= $p->avance ?>" title="Mettre à jour avance">💰</button>
                <?php endif; ?>
            </td>
            <td>
                <select class="vb-status-select vb-badge <?= esc_attr($status_labels[$p->status]['class'] ?? 'vb-badge-blue') ?>" data-id="<?= $p->id ?>">
                    <?php foreach ($status_labels as $key => $info): ?>
                    <option value="<?= $key ?>" <?= selected($p->status, $key, false) ?>><?= $info['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><?= date('d/m/Y', strtotime($p->created_at)) ?></td>
            <td class="vb-actions-cell">
                <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . $p->id) ?>" class="vb-action-btn vb-btn-edit" title="Modifier"><span class="dashicons dashicons-edit"></span></a>
                <button class="vb-action-btn vb-btn-delete" data-id="<?= $p->id ?>" title="Supprimer"><span class="dashicons dashicons-trash"></span></button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="11" class="vb-empty">Aucun projet trouvé. <a href="<?= admin_url('admin.php?page=vendbase-new') ?>">Créer le premier →</a></td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Quick pay modal -->
<div id="vb-pay-modal" class="vb-modal" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box">
        <div class="vb-modal-header">
            <h3>Mettre à jour le paiement</h3>
            <button class="vb-modal-close">✕</button>
        </div>
        <div class="vb-modal-body">
            <p>Prix total: <strong id="vb-pay-prix"></strong> MAD</p>
            <label>Avance reçue (MAD)
                <input type="number" id="vb-pay-avance" class="vb-input" step="0.01">
            </label>
            <p id="vb-pay-reste-preview" class="vb-pay-reste"></p>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost vb-modal-close">Annuler</button>
            <button id="vb-pay-save" class="vb-btn vb-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>


<script>
(function($){

/* ── helpers ── */
function getTableData() {
    var rows = [];
    $('#vb-projects-table tbody tr').each(function() {
        var cells = [];
        $(this).find('td').each(function(i) {
            // skip WhatsApp icon cell (col 2) and actions cell (last)
            if (i === 2 || i === $(this).closest('tr').find('td').length - 1) return;
            cells.push($(this).text().trim().replace(/\s+/g,' '));
        });
        if (cells.length > 2) rows.push(cells);
    });
    return rows;
}

/* ── Export CSV ── */
$('#vb-export-csv').on('click', function() {
    var headers = ['#','Client','Type','Site','Prix','Avance','Reste','Statut','Date'];
    var rows = getTableData();
    var csv = [headers.join(',')];
    rows.forEach(function(r) {
        csv.push(r.map(function(c){ return '"' + c.replace(/"/g,'""') + '"'; }).join(','));
    });
    var blob = new Blob(['﻿' + csv.join('\n')], {type:'text/csv;charset=utf-8;'});
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href   = url;
    a.download = 'projets-younessWeb-' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
    vbToast('✅ Export CSV téléchargé', 'success');
});

/* ── Export PDF Report ── */
$('#vb-export-pdf').on('click', function() {
    // Collect stats from visible cards
    var statCards = [];
    $('.vb-stat-card').each(function() {
        var val   = $(this).find('.vb-stat-value').text().trim();
        var label = $(this).find('.vb-stat-label').text().trim();
        if (label) statCards.push({val:val, label:label});
    });

    // Collect table rows
    var headers = ['#','Client','Type','Site','Prix (MAD)','Avance','Reste','Statut','Date'];
    var rows = getTableData();

    // Build HTML for PDF
    var now = new Date().toLocaleDateString('fr-MA', {year:'numeric',month:'long',day:'numeric'});
    var statsHtml = statCards.map(function(s){
        return '<div class="pdf-stat"><div class="pdf-stat-val">'+s.val+'</div><div class="pdf-stat-lbl">'+s.label+'</div></div>';
    }).join('');

    var tableRows = rows.map(function(r){
        return '<tr>' + r.map(function(c){ return '<td>'+c+'</td>'; }).join('') + '</tr>';
    }).join('');

    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Rapport Projets</title><style>'
        + 'body{font-family:Arial,sans-serif;font-size:11px;color:#1e293b;margin:0;padding:20px}'
        + 'h1{font-size:20px;color:#6366f1;margin-bottom:4px}'
        + '.sub{color:#64748b;font-size:12px;margin-bottom:20px}'
        + '.stats{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:24px}'
        + '.pdf-stat{background:#f1f5f9;border-radius:8px;padding:12px 18px;min-width:120px;text-align:center}'
        + '.pdf-stat-val{font-size:18px;font-weight:700;color:#6366f1}'
        + '.pdf-stat-lbl{font-size:10px;color:#64748b;margin-top:4px}'
        + 'table{width:100%;border-collapse:collapse;margin-top:8px}'
        + 'th{background:#6366f1;color:#fff;padding:7px 8px;text-align:left;font-size:10px}'
        + 'td{padding:6px 8px;border-bottom:1px solid #e2e8f0;font-size:10px}'
        + 'tr:nth-child(even) td{background:#f8fafc}'
        + '.footer{margin-top:24px;font-size:9px;color:#94a3b8;text-align:center}'
        + '</style></head><body>'
        + '<h1>📊 Rapport Projets — YounessWeb Manager</h1>'
        + '<div class="sub">Généré le ' + now + ' · ' + rows.length + ' projet(s)</div>'
        + '<div class="stats">' + statsHtml + '</div>'
        + '<table><thead><tr>' + headers.map(function(h){ return '<th>'+h+'</th>'; }).join('') + '</tr></thead>'
        + '<tbody>' + tableRows + '</tbody></table>'
        + '<div class="footer">YounessWeb Manager — younesweb.ma</div>'
        + '</body></html>';

    var win = window.open('', '_blank');
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(function(){ win.print(); }, 600);
});

/* ── Import CSV ── */
$('#vb-import-csv-file').on('change', function() {
    var file = this.files[0];
    if (!file) return;
    if (!confirm('Importer ' + file.name + ' ? Les projets existants ne seront pas modifiés.')) {
        $(this).val('');
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var lines = e.target.result.split('\n').filter(function(l){ return l.trim(); });
        if (lines.length < 2) { vbToast('❌ Fichier CSV vide ou invalide', 'error'); return; }

        // Parse CSV (simple, handles quoted fields)
        function parseCSVLine(line) {
            var result = [], cur = '', inQ = false;
            for (var i = 0; i < line.length; i++) {
                var c = line[i];
                if (c === '"') { inQ = !inQ; }
                else if (c === ',' && !inQ) { result.push(cur.trim()); cur = ''; }
                else { cur += c; }
            }
            result.push(cur.trim());
            return result;
        }

        var headers = parseCSVLine(lines[0]).map(function(h){ return h.toLowerCase().replace(/[^a-z_]/g,''); });
        var projects = [];
        for (var i = 1; i < lines.length; i++) {
            var vals = parseCSVLine(lines[i]);
            var obj  = {};
            headers.forEach(function(h, idx){ obj[h] = vals[idx] || ''; });
            projects.push(obj);
        }

        $('#vb-import-notice').show();
        $('#vb-import-msg').text('Import de ' + projects.length + ' projet(s)...');

        // Send each project via AJAX
        var done = 0, errors = 0;
        function importNext(idx) {
            if (idx >= projects.length) {
                $('#vb-import-msg').text('✅ ' + done + ' projet(s) importé(s)' + (errors ? ', ' + errors + ' erreur(s)' : ''));
                vbToast('Import terminé: ' + done + ' projet(s)', 'success');
                setTimeout(function(){ location.reload(); }, 1500);
                return;
            }
            var row = projects[idx];
            var data = {
                action: 'vb_save_project',
                nonce:  VB.nonce,
                client_name:  row.client || row.clientname || row.nom || row.client_name || 'Import #'+(idx+1),
                client_phone: row.telephone || row.phone || row.client_phone || '',
                client_email: row.email || row.client_email || '',
                client_city:  row.ville || row.city || row.client_city || '',
                site_type:    row.type || row.site_type || '',
                site_url:     row.site || row.url || row.site_url || '',
                prix:         row.prix || row.price || '0',
                avance:       row.avance || '0',
                status:       row.statut || row.status || 'in_progress',
                notes:        row.notes || '',
            };
            $.post(VB.ajax, data, function(r) {
                if (r.success) done++; else errors++;
                $('#vb-import-msg').text('Import... ' + (idx+1) + '/' + projects.length);
                importNext(idx + 1);
            }).fail(function(){ errors++; importNext(idx + 1); });
        }
        importNext(0);
    };
    reader.readAsText(file, 'UTF-8');
    $(this).val('');
});

})(jQuery);
</script>

</div><!-- .vb-wrap -->
