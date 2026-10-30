# Jour 17/30 — BinkTime, vos tournois de poker, réglés comme une horloge

> Stack : **Symfony**, **Twig**, **MySQL** · hébergement **Coolify**, sous-domaine `binktime.projectp.fr`.
> Durée visée : **2 h 45** pour le MVP, les bonus ensuite.

Ce document est notre contrat : on le suit étape par étape, et toute décision qui s'en écarte est d'abord notée ici. Il s'ajuste au début de la journée.

## 1. L'idée

Un gestionnaire de tournois de poker entre amis : inscriptions, structure des blindes, **horloge** plein écran, éliminations, recaves et calcul des gains. **BinkTime** garde aussi le classement de la saison.

## 2. MVP — ce qui doit fonctionner à la fin

1. Créer un tournoi : buy-in, tapis de départ, structure des blindes (modèles fournis).
2. Inscrire les joueurs, gérer recaves et add-ons.
3. **Horloge** plein écran : niveau, blindes, temps restant, prochain niveau, pause.
4. Éliminations dans l'ordre, calcul des places payées et des gains.
5. Classement de la saison (points par place).
6. En ligne sur Coolify, avec un README à jour et un aperçu de lien (Open Graph).

### Périmètre volontairement limité

- Pas de jeu en ligne : on gère un tournoi réel.
- Pas d'argent réel géré par le site.

## 3. Cahier des charges fonctionnel

### Horloge

- Son à chaque changement de niveau, contrôle depuis un téléphone.
- Moyenne des tapis et nombre de joueurs restants.

### Gains

- Répartition modifiable (50 / 30 / 20 par défaut), arrondie au jeton près.

## 4. Cahier des charges technique

- Entités `Tournament`, `Level`, `Entry`, `Season`.
- Horloge en JavaScript côté client, état sauvegardé côté serveur (reprise après rechargement).
- Tests des calculs de gains et de points.
- Mise en ligne sur Coolify à chaque push sur la branche principale.

## 5. Critères de réussite

- [ ] La somme des gains vaut la cagnotte.
- [ ] L'horloge reprend au bon niveau après un rechargement.
- [ ] Le classement de saison se recalcule à chaque tournoi terminé.

## 6. Bonus facultatifs

- Tirage des tables.
- Statistiques par joueur.
- Mode Sit & Go.

## 7. Définition du « terminé »

Le MVP fonctionne, les critères de la section 5 sont cochés, le site est en ligne sur `binktime.projectp.fr`, le README a son lien et sa capture, et la carte du projet passe à « terminé » sur la vitrine.

## 8. À me demander avant de coder

À poser **avant d'écrire le code**, et attendre ma réponse :

1. **Le jeu quotidien.** Chaque projet a un jeu du jour pour faire revenir les visiteurs : le même tirage pour tout le monde (d'après la date), une tentative par jour, une série 🔥 et un résultat à partager. Me proposer deux ou trois idées adaptées au projet, puis attendre mon choix.
2. **Le look.** Me demander une référence visuelle sur laquelle se baser (un site, une marque, une ambiance, une capture). Le site doit être original et agréable à regarder, **pas « basique »** : une vraie identité (typographie, palette, mise en page, détails, animations), pas un gabarit générique. Si je n'ai pas d'idée, me proposer deux directions et attendre mon choix.

### Réponses (30/10/2026)

- **Nom** : le projet s'appelle **BinkTime**, en ligne sur `binktime.projectp.fr`.
- **Jeu du jour** : « Compte du tapis ». Un tapis de jetons (couleur = valeur) s'affiche quelques secondes, on retrouve le total en 4 essais avec indice plus/moins. Même tapis pour tous selon la date, série 🔥, résultat à partager.
- **Look** : maquettes fournies (charte « Noir & Or », site vitrine, horloge plein écran) dans `docs/maquettes/`. Cormorant Garamond + Manrope, noir onyx `#0A0A0B`, or champagne `#C9A96E`, ivoire `#F3EEE5`.
- **Accès** : deux modes.
  - **Comptes organisateurs** (un établissement qui organise des tournois réguliers) : page publique de l'établissement, visible par tous, avec ses tournois et son classement de saison.
  - **Sans compte** (tournoi à la maison) : un lien secret pour piloter, un lien public pour l'horloge.
- **Base locale** : SQLite en développement, MySQL en production sur Coolify.

## 9. Aperçu du lien (à faire sans me le redemander)

- Image Open Graph **1200 × 630** stylisée aux couleurs du projet : nom, accroche, technos et capture, générée avec `scripts/og/build-og.py` (ajouter le projet à la liste).
- Sur chaque page publique : `og:title`, `og:description`, `og:url`, `og:image` (adresse absolue), `og:image:width`, `og:image:height`, `og:image:alt` et `twitter:card` = `summary_large_image`.
- Vérifier en ligne que l'image répond en 200 et que les balises sont servies.

## 10. Indexation (à faire sans me le redemander)

- `robots.txt` : pages privées et routes techniques (API, JSON, admin) en `Disallow`, lien vers le sitemap.
- `sitemap.xml` : les pages publiques uniquement.
- Balise `canonical` sur chaque page publique ; `noindex` sur les pages privées.
- Données structurées JSON-LD sur l'accueil : `WebApplication`, avec `isPartOf` ProjectP (https://projectp.fr/).
- Lien « 30 jours, 30 projets » vers https://projectp.fr/ dans le pied de page.
- Site statique : ajouter le projet à `scripts/seo/apply-seo.py` et relancer le script.
- Vérifier en ligne, puis me rappeler de soumettre `https://binktime.projectp.fr/sitemap.xml` dans Google Search Console.

## 11. Vitrine 30days30projects (avec ma validation)

Une fois le projet en ligne, **me demander mon accord**, puis seulement :

- `projects.js` : l'entrée du jour 17 passe à `status: 'done'`, avec `name: 'BinkTime'`, `url`, `repo`, `image` et `description`.
- `preview-days-images/day17_binktime.png` : la capture de l'accueil, en 1600 × 900.
- `README.md` : la ligne du jour 17 dans le tableau des 30 projets.
- Commit et push du dépôt `30days30projects`.
