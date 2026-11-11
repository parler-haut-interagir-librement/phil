<!-- phil-dev source-sha256=ccb24a7134cbe87450c82e292eab6bee2ede0f3af740ec9c4634632d9a760683 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` lit des messages écrits pour elle dans `tests/Fixtures` — une notification de Postfix, un délai et
un succès, une boîte pleine en base64, une notification internationale, un rapport de plainte, des rebonds d'Exim et de
qmail écrits en texte, une réponse automatique et un message d'une personne — et teste chaque lecture, chaque genre, les
règles et leur ordre, VERP, les authentificateurs, les codes d'état, et le lecteur MIME à chacune de ses limites.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/bounce-parser/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 %.

## Fuzzing

La cible de fuzzing `bounce-parser` donne à l'analyseur des messages que personne n'a écrits, à partir des germes de
`tests/Fuzz/corpus/`, avec deux règles et un `Verp` : quoi qu'il lise, il retourne un rapport et ne lève jamais
d'exception. Depuis la racine du monodépôt :

```sh
composer fuzz -- bounce-parser
```

## Tester du code qui lit des rebonds

L'analyseur est déterministe et n'exige aucun serveur : gardez les rebonds qu'envoient vos serveurs sous forme de
fichiers `.eml`, analysez-les, et comparez le genre, l'action et les adresses.

```php
$report = new BounceParser()->parse((string) file_get_contents(__DIR__.'/bounces/mailbox-full.eml'));

self::assertSame(BounceKind::Soft, $report->kind);
self::assertSame(['ada@example.org'], $report->addresses());
```

Pour tester une règle, analysez un message qui devrait lui correspondre et un autre qui ne le devrait pas, et comparez
`BounceReport::$rule`.
