<!-- phil-dev source-sha256=3841fcc2d2e28c3b01a4f8ff185699f9e3b047408e730e1ecea21f683a4ef411 -->
# Formats et URL

## Formats

Un format est une variation d'une image que l'application déclare par nom : les transformations appliquées l'une après
l'autre, la qualité des types de fichiers avec perte, de 1 à 100, et les largeurs plus étroites auxquelles il est aussi
rendu, pour un `srcset`. Le nom `original` est réservé au fichier lui-même, converti en entier vers un autre type.

```yaml
phil_media:
    formats:
        thumbnail: { width: 320, height: 320 }                     # a box: one thumbnail transformation, crop
        hero:      { width: 1600, height: 900, mode: fit, quality: 70, widths: [480, 960] }
        card:
            quality: 75
            widths: [320, 640]
            transformers:
                - { type: crop, width: 1200, height: 800 }          # around the focal point
                - { type: widen, width: 960 }
                - { type: expand, width: 960, height: 720, background: '#ffffff' }
    original:
        quality: 80
        widths: [640, 1280]
```

Une boîte, `width` et `height` avec un `mode` facultatif, est une seule transformation `thumbnail`. Avec
`transformers`, la boîte est ignorée et la liste est appliquée dans l'ordre. Chaque transformation voit l'image telle
que l'a laissée la précédente, son point focal suivant le mouvement, et chaque taille va de 1 à 8000 pixels :

| `type` | Clés | L'image |
|---|---|---|
| `thumbnail` | `width`, `height`, `mode` | `crop` couvre la boîte et coupe ce qui dépasse autour du point focal ; `fit` reste entière à l'intérieur |
| `crop` | `width`, `height`, `x`, `y` | Une partie de l'image, sans mise à l'échelle, à `x`, `y` ou centrée sur le point focal ; jamais plus grande que l'image |
| `resize` | `width`, `height`, `upscale` | L'image entière à cette taille, ses proportions non gardées |
| `widen` | `width`, `upscale` | L'image entière à cette largeur, ses proportions gardées |
| `heighten` | `height`, `upscale` | L'image entière à cette hauteur, ses proportions gardées |
| `expand` | `width`, `height`, `x`, `y`, `background` | L'image sur un canevas au moins aussi grand, sans mise à l'échelle, à `x`, `y` ou centrée ; la marge transparente, ou `#rrggbb` |

Aucune transformation n'agrandit une image sauf si elle dit `upscale: true` : une image de 40 × 40 dans une vignette de
100 × 100 sort en 40 × 40. En PHP, les mêmes formats sont `ImageFormat::of()` pour une boîte et
`ImageFormat::transforming()` pour une liste de `Transform\Transformer` (`Thumbnail`, `Crop`, `Resize`, `Widen`,
`Heighten`, `Expand`), que `Transformers::fromDefinition()` lit aussi depuis un tableau. `ImageFormat::apply()` calcule
la taille et le point focal qu'un format donne à une image sans la dessiner.

Les `widths` d'un format sont aussi rendues sur demande : `atWidth(480)` est le format suivi d'un `widen` à 480 pixels,
jamais agrandi. La qualité est celle du WebP, de l'AVIF et du JPEG ; le PNG est sans perte.

## Le point focal

```php
$library->focus($media, 0.3, 0.4);   // 0 to 1, across and down; 0.5, 0.5 by default
```

`MediaLibrary::focus()` enregistre le média et, quand le point se déplace d'un centième ou plus, supprime les rendus
produits autour du point précédent. `Media::focus()` seul ne change que l'entité : les rendus autour de l'ancien point
restent alors dans le stockage jusqu'à la suppression du média.

Une `thumbnail` qui recadre et un `crop` sans position gardent le point focal aussi près de leur centre que l'image le
permet ; `RenderBox::fill()` calcule la partie que garde une vignette et la taille qu'elle rend.

## Le rendu

