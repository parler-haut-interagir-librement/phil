<!-- phil-dev source-sha256=99f69891bdbdd814645f91fea790c71f96f3a46becdedb353c46b5f904ec27eb -->
# Mise en page

Les classes de mise en page disent ce qu'est l'élément. Elles ne portent aucune couleur et lisent leurs écarts dans des
jetons, chacun par une propriété personnalisée à elle qu'un site définit sur un élément.

| Classe | Ce que c'est | Ses propriétés personnalisées |
|---|---|---|
| `isStack` | Une colonne d'enfants au même écart, leurs marges retirées | `--phil-stack-gap` (`md`) |
| `isCluster` | Une rangée qui passe à la ligne, le même écart dans les deux sens, centrée sur l'axe transversal | `--phil-cluster-gap` (`sm`) |
| `isGrid` | Autant de colonnes qu'il en tient, chacune au moins de la largeur minimale | `--phil-grid-min` (`--phil-panel-sm`), `--phil-grid-gap` (`md`) |
| `isCenter` | Un bloc centré dans son conteneur, pas plus large qu'une ligne lisible | `--phil-center-measure`, `--phil-center-gutter` |
| `isNarrow` | Pas plus large que la mesure étroite, 36ch | `--phil-center-measure` |
| `isSplit` | Deux colonnes à partir de 48rem, empilées en dessous, le premier enfant d'abord | `--phil-split-gap` (0) |
| `isFullHeight` | Au moins la hauteur de l'écran, `100dvh` | |
| `isCover` | Une colonne avec un remplissage ; son enfant `isCoverBody` prend l'espace qui reste et centre son contenu | `--phil-cover-gap`, `--phil-cover-padding` |
| `isBleed` | Un média qui remplit sa cellule, l'image couvrant la boîte ; 40 % de la hauteur de l'écran sous 48rem | `--phil-bleed-min-size` |
| `hasEntrance` | Sur un `isBleed` : entre par un découpage oblique à partir de 48rem, jamais sous le mouvement réduit | |

## Une page partagée

```html
<div class="isSplit isFullHeight">
    <div class="isCover">
        <header><a href="/" class="isLogo"><img src="/logo.svg" alt="Studio"></a></header>
        <main class="isCoverBody">
            <div class="isNarrow">…</div>
        </main>
    </div>
    <figure class="isBleed hasEntrance">
        <picture>
            <source type="image/webp" srcset="/images/main.webp">
            <img src="/images/main.png" alt="">
        </picture>
    </figure>
</div>
```
