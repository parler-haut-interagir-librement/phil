<!-- phil-dev source-sha256=fbe59d2874846de3622c01be14c8773ca38a7ee20503eece463ed505f9711be1 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les règles du domaine — les états des abonnés, les campagnes, les envois, les fenêtres
d'envoi à travers les fuseaux horaires — ; les abonnements, les liens signés, l'envoi — mise en file une seule fois,
vérifications à l'envoi, fenêtres, rythme, échecs de transport, confirmations —, l'approbation des campagnes et sa règle
des quatre yeux, les exécutions d'envoi, leurs comptes, leur disjoncteur sur chacun de ses seuils et un *worker* qui perd
une course pour l'état d'une exécution, les gabarits en bac à sable contre les fonctions, les méthodes, les propriétés,
les balises, les filtres, l'opérateur d'intervalle et les grandes sorties, la réécriture des liens, le suivi des clics,
les rebonds à partir de vraies notifications d'état de livraison, le client IMAP sur une paire de sockets, le limiteur
sur le limiteur de débit de Symfony, les événements du fournisseur, les déclencheurs et les actions des automatisations,
le module de vie privée et les webhooks d'audience, le tout sur SQLite en mémoire, une horloge figée et un mailer qui
garde ses messages ; le conteneur du bundle sous plusieurs configurations ; et la recette Flex, dont la configuration est
acceptée par le bundle.

La suite `tests/Functional` démarre une application avec FrameworkBundle, TwigBundle, SecurityBundle et DoctrineBundle
sur SQLite, et parcourt les pages : le formulaire d'abonnement et son jeton CSRF, la double confirmation par Messenger
et le mailer, la confirmation, une campagne approuvée par un utilisateur connecté sous la règle des quatre yeux et son exécution
d'envoi terminée, son lien suivi, la désinscription en un clic, le centre de préférences, et l'aperçu derrière son rôle. Elle exécute aussi `lint:container` sur cette application, sans débogage pour que la
commande compile elle-même le conteneur : chaque argument des services du bundle, y compris les arguments en ligne,
correspond au type que déclare son constructeur.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/newsletter-bundle/tests
```

La suite `tests/Integration` exécute le contrat des dépôts sur un vrai MySQL 8.4 et un vrai PostgreSQL 17, avec des
clics comptés depuis deux connexions et la migration à partir des tables de 0.2 qui contiennent des lignes ; le test de chaque serveur lit son URL dans `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL` et est ignoré sans elle :

```sh
PHIL_TEST_MYSQL_URL='mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4' \
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' \
vendor/bin/phpunit packages/newsletter-bundle/tests/Integration
```

`tests/Fuzz/signed-link.php` donne aux liens signés des jetons que personne n'a écrits : le vérificateur refuse chacun
avec `LinkRefused`, n'en accepte jamais un qu'il n'a pas signé, et ne lève jamais autre chose. Dans le monodépôt,
Infection maintient le bundle à 100 % des mutants couverts.
