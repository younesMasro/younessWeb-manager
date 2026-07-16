<?php
if ( ! defined('ABSPATH') ) exit;

// v2.1 addition — page dédiée aux clients "Suivi mensuel"
$search = sanitize_text_field($_GET['s'] ?? '');

$tracked_projects = vb_get_tracking_projects([ 'search' => $search ]);
$tstats           = vb_get_tracking_stats();

$active_count = intval($tstats->active_count ?? 0);
$mrr          = floatval($tstats->mrr ?? 0);
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">🔔 Suivi mensuel des clients</h1>
            <span class="vb-page-sub">Clients avec abonnement de suivi/maintenance après livraison</span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-projects&tracking=1') ?>" class="vb-btn vb-btn-secondary">
            <span class="dashicons dashicons-list-view"></span> Voir dans Projets
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-bell"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $active_count ?></div>
            <div class="vb-stat-label">Clients avec suivi actif</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($mrr, 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Revenu récurrent / mois (MRR)</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-teal">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($mrr * 12, 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Estimation annuelle (ARR)</div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="vb-filters" style="margin-top:20px">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-tracking">
        <div class="vb-filter-group">
            <input type="text" name="s" value="<?= esc_attr($search) ?>" placeholder="Rechercher un client, un site..." class="vb-input vb-input-search">
        </div>
        <div class="vb-filter-group">
            <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
            <a href="<?= admin_url('admin.php?page=vendbase-tracking') ?>" class="vb-btn vb-btn-ghost">Reset</a>
        </div>
    </form>
</div>

<!-- Tracked clients table -->
<div class="vb-card" style="margin-top:16px">
<table class="vb-table vb-table-full">
    <thead>
        <tr>
            <th>Client</th>
            <th>WhatsApp</th>
            <th>Site</th>
            <th>Montant / mois</th>
            <th>Début du suivi</th>
            <th>Ancienneté</th>
            <th>Note</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($tracked_projects): ?>
    <?php foreach ($tracked_projects as $p): ?>
        <?php
            $months_active = '—';
            if ( ! empty($p->tracking_start_date) ) {
                $start = new DateTime($p->tracking_start_date);
                $now   = new DateTime();
                $diff  = $start->diff($now);
                $months_active = max(0, $diff->y * 12 + $diff->m) . ' mois';
            }
        ?>
        <tr class="vb-tracking-row" data-id="<?= $p->id ?>">
            <td>
                <strong><?= esc_html($p->client_name) ?></strong>
                <?php if ($p->client_city): ?><div class="vb-sub"><?= esc_html($p->client_city) ?></div><?php endif; ?>
            </td>
            <td class="vb-wa-cell">
                <?php if ($p->client_phone):
                    $wa_number = preg_replace('/[^0-9]/', '', $p->client_phone);
                    if (strlen($wa_number) === 10 && $wa_number[0] === '0') $wa_number = '212' . substr($wa_number, 1);
                ?>
                <a href="https://wa.me/<?= esc_attr($wa_number) ?>" target="_blank" class="vb-wa-btn" title="WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
            </td>
            <td>
                <?php if ($p->site_url): ?>
                <a href="<?= esc_url($p->site_url) ?>" target="_blank" class="vb-url-link"><?= esc_html(parse_url($p->site_url, PHP_URL_HOST) ?: $p->site_url) ?></a>
                <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
            </td>
            <td><strong><?= number_format($p->tracking_price, 0, ',', ' ') ?> MAD</strong></td>
            <td><?= $p->tracking_start_date ? date('d/m/Y', strtotime($p->tracking_start_date)) : '—' ?></td>
            <td><?= $months_active ?></td>
            <td><span class="vb-sub"><?= esc_html($p->tracking_note ?: '—') ?></span></td>
            <td class="vb-actions-cell">
                <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . $p->id) ?>" class="vb-action-btn vb-btn-edit" title="Modifier"><span class="dashicons dashicons-edit"></span></a>
                <button class="vb-action-btn vb-btn-delete vb-tracking-stop" data-id="<?= $p->id ?>" title="Désactiver le suivi"><span class="dashicons dashicons-dismiss"></span></button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8" class="vb-empty">Aucun client avec suivi actif pour le moment. Active le "Suivi mensuel" depuis la fiche d'un projet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

</div><!-- .vb-wrap -->

<script>
document.querySelectorAll('.vb-tracking-stop').forEach(function(btn){
    btn.addEventListener('click', function(){
        if (!confirm('Désactiver le suivi mensuel pour ce client ?')) return;
        var id = this.dataset.id;
        jQuery.post(VB.ajax, {action:'vb_toggle_tracking', nonce:VB.nonce, id:id, enabled:0}, function(r){
            if (r.success) {
                var row = document.querySelector('.vb-tracking-row[data-id="'+id+'"]');
                if (row) row.remove();
            }
        });
    });
});
</script>
