<?php
if ( ! defined('ABSPATH') ) exit;

// Dashboard filters
$filter_year   = intval($_GET['year']   ?? date('Y'));
$filter_month  = sanitize_text_field($_GET['month']  ?? date('m'));
$filter_status = sanitize_text_field($_GET['status'] ?? '');
$filter_from   = sanitize_text_field($_GET['from']   ?? '');
$filter_to     = sanitize_text_field($_GET['to']     ?? '');

$month = $filter_month ? intval($filter_month) : intval(date('m'));
$year  = $filter_year;
$stats = vb_get_stats($filter_month, $year);
$stats_year = vb_get_stats('', $year);

$recent_args = ['limit' => 10, 'orderby' => 'created_at', 'order' => 'DESC',
    'month' => $filter_month, 'year' => $filter_year];
if ($filter_status) $recent_args['status'] = $filter_status;
$recent = vb_get_projects($recent_args);

$status_labels = [
    'in_progress' => ['label' => 'En cours',  'class' => 'vb-badge-blue'],
    'completed'   => ['label' => 'Terminé',   'class' => 'vb-badge-green'],
    'paused'      => ['label' => 'En pause',  'class' => 'vb-badge-orange'],
    'cancelled'   => ['label' => 'Annulé',    'class' => 'vb-badge-red'],
];

$months_fr = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
?>
<div class="vb-wrap">

<!-- ── Header ── -->
<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="Vendbase">
        <div>
            <h1 class="vb-page-title">Dashboard</h1>
            <span class="vb-page-sub"><?= esc_html($months_fr[$month]) ?> <?= $year ?></span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-new') ?>" class="vb-btn vb-btn-primary">
            <span class="dashicons dashicons-plus-alt2"></span> Nouveau projet
        </a>
    </div>
</div>

<!-- ── Dashboard Filters ── -->
<div class="vb-filters" style="margin-bottom:20px">
    <form method="get" action="" class="vb-filter-form" id="vb-dash-filter-form">
        <input type="hidden" name="page" value="vendbase">
        <div class="vb-filter-group">
            <select name="year" class="vb-select" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= 2022; $y--): ?>
                <option value="<?= $y ?>" <?= selected($filter_year, $y, false) ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="month" class="vb-select" onchange="this.form.submit()">
                <option value="">Tous les mois</option>
                <?php
                $months_list = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
                    '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
                foreach ($months_list as $mk => $mv): ?>
                <option value="<?= $mk ?>" <?= selected($filter_month, $mk, false) ?>><?= $mv ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="status" class="vb-select" onchange="this.form.submit()">
                <option value="">Tous statuts</option>
                <option value="in_progress" <?= selected($filter_status,'in_progress',false) ?>>En cours</option>
                <option value="completed"   <?= selected($filter_status,'completed',false) ?>>Terminé</option>
                <option value="paused"      <?= selected($filter_status,'paused',false) ?>>En pause</option>
                <option value="cancelled"   <?= selected($filter_status,'cancelled',false) ?>>Annulé</option>
            </select>
        </div>
        <div class="vb-filter-group">
            <input type="date" name="from" class="vb-input" value="<?= esc_attr($filter_from) ?>" placeholder="Du" title="Date début" style="max-width:140px">
        </div>
        <div class="vb-filter-group">
            <input type="date" name="to" class="vb-input" value="<?= esc_attr($filter_to) ?>" placeholder="Au" title="Date fin" style="max-width:140px">
        </div>
        <div class="vb-filter-group">
            <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
            <a href="<?= admin_url('admin.php?page=vendbase') ?>" class="vb-btn vb-btn-ghost">Reset</a>
        </div>
    </form>
</div>

<!-- ── Stats cards THIS MONTH ── -->
<div class="vb-section-label">Période sélectionnée — <?= $filter_month ? esc_html($months_fr[$month]) . ' ' : '' ?><?= $filter_year ?><?= $filter_status ? ' · ' . esc_html($filter_status) : '' ?></div>
<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-portfolio"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats->total_projects) ?></div>
            <div class="vb-stat-label">Projets</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats->total_revenue), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">CA total</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-teal">
        <div class="vb-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats->total_received), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Reçu (avances)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-clock"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats->total_pending), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Reste à payer</div>
        </div>
    </div>
