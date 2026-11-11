<!-- phil-dev source-sha256=2e95a7d1aca6cef6a766e7e7058dd9e90d8094990a5092bf27cd4c0f4bf5fd47 -->
# Sortie HTML

`TextDiff::toHtml()` — ou `HtmlFormatter::format()` — écrit un `Diff` en HTML : le texte conservé tel quel, le texte
supprimé dans `<del>`, le texte inséré dans `<ins>`. Les éléments ne portent aucun attribut ; mettez-les en forme
comme la page le souhaite.

## Texte brut

Une différence produite par `Words`, `Lines` ou `Paragraphs` est du texte brut : chaque partie est échappée (`&`, `<`,
`>`, `"` et `'`), et les sauts de ligne sont conservés tels quels.

```php
echo $differ->toHtml($differ->lines("one\ntwo", "one\n2"));
// one
// <del>two</del><ins>2</ins>
```

Affichez-la dans un élément mis en forme avec `white-space: pre-wrap` pour voir les sauts de ligne et les paragraphes.

## HTML

Une différence produite par `Html` est du balisage : donnez-lui du HTML déjà purifié — la sortie du moteur de rendu de
`phil/ujo`, du purificateur de `phil/markup`, ou de n'importe quel assainisseur —, puisque ses balises sont écrites
telles quelles. Le résultat **garde la structure du nouveau HTML** :

- les balises conservées sont écrites telles quelles ;
- les balises insérées sont écrites telles quelles, et le texte inséré entre elles est enveloppé dans `<ins>` ;
- les balises supprimées sont omises, et le texte supprimé entre elles est enveloppé dans `<del>`, là où il se
  trouvait.

Le texte entre les balises, qu'il soit conservé, supprimé ou inséré, est échappé (`&`, `<`, `>`, `"` et `'`), et les
références de caractères connues (`&amp;`, `&#233;`) restent telles quelles : aucun texte de l'ancien HTML ne devient
du balisage du nouveau. Une balise est lue selon la grammaire de ses attributs : un `>` dans une valeur entre
guillemets, `<abbr title="a>b">`, reste dans la balise, et un `<` qui n'ouvre pas une balise entière est du texte. Le
contenu de `<script>` et de `<style>`, qui n'est pas du HTML, n'est pas comparé comme tel : donnez du HTML sans eux.

Ainsi, `<ins>` et `<del>` n'enveloppent jamais que du texte, jamais une balise, et le résultat est exactement aussi
bien formé que le nouveau HTML.

```php
echo $differ->toHtml($differ->html('<p>Hello <b>big</b> world</p>', '<p>Hello <i>small</i> world!</p>'));
// <p>Hello <del>big</del><i><ins>small</ins></i> world<ins>!</ins></p>
```

Les espaces seuls entre deux balises d'un changement ne sont pas marqués : insérés, ils sont écrits tels quels ;
supprimés, ils sont omis. Cela tient `<ins>` et `<del>` à l'écart des endroits où seuls des éléments peuvent se
trouver, entre deux éléments de liste ou deux cellules de tableau.

Ce à quoi ce choix renonce :

- **le changement d'une balise seule n'est pas marqué** : un texte gras devenu italique affiche le texte italique sans
  marque. La liste de changements, et les `FieldChange` d'un document, le contiennent ;
- **un élément supprimé sans texte** — une image, un saut de ligne — n'est pas affiché, puisque sa balise est omise ;
- le texte supprimé peut aboutir dans un parent où le texte est inhabituel, comme un `<ul>` entre deux éléments ; les
  navigateurs en produisent quand même le rendu.

## Mise en forme

```css
ins { background: #e6ffec; text-decoration: none; }
del { background: #ffebe9; text-decoration: line-through; }
```

Les blocs de la différence de deux documents portent leurs propres classes ([documents.md](documents.md)).
