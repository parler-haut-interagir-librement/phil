<!-- phil-dev source-sha256=73544956d906a525b69d184eccaf6e4cd61bdc72a4bb5370545df39c31883270 -->
# Points d'extension

La bibliothèque est en PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des correcteurs
et des motifs de césure ; ce qu'elle choisit, ce sont les correcteurs exécutés pour une locale et les éléments laissés
tels quels.

## `Fixer`

`Phil\Typography\Fixer\Fixer` : une règle typographique appliquée à une suite de texte. Voir
[Correcteurs](fixers.md#écrire-un-correcteur).

| Méthode | Contrat |
|---|---|
| `fix(Run $run): Run` | Ne réécrit que du texte, par `Run::replace()`, `Run::map()` ou `Run::characters()` : ce qu'il retourne est écrit dans des nœuds texte, si bien que le balisage qu'il écrit reste des caractères |

## Typographes

| Point | Comment |
|---|---|
| Les correcteurs d'une locale | `Typographer::for($locale)` donne le préréglage de `Preset` ; `new Typographer($fixers)` accepte n'importe quelle liste, les correcteurs de l'application parmi ceux fournis, dans l'ordre où ils s'exécutent |
| Un typographe par locale | `Typographers` : une locale par défaut, et des noms de correcteurs par locale à la place de leurs préréglages |
| Les éléments laissés tels quels | Les balises `$protected` de `Typographer::for()`, des constructeurs et de `Typographers` : `Typographer::PROTECTED_TAGS` par défaut, soit `pre`, `code`, `kbd`, `script`, `style`, `textarea` |
| La césure | L'indicateur `$hyphenation` de `Typographer::for()`, et le `Dictionary` dans lequel il lit les motifs |
| Les motifs que la bibliothèque ne fournit pas | `new Dictionary($directory)` sur des fichiers `<tag>.pat.txt` et `<tag>.hyp.txt`, ou `Patterns::parse()` et `new Hyphenator($patterns)` ; voir [Césure](hyphenation.md#vos-propres-motifs) |

La bibliothèque ne récupère jamais de motifs : les motifs sous une licence qu'elle ne fournit pas sont à la charge de
l'application, sous sa propre responsabilité.
