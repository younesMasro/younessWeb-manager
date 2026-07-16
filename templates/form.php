<?php
if ( ! defined('ABSPATH') ) exit;

$id      = intval($_GET['id'] ?? 0);
$project = $id ? vb_get_project($id) : null;
$is_edit = (bool) $project;

// Default empty
$p = (object) [
    'id'=>'','client_name'=>'','client_phone'=>'','client_email'=>'','client_city'=>'',
    'site_type'=>'','site_url'=>'','admin_url'=>'','admin_user'=>'','admin_pass'=>'',
    'hosting'=>0,'hosting_provider'=>'','hosting_price'=>0,'hosting_expiry'=>'',
    'domain'=>'','domain_price'=>0,'domain_expiry'=>'',
    'prix'=>0,'avance'=>0,'status'=>'in_progress',
    'start_date'=>date('Y-m-d'),'delivery_date'=>'','notes'=>'','tags'=>'',
    'tracking_enabled'=>0,'tracking_price'=>0,'tracking_start_date'=>'','tracking_note'=>'',
];
if ($project) foreach ((array)$project as $key => $val) $p->$key = $val;

$site_types = ['Vitrine','E-commerce','Portfolio','Blog','Application web','Landing Page','Immobilier','Hôtel & Restaurant','Médical','Éducation','Autre'];
$status_options = ['in_progress'=>'En cours','completed'=>'Terminé','paused'=>'En pause','cancelled'=>'Annulé'];
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title"><?= $is_edit ? 'Modifier le projet' : 'Nouveau projet' ?></h1>
            <?php if ($is_edit): ?>
            <span class="vb-page-sub">#<?= $p->id ?> — <?= esc_html($p->client_name) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="vb-header-right">
        <a href="<?= admin_url('admin.php?page=vendbase-projects') ?>" class="vb-btn vb-btn-ghost">← Retour</a>
    </div>
</div>

<form id="vb-project-form" method="post">
    <?= wp_nonce_field('vb_nonce', 'nonce', true, false) ?>
    <input type="hidden" name="id" value="<?= intval($p->id) ?>">

<div class="vb-form-grid">

<!-- ── Col A ── -->
<div class="vb-form-col">

<!-- Client Info -->
<div class="vb-card">
    <div class="vb-card-header"><span>👤 Informations client</span></div>
    <div class="vb-form-group">
        <label class="vb-label">Nom du client *</label>
        <input type="text" name="client_name" class="vb-input" value="<?= esc_attr($p->client_name) ?>" required placeholder="Ahmed Benali">
    </div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Téléphone</label>
            <input type="tel" name="client_phone" class="vb-input" value="<?= esc_attr($p->client_phone) ?>" placeholder="+212 6XX XXX XXX">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Email</label>
            <input type="email" name="client_email" class="vb-input" value="<?= esc_attr($p->client_email) ?>" placeholder="client@email.com">
        </div>
    </div>
    <div class="vb-form-group">
        <label class="vb-label">Ville</label>
        <input type="text" name="client_city" class="vb-input" value="<?= esc_attr($p->client_city) ?>" placeholder="Casablanca">
    </div>
</div>

<!-- Site Info -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>🌐 Site web</span></div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Type de site</label>
            <select name="site_type" class="vb-select">
                <option value="">— Sélectionner —</option>
                <?php foreach ($site_types as $st): ?>
                <option value="<?= esc_attr($st) ?>" <?= selected($p->site_type, $st, false) ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Statut projet</label>
            <select name="status" class="vb-select">
                <?php foreach ($status_options as $k => $v): ?>
                <option value="<?= $k ?>" <?= selected($p->status, $k, false) ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="vb-form-group">
        <label class="vb-label">URL du site</label>
        <input type="url" name="site_url" class="vb-input" value="<?= esc_attr($p->site_url) ?>" placeholder="https://www.exemple.ma">
    </div>
    <div class="vb-form-group">
        <label class="vb-label">URL Admin (WP-Admin / Dashboard)</label>
        <input type="url" name="admin_url" class="vb-input" value="<?= esc_attr($p->admin_url) ?>" placeholder="https://exemple.ma/wp-admin">
    </div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Identifiant Admin</label>
            <input type="text" name="admin_user" class="vb-input" value="<?= esc_attr($p->admin_user) ?>" placeholder="admin">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Mot de passe Admin</label>
            <div class="vb-pass-wrap">
                <input type="password" name="admin_pass" id="vb-admin-pass" class="vb-input" value="<?= esc_attr($p->admin_pass) ?>" placeholder="••••••••">
                <button type="button" class="vb-pass-toggle" onclick="vbTogglePass()">👁</button>
            </div>
        </div>
    </div>
    <div class="vb-form-group">
        <label class="vb-label">Tags</label>
        <input type="text" name="tags" class="vb-input" value="<?= esc_attr($p->tags) ?>" placeholder="woocommerce, seo, rapide...">
    </div>
