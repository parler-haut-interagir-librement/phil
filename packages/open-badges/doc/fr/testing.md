<!-- phil-dev source-sha256=066bdf8c753c10545dfe0cc38c798827ac11fd90d6d820791148da9bf73895e1 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` couvre les encodages, les clés, les JSON Web Tokens, les parties d'un titre et leurs
vérifications, le processeur JSON-LD fonctionnalité par fonctionnalité et chacun de ses refus, la canonicalisation de
jeux de données que seul leur voisinage distingue — et son indépendance par rapport aux étiquettes et à l'ordre —, les
preuves Data Integrity, les listes d'état, les appuis, les relevés d'apprentissage, l'intégration dans les images PNG
et SVG, le vérificateur, les clés RSA lues dans des JSON Web Keys et la résolution d'un `did:web` — son adresse, chaque
règle que doit suivre son document DID et la lecture du document par `phil/safe-http` contre un client HTTP simulé.

Deux vecteurs publiés ancrent la cryptographie : l'exemple `eddsa-rdfc-2022` de la spécification du W3C des suites
cryptographiques EdDSA — sa forme canonique et la valeur de sa preuve sont reproduites exactement — et la paire de clés
de ses exemples sous forme de Multikey. La canonicalisation réussit aussi tous les tests d'évaluation de la suite
RDFC-1.0 du W3C.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/open-badges/tests
```

Infection exige de la bibliothèque un score de mutation couvert de 100 %.

## Fuzzing

Quatre cibles de fuzzing lisent des entrées que personne n'a écrites — un PNG, un SVG, un jeton ou un document à
vérifier, un document DID — et ne doivent jamais faire autre chose que retourner un résultat ou lancer une exception de la
bibliothèque. `composer fuzz`, depuis la racine du monodépôt, exécute chacune pendant 30 secondes à partir des germes
de `tests/Fuzz/corpus`.

## Le banc d'essai

`tests/Benchmark/OpenBadgesBench.php` mesure ce que font une page de vérification et un émetteur : vérifier un jeton et
un document, signer un document — où la canonicalisation domine — et lire un PNG intégré.

## Tester du code qui émet des titres

Signez avec un germe fixe, `Ed25519Signer::fromSeed(str_repeat("\1", 32))`, et vérifiez avec son `verificationKey()`
dans un `DidKeyMethods`. Les signatures Ed25519 sont déterministes : un titre signé deux fois avec les mêmes entrées
donne les mêmes octets, et un test peut le comparer en entier.

Pour vérifier dans un test des titres d'un émetteur `did:web`, donnez à `DidWebMethods` un `DocumentFetcher` qui sert le
document DID depuis la mémoire : rien n'atteint le réseau, et le test décide de ce que répond le site de l'émetteur.
