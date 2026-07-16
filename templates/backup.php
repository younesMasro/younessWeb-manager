<?php
/**
 * v2.3 — Sauvegarde & restauration
 */
if ( ! defined('ABSPATH') ) exit;

global $wpdb;

$counts = [];
foreach ( vb_backup_tables() as $key => $table ) {
    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) ) === $table;
    $counts[$key] = $exists ? intval( $wpdb->get_var("SELECT COUNT(*) FROM $table") ) : 0;
}

$last_backup = get_option('vb_last_backup_at');
$auto_on     = (bool) get_option('vb_auto_backup_enabled');
$auto_email  = get_option('vb_auto_backup_email', get_option('admin_email'));
$next_auto   = wp_next_scheduled('vb_weekly_backup_event');

$done  = isset($_GET['vb_done'])  ? sanitize_text_field( rawurldecode($_GET['vb_done']) )  : '';
$error = isset($_GET['vb_error']) ? sanitize_text_field( rawurldecode($_GET['vb_error']) ) : '';

// "Sauvegarde ancienne" = jamais faite, ou il y a plus de 7 jours.
$stale = ! $last_backup || ( current_time('timestamp') - strtotime($last_backup) ) > 7 * DAY_IN_SECONDS;
?>
<div class="vb-wrap">

<div class="vb-header">
    <div class="vb-header-left">
        <img src="<?= VB_PLUGIN_URL ?>assets/img/logo.png" class="vb-logo" alt="">
        <div>
            <h1 class="vb-page-title">💾 Sauvegarde</h1>
            <span class="vb-page-sub">Tout récupérer sur ton ordinateur — même si le site ou l'hébergement disparaît</span>
        </div>
    </div>
</div>

<?php if ($done): ?>
<div class="vb-notice vb-notice-success" style="margin-bottom:16px">✅ <?= esc_html($done) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="vb-notice" style="margin-bottom:16px;border-left:4px solid #dc2626">⚠️ <?= esc_html($error) ?></div>
<?php endif; ?>

<!-- Etat -->
<div class="vb-cards-grid">
    <div class="vb-stat-card vb-card-blue">
        <div class="vb-stat-icon"><span class="dashicons dashicons-portfolio"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $counts['projects'] ?></div>
            <div class="vb-stat-label">Projets à sauvegarder</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-purple">
        <div class="vb-stat-icon"><span class="dashicons dashicons-email-alt"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $counts['leads'] ?></div>
            <div class="vb-stat-label">Demandes clients</div>
        </div>
    </div>
    <div class="vb-stat-card vb-card-orange">
        <div class="vb-stat-icon"><span class="dashicons dashicons-chart-pie"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value"><?= $counts['expenses'] ?></div>
            <div class="vb-stat-label">Dépenses enregistrées</div>
        </div>
    </div>
    <div class="vb-stat-card <?= $stale ? 'vb-card-red' : 'vb-card-green' ?>">
        <div class="vb-stat-icon"><span class="dashicons dashicons-<?= $stale ? 'warning' : 'yes-alt' ?>"></span></div>
        <div class="vb-stat-body">
            <div class="vb-stat-value" style="font-size:18px">
                <?= $last_backup ? date('d/m/Y', strtotime($last_backup)) : 'Jamais' ?>
            </div>
            <div class="vb-stat-label">Dernière sauvegarde<?= $stale ? ' — à refaire !' : '' ?></div>
        </div>
    </div>
</div>

