<!-- phil-dev source-sha256=8855001475d1e181bd3848385db009d38ba3b50d109d2d5de69494488ef10782 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste la politique — ses valeurs par défaut, chaque `with…()`, ce qui n'est jamais permis —,
chaque raison de retrait et de refus, les références et leurs ruses de schéma, le CSS des attributs et des éléments
`<style>`, l'animation avec et sans la politique, et les limites à leurs bornes.

`Html5DifferentialTest` lit chaque sortie comme un navigateur lit un SVG intégré dans une page : analysée de nouveau par
`Dom\HTMLDocument`, l'analyseur HTML5 de PHP, dans un `div`, après un paragraphe et dans un lien, sous la politique par
défaut, avec des éléments `<style>`, avec les animations et avec tout ce qui est facultatif. Les charges sont les
contournements connus des assainisseurs de SVG — un `<style>` ou une section CDATA qui se ferme trop tôt, du balisage
dans un titre, une description ou un texte, un point d'intégration HTML, MathML, des noms d'attributs en majuscules ou
sous un préfixe, des schémas cachés par des références ou des espaces, une animation qui réécrit un lien, des
commentaires et des instructions de traitement que HTML lit autrement — et celles des avis de sécurité qui suivent :
aucune ne doit laisser sortir un script, un élément HTML, un attribut `on…` ni une adresse `javascript:`. La vérification
elle-même est testée contre des pages écrites à la main qui contiennent chacune de ces charges.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/svg-sanitizer/tests
```

Infection maintient la bibliothèque à un score de 100 % de mutants couverts.

## Fuzzing

La cible de fuzzing `svg-sanitizer` donne à l'assainisseur des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/` : quoi qu'il lise, il retourne ce qu'il a conservé ou un refus, et ne lève jamais d'exception ni
d'avertissement. Depuis la racine du monodépôt :

```sh
composer fuzz -- svg-sanitizer
```

## Tester du code qui assainit du SVG

La sortie est déterministe. Comparez le SVG exactement, et les retraits par raison et par sujet :

```php
$sanitized = new SvgSanitizer()->sanitize('<svg><rect onclick="x"/></svg>');

self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>', $sanitized->svg);
self::assertSame(
    [[RemovalReason::EventHandler, 'onclick']],
    array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject], $sanitized->removals),
);
```

Un test de la politique propre à l'application assainit une image qui contient ce que la politique devrait conserver,
et une autre qui contient ce qu'elle devrait retirer.

Un formulaire qui valide avec `SafeSvg` se teste avec le `ConstraintValidatorTestCase` de Symfony et
`SafeSvgValidator`, comme le fait le test de la bibliothèque elle-même.