</div>

<!-- ── Stats cards THIS YEAR ── -->
<div class="vb-section-label" style="margin-top:28px">Année <?= $year ?></div>
<div class="vb-cards-grid">
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats_year->total_projects) ?></div>
            <div class="vb-stat-label">Total projets</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-awards"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats_year->total_revenue), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">CA annuel</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-flag"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats_year->completed) ?></div>
            <div class="vb-stat-label">Terminés</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-editor-help"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats_year->avg_price), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Prix moyen</div>
        </div>
    </div>
</div>

<!-- ── Suivi mensuel (Tracking) — v2.1 addition ── -->
<?php $tstats = vb_get_tracking_stats(); ?>
<div class="vb-section-label" style="margin-top:28px">🔔 Suivi mensuel des clients</div>
<div class="vb-two-col">
    <a href="<?= admin_url('admin.php?page=vendbase-tracking') ?>" class="vb-stat-card vb-card-purple" style="text-decoration:none">
        <div class="vb-stat-icon"><span class="dashicons dashicons-bell"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($tstats->active_count ?? 0) ?></div>
            <div class="vb-stat-label">Clients avec suivi actif — voir la liste →</div>
        </div>
    </a>
    <a href="<?= admin_url('admin.php?page=vendbase-tracking') ?>" class="vb-stat-card vb-card-green" style="text-decoration:none">
        <div class="vb-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($tstats->mrr ?? 0), 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Revenu récurrent mensuel (MRR)</div>
        </div>
    </a>
</div>

<!-- ── Main Charts Row ── -->
<div class="vb-two-col" style="margin-top:32px">
    <div class="vb-card">
        <div class="vb-card-header">
            <span>Revenus mensuels <?= $year ?></span>
        </div>
        <div style="position:relative;height:220px;width:100%;"><canvas id="vb-chart-monthly"></canvas></div>
    </div>
    <div class="vb-card">
        <div class="vb-card-header"><span>Types de sites</span></div>
        <div style="position:relative;height:220px;width:100%;"><canvas id="vb-chart-types"></canvas></div>
    </div>
</div>

<!-- ── Status summary ── -->
<div class="vb-two-col" style="margin-top:24px">
    <div class="vb-card">
        <div class="vb-card-header"><span>Statuts — <?= esc_html($months_fr[$month]) ?></span></div>
        <div class="vb-status-row">
            <div class="vb-status-item">
                <div class="vb-status-dot blue"></div>
                <span>En cours</span>
                <strong><?= intval($stats->in_progress) ?></strong>
            </div>
            <div class="vb-status-item">
                <div class="vb-status-dot green"></div>
                <span>Terminés</span>
                <strong><?= intval($stats->completed) ?></strong>
            </div>
            <div class="vb-status-item">
                <div class="vb-status-dot orange"></div>
                <span>En pause</span>
                <strong><?= intval($stats->paused) ?></strong>
            </div>
            <div class="vb-status-item">
                <div class="vb-status-dot red"></div>
                <span>Annulés</span>
                <strong><?= intval($stats->cancelled) ?></strong>
            </div>
        </div>
        <?php if ($stats->total_projects > 0): ?>
        <div class="vb-progress-bar-wrap">
            <?php
            $pct_done = round(($stats->completed / $stats->total_projects) * 100);
            ?>
            <div class="vb-progress-bar-bg">
                <div class="vb-progress-bar-fill" style="width:<?= $pct_done ?>%"></div>
            </div>
            <span><?= $pct_done ?>% terminés</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment progress -->
    <div class="vb-card">
        <div class="vb-card-header"><span>Paiements — <?= esc_html($months_fr[$month]) ?></span></div>
        <?php
        $total = floatval($stats->total_revenue);
        $recv  = floatval($stats->total_received);
        $pend  = floatval($stats->total_pending);
        $pct_pay = $total > 0 ? round(($recv / $total) * 100) : 0;
        ?>
        <div class="vb-payment-big">
            <div class="vb-payment-row">
                <span>Total CA</span><strong><?= number_format($total, 0, ',', ' ') ?> MAD</strong>
            </div>
            <div class="vb-payment-row">
                <span>Reçu</span><strong class="green"><?= number_format($recv, 0, ',', ' ') ?> MAD</strong>
            </div>
            <div class="vb-payment-row">
                <span>Reste</span><strong class="orange"><?= number_format($pend, 0, ',', ' ') ?> MAD</strong>
            </div>
        </div>
        <div class="vb-progress-bar-wrap" style="margin-top:12px">
            <div class="vb-progress-bar-bg">
                <div class="vb-progress-bar-fill green" style="width:<?= $pct_pay ?>%"></div>
            </div>
            <span><?= $pct_pay ?>% reçu</span>
        </div>
    </div>
