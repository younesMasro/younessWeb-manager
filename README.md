# YounessWeb Manager

Plugin WordPress interne de [YounessWeb](https://www.younessweb.com) — gestion des
projets clients, des demandes reçues via le site, des dépenses publicitaires et
des sauvegardes.

> Plugin privé à usage interne. Publié ici pour permettre la mise à jour
> automatique du site de production depuis GitHub.

## Ce que fait le plugin

| Page | Rôle |
|------|------|
| **Dashboard** | Vue d'ensemble : chiffre d'affaires, projets en cours, encaissements |
| **📩 Demandes** | Les demandes du formulaire de younessweb.com — pipeline commercial `Nouveau → Contacté → Devis → Gagné / Perdu`, conversion en projet en 1 clic |
| **Projets** | Fiche client complète : prix, avance, hébergement, domaine, accès |
| **Statistiques** | Évolution mensuelle, répartition par type de site |
| **Dépenses & Pub** | Budgets Facebook / Instagram / Google / TikTok, outils, freelances |
| **🔔 Suivi clients** | Abonnements de maintenance mensuelle (MRR / ARR) |
| **Calendrier** | Livraisons et échéances |
| **Factures & Devis** | Génération des documents |
| **💾 Sauvegarde** | Export complet (code + données) et restauration en 1 clic |

## Connexion avec le site Next.js

Le site younessweb.com envoie chaque soumission du formulaire de contact à ce
plugin, qui la stocke dans la table des demandes.

```
POST /wp-json/vendbase/v1/leads
X-VB-Secret: <clé partagée>
```

Côté Next.js, deux variables d'environnement suffisent :

```bash
WP_LEADS_ENDPOINT=https://younessweb.me/wp-json/vendbase/v1/leads
WP_LEADS_SECRET=<clé affichée dans WordPress>
```

La clé se trouve dans **YounessWeb → 📩 Demandes → « Connexion au site »**.

L'envoi est *best-effort* : si WordPress est indisponible, le visiteur voit
quand même sa demande acceptée et le lead part par email. Une panne du CRM ne
doit jamais coûter un client.

## Sauvegarde

**YounessWeb → 💾 Sauvegarde → Télécharger la sauvegarde complète** produit un
seul ZIP :

```
data/backup.json    Restauration en 1 clic
data/*.csv          Les mêmes données, lisibles dans Excel sans WordPress
plugin/             Le code complet du plugin, réinstallable
LISEZMOI.txt        La marche à suivre si le site est mort
```

Si l'hébergement n'est pas renouvelé ou que le site tombe : WordPress neuf →
copier `plugin/` → importer `backup.json` → tout revient.

⚠️ Si l'option « inclure les identifiants clients » est cochée, le ZIP contient
les mots de passe d'accès aux sites des clients **en clair**. À garder hors de
tout Drive partagé. La sauvegarde automatique par email ne les inclut jamais.

## Publier une mise à jour

Le site de production se met à jour depuis les releases GitHub, via le bouton
« Mettre à jour » habituel de WordPress.

```bash
# 1. Incrémenter la version (en-tête du plugin + constante VB_VERSION)
#    vendbase-manager.php :  Version: 2.3.1   /   define('VB_VERSION','2.3.1')

# 2. Construire le ZIP (le dossier interne doit s'appeler younessWeb-manager)
./build.sh

# 3. Publier la release
gh release create v2.3.1 younessWeb-manager.zip --title "v2.3.1" --notes "..."
```

Puis sur WordPress : **Extensions → Mettre à jour**, ou
**YounessWeb → 💾 Sauvegarde → Vérifier maintenant**.

Le dépôt étant public, aucun token n'est nécessaire. S'il repasse en privé,
renseigner un token GitHub (`Contents: Read-only`) dans la page Sauvegarde.

## Développement local

Le plugin vit dans `wp-content/plugins/younessWeb-manager/`.
Tables créées automatiquement à l'activation :

- `{prefix}_vb_projects` — projets clients
- `{prefix}_vb_leads` — demandes du formulaire
- `{prefix}_vb_expenses` — dépenses et publicité

## Licence

GPL-2.0 — usage interne YounessWeb.
