<!-- phil-dev source-sha256=e9d77ee16f5a67fbbad3e649cea51daecec311244fea6bfa43c3f06752186f5e -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste la configuration et les services qu'elle enregistre, le sous-répertoire des recettes, la
recette Flex, la déclaration de la configuration et les versions gardées dans SQLite en mémoire (leurs moments en UTC sur
un serveur d'un autre fuseau horaire), la table et son écouteur, le
répertoire de rapports, la régénération, la publication et ses refus, la page d'administration refusée sans le rôle,
sans Symfony Security ou sans sa protection CSRF, la commande, et le lien de pied de page avec les fonctions et le
filtre de Twig.

La suite `tests/Functional` démarre une application avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle,
TwigComponentBundle avec les recettes d'un kit copiées là où `ux:install` les met (le kit PHIL UX dans un
sous-répertoire des composants et à leur racine, ou le kit Shadcn du Symfony UX Toolkit à leur racine), et Symfony
Security avec une connexion par HTTP Basic. Elle couvre la page dans les deux
langues et chaque libellé dessiné par les recettes, les versions, le lien de pied de page, la commande qui publie une
version à partir d'un rapport, et la page d'administration : refusée à un visiteur et à un lecteur, un jeton falsifié,
un document refusé puis le brouillon publié. `KitRenderingTest` rend la page et la page d'administration avec chaque
kit : les mêmes titres, éléments de liste, cellules d'en-tête et libellés, aucune propriété d'un kit écrite comme
attribut par l'autre, aucun attribut en double, et aucun mot écrit par un gabarit. Chaque requête de la page traverse le
noyau comme un *worker* la sert : elle est terminée, puis les services sont réinitialisés. Le kit Shadcn est lu dans
`symfony/ux-toolkit`, une exigence de développement ; ses tests sont ignorés sans lui.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/accessibility-statement-bundle/tests
```

Le bundle est tenu à un score de mutation couvert de 100 % par Infection.

## Tester une application

Avec la source `config`, la page n'a besoin d'aucune base de données : un test fonctionnel demande `/accessibility` et
lit la page. Avec la source `doctrine`, créez la table à partir de `StatementTables::configure()`. Testez la commande
avec un répertoire de rapports écrit par le test et nommé par `audit.directory`.
