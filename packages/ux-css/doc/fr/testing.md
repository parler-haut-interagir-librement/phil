<!-- phil-dev source-sha256=01fc46411a24bf90641f194c4f428aa2d422ad123ee01748b24aad0534d809e5 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Oklch` | Les façons dont le CSS l'écrit, ce qu'il refuse, la conversion en sRGB linéaire, la gamme et sa tolérance, la luminance écrêtée, le contraste dans les deux sens |
| `TokenSheet` | La dernière déclaration qui l'emporte, les commentaires, les fichiers, les tons et les rôles trouvés, `var()` avec ses replis, `light-dark()`, quelles que soient leur casse et leurs lignes, une fonction qui n'est pas toute la valeur, l'imbrication comptée dans la limite, les boucles et les noms orphelins |
| `ContrastRule` | Les paires listées, une paire sous son minimum dans chaque schéma, un jeton qui ne se résout pas, les écarts 40 et 50 de la palette, la gamme |
| `StylesheetBundler` | Chaque import en couche intégré, les autres laissés, un fichier absent, un import hors du dossier de l'entrée, `dist/phil-ux.css` à jour |
| `Calc` | Les nombres, les longueurs, les durées, `calc()` et ses opérateurs, la priorité et les parenthèses, `var()` suivi et calculé une fois, ce qui n'a pas de valeur, les bornes sur le texte, ses morceaux et la profondeur de `var()` |
| `DesignTokens` | Chaque type du format, les alias, `light-dark()` et sa valeur sombre, un zéro parmi des longueurs, ce qui est laissé de côté, le préfixe, l'ordre, les noms faits de chiffres en objets, l'arrondi, les jetons de phil-ux, `dist/tokens.json` à jour |
| Le thème | La règle de contraste sur les jetons du paquet, la numérotation de la palette, la grille de chaque rôle dans les deux schémas, couleurs et dimensions dans des couches séparées, les échelles en tailles de t-shirt et leurs multiplicateurs, les cibles tactiles |
| Les feuilles de style | Les couches et les imports de l'entrée, chaque `var()` déclaré ou muni d'un repli, chaque classe nommée `isX` ou `hasX` et documentée, une animation seulement là où le mouvement réduit est traité, les éléments et le balisage des formulaires de Symfony habillés, les composants dont le kit dépend, aucune couleur propre ni lecture de la palette dans les couches, les directions logiques, le repli d'impression, les tonalités et les tailles en dernier |

```sh
vendor/bin/phpunit packages/ux-css/tests
php packages/ux-css/bin/build.php    # after a change under css/
```

Deux cibles de fuzzing lisent des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/<target>` : `calc`, une quantité finie ou aucune, et `design-tokens`, chaque propriété un jeton ou
laissée de côté et le fichier encodé en JSON :

```sh
composer fuzz -- calc design-tokens
```
