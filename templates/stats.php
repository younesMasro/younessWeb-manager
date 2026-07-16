<?php
if ( ! defined('ABSPATH') ) exit;

$year  = intval($_GET['year'] ?? date('Y'));
$month = sanitize_text_field($_GET['month'] ?? '');

$stats        = vb_get_stats($month, $year);
$exp_totals   = vb_get_expenses_totals($month, $year);
$monthly_data = vb_get_monthly_chart($year);
$monthly_exp  = vb_get_monthly_expenses_chart($year);
$types_data   = vb_get_site_types_chart($year);

// Calculated KPIs
$ca_total     = floatval($stats->total_revenue   ?? 0);
$recu_total   = floatval($stats->total_received  ?? 0);
$pending      = floatval($stats->total_pending   ?? 0);
$total_exp    = floatval($exp_totals->total      ?? 0);
$total_ads    = floatval($exp_totals->total_ads  ?? 0);
$benefice_net = $recu_total - $total_exp;  // Bénéfice net = argent reçu - toutes les charges
$marge_nette  = $recu_total > 0 ? round(($benefice_net / $recu_total) * 100) : 0;

$months_fr = [''=> 'Toute l\'année','01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
    '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];

// Build monthly arrays with expenses
$by_month    = [];
foreach ($monthly_data as $row) $by_month[$row->month] = $row;
$by_month_exp= [];
foreach ($monthly_exp as $row) $by_month_exp[$row->month] = $row;
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">Statistiques</h1>
            <span class="vb-page-sub"><?= esc_html($months_fr[$month]) ?> <?= $year ?></span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-expenses&year='.$year.'&month='.$month) ?>" class="vb-btn vb-btn-secondary">💸 Gérer les dépenses</a>
    </div>
</div>

<div class="vb-filters">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-stats">
        <select name="year" class="vb-select"><?php for ($y=date('Y');$y>=2022;$y--): ?><option value="<?=$y?>" <?=selected($year,$y,false)?>><?=$y?></option><?php endfor;?></select>
        <select name="month" class="vb-select"><?php foreach($months_fr as $k=>$v):?><option value="<?=$k?>" <?=selected($month,$k,false)?>><?=$v?></option><?php endforeach;?></select>
        <button type="submit" class="vb-btn vb-btn-secondary">Appliquer</button>
    </form>
</div>

<!-- ── ROW 1 : CA + Reçu + Dépenses + Bénéfice Net ── -->
<div class="vb-cards-grid" style="grid-template-columns:repeat(4,1fr);margin-top:20px">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($ca_total, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">CA Total (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($recu_total, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">Reçu (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-red">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($total_exp, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">Total dépenses (MAD)</div>
            <?php if ($total_ads > 0): ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px">dont <?= number_format($total_ads,0,',',' ') ?> MAD ads</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="vb-stat-card <?= $benefice_net >= 0 ? 'vb-card-teal' : 'vb-card-red' ?>" style="border:2px solid <?= $benefice_net>=0?'#10b981':'#ef4444' ?>">
        <div class="vb-stat-icon"><span class="dashicons dashicons-<?= $benefice_net>=0?'thumbs-up':'warning' ?>"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value" style="color:<?= $benefice_net>=0?'#10b981':'#ef4444' ?>"><?= ($benefice_net<0?'-':'') . number_format(abs($benefice_net), 0, ',', ' ') ?></div>
            <div class="vb-stat-label">🏆 Bénéfice net (MAD)</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px">Marge nette : <?= $marge_nette ?>%</div>
        </div>
    </div>
</div>

<!-- ── ROW 2 : Projects counts + avg + rate ── -->
<div class="vb-cards-grid" style="grid-template-columns:repeat(5,1fr);margin-top:12px">
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-portfolio"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats->total_projects) ?></div>
            <div class="vb-stat-label">Total projets</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-flag" style="color:#10b981"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= intval($stats->completed) ?></div>
            <div class="vb-stat-label">Terminés</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-warning" style="color:#f59e0b"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($pending, 0, ',', ' ') ?></div>
            <div class="vb-stat-label">Reste à recevoir</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-area"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format(floatval($stats->avg_price), 0, ',', ' ') ?></div>
            <div class="vb-stat-label">Prix moyen (MAD)</div>
        </div>
    </div>
    <div class="vb-stat-card">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-pie"></span></div>
        <div class="vb-stat-body">
            <?php $rate = $ca_total > 0 ? round(($recu_total/$ca_total)*100) : 0; ?>
            <div class="vb-stat-value"><?= $rate ?>%</div>
            <div class="vb-stat-label">Taux encaissement</div>
        </div>
    </div>
</div>

<!-- ── ROW 3 : Ads breakdown mini cards ── -->
<?php if ($total_ads > 0 || floatval($exp_totals->tools??0) > 0): ?>
<div class="vb-ads-breakdown-row">
    <?php
    $ads_items = [
        'Facebook Ads'  => floatval($exp_totals->ads_facebook  ?? 0),
        'Instagram Ads' => floatval($exp_totals->ads_instagram ?? 0),
        'Google Ads'    => floatval($exp_totals->ads_google    ?? 0),
        'TikTok Ads'    => floatval($exp_totals->ads_tiktok    ?? 0),
        'Outils'        => floatval($exp_totals->tools         ?? 0),
        'Hébergement'   => floatval($exp_totals->hosting_exp   ?? 0),
        'Freelance'     => floatval($exp_totals->freelance     ?? 0),
        'Autre'         => floatval($exp_totals->other_exp     ?? 0),
    ];
    $colors = ['#1877f2','#e1306c','#ea4335','#010101','#6366f1','#06b6d4','#8b5cf6','#94a3b8'];
    $i = 0;
    foreach ($ads_items as $label => $amount):
        if ($amount <= 0) { $i++; continue; }
        $roi = $amount > 0 && $recu_total > 0 ? round(($recu_total / $amount)) : 0;
    ?>
    <div class="vb-ads-mini-card" style="border-left:3px solid <?= $colors[$i] ?>">
        <div class="vb-ads-mini-label" style="color:<?= $colors[$i] ?>"><?= $label ?></div>
        <div class="vb-ads-mini-amount"><?= number_format($amount, 0, ',', ' ') ?> MAD</div>
        <?php if (in_array($label, ['Facebook Ads','Instagram Ads','Google Ads','TikTok Ads']) && $roi > 0): ?>
        <div class="vb-ads-mini-roi">ROI estimé : <?= $roi ?>x</div>
        <?php endif; ?>
    </div>
    <?php $i++; endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Charts ── -->
<div class="vb-two-col" style="margin-top:28px">
    <div class="vb-card">
        <div class="vb-card-header"><span>CA vs Dépenses vs Bénéfice net — <?= $year ?></span></div>
        <div style="position:relative;height:280px;width:100%"><canvas id="vb-stats-main"></canvas></div>
    </div>
    <div class="vb-card">
        <div class="vb-card-header"><span>Répartition par type de site</span></div>
        <div style="position:relative;height:280px;width:100%"><canvas id="vb-stats-types"></canvas></div>
    </div>
</div>

<!-- ── Monthly table with expenses + net ── -->
<div class="vb-card" style="margin-top:24px">
    <div class="vb-card-header"><span>Détail financier mensuel — <?= $year ?></span></div>
    <table class="vb-table">
        <thead>
            <tr>
                <th>Mois</th>
                <th>Projets</th>
                <th>CA</th>
                <th>Reçu</th>
                <th class="vb-red">Dépenses</th>
                <th class="vb-red">Pub (Ads)</th>
                <th style="color:#10b981">Bénéfice net</th>
                <th>Marge</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $month_names   = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $tot_p=$tot_ca=$tot_rec=$tot_exp_t=$tot_ads_t=$tot_net=0;
        for ($m = 1; $m <= 12; $m++):
            $row    = $by_month[$m]     ?? null;
            $exprow = $by_month_exp[$m] ?? null;
            $pr  = $row    ? intval($row->projects)       : 0;
            $rv  = $row    ? floatval($row->revenue)      : 0;
            $rc  = $row    ? floatval($row->received)     : 0;
            $ex  = $exprow ? floatval($exprow->total)     : 0;
            $ads = $exprow ? floatval($exprow->ads)       : 0;
            $net = $rc - $ex;
            $mg  = $rc > 0 ? round(($net / $rc) * 100) : 0;
            $tot_p+=$pr; $tot_ca+=$rv; $tot_rec+=$rc; $tot_exp_t+=$ex; $tot_ads_t+=$ads; $tot_net+=$net;
            $is_current = ($m == intval(date('m')) && $year == date('Y'));
        ?>
        <tr class="<?= $is_current ? 'vb-current-month' : '' ?>">
            <td>
                <a href="<?= admin_url('admin.php?page=vendbase-stats&year='.$year.'&month='.sprintf('%02d',$m)) ?>" style="font-weight:600">
                    <?= $month_names[$m-1] ?>
                </a>
                <?= $is_current ? ' <span class="vb-now-badge">Actuel</span>' : '' ?>
            </td>
            <td><?= $pr ?: '<span class="vb-muted">0</span>' ?></td>
            <td><?= $rv ? number_format($rv,0,',',' ').' MAD' : '<span class="vb-muted">—</span>' ?></td>
            <td class="green"><?= $rc ? number_format($rc,0,',',' ') : '<span class="vb-muted">—</span>' ?></td>
            <td class="<?= $ex>0?'vb-red':'' ?>"><?= $ex ? number_format($ex,0,',',' ') : '<span class="vb-muted">—</span>' ?></td>
            <td style="color:#1877f2;font-size:12px"><?= $ads ? number_format($ads,0,',',' ') : '<span class="vb-muted">—</span>' ?></td>
            <td>
                <?php if ($rv > 0 || $ex > 0): ?>
                <strong style="color:<?= $net>=0?'#10b981':'#ef4444' ?>"><?= ($net<0?'-':'').number_format(abs($net),0,',',' ') ?></strong>
                <?php else: echo '<span class="vb-muted">—</span>'; endif; ?>
            </td>
            <td>
                <?php if ($rc > 0): ?>
                <div style="display:flex;align-items:center;gap:6px">
                    <div style="flex:1;background:#f1f5f9;border-radius:4px;height:6px;min-width:50px">
                        <div style="width:<?= max(0,min(100,$mg)) ?>%;background:<?= $mg>=50?'#10b981':($mg>=0?'#f59e0b':'#ef4444') ?>;height:6px;border-radius:4px"></div>
                    </div>
                    <span style="font-size:12px;color:<?= $mg>=0?'inherit':'#ef4444' ?>;min-width:36px"><?= $mg ?>%</span>
                </div>
                <?php else: echo '<span class="vb-muted">—</span>'; endif; ?>
            </td>
        </tr>
        <?php endfor; ?>
        <tr class="vb-total-row">
            <td><strong>TOTAL</strong></td>
            <td><strong><?= $tot_p ?></strong></td>
            <td><strong><?= number_format($tot_ca,0,',',' ') ?> MAD</strong></td>
            <td class="green"><strong><?= number_format($tot_rec,0,',',' ') ?></strong></td>
            <td class="vb-red"><strong><?= number_format($tot_exp_t,0,',',' ') ?></strong></td>
            <td style="color:#1877f2"><strong><?= number_format($tot_ads_t,0,',',' ') ?></strong></td>
            <td><strong style="color:<?= $tot_net>=0?'#10b981':'#ef4444' ?>;font-size:15px"><?= ($tot_net<0?'-':'').number_format(abs($tot_net),0,',',' ') ?> MAD</strong></td>
            <td><strong><?= $tot_rec>0 ? round((($tot_rec-$tot_exp_t)/$tot_rec)*100) : 0 ?>%</strong></td>
        </tr>
        </tbody>
    </table>
</div>

<?php if (!$month): ?>
<!-- ── No-expense warning ── -->
<?php
$total_exp_check = floatval($exp_totals->total ?? 0);
if ($total_exp_check == 0): ?>
<div class="vb-notice-box">
    ⚠️ <strong>Aucune dépense enregistrée pour <?= $year ?>.</strong>
    Le bénéfice net affiché n'est pas réel.
    <a href="<?= admin_url('admin.php?page=vendbase-expenses&year='.$year) ?>" class="vb-btn vb-btn-sm vb-btn-primary" style="margin-left:12px">Ajouter mes dépenses →</a>
</div>
<?php endif; ?>
<?php endif; ?>

</div>

<script>
(function(){
const monthly = <?= json_encode($monthly_data) ?>;
const monthlyExp = <?= json_encode($monthly_exp) ?>;
const types   = <?= json_encode($types_data) ?>;
const labels  = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
const ca=Array(12).fill(0), received=Array(12).fill(0), expenses=Array(12).fill(0), net=Array(12).fill(0);
monthly.forEach(m=>{
    ca[m.month-1]=parseFloat(m.revenue||0);
    received[m.month-1]=parseFloat(m.received||0);
});
monthlyExp.forEach(m=>{ expenses[m.month-1]=parseFloat(m.total||0); });
for(let i=0;i<12;i++) net[i]=received[i]-expenses[i];

new Chart(document.getElementById('vb-stats-main'),{
    type:'line',
    data:{labels, datasets:[
        {label:'CA (MAD)',       data:ca,       borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.08)', fill:true, tension:0.4, borderWidth:2, pointRadius:4},
        {label:'Reçu',           data:received, borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.08)', fill:true, tension:0.4, borderWidth:2, pointRadius:4},
        {label:'Dépenses',       data:expenses, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.06)',  fill:true, tension:0.4, borderWidth:2, pointRadius:3, borderDash:[4,3]},
        {label:'Bénéfice net',   data:net,      borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.08)', fill:false,tension:0.4, borderWidth:3, pointRadius:5, pointStyle:'circle'},
    ]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},
        scales:{y:{beginAtZero:false,grid:{color:'rgba(0,0,0,0.04)'}},x:{grid:{display:false}}}}
});

if (types.length) {
    const colors=['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316','#84cc16','#ec4899','#64748b'];
    new Chart(document.getElementById('vb-stats-types'),{
        type:'bar',
        data:{labels:types.map(t=>t.site_type||'Autre'), datasets:[{label:'Projets',data:types.map(t=>parseInt(t.count)),backgroundColor:colors,borderRadius:6}]},
        options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},
            scales:{x:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.04)'}},y:{grid:{display:false}}}}
    });
}
})();
</script>
