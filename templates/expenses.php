<?php
if ( ! defined('ABSPATH') ) exit;

$year       = intval($_GET['year']  ?? date('Y'));
$month      = sanitize_text_field($_GET['month'] ?? '');
$cat_filter = sanitize_text_field($_GET['cat']   ?? '');

$expenses      = vb_get_expenses(['year' => $year, 'month' => $month, 'category' => $cat_filter]);
$totals        = vb_get_expenses_totals($month, $year);
$monthly_chart = vb_get_monthly_expenses_chart($year);
$projects      = vb_get_projects(['limit' => 200, 'orderby' => 'client_name', 'order' => 'ASC']);

$months_fr = [
    ''   => "Toute l'annee",
    '01' => 'Janvier', '02' => 'Fevrier',  '03' => 'Mars',
    '04' => 'Avril',   '05' => 'Mai',      '06' => 'Juin',
    '07' => 'Juillet', '08' => 'Aout',     '09' => 'Septembre',
    '10' => 'Octobre', '11' => 'Novembre', '12' => 'Decembre',
];

$categories = [
    'ads_facebook'  => ['label' => 'Facebook Ads',           'color' => '#1877f2'],
    'ads_instagram' => ['label' => 'Instagram Ads',          'color' => '#e1306c'],
    'ads_google'    => ['label' => 'Google Ads',             'color' => '#ea4335'],
    'ads_tiktok'    => ['label' => 'TikTok Ads',             'color' => '#010101'],
    'tools'         => ['label' => 'Outils & Abonnements',   'color' => '#6366f1'],
    'hosting'       => ['label' => 'Hebergement',            'color' => '#06b6d4'],
    'freelance'     => ['label' => 'Freelance / Sous-trait.','color' => '#8b5cf6'],
    'other'         => ['label' => 'Autre charge',           'color' => '#94a3b8'],
];

$cat_totals = [
    'ads_facebook'  => floatval($totals->ads_facebook  ?? 0),
    'ads_instagram' => floatval($totals->ads_instagram ?? 0),
    'ads_google'    => floatval($totals->ads_google    ?? 0),
    'ads_tiktok'    => floatval($totals->ads_tiktok    ?? 0),
    'tools'         => floatval($totals->tools         ?? 0),
    'hosting'       => floatval($totals->hosting_exp   ?? 0),
    'freelance'     => floatval($totals->freelance     ?? 0),
    'other'         => floatval($totals->other_exp     ?? 0),
];
$grand_total = array_sum($cat_totals) ?: 1;

$projects_map = [];
foreach ($projects as $p) $projects_map[$p->id] = $p->client_name;

/* Pass chart data to JS via inline script (safe — numbers and strings only) */
$cat_data_js   = [];
$cat_labels_js = [];
$cat_colors_js = [];
foreach ($cat_totals as $k => $v) {
    if ($v <= 0) continue;
    $cat_data_js[]   = (float) $v;
    $cat_labels_js[] = $categories[$k]['label'];
    $cat_colors_js[] = $categories[$k]['color'];
}
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?php echo esc_url(VB_PLUGIN_URL); ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">Depenses &amp; Publicite</h1>
            <span class="vb-page-sub">Enregistrez vos charges pour calculer le benefice net reel</span>
        </div>
    </div>
    <div class="vb-header-right">
        <button type="button" class="vb-btn vb-btn-primary" id="vb-open-modal-btn">+ Ajouter une depense</button>
    </div>
</div>

