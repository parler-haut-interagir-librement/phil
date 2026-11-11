<!-- phil-dev source-sha256=b324e6320354c6d0df0f6375c6bbe97e7f93ed9d81faa580efddcca2bf50ebb8 -->
# HTML et texte

## Trois points d'entrée

| Méthode | Entrée | Sortie |
|---|---|---|
| `fix(string $html)` | Un fragment, tel qu'on en trouve dans le corps d'une page | Le fragment, sérialisé en HTML5 |
| `fixDocument(string $html)` | Un document entier, doctype, en-tête et titre compris | Le document |
| `fixText(string $text)` | Du texte brut, lu comme un seul bloc ; les octets qui ne sont pas de l'UTF-8 deviennent `?` | Le texte |

`Typographers::fixHtml(string $html, $locale = null)` corrige un fragment avec le typographe d'une locale, celle par
défaut quand elle vaut `null`. Chaque passage dont le `lang` nomme une autre langue (`<span lang="en">`,
`<blockquote lang="de">`) est corrigé avec le typographe de cette langue : ses guillemets, ses espaces avant la
ponctuation, sa césure. Un passage dans une langue qu'aucun typographe ne sert est corrigé comme le texte qui
l'entoure. Un passage forme sa propre suite : une paire de guillemets n'est donc jamais partagée entre deux langues.

Un fragment est analysé dans le contexte de `<body>`, comme `innerHTML` l'analyserait : un `<td>` hors d'un tableau est
retiré, comme un navigateur le retire. La sortie est sérialisée par le sérialiseur HTML5 de PHP : une espace
insécable s'écrit `&nbsp;`, les autres caractères tels quels, les attributs entre guillemets doubles. Un `<` ou un `>`
dans la valeur d'un attribut s'écrit ensuite `&lt;` ou `&gt;`, comme le sérialise la norme HTML actuelle et comme ne le
fait pas le sérialiseur de PHP : un outil qui lit une balise jusqu'à son premier `>`, comme une différence de HTML, ne
la coupe jamais à l'intérieur d'une valeur.

## Seul le texte change

Un correcteur reçoit du texte et retourne du texte ; il ne voit jamais de balisage. Les éléments, les attributs (un
`alt`, un `title`, un `href`) et les commentaires sont réécrits tels qu'ils ont été analysés. Ce qu'ajoutent les
correcteurs, ce sont des caractères : guillemets typographiques, tirets, espaces insécables, traits d'union
conditionnels. Du HTML sûr avant l'est tout autant après : c'est pourquoi la bibliothèque peut s'exécuter sur la sortie
d'un assainisseur.

## Suites de texte

Le texte est corrigé bloc par bloc. Tous les nœuds texte entre deux limites de bloc forment un `Run` : les parties
sont les nœuds texte dans l'ordre de lecture. Un guillemet ouvert avant `<em>` et fermé après forme donc une seule
paire, et une espace du français se place avant un `!` qui suit un `</strong>`. Les limites sont les éléments qui
ouvrent et ferment des blocs (`p`, `h1` à `h6`, `li`, `td`, `th`, `blockquote`, `div`, `section`, `figcaption`, `dt`,
`dd`, `caption`, `option`, `title` et les autres listés dans `Blocks::BOUNDARIES`), ainsi que `br` et `hr`.

Un correcteur qui change une partie peut regarder les caractères qui l'entourent (le dernier caractère des parties
précédentes et le premier des parties suivantes), mais ne les change jamais : `Run::replace()` ne garde un résultat
que lorsque les voisins sont inchangés. Un correcteur qui forme des paires, comme `SmartQuotes`, lit toute la suite
comme une seule liste de caractères.

## Éléments protégés

Le texte d'un élément protégé n'est jamais changé. Il appartient toujours à sa suite, comme une partie verrouillée :
`"<code>x</code>"` reçoit ses guillemets, et `x <code>y</code>"z"` ferme le guillemet après `y`. La liste par défaut
est `pre`, `code`, `kbd`, `script`, `style` et `textarea` ; le second argument du constructeur la remplace :

```php
$typographer = new Typographer($fixers, ['pre', 'code', 'kbd', 'samp', 'var', 'script', 'style', 'textarea']);
```

## Émojis

Pour aucun correcteur les émojis ne sont des lettres, des chiffres ou de la ponctuation. Chaque séquence (jointe par
des liants sans chasse, avec des tons de peau, des sélecteurs de variante, des touches, des drapeaux ou des caractères
d'étiquette) ressort octet pour octet : la césure ne coupe que des suites de lettres et leurs signes combinants, la
normalisation en forme C laisse les émojis tels quels, et un guillemet voisin d'un émoji est apparié comme s'il était
voisin d'un mot.