`MediaLibrary::rendered($media, 'thumbnail', 'avif')` rend un format à la première demande et le garde sous
`formats/{id}/{version}/{format}-{x}-{y}.{type}`, où `x` et `y` sont le point focal en centièmes : déplacer le point
focal produit un nouveau rendu. Une largeur plus étroite est gardée sous `{format}-{x}-{y}@{width}.{type}`.
`MediaController` rend un rendu manquant quand son URL est demandée pour la première fois : rien n'est donc rendu
d'avance. `MediaLibrary::replace()` supprime les rendus de la version précédente, dont les URL répondent `404`, et
`MediaLibrary::focus()` ceux autour du point focal précédent. Un document qui n'est pas une image, un format qui n'est
pas déclaré, une largeur que le format ne liste pas, ou un type autre que `webp`, `avif`, `jpeg` et `png` est refusé.

Chaque rendu est dessiné à partir de l'original, jamais à partir d'un autre rendu : un WebP ou un AVIF n'est pas un JPEG
compressé deux fois. `GdImageProcessor` dessine chaque transformation avec GD, transparence gardée, et encode le
résultat. Pour le WebP et l'AVIF, il confie un PNG sans perte aux encodeurs de référence, `cwebp` et `avifenc`, quand ils
sont installés : ils compressent mieux que GD à qualité égale.

```yaml
phil_media:
    encoders:
        webp: auto      # cwebp when found on the PATH, else GD
        avif: /opt/homebrew/bin/avifenc   # this binary; "gd" for GD
```

`BinaryEncoder` lance le binaire sans shell sur deux fichiers d'un répertoire temporaire privé, une minute au plus, et
les retire ensuite ; un encodeur qui échoue donne une `InvalidMedia`, et son URL un `404`.

`GdImageProcessor` lit les dimensions d'une image avant de la décoder, et refuse celle dont la largeur multipliée par la
hauteur dépasse `max_pixels`, 25 millions par défaut : un PNG de quelques centaines de kilooctets peut contenir une
image dont le décodage prend des gigaoctets. Une telle image est quand même téléversée et servie telle quelle ; ses
formats répondent `404`.

### Le rendu par libvips

```yaml
phil_media:
    processor: auto        # libvips when found on the PATH, else GD; "vips" requires it; "gd", the default
    vips_binary: ~         # the path of vips, with vipsheader beside it; ~, searched on the PATH
```

`VipsImageProcessor` rend avec libvips par ses outils en ligne de commande, `vips` et `vipsheader`, selon les mêmes
étapes que GD : libvips lit une image par tuiles au lieu de la décoder en entier, et une grande photo prend une
fraction de la mémoire. Chaque étape est un recadrage (`extract_area`), une mise à l'échelle (`resize`) et un placement
sur un canevas (`embed`), sur des fichiers au format natif de libvips dans un répertoire temporaire privé, retirés
ensuite ; le résultat est enregistré à la qualité du format, sans métadonnées. Seule une image JPEG, PNG, GIF, WebP ou
AVIF qui respecte `max_pixels` atteint libvips, pour qu'aucun de ses autres lecteurs ne lise un téléversement ; chaque
outil tourne sans shell, avec `VIPS_BLOCK_UNTRUSTED`, une minute au plus. Avec `processor: vips` et aucun `vips` trouvé,
le traitement échoue à sa première utilisation avec `InvalidMedia`.

## URL signées

```twig
{{ media_url(media) }}                       {# the file itself #}
{{ media_url(media, 'thumbnail') }}          {# WebP #}
{{ media_url(media, 'thumbnail', 'avif') }}
{{ media_url(media, 'thumbnail', 'avif', 480) }}   {# one of the format's narrower widths #}
{{ media_url(media, 'original', 'webp') }}         {# the file itself, converted #}
```

| Route | Chemin |
|---|---|
| `phil_media_original` | `/{id}/{version}/original` |
| `phil_media_format` | `/{id}/{version}/{format}.{type}` |
| `phil_media_format_width` | `/{id}/{version}/{format}@{width}.{type}` |

Une URL porte une signature HMAC-SHA256 de son chemin et, avec `url_ttl`, du moment où elle expire. Ce moment est
arrondi vers le haut à un palier, un dixième de `url_ttl` et une heure au plus : chaque page rendue pendant un même
palier donne la même URL, qu'un navigateur ou un CDN peut alors garder, et une URL reste valide de `url_ttl` à
`url_ttl` plus un palier. `MediaController` répond `404` à une URL qui n'est pas signée, qui a été signée avec un autre
secret, qui a expiré, qui nomme une ancienne version ou un média inconnu, ou qui demande un format non déclaré :
personne n'atteint un fichier que l'application n'a pas donné.

