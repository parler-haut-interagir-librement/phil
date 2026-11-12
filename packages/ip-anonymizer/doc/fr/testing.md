<!-- phil-dev source-sha256=ee8e42a61628814bdb993220e5458643472f9c2d919953bc89ae950ef9060b5d -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste ce que l'analyseur accepte et refuse (zéros initiaux, index de zone, crochets, octets NUL,
texte le plus long), les adresses mappées sur IPv4 lues comme IPv4, le texte RFC 5952 d'IPv6 comparé à des adresses
aléatoires relues par `inet_pton()`, les masques de chaque octet partiel, la notation CIDR, la troncature, les sels et
leurs bornes, les périodes quotidiennes et personnalisées des sels aléatoires et dérivés et leurs limites UTC, le magasin partagé d'un
sel oublié et ce qui reste de sa période, les empreintes à clé
comparées à `sodium_crypto_generichash()`, chaque plage à usage particulier des portées, la recherche par plus long
préfixe des réseaux étiquetés et les compositions.

À la racine du monodépôt :

```sh
vendor/bin/phpunit packages/ip-anonymizer/tests
```

Infection impose à la bibliothèque un score de mutation de 100 % sur le code couvert.

## Les cibles de fuzzing

`tests/Fuzz/ip-address.php` et `tests/Fuzz/ip-network.php` donnent aux deux lecteurs du texte que personne n'a écrit :
une adresse doit se relire à partir de son propre texte avec les mêmes octets, un réseau doit redonner le même réseau,
et rien d'autre que `InvalidNetwork` ne peut être lancé. `composer fuzz ip-address ip-network`, à la racine du
monodépôt, les exécute à partir des germes de `tests/Fuzz/corpus`.

## Le banc d'essai

`tests/Benchmark/IpAnonymizerBench.php` mesure ce que coûte une requête anonymisée : l'analyse d'une adresse IPv4 et
d'une adresse IPv6, l'écriture d'un texte IPv6, chaque stratégie, une empreinte sous un sel tournant et sous un sel oublié, et une
recherche
dans une table de mille réseaux étiquetés. `composer bench`, à la racine du monodépôt, l'exécute avec les autres bancs
d'essai.

## Tester du code qui anonymise

Donnez à `KeyedHash` un `FixedSalt` pour des valeurs qui ne changent pas d'une exécution à l'autre, et à `RotatingSalt`
une horloge figée, comme le `MockClock` de Symfony, pour tester un changement de période :

```php
use Phil\IpAnonymizer\KeyedHash;
use Phil\IpAnonymizer\RotatingSalt;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 23:59:59 UTC');
$hash = new KeyedHash(new RotatingSalt(str_repeat('k', 32), $clock));
$before = $hash->anonymize($address);

$clock->sleep(1);
self::assertNotSame($before, $hash->anonymize($address));
```
