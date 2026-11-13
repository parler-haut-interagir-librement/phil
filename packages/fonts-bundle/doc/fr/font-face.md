<!-- phil-dev source-sha256=acd0039a038fcf625625308704604c33197bf7ee89b558cfee1b2a6e7c3d32bd -->
# Règles `@font-face`

`FontFaces` écrit les règles du catalogue :

```php
$css = $faces->css();
```

Les fichiers d'une même face en plusieurs formats (le même chemin à l'extension près, comme `sans/Regular.woff2`,
`sans/Regular.woff` et `sans/Regular.ttf`) forment une seule règle. Ses sources sont d'abord les noms locaux de la face,
puis ses fichiers, du format le plus petit au plus gros : WOFF2, WOFF, OpenType, TrueType. Les métadonnées de la face
(famille, graisse ou plage de graisses, style, largeur) viennent du premier de ces fichiers qui a été lu. Un fichier
WOFF2 illisible sans `ext-brotli` reste donc listé en premier, à côté d'un équivalent qui a été lu.

```css
@font-face {
    font-family: "Example Sans";
    src: local("Example Sans Regular"), local("ExampleSans-Regular"), url("/fonts/sans/Regular.woff2") format("woff2"), url("/fonts/sans/Regular.ttf") format("truetype");
    font-weight: 400;
    font-style: normal;
    font-stretch: 100%;
    font-display: swap;
}
```

Sont exclues : les collections, que les navigateurs ne chargent pas par `@font-face` ; les faces dont la table `name` ne
donne aucune famille ; et les faces dont aucun fichier n'a été lu. Chaque chaîne tirée d'une police est échappée par
`phil/fonts` : rien ne peut donc clore la règle ou l'élément `<style>`.

| Méthode | Retourne |
|---|---|
| `rules(?Closure $url = null)` | Les valeurs `FontFaceRule`, par radical |
| `css(?Closure $url = null)` | Les règles en une seule feuille de style, une ligne vide entre deux, terminée par un saut de ligne ; vide sans police |
| `url(CatalogueEntry $entry)` | L'URL d'un fichier : le chemin public, puis son chemin, avec chaque dossier et le nom encodés en pourcentage |

La fermeture donne l'URL d'un fichier à la place de `url()`, pour une feuille de style placée ailleurs. L'EPUB de
`phil/epub-bundle` pointe ainsi vers `../fonts/…` :

```php
$faces->css(static fn (CatalogueEntry $entry): string => 'https://cdn.example.com/fonts/'.$entry->path);
```

## Dans un gabarit

```twig
<style>{{ fonts_css() }}</style>
```

`fonts_css()` est enregistrée quand Twig est installé et affiche `css()` tel quel.

## Dans un fichier

```sh
bin/console phil:fonts:css --output=assets/styles/fonts.css
```

Sans `--output`, la commande affiche les règles. Une étape de construction qui écrit la feuille de style une fois évite
à la requête de lire les polices ; sinon, le catalogue est lu au plus une fois par requête.