Puisque la version est dans le chemin, une réponse ne change jamais : elle est envoyée avec
`Cache-Control: public, max-age=31536000, immutable`, si bien qu'un navigateur ou un CDN la garde un an. Une URL qui
expire n'est gardée que jusqu'à son expiration : son `max-age` est le nombre de secondes qui lui restent.

Le fichier lui-même est servi sous son nom sûr. Un navigateur ne l'affiche que si son type n'exécute aucun script sur
l'origine du site (AVIF, GIF, JPEG, PNG, WebP et PDF, listés dans `MediaController::INLINE_TYPES`) ; tout autre
fichier, dont une page HTML, est envoyé en `application/octet-stream` avec `Content-Disposition: attachment`, pour
qu'il soit téléchargé. Chaque réponse porte `X-Content-Type-Options: nosniff`, et chacune sauf un PDF porte
`Content-Security-Policy: sandbox`, que la visionneuse PDF d'un navigateur n'ouvre pas.

Une image SVG est servie en `image/svg+xml` quand la médiathèque purifie les images SVG (`phil/svg-sanitizer` installé
et `svg.purify` activé, comme il l'est par défaut), pour qu'un logo ou une illustration s'affiche dans un `<img>`. Elle
est purifiée de nouveau la première fois qu'elle est servie, si bien qu'une image gardée avant l'activation du
purificateur est servie aussi sûrement que les autres, et le résultat est gardé à côté des formats de sa version,
`formats/{id}/{version}/purified.svg`, puis relu : une illustration de quelques milliers de tracés prend une fraction de
seconde à purifier, une seule fois. Une image que le purificateur refuse répond `404`. Elle est envoyée avec
`Content-Disposition: attachment` et le bac à sable : dans un `<img>`, un navigateur n'exécute jamais un script d'une
image SVG et ne charge pas ce qu'elle référence, et la pièce jointe est ignorée ; ouvert directement, le fichier est
enregistré, non affiché comme une page du site. Sans purificateur, une image SVG est une pièce jointe
`application/octet-stream` comme tout autre fichier.

### Sous la politique « contenu d'usager »

Quand `phil/browser-security-bundle` est activé, sa politique « contenu d'usager » décide à la place, pour le fichier
lui-même et pour ses formats. Les types qu'elle affiche dans le navigateur (par défaut les images matricielles, l'audio
et la vidéo des navigateurs, et le texte brut) sont affichés ; tout autre fichier, un PDF compris sauf si la politique
le liste, est envoyé en `application/octet-stream` et téléchargé. Une image SVG purifiée garde `image/svg+xml`, en pièce
jointe : la politique n'affiche jamais un SVG dans le navigateur. Chaque réponse porte les en-têtes de la politique :
sa propre `Content-Security-Policy`, `default-src 'none'; …; sandbox` par défaut, `X-Content-Type-Options: nosniff`, la
disposition avec le nom sûr, `Cross-Origin-Resource-Policy`, `Referrer-Policy: no-referrer`,
`X-Permitted-Cross-Domain-Policies: none` et `X-Frame-Options: DENY`. Les en-têtes de cache restent ceux ci-dessus. La
politique se configure sous `phil_browser_security.user_content` ; rien n'est à configurer dans ce bundle.

## schema.org

`media_schema(media)` donne le média sous forme de données schema.org (un `ImageObject` pour une image, un
`MediaObject` sinon), construites par `MediaSchema` :

| Propriété | Valeur |
|---|---|
| `contentUrl` | l'URL signée de l'original, absolue |
| `name`, `description`, `creditText` | le titre (sinon le nom du fichier), le texte de remplacement, le crédit |
| `license` | `https://spdx.org/licenses/<id>` ; absente quand tous les droits sont réservés |
| `encodingFormat`, `width`, `height`, `dateModified` | le type de média, les dimensions, la dernière modification |
| `digitalSourceType` | le code IPTC de la divulgation de l'IA : `trainedAlgorithmicMedia` quand le média est généré, `compositeWithTrainedAlgorithmicMedia` quand il est assisté ; absente sinon |

```twig
<script type="application/ld+json">{{ media_schema(media)|json_encode(constant('JSON_UNESCAPED_SLASHES') b-or constant('JSON_HEX_TAG'))|raw }}</script>
```
