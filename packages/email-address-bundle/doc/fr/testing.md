<!-- phil-dev source-sha256=e51a9eb6ae5e2e2d220fcb391bf720fb3971079eca6b54380e65c4cb416f69fc -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans réseau et sans DNS : le résolveur répond depuis une
liste, l'horloge est une `MockClock`, et l'historique des rebonds vit en mémoire. Elle couvre les règles — le seuil, la
fenêtre, une plainte avec et sans authentificateur, le même rapport reçu deux fois —, les politiques, la forme
normalisée, le champ, les commandes et l'adaptateur Maildir. Les messages bruts de `tests/Fixtures` sont des
notifications d'état de livraison, un rapport d'abus et une réponse automatique.

La suite fonctionnelle, sous `tests/Functional`, démarre une petite application : le champ dans un formulaire, la route
des domaines connus, un événement de webhook consommé, la commande sur une notification d'état de livraison et sur un
rapport d'abus, et Messenger sur un transport en mémoire qui sérialise, pour vérifier qu'aucune adresse n'atteint la
file d'attente.

Les tests JavaScript exécutent la suggestion, l'avertissement de rôle et le contrôleur sous l'exécuteur de tests de
Node, Stimulus étant résolu vers une doublure par `tests/js/register.mjs`.

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Tester une application

`InMemoryBounceHistoryRepository` garde l'historique en mémoire et l'oublie sur `reset()` ; créez-en un alias pour
`BounceHistoryRepository` dans l'environnement de test. Donnez à `dns.resolver` un `StaticResolver` de
`phil/email-address` pour qu'aucun test n'interroge le DNS.
