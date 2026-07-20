<?php
/**
 * v2.5 — Leads CRM (Inbox)
 * Demandes reçues de DEUX sources : formulaire du site + agent WhatsApp IA.
 */
if ( ! defined('ABSPATH') ) exit;

// --- Filtres ---
$f = [
    'search'            => sanitize_text_field($_GET['s'] ?? ''),
    'source'            => sanitize_text_field($_GET['source'] ?? ''),
    'priority'          => sanitize_text_field($_GET['priority'] ?? ''),
    'website_type'      => sanitize_text_field($_GET['type'] ?? ''),
    'language'          => sanitize_text_field($_GET['lang'] ?? ''),
    'status'            => sanitize_text_field($_GET['status'] ?? ''),
    'maintenance'       => isset($_GET['maint']) && $_GET['maint'] !== '' ? sanitize_text_field($_GET['maint']) : '',
    'preferred_contact' => sanitize_text_field($_GET['contact'] ?? ''),
    'date_from'         => sanitize_text_field($_GET['from'] ?? ''),
    'date_to'           => sanitize_text_field($_GET['to'] ?? ''),
    'archived'          => !empty($_GET['archived']) ? '1' : '0',
];

$leads = vb_get_leads($f);
$stats = vb_get_leads_stats();

// --- Référentiels ---
$L_status  = vb_lead_statuses();
$L_source  = vb_lead_sources();
$L_prio    = vb_lead_priorities();
$L_lang    = vb_lead_languages();
$L_type    = vb_lead_website_types();
$L_ptype   = vb_lead_project_types();
$L_domain  = vb_lead_domain_statuses();
$L_content = vb_lead_content_readiness();
$L_contact = vb_lead_contact_prefs();
$L_pkg     = vb_lead_packages();

$total      = intval($stats->total ?? 0);
$wa_count   = intval($stats->whatsapp_count ?? 0);
$web_count  = intval($stats->website_count ?? 0);
$new_c      = intval($stats->new_count ?? 0);
$high_open  = intval($stats->high_priority_open ?? 0);
$won_c      = intval($stats->won_count ?? 0);
$conv_rate  = $total > 0 ? round(($won_c / $total) * 100) : 0;

// Construit le jeu de données JS pour le panneau de détail (labels pré-calculés).
$leads_js = [];
foreach ( $leads as $l ) {
    $src = vb_normalize_lead_source($l->source);
    $leads_js[$l->id] = [
        'id'         => $l->id,
        'reference'  => $l->reference,
        'name'       => $l->full_name,
        'phone'      => $l->phone,
        'wa'         => vb_lead_wa_number($l->phone),
        // Lien WhatsApp avec le premier message déjà rédigé, dans la langue du lead.
        'wa_url'     => vb_wa_link_for_lead($l),
        'email'      => $l->email,
        'source'     => $src,
        'source_lbl' => $L_source[$src]['label'] ?? $src,
        'source_ico' => $L_source[$src]['icon'] ?? '',
        'priority'   => $l->priority ?: 'medium',
        'lang'       => $l->locale,
        'lang_lbl'   => $L_lang[$l->locale] ?? ($l->locale ? strtoupper($l->locale) : '—'),
        'type_lbl'   => $L_type[$l->website_type] ?? ($l->website_type ?: '—'),
        'ptype_lbl'  => $L_ptype[$l->project_type] ?? ($l->project_type ?: '—'),
        'products'   => $l->products_count ?: '—',
        'domain_lbl' => $L_domain[$l->domain_status] ?? ($l->domain_status ?: '—'),
        'content_lbl'=> $L_content[$l->content_ready] ?? ($l->content_ready ?: '—'),
        'maint'      => intval($l->maintenance),
        'contact_lbl'=> $L_contact[$l->preferred_contact] ?? ($l->preferred_contact ?: '—'),
        'pkg_lbl'    => $L_pkg[$l->package_interest] ?? '',
        'status'     => $l->status,
        'status_lbl' => $L_status[$l->status]['label'] ?? $l->status,
        'quoted'     => floatval($l->quoted_price),
        'note'       => $l->internal_note,
        'message'    => $l->message,
        'assigned'   => $l->assigned_to,
        'created'    => date('d/m/Y H:i', strtotime($l->created_at)),
        'contacted'  => $l->first_contact_at ? date('d/m/Y H:i', strtotime($l->first_contact_at)) : '',
        'project_id' => intval($l->project_id),
    ];
}
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">📥 Leads CRM</h1>
            <span class="vb-page-sub">Toutes les demandes entrantes — site web &amp; agent WhatsApp IA</span>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-leads' . ($f['archived']==='1' ? '' : '&archived=1')) ?>"
           class="vb-btn vb-btn-ghost"><?= $f['archived']==='1' ? '← Actifs' : '🗄️ Archivés' ?></a>
        <button class="vb-btn vb-btn-secondary" id="vb-show-wa-templates">
            <span class="dashicons dashicons-whatsapp"></span> Messages WhatsApp
        </button>
        <button class="vb-btn vb-btn-secondary" id="vb-show-api-config">
            <span class="dashicons dashicons-admin-network"></span> Connexions API
        </button>
    </div>
