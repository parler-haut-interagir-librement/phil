<!-- phil-dev source-sha256=26ca89fb20c9bdea31a3facd0e31b3f2172efd9692b8f413e51db8ba8832eb41 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau, sur SQLite en mémoire : les tables de l'archive à
côté de celles de `phil/newsletter-bundle`, avec ses propres dépôts, une horloge qui ne bouge pas et un gabarit du site
qui écrit les adresses de l'abonné. La suite fonctionnelle, sous `tests/Functional`, démarre une petite application —
FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle, SecurityBundle, l'infolettre et l'archive — et lit les pages par
son navigateur.

La sécurité de l'archive a ses propres tests : un lien révoqué répond 404 ; aucune page ne contient le jeton signé,
l'adresse, l'identifiant externe, le lien de désabonnement ou de préférences, ni le pixel espion d'un abonné, même à
partir d'un gabarit qui les écrit ; un numéro non publié quitte la liste et sa page ; effacer un abonné laisse la
version Web octet pour octet telle qu'elle était. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants
couverts.

## Fuzzing

`tests/Fuzz/link-token.php` donne au lecteur de liens des textes que personne n'a écrits : il ne lève jamais
d'exception, n'émet jamais d'avertissement et n'ouvre jamais un numéro avec un jeton qu'il n'a pas émis. `composer fuzz`
l'exécute.
