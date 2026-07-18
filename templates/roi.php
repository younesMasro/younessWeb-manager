<?php
/**
 * v2.4 — Rentabilité publicitaire (ROI / ROAS)
 */
if ( ! defined('ABSPATH') ) exit;

$year  = intval($_GET['year'] ?? date('Y'));
$month = sanitize_text_field($_GET['month'] ?? '');

$ov        = vb_get_roi_overview($year, $month);
$channels  = vb_get_roi_by_channel($year, $month);
$untracked = vb_get_roi_untracked_leads($year, $month);
$monthly   = vb_get_roi_monthly($year);

$months_fr = [
    ''   => "Toute l'annee",
    '01' => 'Janvier', '02' => 'Fevrier',  '03' => 'Mars',    '04' => 'Avril',
    '05' => 'Mai',     '06' => 'Juin',     '07' => 'Juillet', '08' => 'Aout',
    '09' => 'Septembre','10'=> 'Octobre',  '11' => 'Novembre','12' => 'Decembre',
];

$fmt = fn($n) => number_format((float)$n, 0, ',', ' ');

/** Couleur d'un ROAS : >=3 vert, 1-3 orange, <1 rouge. */
function vb_roas_class($roas) {
    if ($roas === null) return 'vb-muted';
    if ($roas >= 3) return 'vb-badge-green';
    if ($roas >= 1) return 'vb-badge-orange';
    return 'vb-badge-red';
}

$has_ad_spend  = $ov['ad_spend'] > 0;
$has_tracking  = false;
foreach ($channels as $c) { if ($c['leads'] > 0) { $has_tracking = true; break; } }
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">📈 Rentabilité pub (ROI)</h1>
            <span class="vb-page-sub">Ce que chaque dirham de publicité te rapporte réellement</span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-expenses') ?>" class="vb-btn vb-btn-secondary">
            <span class="dashicons dashicons-chart-pie"></span> Gérer les dépenses
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="vb-filters">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-roi">
        <div class="vb-filter-group">
            <select name="month" class="vb-select">
                <?php foreach ($months_fr as $k => $label): ?>
                <option value="<?= $k ?>" <?= selected($month, $k, false) ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="year" class="vb-select">
                <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                <option value="<?= $y ?>" <?= selected($year, $y, false) ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
        </div>
    </form>
</div>

<!-- VUE D'ENSEMBLE -->
<div class="vb-cards-grid" style="margin-top:16px">
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $fmt($ov['revenue']) ?> <small>MAD</small></div>
            <div class="vb-stat-label">Chiffre d'affaires (<?= $ov['projects'] ?> projets)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-megaphone"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $fmt($ov['ad_spend']) ?> <small>MAD</small></div>
            <div class="vb-stat-label">Dépense publicitaire</div>
        </div>
    </div>
    <div class="vb-stat-card <?= $ov['net_profit'] >= 0 ? 'vb-card-teal' : 'vb-card-red' ?>">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $fmt($ov['net_profit']) ?> <small>MAD</small></div>
            <div class="vb-stat-label">Bénéfice net (CA − toutes dépenses)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-superhero"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value">
                <?= $ov['blended_roas'] !== null ? number_format($ov['blended_roas'], 1, ',', ' ') . '×' : '—' ?>
            </div>
            <div class="vb-stat-label">ROAS global — retour par 1 MAD de pub</div>
        </div>
    </div>
</div>

