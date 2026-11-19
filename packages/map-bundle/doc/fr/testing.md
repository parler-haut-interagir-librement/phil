<!-- phil-dev source-sha256=f60b44bb553d73960de140ebe042044f5fa10c76c408abd98d7a81371f55202f -->
# Tests

## Les tests du bundle

`tests/Unit` couvre la source de tuiles et sa grille, les adresses et les lieux, la grille de vie privée — cellules,
taille, milieu, bords du monde, pôles —, le quartier — le minimum, l'ordre, la limite, aucune position qui sorte —, les
plans d'étage et les kiosques, leur échelle et leurs limites, les cartes construites avec Symfony UX Map — marqueurs,
fenêtres d'information échappées, zones, kiosques et leurs états, la période —, les mots en anglais et en français, les
trois couches de base et l'adaptateur PMTiles avec et sans son paquet dans l'import map, le mandataire de tuiles — la
requête qu'il envoie, le cache, chaque échec répondu par 502 et non conservé, la limite pendant le téléchargement —, le
découpeur GD sur chaque type d'image, ses limites et ses bords transparents, les états tirés de
`phil/reservation-bundle`, la recette Flex, les passes du compilateur, et la configuration, les services et les réglages
préajoutés du bundle.

`tests/Functional` démarre un noyau avec FrameworkBundle, Twig, ses composants, Stimulus, Symfony UX Map et le bundle,
la recette Item du kit PHIL UX copiée là où `ux:install` la place : il rend une page de trois cartes et vérifie leur
balisage et leurs listes, répond à une tuile par la route du mandataire, et exécute la commande du découpeur.

`tests/js` exécute le module pur avec l'exécuteur de tests de Node : la classe de chaque forme, le plan et l'archive lus
dans la description de la carte.

Depuis la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/map-bundle/tests
node --test tests/js     # from packages/map-bundle
```

Chaque ligne de `src/` est tenue par la barre de mutation du monodépôt : chaque mutant est tué.

## Dans un navigateur

`tests/Browser` pilote Chrome avec Panther sur l'application de la suite fonctionnelle, servie par le serveur intégré de
PHP : les quatre cartes dessinées par Leaflet avec le contrôleur du bundle, leurs tuiles demandées au mandataire du site
sans référent, les zones et les kiosques dans les classes de leurs états, le plan d'étage sur le système de référence
plat, une archive PMTiles dont le paquet manque et qui se rabat sur les tuiles passées par le mandataire, et les listes.
Il exige Leaflet et Stimulus depuis npm et le chromedriver du système, et s'exécute à part, comme Infection :

```sh
composer test:browser
```
