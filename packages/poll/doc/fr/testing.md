<!-- phil-dev source-sha256=92b623ea15ec77dc24db2cdd6cd48e6bd43224c5f33cdc8e0e10df81f073fd78 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les règles — chaque borne, l'alphabet et la longueur d'une option, les options déclarées
deux fois — et les vérifications d'un bulletin, avec leurs messages. Elle teste aussi le compte des choix, uniques et
multiples ; le vote alternatif, avec sa majorité, ses transferts, ses bulletins épuisés, ses égalités brisées par le
dernier tour où les options à égalité différaient puis par l'ordre déclaré, et son égalité de toutes les options ; la
méthode Borda ; la méthode Schulze sur l'exemple de son auteur, sur un cycle, sur des classements partiels et sur deux
options dont aucune ne bat l'autre ; les places partagées par les options à égalité ; enfin le registre des méthodes.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/poll/tests
```

Infection exige de la bibliothèque un score de mutation couvert de 100 %.

## Le banc d'essai

`tests/Benchmark/CountBench.php` dépouille dix mille bulletins sur dix options par chaque méthode — des bulletins
uniques pour le compte des choix, des bulletins classés de trois à dix choix pour les autres — et mesure la
vérification d'un bulletin. `composer bench`, depuis la racine du monodépôt, l'exécute avec les autres bancs d'essai.

## Tester un dépouillement

Construisez les règles, vérifiez les bulletins avec elles et comparez le classement :

```php
use Phil\Poll\Counting\InstantRunoff;
use Phil\Poll\PollRules;

$rules = PollRules::ranked(['a', 'b', 'c']);
$result = new InstantRunoff()->count($rules, [
    $rules->ballot(['a', 'b']),
    $rules->ballot(['b']),
    $rules->ballot(['c', 'b']),
]);

self::assertSame(['b'], $result->winners());
self::assertSame(['c'], $result->rounds[0]->eliminated);
```