<div class="vb-two-col" style="margin-top:20px">

    <!-- TELECHARGER -->
    <div class="vb-card">
        <div class="vb-card-header"><h2>⬇️ Tout télécharger maintenant</h2></div>
        <div style="padding:16px">
            <p class="vb-sub">
                Un seul fichier ZIP contenant <strong>le code du plugin + toutes tes données</strong>.
                Si le site tombe demain, ce fichier suffit à tout remonter ailleurs.
            </p>

            <ul style="margin:12px 0 16px;padding-left:18px;line-height:1.9">
                <li><code>data/backup.json</code> — pour restaurer en 1 clic</li>
                <li><code>data/*.csv</code> — tes données dans Excel, lisibles sans WordPress</li>
                <li><code>plugin/</code> — le plugin complet, réinstallable</li>
                <li><code>LISEZMOI.txt</code> — la marche à suivre si tout est cassé</li>
            </ul>

            <form method="get" action="<?= esc_url( admin_url('admin-post.php') ) ?>">
                <input type="hidden" name="action" value="vb_export_backup">
                <?php wp_nonce_field('vb_export_backup'); ?>

                <div class="vb-form-group" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px;margin-bottom:14px">
                    <label class="vb-toggle-wrap" style="align-items:flex-start">
                        <input type="checkbox" name="credentials" value="1" checked>
                        <span>
                            <strong>Inclure les identifiants des sites clients</strong>
                            <div class="vb-sub" style="margin-top:4px">
                                Les colonnes <code>admin_url</code>, <code>admin_user</code> et
                                <code>admin_pass</code> de tes projets. Sans elles la sauvegarde est
                                inoffensive, mais tu perds les accès aux sites de tes clients.
                                <br><strong>Si tu coches :</strong> ce ZIP contient des mots de passe en clair.
                                Ne le mets jamais sur un Drive partagé ni dans un email.
                            </div>
                        </span>
                    </label>
                </div>

                <button type="submit" class="vb-btn vb-btn-primary vb-btn-lg" style="width:100%">
                    <span class="dashicons dashicons-download"></span>
                    Télécharger la sauvegarde complète
                </button>
            </form>
        </div>
    </div>

    <!-- RESTAURER -->
    <div class="vb-card">
        <div class="vb-card-header"><h2>⬆️ Restaurer une sauvegarde</h2></div>
        <div style="padding:16px">
            <p class="vb-sub">
                Envoie ici un ZIP (ou un <code>backup.json</code>) déjà téléchargé.
                À utiliser sur un site neuf après une panne, ou pour rapatrier
                tes données depuis un autre WordPress.
            </p>

            <form method="post" action="<?= esc_url( admin_url('admin-post.php') ) ?>"
                  enctype="multipart/form-data" id="vb-import-form">
                <input type="hidden" name="action" value="vb_import_backup">
                <?php wp_nonce_field('vb_import_backup'); ?>

                <div class="vb-form-group">
                    <label class="vb-label">Fichier de sauvegarde</label>
                    <input type="file" name="backup_file" accept=".zip,.json" required class="vb-input">
                </div>

                <div class="vb-form-group">
                    <label class="vb-label">Mode de restauration</label>
                    <label class="vb-toggle-wrap" style="align-items:flex-start;margin-bottom:8px">
                        <input type="radio" name="mode" value="merge" checked>
                        <span><strong>Ajouter</strong>
                            <div class="vb-sub">Garde ce qui existe et ajoute le contenu du fichier. Sans risque, mais peut créer des doublons.</div>
                        </span>
                    </label>
                    <label class="vb-toggle-wrap" style="align-items:flex-start">
                        <input type="radio" name="mode" value="replace">
                        <span><strong style="color:#dc2626">Remplacer tout</strong>
                            <div class="vb-sub">Efface les projets, demandes et dépenses actuels, puis restaure le fichier. À réserver à un site vide ou cassé.</div>
                        </span>
                    </label>
                </div>

                <button type="submit" class="vb-btn vb-btn-secondary" style="width:100%">
                    <span class="dashicons dashicons-upload"></span> Restaurer
                </button>
            </form>
        </div>
    </div>

</div>

<!-- SAUVEGARDE AUTO -->
<div class="vb-card" style="margin-top:20px">
    <div class="vb-card-header"><h2>🔁 Sauvegarde automatique par email</h2></div>
    <div style="padding:16px">
        <p class="vb-sub">
            Une sauvegarde de tes données t'est envoyée par email chaque semaine.
            Ça ne remplace pas le ZIP complet (le code du plugin et les identifiants
            clients ne sont jamais envoyés par email) — c'est un filet de sécurité
            pour ne jamais repartir de zéro.
        </p>

        <form method="post" action="<?= esc_url( admin_url('admin-post.php') ) ?>">
            <input type="hidden" name="action" value="vb_save_backup_settings">
            <?php wp_nonce_field('vb_backup_settings'); ?>

            <div class="vb-form-row">
                <div class="vb-form-col">
                    <label class="vb-toggle-wrap">
                        <input type="checkbox" name="auto_backup_enabled" value="1" <?= checked($auto_on, true, false) ?>>
                        <span><strong>Activer l'envoi hebdomadaire</strong></span>
                    </label>
                </div>
                <div class="vb-form-col">
                    <label class="vb-label">Envoyer à</label>
                    <input type="email" name="auto_backup_email" class="vb-input" value="<?= esc_attr($auto_email) ?>" placeholder="younes.masroure@gmail.com">
                </div>
            </div>

            <?php if ($auto_on && $next_auto): ?>
            <p class="vb-sub" style="margin-top:8px">
                ⏱️ Prochaine sauvegarde automatique : <strong><?= date('d/m/Y à H:i', $next_auto) ?></strong>
            </p>
            <?php endif; ?>

            <button type="submit" class="vb-btn vb-btn-primary" style="margin-top:12px">Enregistrer</button>
        </form>
    </div>
</div>

<!-- MISES A JOUR -->
<div class="vb-card" style="margin-top:20px">
    <div class="vb-card-header"><h2>🚀 Mises à jour du plugin</h2></div>
    <div style="padding:16px">
        <p class="vb-sub">
            Le plugin se met à jour depuis GitHub, comme n'importe quelle extension WordPress.
            Tu développes sur ton ordinateur, tu publies une release, et ici le bouton
            « Mettre à jour » apparaît tout seul.
        </p>

        <div class="vb-form-row" style="align-items:center;margin-bottom:12px">
            <div class="vb-form-col">
                <span class="vb-label">Version installée</span>
                <div class="vb-stat-value" style="font-size:22px"><?= esc_html(VB_VERSION) ?></div>
            </div>
            <div class="vb-form-col">
                <span class="vb-label">Dépôt</span>
                <div><code><?= esc_html(VB_GITHUB_REPO) ?></code></div>
            </div>
            <div class="vb-form-col">
                <button class="vb-btn vb-btn-secondary" id="vb-check-update">
                    <span class="dashicons dashicons-update"></span> Vérifier maintenant
                </button>
            </div>
        </div>

        <div id="vb-update-result" style="display:none;margin-bottom:14px"></div>

        <form method="post" action="<?= esc_url( admin_url('admin-post.php') ) ?>">
            <input type="hidden" name="action" value="vb_save_github_token">
            <?php wp_nonce_field('vb_github_token'); ?>
            <div class="vb-form-group">
                <label class="vb-label">Token GitHub <span class="vb-sub">(obligatoire si le dépôt est privé)</span></label>
                <input type="password" name="github_token" class="vb-input"
                       value="<?= esc_attr( get_option('vb_github_token') ) ?>"
                       placeholder="github_pat_...">
                <p class="vb-sub">
                    À créer sur GitHub → Settings → Developer settings → Fine-grained tokens,
                    avec le seul accès <strong>Contents : Read-only</strong> sur ce dépôt.
                    Stocké en base, jamais dans le code du plugin.
                </p>
            </div>
            <button type="submit" class="vb-btn vb-btn-primary">Enregistrer le token</button>
        </form>
    </div>
</div>

</div><!-- .vb-wrap -->

<script>
jQuery('#vb-check-update').on('click', function(){
    var $btn = jQuery(this), $out = jQuery('#vb-update-result');
    $btn.prop('disabled', true).text('Vérification...');

    jQuery.post(VB.ajax, { action:'vb_check_update', nonce:VB.nonce }, function(r){
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Vérifier maintenant');
        if (!r.success) {
            $out.show().html('<div class="vb-notice" style="border-left:4px solid #dc2626">⚠️ ' + r.data + '</div>');
            return;
        }
        if (r.data.available) {
            $out.show().html(
                '<div class="vb-notice vb-notice-success">🎉 Version <strong>' + r.data.latest +
                '</strong> disponible (tu as la ' + r.data.current + ').<br>' +
                '<a href="' + r.data.url + '" class="vb-btn vb-btn-primary" style="margin-top:8px">Aller mettre à jour</a></div>'
            );
        } else {
            $out.show().html('<div class="vb-notice vb-notice-success">✅ Tu es déjà à jour (version ' + r.data.current + ').</div>');
        }
    });
});

document.getElementById('vb-import-form').addEventListener('submit', function(e){
    var mode = this.querySelector('input[name="mode"]:checked').value;
    if (mode === 'replace' &&
        !confirm('ATTENTION\n\nTous tes projets, demandes et dépenses actuels vont être EFFACÉS puis remplacés par le contenu du fichier.\n\nCette action est irréversible. Continuer ?')) {
        e.preventDefault();
    }
});
</script>
