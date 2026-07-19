# YounessWeb Manager — Rapport complet des fonctionnalités

**Version : 2.5.0** · Plugin WordPress interne de gestion de l'activité YounessWeb.
Document de référence pour planifier de nouvelles options.

---

## 1. Vue d'ensemble

Le plugin est le **centre de gestion (CRM + finances)** de l'activité :
il suit les clients, les projets, les demandes reçues via le site, l'argent
(entrées et dépenses), la rentabilité de la publicité, les factures, et
sauvegarde tout. Il est relié au site vitrine **younessweb.com** (Next.js).

Menu WordPress : **YounessWeb** (11 pages).

---

## 2. Base de données (4 tables)

| Table | Contenu |
|-------|---------|
| `wp_vb_projects` | Clients & projets (le cœur) |
| `wp_vb_leads` | Demandes reçues via le formulaire du site |
| `wp_vb_expenses` | Dépenses & budgets publicitaires |
| `wp_vb_invoices` | Factures & devis générés |

**`wp_vb_projects`** — champs : nom client, téléphone, email, ville, type de site,
URL du site, URL admin + identifiant + mot de passe, hébergement (oui/non,
fournisseur, prix, expiration), domaine (nom, prix, expiration), **prix**,
**avance**, **reste** (calculé automatiquement = prix − avance), statut
(en cours / terminé / en pause / annulé), date de début, date de livraison,
notes, tags, et un bloc **suivi mensuel** (abonnement maintenance : activé,
prix/mois, date de début, note).

**`wp_vb_leads`** — nom, téléphone, email, type de site voulu, formule
(essentiel/premium), statut du domaine, message, langue (fr/en/ar), **statut
du pipeline** (nouveau → contacté → devis → gagné / perdu), prix du devis
proposé, raison de perte, note interne, lien vers le projet créé, **source**
(utm_source / utm_medium / utm_campaign), referer, IP, date du 1er contact.

**`wp_vb_expenses`** — projet lié (optionnel), mois, année, **catégorie**
(Facebook Ads, Instagram Ads, Google Ads, TikTok Ads, Outils, Hébergement,
Freelance, Autre), libellé, montant, note, date.

**`wp_vb_invoices`** — type (facture/devis), sous-total, TVA (%), total, lignes.

---

## 3. Les 11 pages du plugin

### 3.1 · Dashboard
Vue d'ensemble : chiffre d'affaires, projets en cours, montants encaissés et
restant dus, indicateurs clés.

### 3.2 · 📩 Demandes clients *(pipeline commercial)*
Toutes les demandes du formulaire de younessweb.com, enregistrées
automatiquement. Pour chaque demande :
- **Pipeline** : Nouveau → Contacté → Devis → Gagné / Perdu (menu déroulant).
- **Bouton WhatsApp** direct (numéro marocain normalisé automatiquement).
- **Prix du devis** saisi en ligne (passe la demande en « Devis » tout seul).
- **Note interne** (budget évoqué, deadline, objections…).
- **Conversion en projet en 1 clic** (copie client + contact + prix, marque
  « Gagné », relie les deux).
- Alerte **⏰ en retard** si une demande n'est pas contactée depuis +24h.
- Cartes stats : demandes reçues, jamais contactées, devis en attente,
  **taux de conversion**, **délai moyen de 1re réponse**.
- Pastille rouge dans le menu = nombre de demandes non traitées.
- Bouton **« Connexion au site »** : affiche l'URL + la clé API à mettre
  dans le site Next.js, avec une commande de test.

### 3.3 · Projets
Liste complète des projets clients avec recherche et filtres (statut, type,
mois/année, suivi). Fiche détaillée : prix, avance, reste, hébergement,
domaine, accès admin. Modification rapide du statut et de l'avance. Bouton
WhatsApp. Lien vers le site et l'admin.

### 3.4 · Nouveau / Éditer *(formulaire projet)*
Création et modification d'un projet, tous champs (client, contact, site,
hébergement, domaine, prix, dates, notes, suivi mensuel).

### 3.5 · Statistiques
Évolution mensuelle du CA et des projets, répartition par type de site,
graphiques (Chart.js), filtrable par année/mois.