</div>

<!-- ── Recent Projects ── -->
<div class="vb-card" style="margin-top:24px">
    <div class="vb-card-header">
        <span>Derniers projets</span>
        <a href="<?= admin_url('admin.php?page=vendbase-projects') ?>" class="vb-link">Voir tout →</a>
    </div>
    <table class="vb-table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Type</th>
                <th>Prix</th>
                <th>Avance</th>
                <th>Reste</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recent as $p): ?>
            <tr>
                <td>
                    <strong><?= esc_html($p->client_name) ?></strong>
                    <?php if ($p->client_phone): ?>
                    <div class="vb-sub"><?= esc_html($p->client_phone) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="vb-type-badge"><?= esc_html($p->site_type ?: '—') ?></span></td>
                <td><?= number_format($p->prix, 0, ',', ' ') ?> MAD</td>
                <td class="green"><?= number_format($p->avance, 0, ',', ' ') ?></td>
                <td class="<?= $p->reste > 0 ? 'orange' : 'green' ?>"><?= number_format($p->reste, 0, ',', ' ') ?></td>
                <td>
                    <span class="vb-badge <?= esc_attr($status_labels[$p->status]['class'] ?? 'vb-badge-blue') ?>">
                        <?= esc_html($status_labels[$p->status]['label'] ?? $p->status) ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($p->created_at)) ?></td>
                <td>
                    <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . $p->id) ?>" class="vb-action-btn" title="Modifier"><span class="dashicons dashicons-edit"></span></a>
                    <?php if ($p->site_url): ?>
                    <a href="<?= esc_url($p->site_url) ?>" target="_blank" class="vb-action-btn" title="Voir le site"><span class="dashicons dashicons-external"></span></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?>
            <tr><td colspan="8" class="vb-empty">Aucun projet. <a href="<?= admin_url('admin.php?page=vendbase-new') ?>">Créer le premier →</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

</div><!-- .vb-wrap -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    jQuery.post(VB.ajax, {action:'vb_get_stats', nonce:VB.nonce, year:VB.year}, function(r) {
        if (!r.success) return;
        const monthly = r.data.monthly;
        const types   = r.data.types;

        // Monthly chart
        const labels = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        const revenue = Array(12).fill(0);
        const received = Array(12).fill(0);
        monthly.forEach(m => { revenue[m.month-1]=parseFloat(m.revenue||0); received[m.month-1]=parseFloat(m.received||0); });

        new Chart(document.getElementById('vb-chart-monthly'), {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {label:'CA (MAD)', data:revenue,  backgroundColor:'rgba(99,102,241,0.85)', borderRadius:6},
                    {label:'Reçu',     data:received, backgroundColor:'rgba(16,185,129,0.7)',  borderRadius:6},
                ]
            },
            options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}},
                scales:{y:{beginAtZero:true, grid:{color:'rgba(0,0,0,0.05)'}},x:{grid:{display:false}}}}
        });

        // Types donut
        if (types.length) {
            const colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16'];
            new Chart(document.getElementById('vb-chart-types'), {
                type: 'doughnut',
                data: {
                    labels: types.map(t=>t.site_type||'Autre'),
                    datasets:[{data: types.map(t=>parseInt(t.count)), backgroundColor: colors, borderWidth:0, hoverOffset:6}]
                },
                options: {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}, cutout:'65%'}
            });
        }
    });
});
</script>
