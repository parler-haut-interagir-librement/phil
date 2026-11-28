<!-- phil-dev source-sha256=e8fe326a825238d5390419142d2e6c90916a4069809e706108e1615047aef34e -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les rôles et les
fenêtres sont implémentés sous `tests/Support`, le magasin et le journal par les implémentations en mémoire du paquet,
et le scelleur s'exécute sur le `InMemoryKms` du composant KeyManagement de Symfony, qui refuse une mauvaise clé, un
mauvais contexte ou un mauvais client comme le ferait un vrai KMS. Dans le monodépôt, Infection maintient le paquet à
100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/participant-safety-info-codec.php` décode des textes en clair que personne n'a encodés : chaque entrée se
décode en une information qui s'encode de la même façon deux fois, ou est refusée comme illisible, jamais par une autre
exception. Dans le monodépôt :

```sh
composer fuzz -- participant-safety-info-codec
```

Ses graines sont sous `tests/Fuzz/corpus/participant-safety-info-codec`.
