<!-- phil-dev source-sha256=9caa50d798c46e24512ec64243ece7db3cabc642ad7d1c59d79e5d4552c97454 -->
# Lignes

Par défaut, le HTML n'a pas de structure de lignes : les sauts de ligne de la source gardent les lignes dans `<pre>`.
Avec des `LineOptions`, `toHtml()` et `highlight()` écrivent plutôt le bloc ligne par ligne, pour qu'un thème puisse
numéroter les lignes, en marquer certaines et montrer ce qu'un changement ajoute et retire.

```php
use Phil\Highlight\Highlighter;
use Phil\Highlight\LineOptions;
use Phil\Highlight\LineRanges;

$options = LineOptions::numbered()->withHighlighted(LineRanges::parse('2'))->withAnnotations();

echo Highlighter::default()->toHtml("\$a = 1;\n\$b = 2;\n\$c = 3; // [!code ++]\n", 'php', $options);
```

```html
<span class="hl-line"><span class="hl-line-number">1</span><span class="hl-variable">$a</span> …</span>
<span class="hl-line hl-line-highlighted"><span class="hl-line-number">2</span><span class="hl-variable">$b</span> …</span>
<span class="hl-line hl-line-inserted"><span class="hl-line-number">3</span><span class="hl-variable">$c</span> …</span>
```

## Le HTML d'une ligne

| Balisage | Quand |
|---|---|
| `<span class="hl-line">` | Chaque ligne, ses jetons à l'intérieur ; les lignes sont jointes par les sauts de ligne de la source |
| `hl-line-highlighted`, en plus | La ligne est dans les plages mises en évidence, ou un `[!code highlight]` la couvre |
| `hl-line-inserted`, en plus | Un `[!code ++]` la couvre |
| `hl-line-deleted`, en plus | Un `[!code --]` la couvre |
| `<span class="hl-line-number">` | En tête de ligne, quand les lignes sont numérotées |

Une ligne porte chaque marque qui la couvre, dans l'ordre du tableau. Un jeton qui s'étend sur plusieurs lignes, comme
un commentaire de bloc, est coupé en un `span` par ligne, chacun de son type. Un saut de ligne qui termine la source
termine la dernière ligne ; il n'en ouvre pas une vide. La sortie ne contient toujours que des `<span class="…">` et du
texte échappé.

## Les options

`LineOptions` porte trois choix, chacun changé seul par sa méthode `with…()` :

| Option | Défaut | Effet |
|---|---|---|
| `firstNumber` | `null` | Le numéro de la première ligne, de 0 à 1 000 000 ; `null` n'écrit aucun numéro. `LineOptions::numbered()` commence à 1 |
| `highlighted` | aucune | Les lignes mises en évidence, une `LineRanges` |
| `annotations` | `false` | Si les annotations des commentaires sont lues et retirées |

Les plages mises en évidence comptent les lignes par leur place dans le bloc, à partir de 1, quel que soit le premier
numéro : avec `numbered(10)`, la plage `2` marque la ligne numérotée 11.

## Plages de lignes

`LineRanges::parse()` lit la notation des blocs de code et des gabarits : des lignes et des plages inclusives séparées
par des virgules, avec des espaces et des tabulations permis autour de chaque partie.

```php
LineRanges::parse('1,3-5');     // lines 1, 3, 4 and 5
LineRanges::parse(' 7 - 9 , 2'); // lines 2, 7, 8 and 9
LineRanges::parse('');          // no line
LineRanges::of([3, 5], [1, 1]); // the same as '1,3-5'
```

Les plages sont triées et fusionnées, si bien que `toString()` donne le texte le plus court, que `parse()` relit. Une
ligne va de 1 à 1 000 000, un texte contient au plus 256 parties, et une plage finit à son début ou après ; tout le
reste lève `InvalidLineRanges`, dont le message cite au plus 64 octets du texte.

## Annotations

Avec `annotations`, un commentaire peut marquer sa propre ligne :

| Annotation | Marque |
|---|---|
| `[!code ++]` | Ajoutée |
| `[!code --]` | Retirée |
| `[!code highlight]` | Mise en évidence |

Suivie de `:` et d'un nombre, une annotation marque autant de lignes à partir de la sienne : `[!code highlight:3]`
marque sa ligne et les deux suivantes, et s'arrête à la fin du bloc. Seuls les commentaires sont lus — une annotation
dans une chaîne reste telle quelle —, et un autre mot, comme `[!code focus]`, est laissé tel quel.

L'annotation est retirée du commentaire, avec les espaces qui la précèdent. Un commentaire qui ne garde que ses
délimiteurs — `//`, `#`, `--`, `;`, `%`, `/* */`, `<!-- -->`, `{# #}`, `(* *)` — disparaît, avec les espaces qui le
précèdent sur sa ligne :

| Source | Ligne écrite |
|---|---|
| `$a = 1; // [!code ++]` | `$a = 1;`, ajoutée |
| `$a = 1; // why [!code ++]` | `$a = 1; // why`, ajoutée |
| `<p>x</p> <!-- [!code --] -->` | `<p>x</p>`, retirée |

Les annotations changent le texte du bloc. Un moteur de rendu qui compare le texte reçu au code, comme le font les
blocs de code de `phil/ujo`, garde la sortie de `highlight()` sans `LineOptions`.

## Sans langage connu

`HtmlFormatter::formatLines()` prend n'importe quels jetons. Pour un langage que le colorateur ne connaît pas, un seul
jeton sans type donne les lignes sans couleurs :

```php
use Phil\Highlight\HtmlFormatter;
use Phil\Highlight\Token;

HtmlFormatter::formatLines([new Token($code)], LineOptions::numbered());
```

## Coût

Les lignes s'écrivent en un passage sur les jetons, et les annotations en un passage sur les lignes : une annotation
qui couvre beaucoup de lignes ne coûte pas plus qu'une qui ne couvre que la sienne. Le thème fait le reste : voir
[HTML et thèmes](html-and-themes.md#lignes).
