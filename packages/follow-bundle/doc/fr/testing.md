<!-- phil-dev source-sha256=5c8bd787cb238e4af4f7e117a7794dad82d5c53a6879e4814d0ccadf9a79d0da -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, exécute le bundle sur SQLite en mémoire, ses tables créées à partir de leur
définition, ses requêtes comptées, les notifications de phil/notifications-bundle remettant leurs messages à un bus
enregistreur. Elle prouve, entre autres, qu'une activité atteint 2 400 abonnés en un nombre borné de requêtes, qu'un
message répété n'envoie à personne deux fois, qu'ignorer un sujet l'emporte sur surveiller son parent, qu'un profil
verrouillé prend une demande, et que cesser de suivre un membre conserve la relation mutuelle.

La suite fonctionnelle, sous `tests/Functional`, démarre une application avec FrameworkBundle, DoctrineBundle et l'ORM
sur un fichier SQLite, Twig et phil/notifications-bundle, crée les tables à partir du schéma de l'ORM, et envoie les
formulaires des gabarits comme le fait un navigateur sans JavaScript. Dans le monodépôt, Infection maintient le paquet à
100 % des mutants couverts.
