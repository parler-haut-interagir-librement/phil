<!-- phil-dev source-sha256=ad3b2aa08c17e590ec8a07fdc1e2294ea658d5c0943ca84497dfe772cb1571e6 -->
# Cartes

Une carte est l'image de 1200×630 avec laquelle une page est partagée quand elle n'en a pas de propre. Elle est dessinée
à partir d'un gabarit et d'un contenu, de façon déterministe, et gardée sous une empreinte de tout ce qu'elle montre.

## Gabarits

```php
$template = CardTemplate::fromArray([
    'title_font' => '/fonts/Inter-Bold.ttf',
    'subtitle_font' => '/fonts/Inter-Regular.ttf',
    'background' => '#0f172a',
    'title_color' => '#f8fafc',
    'subtitle_color' => '#cbd5e1',
    'accent' => '#e11d48',
    'background_image' => '/images/texture.png',
    'logo' => '/images/logo.png',
    'padding' => 80,
    'title_max_size' => 64,
    'title_min_size' => 36,
    'title_max_lines' => 3,
    'subtitle_size' => 32,
    'subtitle_max_lines' => 2,
]);
```

| Clé | Valeur par défaut | Bornes |
|---|---|---|
| `title_font` | requise | Un fichier TrueType ou OpenType de 32 Mio au plus |
| `subtitle_font` | celle du titre | Les mêmes |
| `background`, `title_color`, `subtitle_color` | `#ffffff`, `#111827`, `#4b5563` | `#rgb`, `#rgba`, `#rrggbb` ou `#rrggbbaa` |
| `accent` | aucun | Une couleur, dessinée en bande de 12 pixels le long du bord inférieur |
| `background_image` | aucune | Un PNG, JPEG, GIF ou WebP de 10 Mio, de 8 000 pixels de côté et de 16 777 216 pixels en tout au plus — sa taille lue dans son en-tête, avant que GD le décode —, mis à l'échelle pour couvrir la carte |
| `logo` | aucun | Les mêmes, dans le coin inférieur gauche, réduit dans ses proportions à 360×96 pixels au plus |
| `padding` | 80 | De 0 à 200 pixels |
| `title_max_size`, `title_min_size` | 64, 36 | De 8 à 200 pixels, la plus petite ne dépassant pas la plus grande |
| `title_max_lines` | 3 | De 1 à 6 |
| `subtitle_size`, `subtitle_max_lines` | 32, 2 | De 8 à 120 pixels ; de 1 à 4 lignes |

`CardFont` lit chaque police avec phil/fonts et garde sa famille, sa licence et son SHA-256 ; WOFF, WOFF2 et les
collections sont refusés, puisque GD dessine à partir d'un simple fichier `sfnt`. Une carte matricielle n'intègre pas la
police, mais respecter la licence sous laquelle une police est employée revient à l'application :
`$font->licence->spdx()` nomme les plus courantes.

`CardTemplate::fingerprint()` hache le SHA-256 des polices et des images plutôt que leurs chemins, avec les couleurs,
les tailles et les bornes : le même gabarit déployé ailleurs a la même empreinte.

## Le titre

`TextFitter` ajuste un texte à une boîte. Il coupe le texte là où une ligne peut se briser (`LineBreaker` applique
l'algorithme de coupure de ligne Unicode d'ICU dans la locale du texte : entre les idéogrammes du chinois et du
japonais, aux espaces des autres écritures, jamais avant `:`, `;`, `!` ou `?`, jamais à l'intérieur de `« »`, jamais à
une espace insécable) et renvoie les morceaux à la ligne à chaque taille. La plus grande taille à laquelle le texte
tient dans ses lignes est trouvée en coupant en deux l'intervalle des tailles : un titre de 300 caractères coûte ainsi
quelques centaines de mesures. Un mot plus large que la boîte est coupé entre deux caractères. Quand le texte ne tient
pas même à la plus petite taille, ses premières lignes sont gardées et la dernière se termine par des points de
suspension.

Le sous-titre est dessiné à sa propre taille sous le titre, en autant de lignes qu'il en tient au-dessus du logo ou de
la marge inférieure : moins de lignes, ou aucune.

## Le dessin

`GdCardRenderer` dessine la couleur de fond, l'image de fond recadrée autour de son centre, le titre en haut à gauche
dans la marge, le sous-titre, le logo et la bande d'accent, et écrit un PNG opaque à la plus forte compression. Le même
gabarit et le même contenu dessinent les mêmes octets avec le même GD. Sa `version()` (celle du moteur de rendu et celle
de GD) entre dans l'empreinte, si bien qu'un nouveau GD dessine de nouvelles cartes.

### Ce que GD dessine et ce qu'il ne dessine pas

Les cartes sont dessinées par GD seul, au moyen de FreeType, qui dessine chaque caractère tel que le donne la police,
l'un après l'autre, de gauche à droite, sans mise en forme du texte :

| Texte | Dessiné |
|---|---|
| Lettres latines, grecques et cyrilliques, accents compris, chiffres, ponctuation, `« »` et `…` | Comme la police les dessine |
| Chinois, japonais et coréen | Comme la police les dessine ; les lignes se coupent entre les caractères, selon les règles d'ICU |
| Arabe, persan, ourdou | Faux : chaque lettre sous sa forme isolée, non liée, de gauche à droite au lieu de droite à gauche |
| Hébreu | Faux : de gauche à droite au lieu de droite à gauche |
| Devanagari, bengali, tamoul et les autres écritures de l'Inde, thaï, khmer, birman | Faux : aucune ligature, signes vocaliques mal placés |
| Émojis en couleur, drapeaux, séquences jointes par U+200D | Faux : dans le contour que la police possède, s'il y en a un, une pièce d'une séquence après l'autre |
| Un caractère que la police n'a pas | La boîte de glyphe manquant de la police |

Un site dont les titres sont dans une écriture des mauvaises rangées dessine ses cartes ailleurs, avec un moteur de rendu
qui met le texte en forme, par le port `CardRenderer` ; l'image par défaut de la page est une autre issue. La police
d'un gabarit doit couvrir chaque caractère qu'emploient ses titres, y compris les points de suspension `…` qui
terminent un titre coupé.

## Empreintes et stockages

`CardGenerator::fingerprint()` hache, avec xxHash128, la version du moteur de rendu, l'empreinte du gabarit, le titre,
le sous-titre et la locale : 32 chiffres hexadécimaux en minuscules, qu'une page place dans l'adresse de sa carte. Un
changement de l'un d'eux donne une nouvelle adresse : une carte peut donc être mise en cache pour toujours.
`generate()` retourne la carte gardée, ou la dessine et la garde.

| Stockage | Garde |
|---|---|
| `DirectoryCardStore` | `<directory>/<first two digits>/<fingerprint>.png`, écrit sous un nom temporaire puis renommé en place |
| `InMemoryCardStore` | Les cartes du processus, jusqu'à `reset()` |

Un stockage n'accepte qu'une empreinte valide : rien qui permettrait à un chemin de remonter n'atteint le système de
fichiers.