<!-- Filters -->
<div class="vb-filters">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-expenses">
        <select name="year" class="vb-select">
            <?php for ($y = date('Y'); $y >= 2022; $y--): ?>
            <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>><?php echo $y; ?></option>
            <?php endfor; ?>
        </select>
        <select name="month" class="vb-select">
            <?php foreach ($months_fr as $k => $v): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($month, $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="cat" class="vb-select">
            <option value="">Toutes categories</option>
            <?php foreach ($categories as $k => $c): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($cat_filter, $k); ?>><?php echo esc_html($c['label']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="vb-cards-grid" style="grid-template-columns:repeat(4,1fr);margin-top:20px">
    <div class="vb-stat-card vb-card-red">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?php echo number_format(floatval($totals->total ?? 0), 0, ',', ' '); ?></div>
            <div class="vb-stat-label">Total depenses (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card" style="border-left:3px solid #1877f2">
        <div class="vb-stat-icon" style="background:#eff6ff;color:#1877f2;font-weight:900;font-size:18px;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;flex-shrink:0;">f</div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?php echo number_format(floatval($totals->ads_facebook ?? 0), 0, ',', ' '); ?></div>
            <div class="vb-stat-label">Facebook Ads (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card" style="border-left:3px solid #e1306c">
        <div class="vb-stat-icon" style="background:#fff0f6;color:#e1306c;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;flex-shrink:0;">IG</div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?php echo number_format(floatval($totals->ads_instagram ?? 0), 0, ',', ' '); ?></div>
            <div class="vb-stat-label">Instagram Ads (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card" style="border-left:3px solid #6366f1">
        <div class="vb-stat-icon"><span class="dashicons dashicons-admin-tools" style="color:#6366f1"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?php
                echo number_format(
                    floatval($totals->tools ?? 0) + floatval($totals->hosting_exp ?? 0) +
                    floatval($totals->freelance ?? 0) + floatval($totals->other_exp ?? 0),
                    0, ',', ' '
                );
            ?></div>
            <div class="vb-stat-label">Autres charges (MAD)</div>
        </div>
    </div>
</div>

<!-- Breakdown bars -->
<?php if (array_sum($cat_totals) > 0): ?>
<div class="vb-exp-breakdown">
    <?php foreach ($cat_totals as $k => $v):
        if ($v <= 0) continue;
        $pct = round($v / $grand_total * 100);
        $c   = $categories[$k];
    ?>
    <div class="vb-exp-breakdown-item">
        <span class="vb-exp-cat-dot" style="background:<?php echo $c['color']; ?>"></span>
        <span class="vb-exp-cat-label"><?php echo esc_html($c['label']); ?></span>
        <div class="vb-exp-bar-wrap"><div class="vb-exp-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $c['color']; ?>"></div></div>
        <span class="vb-exp-cat-pct"><?php echo $pct; ?>%</span>
        <span class="vb-exp-cat-amount"><?php echo number_format($v, 0, ',', ' '); ?> MAD</span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Charts -->
<div class="vb-two-col" style="margin-top:24px">
    <div class="vb-card">
        <div class="vb-card-header"><span>Depenses mensuelles <?php echo $year; ?></span></div>
        <div style="position:relative;height:260px"><canvas id="vb-exp-chart"></canvas></div>
    </div>
    <div class="vb-card">
        <div class="vb-card-header"><span>Repartition par categorie</span></div>
        <div style="position:relative;height:260px"><canvas id="vb-exp-pie"></canvas></div>
    </div>
</div>

<!-- Expenses list -->
<div class="vb-card" style="margin-top:24px">
    <div class="vb-card-header">
        <span>Liste — <?php echo esc_html($months_fr[$month] ?? "Toute l'annee"); ?> <?php echo $year; ?></span>
        <span class="vb-badge"><?php echo count($expenses); ?></span>
    </div>
    <?php if (empty($expenses)): ?>
        <div class="vb-empty-state" style="text-align:center;padding:40px 20px">
            <span class="dashicons dashicons-chart-bar" style="font-size:40px;width:40px;height:40px;color:#cbd5e1"></span>
            <p style="color:#94a3b8;margin:12px 0 20px">Aucune depense enregistree pour cette periode.</p>
            <button type="button" class="vb-btn vb-btn-primary" id="vb-open-modal-btn2">+ Ajouter la premiere depense</button>
        </div>
    <?php else: ?>
    <table class="vb-table">
        <thead>
            <tr>
                <th>Date</th><th>Categorie</th><th>Description</th>
                <th>Projet lie</th><th>Montant</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $exp):
            $cat        = $categories[$exp->category] ?? ['label' => $exp->category, 'color' => '#94a3b8'];
            $proj_name  = $exp->project_id ? ($projects_map[$exp->project_id] ?? '?') : '';
            $date_label = $exp->expense_date
                ? date('d/m/Y', strtotime($exp->expense_date))
                : sprintf('%02d/%04d', $exp->month ?? 0, $exp->year ?? date('Y'));
        ?>
        <tr>
            <td><?php echo esc_html($date_label); ?></td>
            <td>
                <span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:600;
                    background:<?php echo $cat['color']; ?>22;color:<?php echo $cat['color']; ?>;
                    border:1px solid <?php echo $cat['color']; ?>55">
                    <?php echo esc_html($cat['label']); ?>
                </span>
            </td>
            <td><?php echo esc_html($exp->label ?: '—'); ?></td>
            <td>
                <?php if ($exp->project_id && $proj_name): ?>
                    <span style="color:#6366f1;font-weight:500"><?php echo esc_html($proj_name); ?></span>
                <?php else: ?>
                    <span class="vb-muted">Global</span>
                <?php endif; ?>
            </td>
            <td><strong style="color:#ef4444"><?php echo number_format(floatval($exp->amount), 0, ',', ' '); ?> MAD</strong></td>
            <td>
                <div style="display:flex;gap:6px">
                    <button type="button" class="vb-btn vb-btn-sm vb-edit-exp-btn" data-id="<?php echo intval($exp->id); ?>">Editer</button>
                    <button type="button" class="vb-btn vb-btn-sm vb-btn-danger vb-del-exp-btn" data-id="<?php echo intval($exp->id); ?>">Suppr.</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="vb-total-row">
                <td colspan="4"><strong>TOTAL</strong></td>
                <td colspan="2"><strong style="color:#ef4444"><?php
                    $sum = 0;
                    foreach ($expenses as $e) $sum += floatval($e->amount);
                    echo number_format($sum, 0, ',', ' ');
                ?> MAD</strong></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</div>

</div><!-- .vb-wrap -->

<!-- ===================== MODAL ===================== -->
<div id="vb-exp-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:99999;align-items:center;justify-content:center;">
    <div id="vb-exp-backdrop" style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.6);cursor:pointer;"></div>
    <div style="position:relative;z-index:1;background:#fff;border-radius:12px;width:100%;max-width:540px;margin:20px auto;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <h3 id="vb-exp-modal-title" style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">Ajouter une depense</h3>
            <button type="button" id="vb-exp-close-btn" style="background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#94a3b8;padding:0 4px;">&times;</button>
        </div>
        <div style="padding:20px 24px;max-height:65vh;overflow-y:auto;">
            <input type="hidden" id="vb-exp-id" value="">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">CATEGORIE *</label>
                    <select id="vb-exp-category" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;">
                        <?php foreach ($categories as $k => $c): ?>
                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($c['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">MONTANT (MAD) *</label>
                    <input type="number" id="vb-exp-amount" step="1" min="0" placeholder="0"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">DESCRIPTION</label>
                    <input type="text" id="vb-exp-label" placeholder="Ex: Campagne Ramadan"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">DATE</label>
                    <input type="date" id="vb-exp-date" value="<?php echo date('Y-m-d'); ?>"
                        style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">MOIS</label>
                    <select id="vb-exp-month" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;">
                        <?php foreach ($months_fr as $k => $v):
                            if (!$k) continue; ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php echo ($k === date('m')) ? 'selected' : ''; ?>><?php echo esc_html($v); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">ANNEE</label>
                    <select id="vb-exp-year" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;">
                        <?php for ($y = date('Y'); $y >= 2022; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">PROJET LIE (optionnel)</label>
                <select id="vb-exp-project" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;cursor:pointer;">
                    <option value="">— Depense globale —</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?php echo intval($p->id); ?>"><?php echo esc_html($p->client_name . ' — ' . $p->site_type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px;">NOTE</label>
                <textarea id="vb-exp-note" rows="2" placeholder="Objectif, resultats..."
                    style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
            </div>
        </div>
        <div style="padding:14px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;background:#f8fafc;">
            <button type="button" id="vb-exp-cancel-btn" class="vb-btn vb-btn-secondary">Annuler</button>
            <button type="button" id="vb-exp-save-btn" class="vb-btn vb-btn-primary">Enregistrer</button>
        </div>
    </div>
</div>

<!-- Chart data passed to JS -->
<script>
window.VB_EXP_DATA = {
    monthly:   <?php echo json_encode(array_values($monthly_chart)); ?>,
    catData:   <?php echo json_encode($cat_data_js); ?>,
    catLabels: <?php echo json_encode($cat_labels_js); ?>,
    catColors: <?php echo json_encode($cat_colors_js); ?>
};
</script>
