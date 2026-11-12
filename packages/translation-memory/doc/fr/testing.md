<!-- phil-dev source-sha256=ef7b74fa99ae01902d6ed2f3b94600c491f15eb488f57ebbae4080cca09517df -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les étiquettes de langue et les empreintes ; chaque transition d'un segment et chaque
refus ; le découpeur de phrases sur les fins, les fermants, les abréviations, les initiales, les fins CJK et les
balises, chaque texte redonné en entier ; le découpage des documents par champ et par phrase, les blocs imbriqués, les
clés qui ne peuvent pas être nommées et les valeurs trop profondes, et la remise en place des segments dans les états
acceptés et sur la même source seulement ; la synchronisation (conservés, déplacés, périmés, ajoutés, retirés) ; la
distance de Levenshtein contre chaque borne, les trigrammes, la fenêtre de longueurs, la présélection, l'ordre et la
limite des correspondances, et les unités d'une langue parente lues pour une variante ; les variantes régionales et
leurs cibles héritées ; la recherche et la vérification du glossaire, les formes fléchies et les pluriels réguliers ;
chaque contrôle de qualité sur ce qu'il accepte et ce qu'il signale, les indicateurs, le lecteur de pluriels ICU sur
des messages équilibrés et brisés, les largeurs et les glyphes ; le XLIFF écrit exactement, lu en 2.x et en 1.2 avec
chaque code en ligne et chaque état, refusé, et fusionné ; le TMX et le TBX écrits exactement, relus, lus à partir
d'autres outils, et refusés.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/translation-memory/tests
```

Infection maintient la bibliothèque à un score de mutation couverte de 100 %.

## Fuzzing

Trois cibles de fuzzing dans `tests/Fuzz` exécutent les analyseurs sur des entrées que personne n'a écrites, à partir des
germes de `tests/Fuzz/corpus` : `tmx-reader` et `tbx-reader` lisent des unités et des glossaires ou refusent le fichier
avec leur exception, et `plurals` exécute le contrôle des pluriels sur n'importe quelle source et cible ; aucune ne doit
lancer autre chose ni émettre d'avertissement.

```sh
composer fuzz -- tmx-reader tbx-reader plurals
```

## Bancs d'essai

`TranslationMemoryBench` cherche un segment dans des mémoires de 100, 1 000 et 10 000 unités, et calcule le score de
segments de 100 et 1 000 caractères, proches et éloignés les uns des autres. À partir de la racine du monodépôt :

```sh
composer bench -- packages/translation-memory/tests/Benchmark
```

## Tester du code qui traduit

Chaque service est déterministe et ne garde aucun état hormis son stockage. Construisez les segments directement,
utilisez un `InMemoryStore`, et comparez les résultats exactement :

```php
$memory = new TranslationMemory(new InMemoryStore([
    new MemoryUnit(LanguageTag::of('en'), LanguageTag::of('fr'), 'Hello', 'Bonjour'),
]));

self::assertSame('Bonjour', $memory->lookup('Hello', LanguageTag::of('en'), LanguageTag::of('fr'))[0]->unit->target);
```

Pour le XLIFF, relisez ce qui a été écrit plutôt que de comparer des fichiers : `new XliffReader()->read($xml)->units`.
