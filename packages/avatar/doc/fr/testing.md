<!-- phil-dev source-sha256=65687adb7d115dd693e95857d3ce71c7961002596c56eb81f11435487f2e35ea -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste le germe et son flux de choix, fixés d'après SHA-256 ; les couleurs, la luminance et le
contraste d'après WCAG ; chaque palette fournie, pour le contraste de ses encres ; les initiales de noms en latin,
grec, cyrillique, devanagari, han, kana, hangul, arabe, hébreu et persan, et de noms sans lettres ; la toile et son
échappement ; chaque style sur de nombreux germes ; et le générateur — un avatar fixé octet pour octet, les contours,
l'accessibilité, des noms hostiles réanalysés comme du XML, les styles de l'application.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/avatar/tests
```

La cible de fuzzing `initials` tient l'extraction et le générateur à leur contrat sur des noms que personne n'a
écrits ; `tests/Benchmark` mesure un avatar de chaque style. Dans le monodépôt, Infection tient la bibliothèque à 100 %
des mutants couverts.

## Tester du code qui dessine des avatars

Le générateur est déterministe et n'a besoin de rien : construisez-le dans le test, et comparez ce que le code affiche
avec ce que le générateur dessine pour le même germe et les mêmes options.

```php
$expected = new AvatarGenerator()->svg('member-1', new AvatarOptions(name: 'Ada Lovelace', size: 32));

self::assertStringContainsString($expected, $page);
```
