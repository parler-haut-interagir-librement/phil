<!-- phil-dev source-sha256=0d28e68f1d9001b86d4e70c2821d450452202dcab2f72ac592b79ad6fc383dcd -->
# Twig

Les filtres sont enregistrés quand Twig est installé.

| Filtre | Entrée | Sortie |
|---|---|---|
| `typography(locale = null)` | HTML | HTML corrigé, marqué sûr |
| `typography_text(locale = null)` | Texte brut | Texte corrigé, échappé comme d'habitude par Twig |

Sans locale, les deux utilisent la locale courante, celle de la requête, et donnent une chaîne vide pour `null`.

## L'échappement

`typography` échappe d'abord son entrée si elle n'est pas marquée sûre, puis la corrige comme du HTML. Une chaîne
venue d'un visiteur reste du texte : son `<script>` est affiché, pas exécuté. Le HTML auquel l'application fait
confiance est marqué sûr avant le filtre (avec `|raw`, ou parce qu'un filtre comme `ujo_html` ou `markup_html` l'a
retourné sûr) et garde son balisage :

```twig
{{ comment.text|typography }}                 {# escaped, then fixed #}
{{ article.bodyHtml|raw|typography }}         {# trusted HTML, fixed #}
{{ article.body|ujo_html|typography('fr') }}  {# safe output of another filter #}
```

Les correcteurs ne réécrivent que le texte : ce que retourne `typography` est donc aussi sûr que ce qu'il a reçu.

`typography_text` traite son entrée comme du texte brut, où les balises sont des caractères, et Twig échappe sa
sortie comme n'importe quelle chaîne. Utilisez-le pour les titres, les attributs et les autres endroits où le
balisage n'a pas de sens :

```twig
<title>{{ page.title|typography_text }}</title>
<img alt="{{ image.caption|typography_text }}" src="…">
```

## La locale d'un gabarit

Sans locale, les filtres demandent la locale courante au `LocaleSwitcher` de Symfony. C'est la locale de la requête,
telle que le routeur et `LocaleListener` la fixent, ou celle vers laquelle bascule `LocaleSwitcher::runWithLocale()`,
dans une commande ou un *handler* de message qui produit un courriel dans la langue de son lecteur. Hors d'une
requête, le sélecteur donne la locale par défaut du noyau. Sans le traducteur, `symfony/translation`, il n'y a pas de
sélecteur et les filtres utilisent `default_locale`.

```twig
{{ article.body|raw|typography }}   {# the locale of the request #}
```

```php
$html = $localeSwitcher->runWithLocale('de_CH', fn (): string => $twig->render('mail.html.twig', $context));
```

Un texte dont la langue n'est pas celle de la page passe la sienne, qui l'emporte toujours :

```twig
<blockquote lang="en-GB">{{ quote.text|typography_text('en_GB') }}</blockquote>
```
