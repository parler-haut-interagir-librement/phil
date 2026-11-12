<!-- phil-dev source-sha256=01ad7ce0f63b1085223eeda88ca1b3f1f1628d42459d7b3d3dd429a02cdbe310 -->
# Images adaptatives

Avec `symfony/ux-twig-component`, le bundle enregistre deux composants Twig qui affichent un média dans un format,
dimensionné avant que l'image n'arrive et offert aux largeurs plus étroites du format.

## `Img`

```twig
<twig:PhilMedia:Img :media="post.cover" format="card" sizes="(min-width: 60em) 50vw, 100vw" class="cover"/>
```

```html
<img src="/media/…/card.webp?signature=…"
     srcset="/media/…/card@320.webp?signature=… 320w, /media/…/card.webp?signature=… 640w" sizes="(min-width: 60em) 50vw, 100vw"
     width="640" height="427" alt="People dancing under paper lanterns" loading="lazy" decoding="async" class="cover">
```

| Propriété | Par défaut | |
|---|---|---|
| `media` | — | Un `Media`, un `MediaId` (ce que contient une colonne `phil_media`) ou le texte de l'identifiant |
| `format` | `original` | Un format déclaré, ou le fichier lui-même converti |
| `type` | `webp` | `webp`, `avif`, `jpeg` ou `png` |
| `alt` | le texte de remplacement du média | `''` pour une image décorative |
| `sizes` | aucun | Affiché avec le `srcset` |
| `priority` | `false` | `true` pour l'image principale au-dessus de la ligne de flottaison ; voir [Priorité](#priorité) |
| `loading` | `lazy`, `eager` avec `priority` | `lazy` ou `eager` |
| `decoding` | `async`, aucun avec `priority` | `async`, `sync` ou `auto` |
| `placeholder` | `true` | `false` pour laisser de côté l'aperçu minuscule du média |

Tout autre attribut, `class` ci-dessus, est affiché sur l'`<img>` tel quel. Un `data-controller` propre au composant est
gardé à côté du contrôleur `placeholder` du bundle.

## `Picture`

```twig
<twig:PhilMedia:Picture :media="post.cover" format="card" sizes="100vw"/>
```

```html
<picture>
    <source type="image/avif" srcset="…/card@320.avif?… 320w, …/card.avif?… 640w" sizes="100vw">
    <source type="image/webp" srcset="…/card@320.webp?… 320w, …/card.webp?… 640w" sizes="100vw">
    <img src="…/card.jpeg?…" srcset="…" sizes="100vw" width="640" height="427" alt="…" loading="lazy" decoding="async">
</picture>
```

Les `<source>` offrent les `types`, `['avif', 'webp']` par défaut, dans l'ordre ; l'`<img>` se replie sur le PNG pour un
original qui peut être transparent (PNG, GIF, WebP, AVIF), et sur le JPEG sinon. Les autres propriétés sont celles
d'`Img`.

## Priorité

```twig
<twig:PhilMedia:Picture :media="page.hero" format="hero" sizes="100vw" priority/>
```

L'image qu'un visiteur voit en premier, au-dessus de la ligne de flottaison, est celle que le navigateur doit récupérer
en premier. Avec `priority`, l'`<img>` est chargée sans délai et récupérée avant les autres, et son décodage est laissé
au navigateur :

```html
<img src="…/hero.jpeg?…" … loading="eager" fetchpriority="high">
```

Avec `symfony/web-link`, l'image est aussi préchargée : la réponse porte
`Link: <…/hero.avif?…>; rel="preload"; as="image"; fetchpriority="high"`, avec `imagesrcset` et `imagesizes` quand
l'image a un `srcset`, et le `type` de la première `<source>` d'un `Picture`, celle que prend un navigateur qui lit
l'AVIF. Le serveur Web peut envoyer cet en-tête plus tôt, en `103 Early Hints`. Un `loading` ou un `decoding` donné à
côté de `priority` l'emporte sur lui. Donnez `priority` à une image par page, deux au plus : une image récupérée en
premier retarde les autres.

