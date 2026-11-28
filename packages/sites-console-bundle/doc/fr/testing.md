<!-- phil-dev source-sha256=ce50955691fc44f40de0cb529894023f990e54ac0f4c9c1079fbb8bdd423f618 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Les clés et les messages signés, les documents du protocole et leurs limites, les versions et les motifs, les modèles, les règles d'alerte, le vérificateur et les points de terminaison de l'agent avec `phil/idempotency` sur un pool en tableau, les clés de la console qu'un agent accepte et leur magasin, la rotation de la clé de la console — arrêt au premier échec, reprise, sites partis —, les services de la console avec un client d'agent factice, le client HTTP face au contrôleur de l'agent par un transport mock — tout l'échange signé dans les deux sens —, le lecteur du fichier de verrouillage, la santé tirée d'un substitut de `phil/monitoring-bundle`, les dépôts en mémoire et les dépôts Doctrine sur SQLite, la configuration et ses passes. |
| `tests/Functional` | Un noyau qui est à la fois la console et le site « shop », avec SecurityBundle, les sessions, CSRF, Messenger, TwigBundle, Twig Component et les recettes du kit, phil/monitoring-bundle, DoctrineBundle sur SQLite et une horloge fixe, son client HTTP rendant chaque requête à son propre agent : les écrans avec leurs rôles et leurs jetons, chaque commande, une rotation de clé arrêtée puis reprise, les handlers et l'association Doctrine. |
| `tests/Integration` | Les dépôts Doctrine de nouveau, sur MySQL 8.4 avec `PHIL_TEST_MYSQL_URL` et sur PostgreSQL 17 avec `PHIL_TEST_POSTGRESQL_URL` ; ignorés sans elles. |

Rien d'autre n'a besoin d'un réseau ou d'un serveur. Dans le monodépôt, `composer test:services` démarre les serveurs et
exécute la suite d'intégration, et Infection maintient le paquet à 100 % des mutants couverts.

## Fuzzing

Trois lecteurs d'octets que personne n'a écrits déclarent des cibles de fuzzing, exécutées à partir des germes de
`tests/Fuzz/corpus/<target>` :

| Cible | Ce qu'elle lit |
|---|---|
| `status-document` | Le statut qu'un agent répond : il se lit, ou est refusé avec `MalformedDocument`, et se relit à l'identique une fois réécrit. |
| `composer-lock` | Le `composer.lock` d'un site : seuls des noms et des versions vérifiés en sortent, triés, et rien n'est levé. |
| `key-request` | Ce que la console poste à `keys`, et les clés de la console qu'un agent stocke : chacun se lit, ou est refusé avec `MalformedDocument`, et se relit à l'identique une fois réécrit. |

```sh
composer fuzz -- status-document --budget=60
```

## Tester une application

Donnez au noyau de test une `MockClock` comme `clock`, et à l'agent un pool de cache sur cette horloge — `ArrayAdapter`
en accepte une — pour que les nonces et les clés d'idempotence expirent selon elle. Le `http_client` de la console peut
être un `MockHttpClient` qui remet chaque requête au noyau, comme le fait le test fonctionnel du bundle, pour jouer
tout l'échange dans un seul processus. `store: memory` garde les lignes de la console en mémoire.
