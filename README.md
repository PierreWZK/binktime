# BinkTime · Vos tournois de poker, réglés comme une horloge

> Jour 17 du défi **30 jours, 30 projets** · Symfony · Twig · MySQL · ⏳ **À venir**

Un gestionnaire de tournois de poker entre amis : inscriptions, structure des blindes, **horloge** plein écran, éliminations, recaves et calcul des gains. **BinkTime** garde aussi le classement de la saison.

📄 Les règles détaillées sont dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Au programme

- Créer un tournoi : buy-in, tapis de départ, structure des blindes (modèles fournis).
- Inscrire les joueurs, gérer recaves et add-ons.
- **Horloge** plein écran : niveau, blindes, temps restant, prochain niveau, pause.
- Éliminations dans l'ordre, calcul des places payées et des gains.
- Classement de la saison (points par place).

## Lancer en local

```bash
composer install
# identifiants MySQL dans .env.local (jamais versionné)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony serve -d             # http://127.0.0.1:8000
```

## Mise en ligne

Prévue sur Coolify : **https://binktime.projectp.fr**.

## Stack

Symfony · Twig · MySQL.

## Avant de déclarer le projet terminé

> Rappel pour Claude : à faire sur chaque projet, sans que je le redemande (détail dans les sections 9 et 10 du cahier des charges).

- **Indexation** : `robots.txt`, `sitemap.xml`, balise canonical, données structurées JSON-LD (`WebApplication` rattachée à ProjectP) et lien « 30 jours, 30 projets » vers https://projectp.fr/ dans le pied de page. Vérifier en ligne, puis me rappeler de soumettre `https://binktime.projectp.fr/sitemap.xml` dans Google Search Console.
- **Aperçu du lien** : image Open Graph stylisée 1200 × 630 aux couleurs du projet et balises `og:*` / `twitter:card`, vérifiées en ligne.
- **Vitrine** : la mise à jour de `30days30projects` (README et `projects.js`) se fait **après ma validation**.
