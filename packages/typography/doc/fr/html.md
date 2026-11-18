<!-- phil-dev source-sha256=f9bf865c0eb386007fa0d053e890b6926d6962b2aa7f9172f8116350df3c9c58 -->
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

## Le plafond d'un texte

Chaque point d'entrée refuse un texte plus long que le plafond du typographe avant de l'analyser : il lance
`Exception\InputTooLarge`, qui donne la longueur du texte et le plafond dans `$bytes` et `$limit`, et ne coupe jamais le
texte pour qu'il y tienne. Le plafond se compte en octets, chaque caractère UTF-8 comptant chacun de ses octets. C'est
l'argument `maxBytes` du constructeur, de `Typographer::for()` et de `Typographers` (qui le donne à chaque typographe
qu'il construit et le vérifie dans `fixHtml()`), `Typographer::DEFAULT_MAX_BYTES`, 1 Mio, par défaut :

```php
$typographer = Typographer::for('fr_CA', maxBytes: 262_144);   // 256 KiB
$typographers = new Typographers('fr_CA', maxBytes: 262_144);
```

Le plafond borne le temps et la mémoire que prend un appel, quoi qu'on lui donne : un document HTML est analysé en
entier, et chaque correcteur lit chaque suite de son texte. 1 Mio contient bien des fois un long chapitre ; une
application qui corrige des textes plus longs le relève.

## Analyse et sérialisation

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
