<!-- phil-dev source-sha256=b8de426f6190559bea892fc319e61de76f5556be6fabc79c6ea05920295e6bec -->
# Jetons

Chaque jeton est une propriété personnalisée de `:root` nommée `--phil-*`, dans l'un de trois fichiers :
`css/tokens/dimensions.css`, `css/tokens/palette.css` et `css/tokens/colors.css`, chacun dans sa couche. Le CSS est la
source ; `dist/tokens.json` donne les mêmes jetons aux outils de conception, au format du Design Tokens Community Group
— voir [Les jetons en JSON](#les-jetons-en-json).

## Les échelles

Chaque échelle est calculée à partir d'une unité et d'un multiplicateur global : un site change une échelle entière
d'un coup.

| Multiplicateur | Par défaut | Change |
|---|---|---|
| `--phil-scale` | `1` | Les espaces et les rayons |
| `--phil-scale-space` | `var(--phil-scale)` | Les espaces |
| `--phil-scale-text` | `1` | Les tailles du texte |
| `--phil-scale-radius` | `var(--phil-scale)` | Les rayons |

| Échelle | Tailles | Unité |
|---|---|---|
| `--phil-space-*` | `3xs` (⅛) `2xs` (¼) `xs` (½) `sm` (¾) `md` (1) `lg` (1½) `xl` (2) `2xl` (3) `3xl` (4) `4xl` (6) | `--phil-space-unit`, 1rem |
| `--phil-text-*` | `xs` `sm` `md` `lg` `xl` `2xl` `3xl` `4xl` | `--phil-text-md`, 1rem, sur le rapport `--phil-text-ratio` (1.25) |
| `--phil-radius-*` | `none` `sm` (1) `md` (2) `lg` (4) `xl` (6) `full` | `--phil-radius-unit`, 0.25rem |
| `--phil-shadow-*` | `sm` `md` `lg` | Leur couleur est `--phil-color-shadow` |

Les autres dimensions : `--phil-leading-tight` et `--phil-leading-md`, `--phil-measure` (65ch) et
`--phil-measure-narrow` (36ch), les piles de polices `--phil-font-sans`, `--phil-font-serif` et `--phil-font-mono` —
celles du système, rien de téléchargé —, `--phil-focus-width` et `--phil-focus-offset`, les durées
`--phil-duration-sm`, `-md`, `-lg` et `-loop`, et les courbes `--phil-ease-out` et `--phil-ease-in-out`.

| Jeton | Par défaut | Pour |
|---|---|---|
| `--phil-weight-regular`, `-medium`, `-bold` | 400, 500, 700 | Les graisses |
| `--phil-border-sm`, `-md`, `-lg` | 1px, 2px, 0.25rem | Les lignes et les filets |
| `--phil-control-sm`, `-md`, `-lg` | 2rem, 2.75rem, 3.25rem | La hauteur d'un contrôle ; `md` est une cible tactile |
| `--phil-check-size` | 1.5rem | Une case à cocher, un bouton radio, la hauteur d'un interrupteur |
| `--phil-panel-sm`, `-md`, `-lg` | 20rem, 32rem, 48rem | La largeur d'un menu, d'un dialogue, d'un panneau latéral |
| `--phil-z-raised`, `--phil-z-overlay` | 1, 10 | L'empilement dans la page |
| `--phil-opacity-disabled` | 0.55 | Ce qui est désactivé, la pulsation d'un squelette |
| `--phil-mix-hover`, `--phil-mix-active` | 12 %, 24 % | De combien un remplissage va vers la couleur du texte |

## La palette

Sept teintes — `neutral`, `brand`, `red`, `green`, `amber`, `blue`, `violet` — de onze tons chacune, numérotés selon
leur clarté OKLCH : `--phil-brand-40` est `oklch(0.40 …)`. Les neutres ajoutent `99`, le presque blanc de la page.

| Numéro | 05 | 10 | 20 | 30 | 40 | 50 | 60 | 70 | 80 | 90 | 95 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Clarté | 0.05 | 0.10 | 0.20 | 0.30 | 0.40 | 0.50 | 0.60 | 0.70 | 0.80 | 0.90 | 0.95 |

Puisque le numéro est la clarté, la différence de deux numéros donne le contraste, quelles que soient les teintes :

| Différence | Contraste atteint | Pour |
|---|---|---|
| 50 ou plus | 4,5:1 | Le texte de toute taille |
| 40 ou plus | 3:1 | Le grand texte, les bordures, les icônes, les anneaux de focus |

Chaque chroma est propre à sa teinte, tenu dans la gamme sRGB à chaque clarté : un ton paraît le même sur chaque écran.

## Les rôles de couleur

Les composants lisent des rôles, jamais la palette. Chaque rôle est `light-dark(<light tone>, <dark tone>)`.

| Rôle | Clair | Sombre |
|---|---|---|
| `--phil-color-surface` | neutral 99 | neutral 10 |
| `--phil-color-surface-raised` | neutral 95 | neutral 20 |
| `--phil-color-text` | neutral 10 | neutral 95 |
| `--phil-color-text-muted` | neutral 40 | neutral 70 |
| `--phil-color-link` | brand 40 | brand 70 |
| `--phil-color-focus` | blue 50 | blue 70 |
| `--phil-color-border` | neutral 80 | neutral 30 |
| `--phil-color-shadow` | noir à 12 % | noir à 50 % |
| `--phil-color-backdrop` | noir à 40 % | noir à 60 % |
| `--phil-color-activity-0` | neutral 95 | neutral 20 |
| `--phil-color-activity-1` | brand 90 | brand 30 |
| `--phil-color-activity-2` | brand 70 | brand 50 |
| `--phil-color-activity-3` | brand 50 | brand 70 |
| `--phil-color-activity-4` | brand 30 | brand 90 |

### La grille

Six rôles — `brand`, `neutral`, `danger` (rouge), `success` (vert), `warning` (ambre), `info` (bleu) — ont chacun six
couleurs : un remplissage, une bordure et la couleur sur ce remplissage, chacun discret (une teinte légère) ou fort
(plein).

| Partie | Clair | Sombre | Emploi |
|---|---|---|---|
| `fill-quiet` | 95 | 20 | Le fond d'une teinte légère : un avis, une rangée choisie |
| `fill-loud` | 40 | 70 | Le fond d'un plein : un bouton principal |
| `border-quiet` | 80 | 30 | Une bordure décorative |
| `border-loud` | 50 | 60 | Une bordure qu'il faut voir, 3:1 sur la surface |
| `on-quiet` | 30 | 90 | Le texte et les icônes sur `fill-quiet` |
| `on-loud` | 95 | 10 | Le texte et les icônes sur `fill-loud` |

```css
.isNotice {
    color: var(--phil-color-info-on-quiet);
    background: var(--phil-color-info-fill-quiet);
    border: 1px solid var(--phil-color-info-border-loud);
}
```

## Les jetons en JSON

`dist/tokens.json` contient les jetons au format du W3C Design Tokens Community Group (2025.10), que lisent et
écrivent les outils de conception. Il est produit à partir du CSS, qui reste la source, par la même commande que le
fichier unique :

```sh
php bin/build.php    # from the package, after a change under css/
```

Les tests le comparent à un export des sources et échouent tant qu'il est périmé : l'intégration continue attrape un
changement du CSS sans son JSON. Le paquet npm l'exporte en `@phil/ux-css/tokens.json`.

| CSS | JSON |
|---|---|
| `--phil-space-sm` | Le jeton `sm` du groupe `space` : le premier mot est le groupe, le reste le nom |
| `--phil-scale` | Le jeton `$root` du groupe `scale` : un nom d'un seul mot |
| `var(--phil-brand-40)` | L'alias `{brand.40}`, du type de ce qu'il nomme |
| `oklch(0.40 0.058 195 / 0.5)` | Une `color` : `{"colorSpace": "oklch", "components": [0.4, 0.058, 195], "alpha": 0.5}` |
| `light-dark(A, B)` | La valeur de `A` ; la valeur de `B` sous `dark` dans l'extension du jeton |
| `1rem`, `2px` et leurs `calc()` | Une `dimension`, `calc()` calculé aux multiplicateurs par défaut : `{"value": 0.75, "unit": "rem"}` |
| `150ms`, `1s` | Une `duration` |
| `1.25`, `10` | Un `number` ; un `fontWeight` dans le groupe `weight` |
| Une liste de polices | Une `fontFamily`, chaque famille sans guillemets |
| `cubic-bezier(…)` | Une `cubicBezier` |
| `0 1px 2px var(--phil-color-shadow)`, et leurs listes | Une `shadow`, ou une liste d'ombres, chaque couleur un alias ou une valeur |
| `0` parmi les longueurs d'un groupe | Une `dimension` de `0px` |

Chaque jeton porte, sous l'extension `ca.ph-il.phil-ux`, sa propriété personnalisée et son CSS tel qu'il est écrit,
pour qu'un outil qui réécrit du CSS garde les formules :

```json
"sm": {
    "$type": "dimension",
    "$value": {"value": 0.75, "unit": "rem"},
    "$extensions": {
        "ca.ph-il.phil-ux": {"property": "--phil-space-sm", "css": "calc(var(--phil-space-unit) * 0.75)"}
    }
}
```

Une valeur pour laquelle le format n'a pas de type est laissée de côté, et nommée dans l'extension du fichier :
`--phil-measure` et `--phil-measure-narrow`, en `ch`, et `--phil-mix-hover` et `--phil-mix-active`, en pourcentage.
`calc()` n'est calculé que là où il est une simple arithmétique d'une unité — `+`, `-`, `*`, `/`, parenthèses et
`var()` —, dans des bornes sur sa longueur, ses morceaux et la profondeur de `var()` ; `Token\DesignTokens` fait
l'export et `Token\Calc` l'arithmétique, pour un site qui exporte ses propres surcharges :

```php
use Phil\UxCss\Token\DesignTokens;
use Phil\UxCss\Token\TokenSheet;

$sources = [...DesignTokens::sources('vendor/phil/ux-css/css'), 'assets/theme.css'];
$json = DesignTokens::fromSheet(TokenSheet::fromFiles(...$sources))->toJson();
```
