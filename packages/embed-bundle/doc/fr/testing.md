<!-- phil-dev source-sha256=9f43a93f5c72d03ed992aa8fe0ba9860faac5a087aef77e5c853eedef3476038 -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les origines lues par `phil/url`, les clés, les revendications et leurs bornes, le protocole du cadre
contre les jeux de données d'essai que lit aussi le JavaScript, les intégrations et leur registre, le codec de jetons —
son format, sa signature, les clés ordonnées, un jeton expiré, chaque contrefaçon et chaque partie mal formée —, les
jetons émis et relus dans un contexte, les thèmes choisis, le code qu'un partenaire colle et son échappement, l'écouteur
et l'en-tête sur les sous-requêtes et les jetons refusés, la recette Flex, la passe du compilateur, et la configuration,
les services et les réglages préajoutés du bundle — un secret jamais dans un message.

`tests/Functional` démarre un noyau avec FrameworkBundle, Twig et le bundle, puis de nouveau avec
`phil/browser-security-bundle` : la page d'une intégration par sa route et par son attribut, encadrée par la seule
origine du jeton, encadrement refusé pour un jeton contrefait ou celui d'une autre intégration, intacte sans jeton ; la
politique du site modifiée plutôt que doublée, et `X-Frame-Options` retiré ; le script à son adresse stable, lisible
d'une origine à l'autre, mis en cache et revalidé ; le code d'une intégration ; la commande de jeton.

`tests/js` exécute le module du protocole avec l'exécuteur de tests de Node contre les jeux de données d'essai partagés.

Depuis la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/embed-bundle/tests
node --test tests/js     # from packages/embed-bundle
```

Chaque ligne de `src/` est tenue par la barre de mutation du monodépôt : chaque mutant est tué.

## Fuzzing

La cible `embed-token` lit des textes que personne n'a signés : le décodeur les refuse tous, et ne lève jamais autre chose
que `InvalidEmbedToken`. La cible `embed-message` lit des textes que personne n'a écrits : la référence donne un message
ou le refuse, et un message qu'elle donne se relit comme lui-même.

```sh
composer fuzz -- embed-token embed-message --budget=60
```

## Dans un navigateur

`tests/Browser` pilote Chrome avec Panther sur deux origines, servies toutes deux par le serveur intégré de PHP : la page
d'un partenaire sur `localhost:9188` contient le code d'une intégration et un code contrefait ; l'application de la suite
fonctionnelle sur `localhost:9189` sert la page intégrée et le script. Le cadre apparaît une fois prêt, à la hauteur de
son contenu, et le suit quand il grandit et rétrécit ; le thème que demande le partenaire atteint le cadre, un thème
qu'il n'a pas, non ; un message venu d'ailleurs est ignoré ; l'intégration contrefaite se voit refuser l'encadrement et
garde son lien. Il exige Stimulus depuis npm et le chromedriver du système, et s'exécute à part, comme Infection :

```sh
composer test:browser
```