<?php if ($has_ad_spend): ?>
<div class="vb-notice vb-notice-box" style="margin-top:16px">
    <?php if ($ov['blended_roas'] !== null): ?>
        <?php if ($ov['blended_roas'] >= 3): ?>
            💪 Pour chaque <strong>1 MAD</strong> investi en publicité, tu génères
            <strong><?= number_format($ov['blended_roas'], 1, ',', ' ') ?> MAD</strong> de chiffre d'affaires.
            La publicité n'absorbe que <strong><?= number_format($ov['ad_cost_ratio'], 1, ',', ' ') ?>%</strong> de ton CA — c'est rentable, tu peux pousser le budget.
        <?php elseif ($ov['blended_roas'] >= 1): ?>
            ⚠️ Tu récupères <strong><?= number_format($ov['blended_roas'], 1, ',', ' ') ?> MAD</strong> par MAD dépensé :
            c'est positif mais serré. La pub absorbe <strong><?= number_format($ov['ad_cost_ratio'], 1, ',', ' ') ?>%</strong> de ton CA.
        <?php else: ?>
            🚨 Tu dépenses plus en publicité que ce qu'elle rapporte directement
            (<strong><?= number_format($ov['blended_roas'], 1, ',', ' ') ?>×</strong>). À revoir avant d'augmenter le budget.
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- PAR CANAL -->
<div class="vb-card" style="margin-top:20px">
    <div class="vb-card-header"><h2>Détail par canal publicitaire</h2></div>

    <?php if (!$has_tracking && $has_ad_spend): ?>
    <div style="padding:16px">
        <div class="vb-notice vb-notice-box" style="border-left:4px solid #f59e0b">
            <strong>Tu dépenses en publicité, mais tu ne sais pas encore ce qu'elle rapporte, canal par canal.</strong>
            <p class="vb-sub" style="margin:8px 0 0">
                Pour relier une demande à la pub qui l'a générée, ajoute un paramètre
                <code>utm_source</code> à tes liens :
            </p>
            <ul style="margin:8px 0 0;padding-left:18px;line-height:2">
                <li>Lien Instagram (bio, stories) → <code>younessweb.com/contact?utm_source=instagram</code></li>
                <li>Pub Facebook → <code>younessweb.com/contact?utm_source=facebook</code></li>
                <li>Pub Google → <code>younessweb.com/contact?utm_source=google</code></li>
            </ul>
            <p class="vb-sub" style="margin:8px 0 0">
                Dès la première demande taguée, ce tableau se remplit tout seul : coût par lead,
                coût par client signé et ROAS de chaque canal.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <table class="vb-table vb-table-full">
        <thead>
            <tr>
                <th>Canal</th>
                <th>Dépense</th>
                <th>Leads</th>
                <th>Coût / lead</th>
                <th>Clients signés</th>
                <th>Coût / client</th>
                <th>CA attribué</th>
                <th>ROAS</th>
                <th>Bénéfice</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($channels as $key => $c): ?>
            <?php if ($c['spend'] == 0 && $c['leads'] == 0) continue; ?>
            <tr>
                <td><span class="vb-badge vb-badge-<?= $c['color'] ?>"><?= esc_html($c['label']) ?></span></td>
                <td><strong><?= $fmt($c['spend']) ?> MAD</strong></td>
                <td><?= $c['leads'] ?: '<span class="vb-muted">0</span>' ?></td>
                <td><?= $c['cpl'] !== null ? $fmt($c['cpl']) . ' MAD' : '<span class="vb-muted">—</span>' ?></td>
                <td>
                    <?= $c['won'] ?>
                    <?php if ($c['conv'] !== null): ?>
                    <span class="vb-sub">(<?= number_format($c['conv'], 0) ?>%)</span>
                    <?php endif; ?>
                </td>
                <td><?= $c['cpa'] !== null ? $fmt($c['cpa']) . ' MAD' : '<span class="vb-muted">—</span>' ?></td>
                <td><strong><?= $fmt($c['revenue']) ?> MAD</strong></td>
                <td>
                    <?php if ($c['roas'] !== null): ?>
                    <span class="vb-badge <?= vb_roas_class($c['roas']) ?>"><?= number_format($c['roas'], 1, ',', ' ') ?>×</span>
                    <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
                </td>
                <td class="<?= $c['profit'] >= 0 ? '' : 'vb-red' ?>"><strong><?= $fmt($c['profit']) ?> MAD</strong></td>
            </tr>
        <?php endforeach; ?>
        <?php
            $any_row = false;
            foreach ($channels as $c) { if ($c['spend'] != 0 || $c['leads'] != 0) { $any_row = true; break; } }
        ?>
        <?php if (!$any_row): ?>
            <tr><td colspan="9" class="vb-empty">Aucune dépense publicitaire ni lead tagué sur cette période.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($untracked > 0): ?>
    <div style="padding:12px 16px;border-top:1px solid #eee">
        <span class="vb-sub">
            ℹ️ <strong><?= $untracked ?></strong> demande<?= $untracked > 1 ? 's' : '' ?>
            sans source connue (ni utm, ni tag) — non attribuable<?= $untracked > 1 ? 's' : '' ?> à un canal.
        </span>
    </div>
    <?php endif; ?>
</div>

<!-- TENDANCE MENSUELLE -->
<div class="vb-card" style="margin-top:20px">
    <div class="vb-card-header"><h2>CA vs dépense pub — <?= $year ?></h2></div>
    <div style="padding:16px">
        <canvas id="vb-roi-chart" height="90"></canvas>
    </div>
</div>

</div><!-- .vb-wrap -->

<script>
(function(){
    var monthly = <?= wp_json_encode($monthly) ?>;
    var labels  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    var revenue = monthly.map(function(m){ return m.revenue; });
    var spend   = monthly.map(function(m){ return m.spend; });

    var el = document.getElementById('vb-roi-chart');
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Chiffre d\'affaires (MAD)', data: revenue, backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 6, order: 2 },
                { label: 'Dépense pub (MAD)', data: spend, type: 'line', borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.15)', borderWidth: 2, tension: .3, fill: true, order: 1 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { callback: function(v){ return v.toLocaleString('fr-FR') + ' '; } } } }
        }
    });
})();
</script>
