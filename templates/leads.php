<?php
/**
 * v2.2 — Demandes clients (leads) reçues via le formulaire de younessweb.com
 */
if ( ! defined('ABSPATH') ) exit;

$search  = sanitize_text_field($_GET['s'] ?? '');
$status  = sanitize_text_field($_GET['status'] ?? '');
$type    = sanitize_text_field($_GET['type'] ?? '');
$year    = sanitize_text_field($_GET['year'] ?? date('Y'));

$leads   = vb_get_leads([
    'search'       => $search,
    'status'       => $status,
    'website_type' => $type,
    'year'         => $year,
]);
$lstats  = vb_get_leads_stats('', $year);

$statuses   = vb_lead_statuses();
$type_labels    = vb_lead_website_types();
$package_labels = vb_lead_packages();
$domain_labels  = vb_lead_domain_statuses();

$total     = intval($lstats->total ?? 0);
$new_c     = intval($lstats->new_count ?? 0);
$won_c     = intval($lstats->won_count ?? 0);
$pipeline  = floatval($lstats->pipeline_value ?? 0);
$avg_resp  = $lstats->avg_response_hours !== null ? floatval($lstats->avg_response_hours) : null;
$conv_rate = $total > 0 ? round(($won_c / $total) * 100) : 0;

/** Normalise un numéro marocain vers le format wa.me. */
function vb_lead_wa_number( $phone ) {
    $n = preg_replace('/[^0-9]/', '', $phone);
    if ( strlen($n) === 10 && $n[0] === '0' ) $n = '212' . substr($n, 1);
    return $n;
}
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">📩 Demandes clients</h1>
            <span class="vb-page-sub">Formulaire de contact de younessweb.com — du premier message au projet signé</span>
        </div>
    </div>
    <div class="vb-header-right">
        <button class="vb-btn vb-btn-secondary" id="vb-show-api-config">
            <span class="dashicons dashicons-admin-network"></span> Connexion au site
        </button>
    </div>
</div>

