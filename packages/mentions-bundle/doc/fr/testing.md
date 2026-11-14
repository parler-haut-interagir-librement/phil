<!-- phil-dev source-sha256=a513d06168f3da4a9a95f262bc0e82e87e1c95e7afbcdc58b99a0807a31d2ab5 -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les mentions, le motif par défaut et celui de l'application, les motifs refusés, les identifiants
du texte brut et de Markdown avec leurs positions, les marques des documents UJO, les limites, l'enregistreur —
nouvelles mentions, même texte de nouveau, modification, auteur, visibilité, limites vérifiées avant l'annuaire,
mentions d'un membre, sujet oublié —, la façade, le lieur — liens, `span`, adresses dangereuses, éléments jamais lus,
limite, UTF-8 invalide —, le même contrat sur les deux stockages (celui de Doctrine sur SQLite en mémoire, sa table
créée à partir du mappage, chaque ligne relue et la contrainte unique vérifiée) — un membre déjà mentionné écarté et la
ligne trouvée gardée, un changement échoué annulé, le stockage en mémoire vidé entre deux requêtes —, l'upsert de chaque
plateforme, un membre qu'une autre requête a enregistré entre-temps annoncé par cette requête seulement, la recette
Flex et l'annuaire qu'elle copie, la notification et les préférences, le filtre Twig, la recherche de l'éditeur, les
mentions et les auteurs des commentaires, le pont sur les commentaires écrits, modifiés, au-delà de la limite et
supprimés, les membres et les auteurs jamais nommés par une adresse de courriel, la passe de compilation, ainsi que la
configuration, les services et les réglages ajoutés par le bundle.

`tests/Functional` démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, Twig, le bundle de notifications,
le bundle de commentaires et le bundle. Il enregistre deux fois un commentaire, puis vérifie que chaque membre est
notifié une seule fois et que le filtre en fait des liens ; il écrit ensuite un commentaire sur une page par le bundle
de commentaires et vérifie que le pont a notifié le membre qu'il mentionne, puis a oublié la mention une fois le
commentaire supprimé. Quand le bundle est installé seul, le répartiteur d'événements n'est qu'un prérequis de
développement, et la suite fonctionnelle est sautée.

`tests/Integration` exécute le contrat des stockages sur le stockage Doctrine avec MySQL 8 et PostgreSQL 17, où l'upsert
est l'instruction propre à chaque serveur ; elle est sautée à moins que `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL` soit définie, comme le fait `phil-dev services test` :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test
```

`tests/js` exécute les modules purs avec l'exécuteur de tests de Node : la requête avant le curseur, l'identifiant
inséré, les identifiants des suggestions, et la requête avec ses échecs.

À partir de la racine du monodépôt, puis à partir du bundle :

```sh
vendor/bin/phpunit packages/mentions-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/mentions-bundle
```

Chaque ligne de `src/` est tenue par le seuil de mutation du monodépôt : chaque mutant est tué.

## Bancs d'essai

`tests/Benchmark/MentionsBench.php` mesure la lecture de Markdown et la création des liens sur un court commentaire et
sur des textes hostiles à la limite de longueur — cent mille `@`, vingt mille identifiants, des accents graves, des
clôtures jamais fermées —, ainsi que les marques d'un document de cinquante blocs. Un commentaire coûte quelques
microsecondes ; les textes hostiles, de quelques millisecondes à quelques dizaines, selon leur longueur.

```sh
composer bench -- packages/mentions-bundle/tests/Benchmark
```

## Fuzzing

La cible `text-mentions` lit des textes que personne n'a écrits, comme texte brut et comme Markdown, et en fait des
liens : rien ne lève d'exception, et chaque mention trouvée commence par un `@` à sa position.

```sh
composer fuzz -- text-mentions --budget=60
```

## La suite de navigateur

`tests/Browser/MentionBrowserTest.php` pilote le contrôleur dans un vrai Chrome avec `symfony/panther` et le pilote
Chrome du système, sur une page servie par le serveur intégré de PHP (`tests/Browser/router.php`) avec Stimulus, le
contrôleur, sa feuille de style et une recherche de membres qui répond comme celle de l'éditeur par blocs. Elle vérifie :

| Aspect | Ce qui se passe |
|---|---|
| Rôles | Le champ est un `combobox` avec `aria-autocomplete="list"` et `aria-expanded`, qui contrôle une `listbox` masquée ; un `status` la suit |
| Ouverture | `Hi @ad` liste les deux membres dont l'identifiant commence par `ad`, le premier `aria-selected` et nommé par `aria-activedescendant`, et annonce *2 members found* ; le focus reste dans le champ |
| Touches | Les flèches déplacent le membre actif et reviennent au début ; Entrée écrit `@adele` et une espace à la place de `@ad`, le curseur après elle, émet `chosen` avec l'identifiant et la référence, et ferme la liste |
| Tab | Choisit le membre actif plutôt que de quitter le champ |
| Échap | Ferme la liste ; le texte reste |
| Souris | Un clic choisit un membre ; le focus reste dans le champ |
| Sortie | Placer le focus ailleurs ferme la liste |
| Personne | `@zzzz` affiche *No member found* dans la liste et la région dynamique ; Entrée n'écrit rien |
| Refus | Une recherche que le serveur refuse ne liste personne et n'échoue pas |
| Pas une mention | `ada@ex` n'ouvre rien |

Elle s'exécute à part de `composer test`, avec les autres suites de navigateur, après l'installation de son Stimulus :

```sh
composer test:browser
```

Le filtre sur un commentaire enregistré, qui lie `@ada` au profil, est couvert par les suites unitaire et fonctionnelle.