</div>

<!-- Dates -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>📅 Dates</span></div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Date de début</label>
            <input type="date" name="start_date" class="vb-input" value="<?= esc_attr($p->start_date) ?>">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Date de livraison</label>
            <input type="date" name="delivery_date" class="vb-input" value="<?= esc_attr($p->delivery_date) ?>">
        </div>
    </div>
</div>

</div><!-- col A -->

<!-- ── Col B ── -->
<div class="vb-form-col">

<!-- Financial -->
<div class="vb-card">
    <div class="vb-card-header"><span>💰 Finances</span></div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Prix total (MAD)</label>
            <input type="number" name="prix" id="vb-prix" class="vb-input" value="<?= esc_attr($p->prix) ?>" step="0.01" min="0" placeholder="0.00">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Avance reçue (MAD)</label>
            <input type="number" name="avance" id="vb-avance" class="vb-input" value="<?= esc_attr($p->avance) ?>" step="0.01" min="0" placeholder="0.00">
        </div>
    </div>
    <div class="vb-reste-display" id="vb-reste-display">
        <span>Reste à payer :</span>
        <strong id="vb-reste-val">0 MAD</strong>
    </div>
</div>

<!-- Hosting -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header">
        <span>🗄️ Hébergement</span>
        <label class="vb-toggle-wrap">
            <input type="checkbox" name="hosting" id="vb-hosting-toggle" value="1" <?= checked($p->hosting, 1, false) ?>>
            <span class="vb-toggle"></span>
        </label>
    </div>
    <div id="vb-hosting-fields" style="<?= $p->hosting ? '' : 'display:none' ?>">
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Hébergeur</label>
                <input type="text" name="hosting_provider" class="vb-input" value="<?= esc_attr($p->hosting_provider) ?>" placeholder="Hostinger, OVH...">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Prix hébergement / an (MAD)</label>
                <input type="number" name="hosting_price" class="vb-input" value="<?= esc_attr($p->hosting_price) ?>" step="0.01" min="0">
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Date expiration hébergement</label>
            <input type="date" name="hosting_expiry" class="vb-input" value="<?= esc_attr($p->hosting_expiry) ?>">
        </div>
    </div>
</div>

<!-- Domain -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>🔗 Domaine</span></div>
    <div class="vb-form-row">
        <div class="vb-form-group">
            <label class="vb-label">Nom de domaine</label>
            <input type="text" name="domain" class="vb-input" value="<?= esc_attr($p->domain) ?>" placeholder="exemple.ma">
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Prix domaine / an (MAD)</label>
            <input type="number" name="domain_price" class="vb-input" value="<?= esc_attr($p->domain_price) ?>" step="0.01" min="0">
        </div>
    </div>
    <div class="vb-form-group">
        <label class="vb-label">Date expiration domaine</label>
        <input type="date" name="domain_expiry" class="vb-input" value="<?= esc_attr($p->domain_expiry) ?>">
    </div>
</div>

<!-- Suivi mensuel (Tracking) -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header">
        <span>🔔 Suivi mensuel du site</span>
        <label class="vb-toggle-wrap">
            <input type="checkbox" name="tracking_enabled" id="vb-tracking-toggle" value="1" <?= checked($p->tracking_enabled, 1, false) ?>>
            <span class="vb-toggle"></span>
        </label>
    </div>
    <div id="vb-tracking-fields" style="<?= $p->tracking_enabled ? '' : 'display:none' ?>">
        <p class="vb-sub" style="margin:0 0 12px">Client abonné au suivi/maintenance du site après livraison (facturation mensuelle).</p>
        <div class="vb-form-row">
            <div class="vb-form-group">
                <label class="vb-label">Montant mensuel (MAD)</label>
                <input type="number" name="tracking_price" class="vb-input" value="<?= esc_attr($p->tracking_price) ?>" step="0.01" min="0" placeholder="500.00">
            </div>
            <div class="vb-form-group">
                <label class="vb-label">Date de début du suivi</label>
                <input type="date" name="tracking_start_date" class="vb-input" value="<?= esc_attr($p->tracking_start_date) ?>">
            </div>
        </div>
        <div class="vb-form-group">
            <label class="vb-label">Note (canal de paiement, rappel...)</label>
            <input type="text" name="tracking_note" class="vb-input" value="<?= esc_attr($p->tracking_note) ?>" placeholder="Ex: Paiement le 5 de chaque mois via virement">
        </div>
    </div>
