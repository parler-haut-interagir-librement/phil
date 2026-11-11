<!-- phil-dev source-sha256=65f53e836d5bd69f74d4c8ed0b762354438ff206bf7488306e47d27af4fc592c -->
# Sélecteurs

Chaque sélecteur d'une règle de style est vérifié seul. Un sélecteur refusé est listé (`Selector`) et la règle garde
les autres. Une règle restée sans sélecteurs est écartée.

## La grammaire vérifiée

Un sélecteur complexe est une liste de sélecteurs composés reliés par un blanc, `>`, `+` ou `~`. Un sélecteur composé
est, dans l'ordre :

1. un sélecteur de type facultatif (`p`) ou `*` ;
2. autant de sélecteurs d'ID (`#main`), de classe (`.note`), d'attribut et de pseudo-classes qu'il faut ;
3. autant de pseudo-éléments qu'il faut, qui terminent le sélecteur complexe.

| Partie | Ce qui est vérifié |
|---|---|
| ID | Un dièse qui est un identifiant : `#1a` est refusé |
| Attribut | `[name]`, `[name=value]`, `[name~=value]`, `[name\|=value]`, `[name^=value]`, `[name$=value]`, `[name*=value]`, une valeur sous forme d'identifiant ou de chaîne, un `i` ou un `s` facultatif ; le nom parmi les attributs de la politique |
| Pseudo-classe | Un nom présent dans la politique ; `:not()`, `:is()` et `:where()` prennent des listes de sélecteurs sans pseudo-éléments ; `:nth-child()`, `:nth-last-child()`, `:nth-of-type()` et `:nth-last-of-type()` prennent An+B ; `:lang()` prend des noms et des chaînes ; `:dir()` prend `ltr` ou `rtl` |
| Pseudo-élément | Un nom présent dans la politique, avec deux deux-points, ou un seul pour `before`, `after`, `first-line` et `first-letter` ; aucune fonction |

Refusés quoi que dise la politique : les espaces de noms (`ns|a`, `*|a`), le sélecteur d'imbrication `&` hors d'une
règle imbriquée, le combinateur de colonne `||`, les pseudo-éléments fonctionnels (`::part()`), `:nth-child(… of S)`,
et une pseudo-classe fonctionnelle dont la vérification ne sait pas lire les arguments (`:has()`, `:nth-col()`), même
si la politique la nomme.

## Les sélecteurs imbriqués

Le sélecteur d'une règle imbriquée dans une règle de style est relatif à son parent. Il passe la même grammaire, avec
deux ajouts : il peut commencer par un combinateur (`> p`, `+ h2`), et chacun de ses sélecteurs composés peut contenir
`&`, le parent, après un sélecteur de type facultatif — `&:hover`, `p&.note`, `.dark &`, `& + &`. Un sélecteur sans `&`
s'applique aux descendants du parent, ou à ses frères après un `+` ou un `~` de tête.

```css
article {
  & h2 { margin: 0 }
  > p { text-indent: 1em }
  &:hover { color: #702 }
}
```

`&` est refusé dans les arguments de `:not()`, `:is()` et `:where()` : `:not(&)` correspond à chaque élément que le
parent ne désigne pas, donc à toute la page hors de la portée de la politique. Ailleurs, `&` représente un élément que
la portée contient déjà, et un sélecteur qui le contient n'atteint que cet élément, ses descendants et ses frères, dans
la portée. `&` au premier niveau d'une feuille de style, où il désigne la racine, est refusé. Les règles imbriquées ne
reçoivent jamais la portée en préfixe.

## Pourquoi les attributs sont limités

Un sélecteur d'attribut peut tester la valeur d'un champ, lettre par lettre, et un arrière-plan chargé pour chaque
correspondance dit à un serveur ce qui a été tapé. La politique par défaut permet seulement `lang` et `dir`. En
permettre davantage — `withAttributes('data-*')` — est sans danger tant que le réécrivain d'URL garde chaque URL sur
les fichiers propres à l'application.
