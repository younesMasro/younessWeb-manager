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
| **📜 Contrats** | Contrats de création / maintenance, échéanciers, signature, PDF |
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

## Contrats

**YounessWeb → 📜 Contrats.** Trois modèles fournis (création, maintenance,
création + maintenance), personnalisables depuis le bouton « Modèles ».

Le contrat se crée en un clic depuis un projet : client, prix, avance et suivi
mensuel sont recopiés. Trois règles gouvernent le module :

1. **Snapshot, pas de jointure.** Les coordonnées et les montants sont *copiés*
   dans le contrat. Si le client change de téléphone six mois plus tard, le
   document signé ne bouge pas — sinon ce n'est plus une preuve.
2. **Un contrat signé est verrouillé.** Il faut le repasser en brouillon pour
   le corriger, et il ne peut pas être supprimé sans être annulé d'abord.
3. **La signature est le seul événement qui propage.** Elle pose la date de
   démarrage du projet et ouvre l'abonnement de maintenance. Elle ne touche
   jamais à l'argent encaissé : la trésorerie ne se déduit pas d'un document.
   L'écart contrat/projet est affiché, l'alignement reste un clic explicite.

La numérotation `CTR-2026-001` s'appuie sur un compteur qui ne redescend jamais,
même après suppression : deux contrats ne peuvent pas porter le même numéro.

### Le document

Le titre suit le **type réel** du contrat — modèle choisi *plus* clause de
maintenance. Un modèle « création » dont la maintenance est active s'annonce
comme un contrat mixte, et son article 1 le dit. Un titre saisi à la main n'est
jamais réécrit, et le titre enregistré dans un contrat existant fait toujours foi.

Le prestataire est un **freelance**, pas une société : les champs ICE, RC et IF
restent vides et **aucune mention légale n'apparaît** tant qu'un numéro n'a pas
été saisi (page « Modèles »). Même règle pour le client : tout champ vide fait
disparaître sa ligne, jamais de libellé suivi d'un tiret.

Le corps du contrat reste du **texte** dans les modèles ; `contract-render.php`
n'ajoute qu'une couche de mise en forme (titres, listes, tableaux). Un modèle
personnalisé continue donc de fonctionner tel quel.

### PDF

Aucune bibliothèque, aucun appel réseau. La fenêtre d'impression charge la
**même feuille de style que l'aperçu** (`assets/css/contract.css`) — ce qu'on
valide à l'écran est ce qui sort de l'imprimante — puis
`assets/js/contract-print.js` découpe le document en pages A4 réelles :

- en-tête courant sur les pages 2 et suivantes ;
- pied de page identique partout : numéro, site, `Page X / Y`, « Confidentiel » ;
- aucun titre orphelin en bas de page, tableaux coupés entre deux lignes avec
  en-tête répété, fin du contrat (signatures + QR) d'un seul tenant.

### Code QR

Optionnel, éteint par défaut (page « Modèles »). L'encodeur
(`includes/contract-qr.php`) est écrit dans le plugin : un QR servi par un
service tiers devient un carré vide le jour où ce service ferme, et un contrat
se réimprime des années plus tard. Sortie SVG, donc net à l'impression.
Conformité vérifiée matrice par matrice contre une implémentation de référence
dans `tests/contract-qr.test.php`.

## Développement local

Le plugin vit dans `wp-content/plugins/younessWeb-manager/`.
Tables créées automatiquement à l'activation :

- `{prefix}_vb_projects` — projets clients
- `{prefix}_vb_leads` — demandes du formulaire
- `{prefix}_vb_expenses` — dépenses et publicité
- `{prefix}_vb_contracts` — contrats clients
- `{prefix}_vb_invoices` — factures et devis

> Toute nouvelle table doit être déclarée dans `vb_backup_tables()`
> (`includes/backup.php`), sinon elle est absente des sauvegardes.

### Tests

Aucune dépendance : le harnais `tests/bootstrap.php` charge le vrai code du
plugin sur une base SQLite en mémoire.

```bash
for t in tests/*.test.php; do php "$t"; done
```

## Licence

GPL-2.0 — usage interne YounessWeb.