<!-- Stat cards -->
<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-email-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $total ?></div>
            <div class="vb-stat-label">Demandes reçues en <?= esc_html($year) ?></div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-red">
        <div class="vb-stat-icon"><span class="dashicons dashicons-bell"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $new_c ?></div>
            <div class="vb-stat-label">Jamais contactés</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= number_format($pipeline, 0, ',', ' ') ?> <small>MAD</small></div>
            <div class="vb-stat-label">Devis en attente de réponse</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $conv_rate ?><small>%</small></div>
            <div class="vb-stat-label"><?= $won_c ?> gagnés sur <?= $total ?> demandes</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-clock"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value">
                <?= $avg_resp !== null ? ( $avg_resp < 1 ? '<1' : round($avg_resp) ) : '—' ?> <small>h</small>
            </div>
            <div class="vb-stat-label">Délai moyen de 1ère réponse</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="vb-filters" style="margin-top:20px">
    <form method="get" action="" class="vb-filter-form">
        <input type="hidden" name="page" value="vendbase-leads">
        <div class="vb-filter-group">
            <input type="text" name="s" value="<?= esc_attr($search) ?>" placeholder="Nom, téléphone, email, message..." class="vb-input vb-input-search">
        </div>
        <div class="vb-filter-group">
            <select name="status" class="vb-select">
                <option value="">Tous les statuts</option>
                <?php foreach ($statuses as $key => $s): ?>
                <option value="<?= esc_attr($key) ?>" <?= selected($status, $key, false) ?>><?= esc_html($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-filter-group">
            <select name="type" class="vb-select">
                <option value="">Tous les types</option>
                <?php foreach ($type_labels as $key => $label): ?>
                <option value="<?= esc_attr($key) ?>" <?= selected($type, $key, false) ?>><?= esc_html($label) ?></option>
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
            <a href="<?= admin_url('admin.php?page=vendbase-leads') ?>" class="vb-btn vb-btn-ghost">Reset</a>
        </div>
    </form>
</div>

<!-- Table des leads -->
<div class="vb-card" style="margin-top:16px">
<table class="vb-table vb-table-full">
    <thead>
        <tr>
            <th>Client</th>
            <th>Contact</th>
            <th>Demande</th>
            <th>Formule</th>
            <th>Devis (MAD)</th>
            <th>Statut</th>
            <th>Reçu</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($leads): ?>
    <?php foreach ($leads as $l): ?>
        <?php
            $wa      = vb_lead_wa_number($l->phone);
            $age_h   = ( current_time('timestamp') - strtotime($l->created_at) ) / 3600;
            $is_cold = ( $l->status === 'new' && $age_h > 24 );
        ?>
        <tr class="vb-lead-row" data-id="<?= $l->id ?>">
            <td>
                <strong><?= esc_html($l->full_name) ?></strong>
                <?php if ($is_cold): ?>
                    <span class="vb-badge vb-badge-red" title="Reçu il y a plus de 24h et jamais contacté">⏰ en retard</span>
                <?php endif; ?>
                <?php if ($l->locale): ?><div class="vb-sub">Langue : <?= esc_html(strtoupper($l->locale)) ?></div><?php endif; ?>
                <?php if ($l->utm_source): ?><div class="vb-sub">via <?= esc_html($l->utm_source) ?><?= $l->utm_campaign ? ' / ' . esc_html($l->utm_campaign) : '' ?></div><?php endif; ?>
            </td>
            <td class="vb-wa-cell">
                <?php if ($wa): ?>
                <a href="https://wa.me/<?= esc_attr($wa) ?>" target="_blank" class="vb-wa-btn" title="WhatsApp : <?= esc_attr($l->phone) ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <?php endif; ?>
                <div class="vb-sub"><?= esc_html($l->phone) ?></div>
                <?php if ($l->email): ?>
                <div class="vb-sub"><a href="mailto:<?= esc_attr($l->email) ?>" class="vb-link"><?= esc_html($l->email) ?></a></div>
                <?php endif; ?>
            </td>
            <td>
                <span class="vb-type-badge"><?= esc_html($type_labels[$l->website_type] ?? $l->website_type ?: '—') ?></span>
                <?php if ($l->domain_status): ?>
                <div class="vb-sub"><?= esc_html($domain_labels[$l->domain_status] ?? $l->domain_status) ?></div>
                <?php endif; ?>
                <?php if ($l->message): ?>
                <div class="vb-sub vb-lead-msg" title="<?= esc_attr($l->message) ?>">
                    « <?= esc_html( mb_strimwidth($l->message, 0, 70, '…') ) ?> »
                </div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($l->package_interest): ?>
                <span class="vb-badge vb-badge-<?= $l->package_interest === 'premium' ? 'purple' : ($l->package_interest === 'essentiel' ? 'blue' : 'orange') ?>">
                    <?= esc_html($package_labels[$l->package_interest] ?? $l->package_interest) ?>
                </span>
                <?php else: ?><span class="vb-muted">—</span><?php endif; ?>
            </td>
            <td>
                <input type="number" class="vb-input vb-lead-quote" data-id="<?= $l->id ?>"
                       value="<?= $l->quoted_price > 0 ? esc_attr(round($l->quoted_price)) : '' ?>"
                       placeholder="—" min="0" step="100" style="width:100px">
            </td>
            <td>
                <select class="vb-select vb-status-select vb-lead-status" data-id="<?= $l->id ?>">
                    <?php foreach ($statuses as $key => $s): ?>
                    <option value="<?= esc_attr($key) ?>" <?= selected($l->status, $key, false) ?>><?= esc_html($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($l->project_id): ?>
                <div class="vb-sub">
                    <a href="<?= admin_url('admin.php?page=vendbase-edit&id=' . intval($l->project_id)) ?>" class="vb-link">→ Projet #<?= intval($l->project_id) ?></a>
                </div>
                <?php endif; ?>
            </td>
            <td>
                <?= date('d/m/Y', strtotime($l->created_at)) ?>
                <div class="vb-sub"><?= date('H:i', strtotime($l->created_at)) ?></div>
            </td>
            <td class="vb-actions-cell">
                <?php if (!$l->project_id): ?>
                <button class="vb-action-btn vb-lead-convert" data-id="<?= $l->id ?>" title="Convertir en projet">
                    <span class="dashicons dashicons-migrate"></span>
                </button>
                <?php endif; ?>
                <button class="vb-action-btn vb-lead-note" data-id="<?= $l->id ?>"
                        data-note="<?= esc_attr($l->internal_note) ?>" title="Note interne">
                    <span class="dashicons dashicons-edit-page"></span>
                </button>
                <button class="vb-action-btn vb-btn-delete vb-lead-delete" data-id="<?= $l->id ?>" title="Supprimer">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="8" class="vb-empty">
            Aucune demande pour le moment.<br>
            <span class="vb-sub">Vérifie que le site younessweb.com est bien connecté (bouton « Connexion au site » en haut).</span>
        </td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- Modal : configuration de la connexion au site Next.js -->
<div class="vb-modal" id="vb-api-modal" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box vb-modal-md">
        <div class="vb-modal-header">
            <h2>Connexion du site younessweb.com</h2>
            <button class="vb-modal-close" type="button">&times;</button>
        </div>
        <div class="vb-modal-body">
            <p class="vb-sub">
                Le site Next.js envoie chaque demande du formulaire à ce plugin.
                Ajoute ces deux variables d'environnement dans le projet Next.js
                (fichier <code>.env.local</code> en local, et dans Vercel en production) :
            </p>

            <div class="vb-form-group">
                <label class="vb-label">WP_LEADS_ENDPOINT</label>
                <input type="text" class="vb-input" readonly onclick="this.select()"
                       value="<?= esc_url( rest_url('vendbase/v1/leads') ) ?>">
            </div>

            <div class="vb-form-group">
                <label class="vb-label">WP_LEADS_SECRET</label>
                <div class="vb-pass-wrap">
                    <input type="text" class="vb-input" id="vb-api-secret" readonly onclick="this.select()"
                           value="<?= esc_attr( vb_get_lead_api_secret() ) ?>">
                </div>
                <p class="vb-sub">Clé privée : ne la publie jamais dans le code du site (uniquement en variable d'environnement).</p>
            </div>

            <div class="vb-notice vb-notice-box">
                <strong>Test rapide</strong> — colle ceci dans un terminal pour simuler une demande :
                <pre style="white-space:pre-wrap;font-size:11px;margin-top:8px">curl -X POST "<?= esc_url( rest_url('vendbase/v1/leads') ) ?>" \
  -H "Content-Type: application/json" \
  -H "X-VB-Secret: <?= esc_html( vb_get_lead_api_secret() ) ?>" \
  -d '{"fullName":"Test Client","phone":"0600000000","websiteType":"ecommerce","packageInterest":"premium"}'</pre>
            </div>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost" id="vb-regen-secret">Régénérer la clé</button>
            <button class="vb-btn vb-btn-primary vb-modal-close-btn">Fermer</button>
        </div>
    </div>
</div>

<!-- Modal : note interne -->
<div class="vb-modal" id="vb-note-modal" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box">
        <div class="vb-modal-header">
            <h2>Note interne</h2>
            <button class="vb-modal-close" type="button">&times;</button>
        </div>
        <div class="vb-modal-body">
            <textarea class="vb-textarea" id="vb-note-text" rows="6"
                      placeholder="Budget évoqué, deadline, ce qu'il veut exactement, objections..."></textarea>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost vb-modal-close-btn">Annuler</button>
            <button class="vb-btn vb-btn-primary" id="vb-note-save">Enregistrer</button>
        </div>
    </div>
</div>

</div><!-- .vb-wrap -->

<script>
(function($){
    var toast = function(msg, ok){
        var t = $('<div class="vb-toast ' + (ok === false ? 'vb-toast-error' : 'vb-toast-success') + '">' + msg + '</div>');
        $('body').append(t);
        setTimeout(function(){ t.fadeOut(300, function(){ t.remove(); }); }, 2200);
    };

    /* ── Statut ── */
    $('.vb-lead-status').on('change', function(){
        var id = $(this).data('id'), status = $(this).val(), $sel = $(this);
        var payload = { action:'vb_update_lead_status', nonce:VB.nonce, id:id, status:status };

        if (status === 'lost') {
            var reason = prompt('Pourquoi ce client est perdu ? (prix, délai, concurrent, pas de réponse...)');
            if (reason === null) { location.reload(); return; }
            payload.lost_reason = reason;
        }
        $.post(VB.ajax, payload, function(r){
            r.success ? toast('Statut mis à jour') : toast(r.data || 'Erreur', false);
        });
    });

    /* ── Prix du devis ── */
    $('.vb-lead-quote').on('change', function(){
        var id = $(this).data('id'), price = $(this).val();
        $.post(VB.ajax, { action:'vb_update_lead_quote', nonce:VB.nonce, id:id, quoted_price:price }, function(r){
            if (!r.success) { toast(r.data || 'Erreur', false); return; }
            if (r.data && r.data.status) {
                $('.vb-lead-status[data-id="' + id + '"]').val(r.data.status);
                toast('Devis enregistré — statut passé à « Devis »');
            } else {
                toast('Devis enregistré');
            }
        });
    });

    /* ── Convertir en projet ── */
    $('.vb-lead-convert').on('click', function(){
        var id = $(this).data('id');
        if (!confirm('Créer un projet à partir de cette demande ?\n\nLe client, son contact et le prix du devis seront copiés dans Projets, et la demande passera en « Gagné ».')) return;
        $.post(VB.ajax, { action:'vb_convert_lead', nonce:VB.nonce, id:id }, function(r){
            if (r.success) {
                toast('Projet créé — ouverture de la fiche...');
                setTimeout(function(){ window.location = r.data.edit_url; }, 700);
            } else {
                toast(r.data || 'Conversion impossible', false);
            }
        });
    });

    /* ── Note interne ── */
    var noteId = null;
    $('.vb-lead-note').on('click', function(){
        noteId = $(this).data('id');
        $('#vb-note-text').val($(this).data('note') || '');
        $('#vb-note-modal').show();
    });
    $('#vb-note-save').on('click', function(){
        var text = $('#vb-note-text').val();
        $.post(VB.ajax, { action:'vb_update_lead_note', nonce:VB.nonce, id:noteId, internal_note:text }, function(r){
            if (r.success) {
                $('.vb-lead-note[data-id="' + noteId + '"]').data('note', text);
                $('#vb-note-modal').hide();
                toast('Note enregistrée');
            } else { toast('Erreur', false); }
        });
    });

    /* ── Suppression ── */
    $('.vb-lead-delete').on('click', function(){
        var id = $(this).data('id');
        if (!confirm('Supprimer définitivement cette demande ?')) return;
        $.post(VB.ajax, { action:'vb_delete_lead', nonce:VB.nonce, id:id }, function(r){
            if (r.success) { $('.vb-lead-row[data-id="' + id + '"]').fadeOut(200, function(){ $(this).remove(); }); }
        });
    });

    /* ── Modal API ── */
    $('#vb-show-api-config').on('click', function(){ $('#vb-api-modal').show(); });
    $('.vb-modal-close, .vb-modal-close-btn, .vb-modal-backdrop').on('click', function(){ $('.vb-modal').hide(); });

    $('#vb-regen-secret').on('click', function(){
        if (!confirm('Régénérer la clé ?\n\nLe site younessweb.com ne pourra plus envoyer de demandes tant que tu n\'auras pas mis à jour WP_LEADS_SECRET dans Vercel.')) return;
        $.post(VB.ajax, { action:'vb_regenerate_lead_secret', nonce:VB.nonce }, function(r){
            if (r.success) { $('#vb-api-secret').val(r.data.secret); toast('Nouvelle clé générée — mets-la à jour dans Vercel'); }
        });
    });
})(jQuery);
</script>
