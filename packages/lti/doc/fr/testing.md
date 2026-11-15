<!-- phil-dev source-sha256=a259724c1c2784c05a02ee344c06bb29572c39deb26c0fa33ee8d89e39268263 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Les jetons, les clés et les jeux de clés ; les revendications d'un lancement lues et écrites ; le lancement complet, de l'initiation de la plateforme au lancement vérifié de l'outil, et chaque refus en chemin ; Deep Linking, de la réponse de l'outil au résultat de la plateforme ; les jetons d'accès, de l'assertion de l'outil à la garde de la plateforme ; les clients d'AGS et de NRPS sur des réponses enregistrées ; les services de la plateforme ; les magasins en mémoire. |

Les tests s'exécutent sans réseau : les clés sont générées à la première exécution et gardées dans le répertoire
temporaire, de sorte qu'aucune clé privée n'est versée au dépôt ; les jeux de clés viennent d'un jeu de données
d'essai ; les services répondent depuis un client enregistreur ou le `MockHttpClient` de Symfony. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Cibles de fuzzing

| Cible | Ce qu'elle lit |
|---|---|
| `lti-jwt` | Les jetons compacts et les jeux de clés. |
| `lti-launch-claims` | Les revendications d'un lancement, lues et réécrites, et les éléments de contenu d'une réponse de Deep Linking. |
| `lti-link-header` | Les en-têtes `Link`. |

```sh
composer fuzz -- lti-jwt lti-launch-claims lti-link-header --budget=60
```

## La suite de certification

1EdTech certifie les outils et les plateformes de LTI Advantage par sa suite de certification, ouverte à ses membres.
L'exécuter contre une application bâtie sur ce paquet est une étape avant la première version stable : la suite lance
l'outil depuis sa plateforme d'essai, ou joue le rôle d'un outil pour la plateforme, et vérifie les messages, les
services et les refus.

## Tester une application

Bâtissez la plateforme et l'outil d'un test à partir des adaptateurs en mémoire, avec une horloge figée et un jeu de
données d'essai de jeu de clés qui donne les clés publiques de l'autre côté : `LaunchIssuer` signe un lancement que le
`LaunchValidator` de l'outil vérifie, sans navigateur ni réseau.
