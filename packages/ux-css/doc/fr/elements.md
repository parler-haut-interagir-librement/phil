<!-- phil-dev source-sha256=464c88be5fba7e3f8667439a3d06ac2f6b0f49e824779cfa6dabb8e07129bb0d -->
# Éléments

La couche de base, `phil.base`, habille les éléments HTML sans classe.

| Éléments | Ce qu'ils reçoivent |
|---|---|
| `*`, `::before`, `::after` | `box-sizing: border-box` |
| `html` | La pile sans empattement, l'interligne du corps, les couleurs du texte et de la surface, la marque en `accent-color` |
| `body` | Aucune marge, la taille du corps |
| `h1` à `h6` | L'échelle du texte de `3xl` jusqu'à `sm`, l'interligne serré, des lignes équilibrées |
| `p`, `ul`, `ol`, `dl`, `blockquote`, `pre`, `figure`, `table` | Une marge inférieure de `md` ; `p` avec un retour à la ligne soigné |
| `hgroup` | Ses enfants sans marges : un surtitre et son titre lus comme un tout |
| `a` | La couleur des liens, un soulignement tiré de la police, plus épais au survol |
| `:focus-visible` | Un anneau de `--phil-color-focus`, 3:1 sur la surface |
| `img`, `svg`, `video`, `canvas`, `picture` | Des blocs, jamais plus larges que leur conteneur |
| `figcaption`, `small` | La petite taille ; les légendes atténuées |
| `hr` | Un filet de la couleur des bordures |
| `code`, `kbd`, `samp`, `pre` | La pile à chasse fixe ; `pre` défile sur la surface surélevée |
| `blockquote` | Un filet de la marque sur son côté de début |
| `mark` | La teinte d'avertissement |
| `dt`, `dd` | Un terme en graisse moyenne, sa description alignée sur lui |
| `kbd`, `abbr[title]` | Une touche sur la surface surélevée ; un soulignement pointillé |
| `[hidden]` | Caché, quel que soit l'affichage qu'une classe définit ; `until-found` est laissé au navigateur |

## Formulaires

| Éléments | Ce qu'ils reçoivent |
|---|---|
| `fieldset`, `legend` | Un cadre arrondi, un titre en graisse moyenne |
| `label` | Un bloc au-dessus de son contrôle ; à côté de lui, à la hauteur d'une cible tactile, quand il enveloppe une case à cocher ou un bouton radio |
| Les champs de texte, `date`, `search` et leurs semblables, `select`, `textarea` | Pleine largeur, la hauteur d'une cible tactile (2.75rem), la bordure neutre forte, 3:1 sur la surface |
| `::placeholder` | La couleur du texte atténué |
| `checkbox`, `radio`, `range`, `date`, les sélecteurs | Les contrôles du navigateur, dans la marque par `accent-color` et dans le schéma par `color-scheme` ; une case à cocher ou un bouton radio fait 24 pixels CSS |
| `color`, `file` | Une pastille encadrée ; `::file-selector-button` en bouton discret |
| `:user-valid` | La bordure de succès |
| `:user-invalid`, `[aria-invalid="true"]` | La bordure de danger, doublée pour ne pas reposer sur la couleur seule |
| `:disabled` | Estompé, un curseur d'interdiction |

`:user-valid` et `:user-invalid` attendent que le lecteur touche au champ : un formulaire vierge s'ouvre sans erreurs.

### Les formulaires de Symfony

Le balisage de `form_row` est habillé tel que Symfony l'écrit, sans thème de formulaire à installer.

| Balisage | Ce qu'il reçoit |
|---|---|
| `form div` qui contient un `label` ou un bouton | L'espace entre deux rangées |
| `label.required` | Une étoile, cachée aux lecteurs d'écran, puisque le champ porte `required` |
| Les erreurs, un simple `ul` après le libellé ou au début du formulaire | Sans puces, petites, dans la couleur de danger |
| `.help-text` | Petit et atténué, sous le widget |
| Une rangée de case à cocher, le libellé d'abord | La case avant son libellé, sans toucher à l'ordre de la source |
| Des choix déployés, frères dans un `div` | Une case et son libellé sur chaque ligne |