## Ce qui est affiché

`ResponsiveImages` calcule, sans rien rendre, la taille que le format donne à l'original (ses transformations
appliquées à la taille et au point focal de l'original par `ImageFormat::apply()`), et liste dans le `srcset` les
largeurs plus étroites du format qui sont plus petites que cette taille, puis le format lui-même. Un format sans largeur
plus étroite n'a pas de `srcset`. Les URL sont celles de `media_url()`, signées et expirant selon la configuration, et
les fichiers sont rendus quand un navigateur les demande pour la première fois.

Un média qui ne peut pas être rendu (un PDF, un SVG, une image dont la taille est inconnue) est affiché comme son
fichier, sans taille ni `srcset`, et un `Picture` n'a alors aucune `<source>`. Une image SVG purifiée s'y affiche comme
une image : voir [Formats et URL](formats.md#url-signées). Un média qui n'existe plus n'affiche rien : un enregistrement dont la colonne a été laissée avec `keep` ne casse pas sa page.

## Aperçus

```yaml
phil_media:
    placeholder:
        enabled: true
        width: 16      # the box the preview fits in, 4 to 64 pixels
        quality: 40
```

Avec `placeholder` actif, chaque version d'une image JPEG, PNG, GIF, WebP ou AVIF reçoit un aperçu minuscule : l'image
entière tenue dans une boîte de `width` pixels, encodée en WebP par le traitement d'images, et gardée sur le média comme
une URL `data:` de 4 096 caractères au plus, quelques centaines en pratique. Il est calculé une fois, quand le fichier
est téléversé ou remplacé ; une nouvelle version le vide et le calcule de nouveau. Une image qui ne peut pas être
rendue, ou dont l'aperçu sort plus grand, n'en a pas, et son téléversement réussit quand même.

Les médias gardés avant l'activation de l'option reçoivent le leur de la commande, jamais d'une page :

```sh
bin/console phil:media:placeholders              # every image without a preview
bin/console phil:media:placeholders --limit=500  # at most 500 in this run
bin/console phil:media:placeholders --dry-run    # how many are missing
```

Elle lit la médiathèque collection par collection, une page de 100 à la fois, et donne un aperçu à chaque image JPEG,
PNG, GIF, WebP ou AVIF de taille connue qui n'en a pas, par `MediaLibrary::placeholder($media)` ; une image qui ne peut
pas être rendue est comptée et laissée sans aperçu. Relancée, elle ne rend que ce qui manque encore : une grande
médiathèque peut donc être remplie en plusieurs passes. Elle échoue quand les aperçus sont désactivés.

Les composants `Img` et `Picture` confient l'aperçu au contrôleur Stimulus `placeholder` du bundle, avec le point focal,
et n'écrivent aucun attribut `style` :

```html
<img src="…/card.webp?…" width="640" height="427" alt="…" loading="lazy" decoding="async"
     data-controller="phil--media-bundle--placeholder"
     data-phil--media-bundle--placeholder-src-value="data:image/webp;base64,UklGR…"
     data-phil--media-bundle--placeholder-position-value="30% 40%">
```

Le contrôleur peint l'aperçu derrière l'image par le CSSOM (il couvre la boîte de l'image, centré sur le point focal),
et le retire une fois l'image chargée ou en échec ; une image déjà dans le cache est laissée telle quelle. La
`Content-Security-Policy` par défaut de `phil/browser-security-bundle`, dont `style-src` refuse les styles en ligne, le
permet, là où un attribut `style` serait retiré. Le contrôleur ne peint qu'une image WebP, AVIF, JPEG ou PNG en ligne
en base 64, de 4 096 caractères au plus, à une position en pourcentages entiers ; toute autre valeur est ignorée. Sans
JavaScript, la page montre les images à mesure qu'elles arrivent. `:placeholder="false"` laisse l'aperçu de côté.