</div>

<!-- Stat cards -->
<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-email-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $total ?></div>
            <div class="vb-stat-label">Leads actifs</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-green">
        <div class="vb-stat-icon"><span class="dashicons dashicons-whatsapp"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $wa_count ?></div>
            <div class="vb-stat-label">💬 WhatsApp</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-teal">
        <div class="vb-stat-icon"><span class="dashicons dashicons-admin-site-alt3"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $web_count ?></div>
            <div class="vb-stat-label">🌐 Site web</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-red">
        <div class="vb-stat-icon"><span class="dashicons dashicons-warning"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $high_open ?></div>
            <div class="vb-stat-label">Priorité haute à traiter</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-bell"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $new_c ?></div>
            <div class="vb-stat-label">Jamais contactés</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $conv_rate ?><small>%</small></div>
            <div class="vb-stat-label">Taux de conversion</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="vb-filters vb-crm-filters" style="margin-top:20px">
    <form method="get" action="" class="vb-filter-form" style="flex-wrap:wrap;gap:8px">
        <input type="hidden" name="page" value="vendbase-leads">
        <?php if ($f['archived']==='1'): ?><input type="hidden" name="archived" value="1"><?php endif; ?>

        <input type="text" name="s" value="<?= esc_attr($f['search']) ?>" placeholder="Nom, téléphone, réf..." class="vb-input vb-input-search">

        <select name="source" class="vb-select">
            <option value="">Toutes sources</option>
            <?php foreach ($L_source as $k => $s): ?>
            <option value="<?= $k ?>" <?= selected($f['source'],$k,false) ?>><?= $s['icon'] ?> <?= esc_html($s['label']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="priority" class="vb-select">
            <option value="">Toute priorité</option>
            <?php foreach ($L_prio as $k => $p): ?>
            <option value="<?= $k ?>" <?= selected($f['priority'],$k,false) ?>><?= esc_html($p['label']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="type" class="vb-select">
            <option value="">Tout type de site</option>
            <?php foreach ($L_type as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= selected($f['website_type'],$k,false) ?>><?= esc_html($lbl) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="lang" class="vb-select">
            <option value="">Toute langue</option>
            <?php foreach ($L_lang as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= selected($f['language'],$k,false) ?>><?= esc_html($lbl) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="vb-select">
            <option value="">Tout statut</option>
            <?php foreach ($L_status as $k => $s): ?>
            <option value="<?= $k ?>" <?= selected($f['status'],$k,false) ?>><?= esc_html($s['label']) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="contact" class="vb-select">
            <option value="">Tout contact préféré</option>
            <?php foreach ($L_contact as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= selected($f['preferred_contact'],$k,false) ?>><?= esc_html($lbl) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="maint" class="vb-select">
            <option value="">Maintenance ?</option>
            <option value="1" <?= selected($f['maintenance'],'1',false) ?>>Oui</option>
            <option value="0" <?= selected($f['maintenance'],'0',false) ?>>Non</option>
        </select>

        <input type="date" name="from" value="<?= esc_attr($f['date_from']) ?>" class="vb-input" title="Du">
        <input type="date" name="to"   value="<?= esc_attr($f['date_to']) ?>" class="vb-input" title="Au">

        <button type="submit" class="vb-btn vb-btn-secondary">Filtrer</button>
        <a href="<?= admin_url('admin.php?page=vendbase-leads') ?>" class="vb-btn vb-btn-ghost">Reset</a>
    </form>
</div>

<!-- Inbox -->
<div class="vb-card" style="margin-top:16px">
<table class="vb-table vb-table-full vb-crm-table">
    <thead>
        <tr>
            <th>Priorité</th>
            <th>Client</th>
            <th>Source</th>
            <th>Langue</th>
            <th>Type de site</th>
            <th>Projet</th>
            <th>Maint.</th>
            <th>Statut</th>
            <th>Reçu</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($leads): ?>
    <?php foreach ($leads as $l):
        $src   = vb_normalize_lead_source($l->source);
        $prio  = $l->priority ?: 'medium';
        $wa_url = vb_wa_link_for_lead($l);
        $age_h = ( current_time('timestamp') - strtotime($l->created_at) ) / 3600;
        $cold  = ( $l->status === 'new' && $age_h > 24 );
    ?>
        <tr class="vb-lead-row vb-prio-<?= esc_attr($prio) ?>" data-id="<?= $l->id ?>">
            <td>
                <span class="vb-prio-dot vb-prio-<?= esc_attr($prio) ?>" title="Priorité <?= esc_attr($L_prio[$prio]['label'] ?? $prio) ?>"></span>
                <span class="vb-sub"><?= esc_html($L_prio[$prio]['label'] ?? $prio) ?></span>
            </td>
            <td>
                <strong class="vb-lead-open" data-id="<?= $l->id ?>" style="cursor:pointer"><?= esc_html($l->full_name) ?></strong>
                <?php if ($cold): ?><span class="vb-badge vb-badge-red">⏰</span><?php endif; ?>
                <div class="vb-sub"><?= esc_html($l->phone) ?></div>
                <div class="vb-sub" style="opacity:.6"><?= esc_html($l->reference) ?></div>
            </td>
            <td>
                <span class="vb-src-badge vb-src-<?= esc_attr($src) ?>">
                    <?= $L_source[$src]['icon'] ?? '' ?> <?= esc_html($L_source[$src]['label'] ?? $src) ?>
                </span>
            </td>
            <td><span class="vb-lang-badge"><?= esc_html($l->locale ? strtoupper($l->locale) : '—') ?></span></td>
            <td><span class="vb-type-badge"><?= esc_html($L_type[$l->website_type] ?? ($l->website_type ?: '—')) ?></span></td>
            <td><?= $l->project_type ? esc_html($L_ptype[$l->project_type] ?? $l->project_type) : '<span class="vb-muted">—</span>' ?></td>
            <td style="text-align:center"><?= intval($l->maintenance) ? '✅' : '<span class="vb-muted">—</span>' ?></td>
            <td>
                <select class="vb-select vb-status-select vb-lead-status" data-id="<?= $l->id ?>">
                    <?php foreach ($L_status as $k => $s): ?>
                    <option value="<?= $k ?>" <?= selected($l->status,$k,false) ?>><?= esc_html($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($l->project_id): ?>
                <div class="vb-sub"><a href="<?= admin_url('admin.php?page=vendbase-edit&id='.intval($l->project_id)) ?>" class="vb-link">→ Projet #<?= intval($l->project_id) ?></a></div>
                <?php endif; ?>
            </td>
            <td><?= date('d/m/Y', strtotime($l->created_at)) ?><div class="vb-sub"><?= date('H:i', strtotime($l->created_at)) ?></div></td>
            <td class="vb-actions-cell vb-crm-actions">
                <button class="vb-action-btn vb-lead-open" data-id="<?= $l->id ?>" title="Ouvrir"><span class="dashicons dashicons-visibility"></span></button>
                <?php if ($l->phone): ?><a class="vb-action-btn" href="tel:<?= esc_attr(preg_replace('/[^0-9+]/','',$l->phone)) ?>" title="Appeler"><span class="dashicons dashicons-phone"></span></a><?php endif; ?>
                <?php if ($wa_url): ?><a class="vb-action-btn" href="<?= esc_url($wa_url) ?>" target="_blank" rel="noopener" title="WhatsApp — message pré-rempli en <?= esc_attr(strtoupper(vb_wa_lead_language($l))) ?>"><span class="dashicons dashicons-whatsapp"></span></a><?php endif; ?>
                <?php if ($l->email): ?><a class="vb-action-btn" href="mailto:<?= esc_attr($l->email) ?>" title="Email"><span class="dashicons dashicons-email"></span></a><?php endif; ?>
                <?php if (!$l->project_id): ?><button class="vb-action-btn vb-lead-convert" data-id="<?= $l->id ?>" title="Convertir en projet"><span class="dashicons dashicons-migrate"></span></button><?php endif; ?>
                <button class="vb-action-btn vb-lead-archive" data-id="<?= $l->id ?>" data-archived="<?= $f['archived']==='1' ? '1' : '0' ?>" title="<?= $f['archived']==='1' ? 'Désarchiver' : 'Archiver' ?>"><span class="dashicons dashicons-archive"></span></button>
                <button class="vb-action-btn vb-btn-delete vb-lead-delete" data-id="<?= $l->id ?>" title="Supprimer"><span class="dashicons dashicons-trash"></span></button>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="10" class="vb-empty">
            Aucun lead ne correspond<?= $f['archived']==='1' ? ' (archives)' : '' ?>.<br>
            <span class="vb-sub">Les demandes du site et de l'agent WhatsApp apparaîtront ici automatiquement.</span>
        </td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

</div><!-- .vb-wrap -->

<!-- ======================= PANNEAU DÉTAIL LEAD ======================= -->
<div class="vb-modal" id="vb-lead-panel" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box vb-crm-panel">
        <div class="vb-modal-header">
            <h2 id="vb-p-name">—</h2>
            <span id="vb-p-ref" class="vb-sub"></span>
            <button class="vb-modal-close" type="button">&times;</button>
        </div>
        <div class="vb-modal-body">
            <div class="vb-crm-grid">

                <!-- Gauche : client + timeline -->
                <div class="vb-crm-col">
                    <div class="vb-section-label">Client</div>
                    <div class="vb-crm-field"><span>Téléphone</span><strong id="vb-p-phone">—</strong></div>
                    <div class="vb-crm-field"><span>Email</span><strong id="vb-p-email">—</strong></div>
                    <div class="vb-crm-field"><span>Langue</span><strong id="vb-p-lang">—</strong></div>
                    <div class="vb-crm-field"><span>Source</span><strong id="vb-p-source">—</strong></div>
                    <div class="vb-crm-field"><span>Contact préféré</span><strong id="vb-p-contact">—</strong></div>

                    <div class="vb-crm-actions-row" id="vb-p-quickactions"></div>

                    <div class="vb-section-label" style="margin-top:16px">Timeline</div>
                    <div class="vb-crm-field"><span>Reçu le</span><strong id="vb-p-created">—</strong></div>
                    <div class="vb-crm-field"><span>1er contact</span><strong id="vb-p-contacted">—</strong></div>
                    <div class="vb-crm-field"><span>Statut</span><strong id="vb-p-status">—</strong></div>

                    <?php if (!empty($leads_js)): ?>
                    <div class="vb-section-label" style="margin-top:16px">Message initial</div>
                    <div id="vb-p-message" class="vb-sub" style="white-space:pre-wrap">—</div>
                    <?php endif; ?>
                </div>

                <!-- Droite : qualification + notes -->
                <div class="vb-crm-col">
                    <div class="vb-section-label">Qualification</div>
                    <div class="vb-crm-field"><span>Type de site</span><strong id="vb-p-type">—</strong></div>
                    <div class="vb-crm-field"><span>Type de projet</span><strong id="vb-p-ptype">—</strong></div>
                    <div class="vb-crm-field"><span>Produits / services</span><strong id="vb-p-products">—</strong></div>
                    <div class="vb-crm-field"><span>Domaine &amp; hébergement</span><strong id="vb-p-domain">—</strong></div>
                    <div class="vb-crm-field"><span>Contenu prêt</span><strong id="vb-p-content">—</strong></div>
                    <div class="vb-crm-field"><span>Maintenance</span><strong id="vb-p-maint">—</strong></div>
                    <div class="vb-crm-field"><span>Formule</span><strong id="vb-p-pkg">—</strong></div>

                    <div class="vb-crm-field">
                        <span>Priorité</span>
                        <select id="vb-p-priority" class="vb-select">
                            <?php foreach ($L_prio as $k => $p): ?>
                            <option value="<?= $k ?>"><?= esc_html($p['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vb-crm-field">
                        <span>Devis (MAD)</span>
                        <input type="number" id="vb-p-quote" class="vb-input" min="0" step="100" style="width:120px">
                    </div>

                    <div class="vb-section-label" style="margin-top:12px">Note interne</div>
                    <textarea id="vb-p-note" class="vb-textarea" rows="4" placeholder="Budget évoqué, deadline, objections..."></textarea>
                    <button class="vb-btn vb-btn-secondary vb-btn-sm" id="vb-p-note-save" style="margin-top:8px">Enregistrer la note</button>
                </div>
            </div>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost vb-modal-close-btn">Fermer</button>
            <button class="vb-btn vb-btn-primary" id="vb-p-convert">Convertir en projet</button>
        </div>
    </div>
</div>

<!-- ================== MODAL MODÈLES DE MESSAGES WHATSAPP ================== -->
<?php $wa_tpl = vb_wa_templates(); $wa_langs = vb_wa_template_languages(); $wa_fb = vb_wa_fallback_language(); ?>
<div class="vb-modal" id="vb-wa-tpl-modal" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box vb-modal-md">
        <div class="vb-modal-header">
            <h2>💬 Premier message WhatsApp</h2>
            <button class="vb-modal-close" type="button">&times;</button>
        </div>
        <div class="vb-modal-body">
            <p class="vb-sub" style="margin-top:0">
                Cliquer sur le bouton WhatsApp d'un lead ouvre la conversation avec ce message
                <strong>déjà écrit</strong>, dans la langue choisie par le client.
                Rien n'est envoyé automatiquement : tu relis, tu modifies si besoin, puis tu envoies.
            </p>

            <div class="vb-notice vb-notice-box" style="margin-bottom:12px">
                Variables utilisables : <code>{name}</code> (nom complet),
                <code>{first_name}</code> (prénom), <code>{reference}</code> (référence du lead).
            </div>

            <?php foreach ($wa_langs as $code => $label): ?>
            <div class="vb-form-group">
                <label class="vb-label"><?= esc_html($label) ?> <span class="vb-sub">(<?= esc_html(strtoupper($code)) ?>)</span></label>
                <textarea class="vb-input vb-wa-tpl" data-lang="<?= esc_attr($code) ?>" rows="6"
                          style="font-family:inherit;line-height:1.6<?= $code === 'ar' ? ';direction:rtl;text-align:right' : '' ?>"><?= esc_textarea($wa_tpl[$code]) ?></textarea>
            </div>
            <?php endforeach; ?>

            <div class="vb-form-group">
                <label class="vb-label">Langue par défaut</label>
                <select class="vb-select" id="vb-wa-fallback">
                    <?php foreach ($wa_langs as $code => $label): ?>
                    <option value="<?= esc_attr($code) ?>" <?= selected($wa_fb, $code, false) ?>><?= esc_html($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="vb-sub">Utilisée quand le lead n'a pas déclaré de langue.</span>
            </div>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost" id="vb-wa-tpl-reset">Restaurer les modèles d'origine</button>
            <button class="vb-btn vb-btn-primary" id="vb-wa-tpl-save">Enregistrer</button>
        </div>
    </div>
</div>

<!-- ======================= MODAL CONNEXIONS API ======================= -->
<div class="vb-modal" id="vb-api-modal" style="display:none">
    <div class="vb-modal-backdrop"></div>
    <div class="vb-modal-box vb-modal-md">
        <div class="vb-modal-header">
            <h2>Connexions API</h2>
            <button class="vb-modal-close" type="button">&times;</button>
        </div>
        <div class="vb-modal-body">
            <div class="vb-section-label">🌐 Site web (formulaire de contact)</div>
            <div class="vb-form-group">
                <label class="vb-label">Endpoint</label>
                <input type="text" class="vb-input" readonly onclick="this.select()" value="<?= esc_url(rest_url('vendbase/v1/leads')) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">WP_LEADS_SECRET</label>
                <input type="text" class="vb-input" readonly onclick="this.select()" value="<?= esc_attr(vb_get_lead_api_secret()) ?>">
            </div>

            <div class="vb-section-label" style="margin-top:16px">💬 Agent WhatsApp IA (Cloudflare Worker)</div>
            <div class="vb-form-group">
                <label class="vb-label">Endpoint</label>
                <input type="text" class="vb-input" readonly onclick="this.select()" value="<?= esc_url(rest_url('younessweb/v1/whatsapp-lead')) ?>">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">X-API-Key</label>
                <input type="text" class="vb-input" id="vb-wa-secret" readonly onclick="this.select()" value="<?= esc_attr(vb_get_whatsapp_api_secret()) ?>">
            </div>
            <div class="vb-notice vb-notice-box">
                <strong>Test</strong> — simule une qualification WhatsApp :
                <pre style="white-space:pre-wrap;font-size:11px;margin-top:8px">curl -X POST "<?= esc_url(rest_url('younessweb/v1/whatsapp-lead')) ?>" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <?= esc_html(vb_get_whatsapp_api_secret()) ?>" \
  -d '{"source":"whatsapp","reference":"LEAD-00123","priority":"high","language":"ar","website_type":"ecommerce","project_type":"new","products_count":"1-10","has_domain":true,"content_ready":"partial","maintenance":true,"preferred_contact":"whatsapp","customer_name":"Ahmed","phone":"+2126xxxxxxxx"}'</pre>
            </div>
        </div>
        <div class="vb-modal-footer">
            <button class="vb-btn vb-btn-ghost" id="vb-regen-wa">Régénérer la clé WhatsApp</button>
            <button class="vb-btn vb-btn-primary vb-modal-close-btn">Fermer</button>
        </div>
    </div>
</div>

<script>
(function($){
    var LEADS = <?= wp_json_encode($leads_js) ?>;
    var current = null;

    function toast(msg, ok){
        var t = $('<div class="vb-toast '+(ok===false?'vb-toast-error':'vb-toast-success')+'">'+msg+'</div>');
        $('body').append(t); setTimeout(function(){ t.fadeOut(300,function(){t.remove();}); }, 2200);
    }

    // ----- Ouvrir le panneau détail -----
    function openLead(id){
        var d = LEADS[id]; if (!d) return; current = id;
        $('#vb-p-name').text(d.name);
        $('#vb-p-ref').text(d.reference);
        $('#vb-p-phone').text(d.phone || '—');
        $('#vb-p-email').text(d.email || '—');
        $('#vb-p-lang').text(d.lang_lbl);
        $('#vb-p-source').text((d.source_ico||'')+' '+d.source_lbl);
        $('#vb-p-contact').text(d.contact_lbl);
        $('#vb-p-created').text(d.created);
        $('#vb-p-contacted').text(d.contacted || '—');
        $('#vb-p-status').text(d.status_lbl);
        $('#vb-p-message').text(d.message || '—');
        $('#vb-p-type').text(d.type_lbl);
        $('#vb-p-ptype').text(d.ptype_lbl);
        $('#vb-p-products').text(d.products);
        $('#vb-p-domain').text(d.domain_lbl);
        $('#vb-p-content').text(d.content_lbl);
        $('#vb-p-maint').text(d.maint ? '✅ Oui' : '— Non');
        $('#vb-p-pkg').text(d.pkg_lbl || '—');
        $('#vb-p-priority').val(d.priority);
        $('#vb-p-quote').val(d.quoted > 0 ? Math.round(d.quoted) : '');
        $('#vb-p-note').val(d.note || '');

        var qa = [];
        if (d.phone) qa.push('<a class="vb-btn vb-btn-sm vb-btn-ghost" href="tel:'+d.phone.replace(/[^0-9+]/g,'')+'"><span class="dashicons dashicons-phone"></span> Appeler</a>');
        if (d.wa_url) qa.push('<a class="vb-btn vb-btn-sm vb-btn-ghost" target="_blank" rel="noopener" href="'+d.wa_url+'"><span class="dashicons dashicons-whatsapp"></span> WhatsApp</a>');
        if (d.email) qa.push('<a class="vb-btn vb-btn-sm vb-btn-ghost" href="mailto:'+d.email+'"><span class="dashicons dashicons-email"></span> Email</a>');
        $('#vb-p-quickactions').html(qa.join(' '));

        $('#vb-p-convert').toggle(!d.project_id).text(d.project_id ? 'Déjà converti' : 'Convertir en projet');
        $('#vb-lead-panel').show();
    }

    $(document).on('click', '.vb-lead-open', function(){ openLead($(this).data('id')); });

    // ----- Priorité (dans le panneau) -----
    $('#vb-p-priority').on('change', function(){
        var pr = $(this).val();
        $.post(VB.ajax, {action:'vb_update_lead_priority', nonce:VB.nonce, id:current, priority:pr}, function(r){
            if (r.success){ if(LEADS[current]) LEADS[current].priority = pr;
                var $dot = $('.vb-lead-row[data-id="'+current+'"] .vb-prio-dot');
                $dot.attr('class','vb-prio-dot vb-prio-'+pr);
                toast('Priorité mise à jour'); }
            else toast('Erreur', false);
        });
    });

    // ----- Devis -----
    $('#vb-p-quote').on('change', function(){
        var v = $(this).val();
        $.post(VB.ajax, {action:'vb_update_lead_quote', nonce:VB.nonce, id:current, quoted_price:v}, function(r){
            r.success ? toast('Devis enregistré') : toast('Erreur', false);
        });
    });

    // ----- Note -----
    $('#vb-p-note-save').on('click', function(){
        var v = $('#vb-p-note').val();
        $.post(VB.ajax, {action:'vb_update_lead_note', nonce:VB.nonce, id:current, internal_note:v}, function(r){
            if (r.success){ if(LEADS[current]) LEADS[current].note = v; toast('Note enregistrée'); }
            else toast('Erreur', false);
        });
    });

    // ----- Statut (ligne) -----
    $('.vb-lead-status').on('change', function(){
        var id=$(this).data('id'), st=$(this).val(), p={action:'vb_update_lead_status',nonce:VB.nonce,id:id,status:st};
        if (st==='lost'){ var r=prompt('Raison de la perte ? (prix, délai, concurrent, pas de réponse...)'); if(r===null){location.reload();return;} p.lost_reason=r; }
        $.post(VB.ajax, p, function(res){ res.success ? toast('Statut mis à jour') : toast(res.data||'Erreur', false); });
    });

    // ----- Convertir -----
    function convert(id){
        if (!confirm('Créer un projet à partir de ce lead ?\n\nLe client, le contact, le prix du devis et la qualification seront copiés dans Projets, et le lead passera en « Gagné ».')) return;
        $.post(VB.ajax, {action:'vb_convert_lead', nonce:VB.nonce, id:id}, function(r){
            if (r.success){ toast('Projet créé — ouverture...'); setTimeout(function(){ window.location=r.data.edit_url; }, 700); }
            else toast(r.data||'Conversion impossible', false);
        });
    }
    $(document).on('click', '.vb-lead-convert', function(){ convert($(this).data('id')); });
    $('#vb-p-convert').on('click', function(){ if(current) convert(current); });

    // ----- Archiver -----
    $('.vb-lead-archive').on('click', function(){
        var id=$(this).data('id'), isArch=$(this).data('archived')==1;
        $.post(VB.ajax, {action:'vb_archive_lead', nonce:VB.nonce, id:id, archived:isArch?0:1}, function(r){
            if (r.success){ $('.vb-lead-row[data-id="'+id+'"]').fadeOut(200,function(){$(this).remove();}); toast(isArch?'Désarchivé':'Archivé'); }
        });
    });

    // ----- Supprimer -----
    $('.vb-lead-delete').on('click', function(){
        var id=$(this).data('id');
        if (!confirm('Supprimer définitivement ce lead ?')) return;
        $.post(VB.ajax, {action:'vb_delete_lead', nonce:VB.nonce, id:id}, function(r){
            if (r.success) $('.vb-lead-row[data-id="'+id+'"]').fadeOut(200,function(){$(this).remove();});
        });
    });

    // ----- Modales -----
    $('#vb-show-api-config').on('click', function(){ $('#vb-api-modal').show(); });
    $('.vb-modal-close, .vb-modal-close-btn, .vb-modal-backdrop').on('click', function(){ $('.vb-modal').hide(); });
    $('#vb-regen-wa').on('click', function(){
        if (!confirm('Régénérer la clé WhatsApp ?\n\nLe Cloudflare Worker ne pourra plus envoyer de leads tant que tu n\'auras pas mis à jour la clé de ton côté.')) return;
        $.post(VB.ajax, {action:'vb_regenerate_whatsapp_secret', nonce:VB.nonce}, function(r){
            if (r.success){ $('#vb-wa-secret').val(r.data.secret); toast('Nouvelle clé générée'); }
        });
    });

    // ----- Modèles de messages WhatsApp -----
    $('#vb-show-wa-templates').on('click', function(){ $('#vb-wa-tpl-modal').show(); });

    function collectTemplates(){
        var t = {};
        $('.vb-wa-tpl').each(function(){ t[$(this).data('lang')] = $(this).val(); });
        return t;
    }

    /**
     * Recalcule les liens wa.me de la page après enregistrement, pour que le
     * nouveau message serve immédiatement — sans recharger la page.
     */
    function refreshWaLinks(templates, fallback){
        $.each(LEADS, function(id, d){
            if (!d.wa) return;
            // Même normalisation que côté PHP : 'ar-MA' -> 'ar'.
            var lang = (d.lang || '').toLowerCase().substring(0, 2);
            var tpl  = templates[lang] || templates[fallback] || '';
            var msg = tpl.replace(/\{name\}/g, d.name || '')
                         .replace(/\{first_name\}/g, (d.name || '').split(/\s+/)[0] || '')
                         .replace(/\{reference\}/g, d.reference || '');
            d.wa_url = 'https://wa.me/' + d.wa + '?text=' + encodeURIComponent(msg);
            $('.vb-lead-row[data-id="'+id+'"] a[href^="https://wa.me/"]').attr('href', d.wa_url);
        });
        if (current) openLead(current);
    }

    $('#vb-wa-tpl-save').on('click', function(){
        var $b = $(this).prop('disabled', true);
        $.post(VB.ajax, {
            action: 'vb_save_wa_templates',
            nonce: VB.nonce,
            templates: collectTemplates(),
            fallback_lang: $('#vb-wa-fallback').val()
        }, function(r){
            $b.prop('disabled', false);
            if (r.success){
                refreshWaLinks(r.data.templates, $('#vb-wa-fallback').val());
                $('#vb-wa-tpl-modal').hide();
                toast('Modèles enregistrés');
            } else {
                toast('Erreur lors de l\'enregistrement', false);
            }
        }).fail(function(){ $b.prop('disabled', false); toast('Erreur réseau', false); });
    });

    $('#vb-wa-tpl-reset').on('click', function(){
        if (!confirm('Restaurer les modèles d\'origine ?\n\nTes textes personnalisés seront perdus.')) return;
        $.post(VB.ajax, {action:'vb_reset_wa_templates', nonce:VB.nonce}, function(r){
            if (!r.success) return toast('Erreur', false);
            $('.vb-wa-tpl').each(function(){
                var l = $(this).data('lang');
                if (r.data.templates[l] !== undefined) $(this).val(r.data.templates[l]);
            });
            refreshWaLinks(r.data.templates, $('#vb-wa-fallback').val());
            toast('Modèles d\'origine restaurés');
        });
    });
})(jQuery);
</script>