## Boutons

`button`, `input` de type `submit`, `button` ou `reset`, et `[role="button"]` : pleins et dans la marque, de 2.75rem de
haut, estompés quand ils sont désactivés ou `aria-disabled`. Le survol ou l'appui mêle le remplissage vers la couleur
du texte. Voir [Composants](components.md) pour les autres apparences.

## Tableaux

| Éléments | Ce qu'ils reçoivent |
|---|---|
| `table` | Pleine largeur, fusionné, des chiffres tabulaires |
| `caption` | Petite, atténuée, au début |
| `th`, `td` | Alignés au début, une ligne sous chaque rangée |
| `thead th`, `tfoot` | Une ligne épaisse du neutre fort, une graisse moyenne |
| `figure:has(> table)` | Fait défiler un tableau trop large pour la page |

Les rangées rayées sont la classe `isStriped`.

## Dialogues et bulles

| Éléments | Ce qu'ils reçoivent |
|---|---|
| `dialog` | La largeur `--phil-panel-md` ou `--phil-dialog-size`, au plus la hauteur de l'écran, défilant à l'intérieur ; modal ou non |
| `dialog > footer` | Ses actions au bout de la ligne |
| `dialog::backdrop` | `--phil-color-backdrop` |
| `html:has(dialog:modal)` | Tient la page immobile derrière une fenêtre modale |
| `[popover]` | Un panneau, centré par le navigateur jusqu'à ce qu'un contrôleur le place et pose `data-placed` |

Ils apparaissent et disparaissent en fondu là où le navigateur sait animer la couche supérieure.

## Dévoilements, navigation et jauges

| Éléments | Ce qu'ils reçoivent |
|---|---|
| `details`, `summary` | Un résumé assez haut pour le toucher ; le contenu s'ouvre jusqu'à `height: auto` là où le navigateur le permet |
| Les listes de `nav` | Sans puces, en rangée qui passe à la ligne |
| `nav a` | La hauteur d'une cible tactile |
| `nav a[aria-current]` | La couleur du texte et une graisse grasse |
| `progress` | Une barre arrondie dans la marque ; une barre indéterminée se déplace, et s'immobilise sous le mouvement réduit |
| `meter` | De même, en succès, avertissement ou danger selon ce que le navigateur juge de la valeur |

Chaque direction est une propriété logique : une page de droite à gauche n'a besoin de rien de plus. Le seul axe
physique est le défilement des tableaux, des barres d'onglets, du code et des carrousels, écrit `overflow-x` parce que
`overflow-inline` n'est pas encore dans tous les navigateurs que cette feuille prend en charge.

Sous `prefers-reduced-motion: reduce`, ou sous une racine qui porte `data-motion="reduce"`, chaque animation et chaque
transition se termine aussitôt. Ces déclarations sont `!important` dans la couche de base, ce qui les fait l'emporter
sur toutes les couches suivantes et sur les styles du site.

## Les préférences d'affichage du lecteur

La page suit les requêtes média du système. Une application qui laisse chaque lecteur choisir écrit le choix sur
l'élément racine, et la couche de base l'applique comme elle applique la requête ; sans l'attribut, la requête décide.

| Attribut sur la racine | Comme | Effet |
|---|---|---|
| `data-theme="light"` ou `"dark"` | `prefers-color-scheme` | Le schéma, voir [Thèmes](themes.md) |
| `data-motion="reduce"` | `prefers-reduced-motion: reduce` | Chaque animation et chaque transition se termine aussitôt |
| `data-contrast="more"` | `prefers-contrast: more` | Le texte atténué prend la couleur du texte, les bordures la bordure neutre forte |
| `data-text-size="large"` ou `"larger"` | la taille du texte du navigateur | La racine à 112,5 % ou à 125 %, et chaque taille de l'échelle suit |

```twig
<html lang="{{ app.request.locale }}" data-motion="reduce" data-text-size="large">
```