### 3.6 · Dépenses & Publicité
Suivi des dépenses par catégorie (Facebook / Instagram / Google / TikTok Ads,
outils, hébergement, freelance, autre). Graphique mensuel, totaux, répartition
pub vs hors-pub. Ajout/édition/suppression d'une dépense, liaison à un projet.

### 3.7 · 📈 Rentabilité ROI *(nouveau v2.4)*
Croise CA + dépenses pub + origine des demandes :
- **Vue d'ensemble** : CA, dépense pub, **bénéfice net**, **ROAS global**
  (retour par 1 MAD de pub), part du CA absorbée par la pub.
- **Détail par canal** (Facebook/Instagram/Google/TikTok) via l'utm_source
  des leads : **coût par lead**, **coût par client signé**, CA attribué,
  **ROAS**, bénéfice, taux de conversion. Fusionne les alias (fb→facebook).
- Graphique mensuel CA vs dépense pub.
- Message d'aide si des dépenses pub existent sans leads tagués.

### 3.8 · 🔔 Suivi clients *(revenu récurrent)*
Clients avec abonnement de maintenance mensuelle. Affiche le **MRR** (revenu
récurrent mensuel) et **ARR** (annuel estimé), ancienneté de chaque client,
montant/mois, bouton WhatsApp. Activation/désactivation du suivi en 1 clic.

### 3.9 · Calendrier
Vue calendrier mensuelle des projets par date, navigation mois par mois.

### 3.10 · Factures & Devis
Génération de factures et devis : lignes (quantité × prix), sous-total,
TVA (%), total. Stockés dans `wp_vb_invoices`.

### 3.11 · 💾 Sauvegarde *(nouveau v2.3)*
- **Export en 1 clic** : un ZIP contenant `backup.json` (restauration),
  les `.csv` (lisibles dans Excel sans WordPress), le **code complet du
  plugin** (réinstallable) et un `LISEZMOI.txt`.
- Option pour inclure ou non les **identifiants clients**.
- **Restauration** : upload d'un ZIP/JSON, mode « Ajouter » ou « Remplacer ».
- **Sauvegarde automatique hebdomadaire par email** (optionnelle).
- **Mises à jour du plugin** depuis GitHub (bouton « Vérifier maintenant »,
  champ token pour dépôt privé).
- Alerte dans le menu si aucune sauvegarde depuis +7 jours.

---

## 4. Intégrations & automatisations

### 4.1 · Connexion au site Next.js (younessweb.com)
- **Endpoint REST** : `POST /wp-json/vendbase/v1/leads`
- Authentification par **clé secrète partagée** (header `X-VB-Secret`).
- Chaque soumission du formulaire de contact est enregistrée automatiquement
  dans la table des demandes, avec sa langue et sa source (utm).
- Anti-doublon (même téléphone/email dans les 24h), protection honeypot.
- Best-effort : si WordPress est indisponible, la demande du visiteur passe
  quand même et part par email — aucun client perdu.

### 4.2 · Mise à jour automatique (GitHub)
- Le plugin se met à jour via les **releases GitHub**, avec le bouton
  « Mettre à jour » habituel de WordPress. Dépôt : `younesMasro/younessWeb-manager`.

### 4.3 · Sauvegarde email planifiée
- Tâche cron hebdomadaire envoyant la sauvegarde par email (hors identifiants).

---

## 5. Ce que le plugin NE fait PAS encore *(pistes de nouvelles options)*

- ❌ **Alertes d'expiration** hébergement / domaine (données stockées mais
  aucune notification automatique).
- ❌ **Chiffrement des mots de passe clients** (stockés en clair).
- ❌ **Rappels de relance** (leads non contactés, devis sans réponse).
- ❌ **Modèles de messages WhatsApp** pré-remplis multilingues (fr/en/ar).
- ❌ **Génération PDF** des factures/devis (données présentes, pas d'export PDF).
- ❌ **Portail client** (suivi du projet côté client).
- ❌ **Synchronisation du portfolio** vers younessweb.com.
- ❌ **Demande d'avis automatique** après livraison.
- ❌ **Notifications temps réel** (nouvelle demande → push/WhatsApp/Slack).
- ❌ **Objectifs & prévisions** (objectif de CA mensuel, suivi vs réalisé).

---

*Généré le 2026-07-19 · YounessWeb Manager v2.5.0*
