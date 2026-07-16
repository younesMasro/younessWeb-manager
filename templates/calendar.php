<?php
if ( ! defined('ABSPATH') ) exit;

$month = intval($_GET['m'] ?? date('m'));
$year  = intval($_GET['y'] ?? date('Y'));
$month = max(1, min(12, $month));

$projects = vb_get_projects(['month' => sprintf('%02d',$month), 'year' => $year, 'limit' => 200]);

// Build day map
$by_day = [];
foreach ($projects as $p) {
    $d = intval(date('j', strtotime($p->created_at)));
    $by_day[$d][] = $p;
}

$months_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$days_fr   = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];

$first_day = mktime(0,0,0,$month,1,$year);
$days_in   = date('t', $first_day);
$start_dow = intval(date('w', $first_day));

$prev_month = $month == 1 ? 12 : $month - 1;
$prev_year  = $month == 1 ? $year - 1 : $year;
$next_month = $month == 12 ? 1 : $month + 1;
$next_year  = $month == 12 ? $year + 1 : $year;

$status_colors = [
    'in_progress' => '#6366f1',
    'completed'   => '#10b981',
    'paused'      => '#f59e0b',
    'cancelled'   => '#ef4444',
];
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">Calendrier</h1>
            <span class="vb-page-sub"><?= $months_fr[$month] ?> <?= $year ?></span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="?page=vendbase-calendar&m=<?= $prev_month ?>&y=<?= $prev_year ?>" class="vb-btn vb-btn-ghost">← Précédent</a>
        <a href="?page=vendbase-calendar&m=<?= intval(date('m')) ?>&y=<?= intval(date('Y')) ?>" class="vb-btn vb-btn-secondary">Aujourd'hui</a>
        <a href="?page=vendbase-calendar&m=<?= $next_month ?>&y=<?= $next_year ?>" class="vb-btn vb-btn-ghost">Suivant →</a>
    </div>
</div>

<div class="vb-cal-wrap">
    <div class="vb-cal-header">
        <?php foreach ($days_fr as $d): ?><div class="vb-cal-dname"><?= $d ?></div><?php endforeach; ?>
    </div>
    <div class="vb-cal-grid">
        <?php for ($i = 0; $i < $start_dow; $i++): ?>
        <div class="vb-cal-day vb-cal-empty"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $days_in; $d++): ?>
        <?php
        $is_today = ($d == intval(date('j')) && $month == intval(date('m')) && $year == intval(date('Y')));
        $day_projects = $by_day[$d] ?? [];
        ?>
        <div class="vb-cal-day <?= $is_today ? 'vb-cal-today' : '' ?> <?= count($day_projects) ? 'vb-cal-has-projects' : '' ?>">
            <div class="vb-cal-day-num"><?= $d ?></div>
            <?php foreach (array_slice($day_projects, 0, 3) as $p): ?>
            <div class="vb-cal-event" style="background:<?= $status_colors[$p->status] ?? '#6366f1' ?>">
                <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . $p->id) ?>">
                    <?= esc_html(mb_substr($p->client_name, 0, 14)) ?>
                </a>
            </div>
            <?php endforeach; ?>
            <?php if (count($day_projects) > 3): ?>
            <div class="vb-cal-more">+<?= count($day_projects)-3 ?> autres</div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Month project list -->
<div class="vb-card" style="margin-top:24px">
    <div class="vb-card-header">
        <span>Projets de <?= $months_fr[$month] ?> <?= $year ?> (<?= count($projects) ?>)</span>
        <a href="<?= admin_url('admin.php?page=vendbase-new') ?>" class="vb-btn vb-btn-primary vb-btn-sm">+ Nouveau</a>
    </div>
    <?php if ($projects): ?>
    <table class="vb-table">
        <thead><tr><th>Date</th><th>Client</th><th>Type</th><th>Prix</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
        <tr>
            <td><?= date('d', strtotime($p->created_at)) ?></td>
            <td><a href="<?= admin_url('admin.php?page=vendbase-edit&id='.$p->id) ?>"><?= esc_html($p->client_name) ?></a></td>
            <td><?= esc_html($p->site_type ?: '—') ?></td>
            <td><?= number_format($p->prix,0,',',' ') ?> MAD</td>
            <td><span class="vb-dot" style="background:<?= $status_colors[$p->status] ?? '#ccc' ?>"></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="vb-empty">Aucun projet ce mois.</p>
    <?php endif; ?>
</div>

</div>
