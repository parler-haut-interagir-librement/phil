<!-- phil-dev source-sha256=ca053676e20612dc48acd12976ffc5f03c3c159312e33bd92f67ab8060a22923 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle donne, le plafond d'un texte refusé quand ce n'est pas un
entier positif et qui atteint les typographes, la décoration du moteur de rendu UJO, les filtres
et le filtre UJO, dans une locale donnée et dans la locale courante. `tests/Functional` démarre une petite application
(FrameworkBundle en français avec son traducteur, TwigBundle, `phil/ujo-bundle` et ce bundle). Elle produit le rendu
d'un gabarit avec les deux filtres et d'un document UJO, dans la locale par défaut et dans une autre fixée par
`LocaleSwitcher`.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/typography-bundle/tests
```

La classe du bundle est tenue à un score de mutation couverte de 100 % par Infection, comme la bibliothèque.

## Tester une application

Un test fonctionnel vérifie le HTML produit. Désactivez la césure dans la configuration de test, sauf si le test la
vérifie, pour que les césures conditionnelles n'apparaissent pas dans chaque chaîne attendue :

```yaml
# config/packages/test/phil_typography.yaml
phil_typography:
    hyphenation: false
```

L'espace insécable apparaît comme `&nbsp;` dans le HTML ; l'espace fine insécable (U+202F) et la césure
conditionnelle (U+00AD) apparaissent telles quelles, et s'écrivent de préférence `\u{202F}` et `\u{AD}` dans les
chaînes PHP.
