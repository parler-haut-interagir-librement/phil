<!-- phil-dev source-sha256=7d9e6539196c197bb5fae0e55fbe95636061b07426397b4e62b9a5b220bc284a -->
# Documents mesurés

Une page mise en page pour l'impression dans un navigateur — une feuille de style d'impression, un polyfill de médias
paginés, un navigateur sans interface — donne ce dont le contrôle préalable a besoin à un script qui s'exécute sur la
page rendue : pour chaque boîte de page, sa taille ; pour chaque élément, `getBoundingClientRect()` par rapport à la
boîte de page ; pour chaque image, `naturalWidth` et `naturalHeight`. Le script, par exemple un contrôleur Stimulus de
l'application, envoie ces nombres en JSON ; `MeasuredDocument` les lit dans le modèle de `phil/print-preflight`.

```json
{
    "unit": "px",
    "pages": [
        {
            "width": 559.37, "height": 793.7, "bleed": 11.34,
            "images": [
                {"id": "cover", "x": -11.34, "y": -11.34, "width": 582.05, "height": 816.38, "naturalWidth": 1800, "naturalHeight": 2500, "bleeds": true},
                {"id": "portrait", "x": 40, "y": 120, "width": 160, "height": 160, "naturalWidth": 600, "naturalHeight": 800, "scaledWidth": 160, "scaledHeight": 213.33, "colourSpace": "rgb"}
            ],
            "boxes": [
                {"id": "title", "x": 40, "y": 40, "width": 479, "height": 48},
                {"id": "band", "x": -11.34, "y": 700, "width": 582.05, "height": 40, "colourSpace": "cmyk", "bleeds": true, "inkCoverage": 280, "transparency": true},
                {"id": "rule", "x": 40, "y": 100, "width": 479, "height": 0.5, "colourSpace": "spot", "overprint": true, "lineWidth": 0.5}
            ]
        }
    ],
    "fonts": [
        {"name": "Example Sans Bold", "fsType": 0, "subset": true},
        {"family": "Example Serif", "weight": 400, "style": "italic", "subset": true}
    ]
}
```

| Champ | De | Obligatoire | Remarques |
|---|---|---|---|
| `unit` | document | non | `px` (par défaut), `pt`, `mm` ou `in` : l'unité de chaque longueur |
| `pages` | document | oui | Dans l'ordre |
| `fonts` | document | non | Les polices avec lesquelles le document s'imprime |
| `width`, `height` | page | oui | Le format rogné |
| `bleed` | page | non | Ce que la page porte au-delà de la zone de coupe de chaque côté ; 0 par défaut |
| `images`, `boxes` | page | non | Les éléments placés |
| `id` | image, boîte | oui | Ce que nomme un constat : un identifiant d'élément, un identifiant de bloc |
| `x`, `y`, `width`, `height` | image, boîte | oui | Le cadre, depuis le coin supérieur gauche de la zone de coupe, y vers le bas |
| `naturalWidth`, `naturalHeight` | image | oui | La taille en pixels |
| `scaledWidth`, `scaledHeight` | image | les deux ou aucun | La taille à laquelle l'image entière est mise à l'échelle quand son cadre la recadre (`object-fit: cover`) |
| `colourSpace` | image, boîte | non | `gray`, `rgb`, `cmyk` ou `spot` ; par défaut RVB pour une image, inconnu pour une boîte |
| `bleeds` | image, boîte | non | Si l'élément doit déborder de la page ; `false` par défaut |
| `inkCoverage` | image, boîte | non | La couverture totale en surface au point le plus sombre de l'élément, en pourcentage (`320` pour 320 %) ; inconnue si on l'omet ou si elle vaut `null` |
| `transparency` | image, boîte | non | Si l'élément est dessiné avec de la transparence (opacité, mode de fusion, canal alpha) ; `false` par défaut |
| `overprint` | boîte | non | Si l'élément s'imprime par-dessus ce qui se trouve dessous au lieu de le détourer ; `false` par défaut |
| `lineWidth` | boîte | non | La largeur du trait le plus fin que l'élément dessine, en `unit` ; inconnue si on l'omet ou si elle vaut `null` |
| `name`, `fsType` | police | `name` | Une police connue par son nom et le `fsType` de sa table `OS/2` ; 0 par défaut |
| `family`, `weight`, `style` | police | `family` | Une police trouvée par le `FontResolver` ; 400 et `normal` par défaut |
| `embedded`, `subset` | police | non | `true` et `false` par défaut ; un navigateur qui imprime en PDF intègre les polices Web en sous-ensembles |

`fromJson(string $json)` et `fromArray(array $data)` donnent le `Document`. Tout ce qu'ils ne savent pas lire lève
`InvalidMeasurement`, dont le message nomme le champ — `The "naturalWidth" of an image must be a number.` —, et les
refus de la bibliothèque — un fond perdu négatif, une image sans pixel, une couverture d'encre ou une largeur de trait
négative — sortent de la même façon.

Les quatre derniers champs d'élément sont ce qu'un navigateur ne sait pas toujours dire : un script qui ne connaît pas la
couverture d'encre ni le trait le plus fin d'un élément omet le champ, et les contrôles de la couverture d'encre et des
traits très fins passent cet élément plutôt que de deviner. Un script qui les connaît — par les couleurs d'une feuille
de style d'impression, par le `border-width` d'un filet — les donne, et ces contrôles, ainsi que ceux de la
surimpression et de la transparence, signalent l'élément.

Une police nommée par famille a besoin d'un `FontResolver` : avec `phil/fonts-bundle` activé, le catalogue de son
répertoire de polices répond, et la `font-family` d'une feuille de style est ainsi vérifiée par rapport au fichier même
que sert le site. Sans résolveur, une telle police lève `InvalidMeasurement` ; nommez-la plutôt avec `name` et
`fsType`.

Un document mesuré porte des identifiants et des nombres, jamais le texte des pages : un point de terminaison qui le
reçoit d'un navigateur n'enregistre rien de personnel, et un message qui met une vérification en file d'attente porte
l'identifiant du document enregistré.
