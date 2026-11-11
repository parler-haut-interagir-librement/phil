<!-- phil-dev source-sha256=e2d72c0bf15126b6fc4e208e9cccae3f22140f36023c4718ea1837204b78e2f2 -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services qu'elle donne, la décoration du moteur de rendu UJO, les filtres
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
