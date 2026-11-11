<!-- phil-dev source-sha256=fe52a6c02d4f0daf0a354a582d4db0a08bbd6f498f925697c5e2ec8735b1caef -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` couvre les encodages, les clés, les JSON Web Tokens, les parties d'un titre et leurs
vérifications, le processeur JSON-LD fonctionnalité par fonctionnalité et chacun de ses refus, la canonicalisation de
jeux de données que seul leur voisinage distingue — et son indépendance par rapport aux étiquettes et à l'ordre —, les
preuves Data Integrity, les listes d'état, les appuis, les relevés d'apprentissage, l'intégration dans les images PNG
et SVG et le vérificateur.

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

Trois cibles de fuzzing lisent des entrées que personne n'a écrites — un PNG, un SVG, un jeton ou un document à
vérifier — et ne doivent jamais faire autre chose que retourner un résultat ou lancer une exception de la
bibliothèque. `composer fuzz`, depuis la racine du monodépôt, exécute chacune pendant 30 secondes à partir des germes
de `tests/Fuzz/corpus`.

## Le banc d'essai

`tests/Benchmark/OpenBadgesBench.php` mesure ce que font une page de vérification et un émetteur : vérifier un jeton et
un document, signer un document — où la canonicalisation domine — et lire un PNG intégré.

## Tester du code qui émet des titres

Signez avec un germe fixe, `Ed25519Signer::fromSeed(str_repeat("\1", 32))`, et vérifiez avec son `verificationKey()`
dans un `DidKeyMethods`. Les signatures Ed25519 sont déterministes : un titre signé deux fois avec les mêmes entrées
donne les mêmes octets, et un test peut le comparer en entier.
