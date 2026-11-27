<!-- phil-dev source-sha256=7aa91ae8a9360e9cc46ba7d6128517bcf39fb10d295162e71b0a9fc9004c1133 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/map-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-25

### Modifié

- Les prérequis de développement acceptent `phil/ux-kit` 0.6, la version mineure de sa branche principale et aucune plus
  ancienne, pour que le paquet s'installe seul à côté de ce paquet.
- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31, `symfony/cache-contracts` 3.8,
  `symfony/http-client-contracts` 3.8, `symfony/translation-contracts` 3.8, …), and of each development requirement
  (`symfony/ux-twig-component` 3.8, `symfony/stimulus-bundle` 3.8).

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- Des cartes sur Symfony UX Map et son pont Leaflet, décorées par leurs événements par un contrôleur Stimulus servi par
  AssetMapper, avec une feuille de style sur les jetons de PHIL UX.
- `TileSource` : le modèle de tuiles lu par `phil/url`, son attribution et sa grille.
- Le mandataire de tuiles, par défaut : des tuiles récupérées par le site sous son propre agent utilisateur, gardées
  dans un pool de cache, bornées en taille et en temps, sans jamais transmettre quoi que ce soit du visiteur ; les
  échecs répondus par une 502 et non gardés.
- Des tuiles directes sans référent et, par défaut, sans témoins ; ou aucune tuile.
- `PmtilesLayer` : une archive PMTiles hébergée par le site, matricielle ou vectorielle, lue par `pmtiles` ou
  `protomaps-leaflet` de l'import map, qui se rabat sur les tuiles du mandataire quand le paquet manque.
- `MapPlace` et `SiteAddress` : des lieux de `phil/geo` comme marqueurs, leurs textes échappés.
- `PrivacyGrid` et `Neighbourhood` : des positions comptées par cellule d'une grille fixe, seules les cellules qui
  atteignent le minimum montrées, jamais une position.
- `FloorPlan` et `Booth` : des plans sur le système de référence plat de Leaflet, comme image ou comme tuiles, leurs
  kiosques dans les classes de leurs états.
- `BoothAvailability`, avec `ReservationBoothAvailability` sur `phil/reservation-bundle`, mis en place quand c'est un
  service, et `UnknownBoothAvailability`.
- `ImageTiler`, `GdImageTiler` et `phil:map:tile-image` : de grandes images coupées en tuiles avec GD, leur taille
  vérifiée avant leurs pixels.
- `Maps`, `MapView` et `phil_map()` : chaque carte avec la liste de ce qu'elle montre, dessinée avec la recette Item du
  kit PHIL UX, visible sans JavaScript ; les mots en anglais et en français.
- La recette Flex dans `recipe/0.1` : le bundle, les tuiles du mandataire et la route du mandataire.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