</div>

<!-- Notes -->
<div class="vb-card" style="margin-top:16px">
    <div class="vb-card-header"><span>📝 Notes</span></div>
    <div class="vb-form-group">
        <textarea name="notes" class="vb-input vb-textarea" rows="5" placeholder="Remarques, détails spéciaux, login FTP..."><?= esc_textarea($p->notes) ?></textarea>
    </div>
</div>

<!-- Save btn -->
<div style="margin-top:20px;display:flex;gap:12px">
    <button type="submit" class="vb-btn vb-btn-primary vb-btn-lg">
        <span class="dashicons dashicons-saved"></span>
        <?= $is_edit ? 'Mettre à jour' : 'Créer le projet' ?>
    </button>
    <?php if ($is_edit): ?>
    <button type="button" class="vb-btn vb-btn-danger" id="vb-delete-btn" data-id="<?= $p->id ?>">
        <span class="dashicons dashicons-trash"></span> Supprimer
    </button>
    <?php endif; ?>
</div>

</div><!-- col B -->

</div><!-- .vb-form-grid -->
</form>

<div id="vb-toast" class="vb-toast" style="display:none"></div>

</div><!-- .vb-wrap -->

<script>
// Real-time reste calculator
(function() {
    function update() {
        var prix   = parseFloat(document.getElementById('vb-prix').value) || 0;
        var avance = parseFloat(document.getElementById('vb-avance').value) || 0;
        var reste  = prix - avance;
        document.getElementById('vb-reste-val').textContent = reste.toLocaleString('fr-MA') + ' MAD';
        document.getElementById('vb-reste-val').style.color = reste > 0 ? '#f59e0b' : '#10b981';
    }
    document.getElementById('vb-prix').addEventListener('input', update);
    document.getElementById('vb-avance').addEventListener('input', update);
    update();
})();

// Hosting toggle
document.getElementById('vb-hosting-toggle').addEventListener('change', function() {
    document.getElementById('vb-hosting-fields').style.display = this.checked ? '' : 'none';
});

// Tracking (Suivi) toggle
document.getElementById('vb-tracking-toggle').addEventListener('change', function() {
    document.getElementById('vb-tracking-fields').style.display = this.checked ? '' : 'none';
});

// Password toggle
function vbTogglePass() {
    var f = document.getElementById('vb-admin-pass');
    f.type = f.type === 'password' ? 'text' : 'password';
}

// Form submit
document.getElementById('vb-project-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var data = new FormData(this);
    data.append('action', 'vb_save_project');
    data.append('nonce', VB.nonce);
    var btn = this.querySelector('[type=submit]');
    btn.disabled = true;
    btn.textContent = 'Enregistrement...';

    fetch(VB.ajax, {method:'POST', body: data})
    .then(r => r.json())
    .then(r => {
        if (r.success) {
            vbToast('✅ Projet enregistré!', 'success');
            if (!document.querySelector('input[name=id]').value) {
                setTimeout(() => { window.location = VB.ajax.replace('admin-ajax.php','admin.php') + '?page=vendbase-edit&id=' + r.data.id; }, 800);
            }
        } else {
            vbToast('❌ ' + (r.data || 'Erreur lors de l\'enregistrement'), 'error');
            console.error('vb_save_project error:', r.data);
        }
        btn.disabled = false;
        btn.innerHTML = '<span class="dashicons dashicons-saved"></span> Enregistré';
    })
    .catch(function(err) {
        console.error('vb_save_project request failed:', err);
        vbToast('❌ Erreur de connexion au serveur. Voir la console.', 'error');
        btn.disabled = false;
        btn.innerHTML = 'Enregistrer';
    });
});

// Delete
var delBtn = document.getElementById('vb-delete-btn');
if (delBtn) {
    delBtn.addEventListener('click', function() {
        if (!confirm('Supprimer ce projet définitivement?')) return;
        jQuery.post(VB.ajax, {action:'vb_delete_project', nonce:VB.nonce, id:this.dataset.id}, function(r) {
            if (r.success) window.location = VB.ajax.replace('admin-ajax.php','admin.php') + '?page=vendbase-projects';
        });
    });
}

function vbToast(msg, type) {
    var t = document.getElementById('vb-toast');
    t.textContent = msg;
    t.className = 'vb-toast vb-toast-' + (type||'success');
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3000);
}
</script>
