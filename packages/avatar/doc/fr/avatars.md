<!-- phil-dev source-sha256=516c8566d210f67dcb929e0e10e49bc295f90b90bfe1c12e1d7478a8391fe1cd -->
# Avatars

## Dessiner un avatar

`AvatarGenerator::svg($seed, $options)` dessine l'avatar d'un germe sous forme de document SVG ; `dataUri()` donne le
même document sous forme d'URI `data:image/svg+xml,…`, encodée en pourcentage, pour le `src` d'une `<img>`.

Le germe est un `Seed` ou une chaîne : un identifiant opaque et stable de l'application, comme l'UUID du membre ou une
valeur aléatoire gardée sur son profil. Dessinez à partir d'une valeur qui ne change pas quand le membre se renomme,
pour que son avatar reste le sien.

```php
$avatars = new AvatarGenerator();

$avatars->svg('0192f3c4-7b1e-7000-8000-00000000002a', new AvatarOptions(name: 'Ada Lovelace'));
```

## Les options

| Option | Valeur par défaut | Signification |
|---|---|---|
| `style` | `initials` | `initials`, `shapes`, `pixels`, ou un style de l'application. |
| `size` | `64` | La largeur et la hauteur en pixels, de 16 à 1024 ; le dessin est vectoriel, sur une zone de 100 × 100. |
| `shape` | `Shape::Circle` | `Shape::Circle`, `Shape::Rounded` (coins du sixième du côté) ou `Shape::Square`. |
| `palette` | `Palette::named('default')` | Les fonds et les encres : voir [Palettes et contraste](palettes.md). |
| `name` | aucune | Le nom dont viennent les initiales, comme le nom d'affichage du membre. |
| `label` | aucune | Le texte qui nomme l'avatar pour les technologies d'assistance, comme « Avatar de Ada Lovelace » ; 200 caractères au plus. |
| `maxLetters` | `2` | Le nombre maximal de lettres des initiales, de 1 à 3. |

## Les styles

| Style | Dessine |
|---|---|
| `initials` | Les initiales du nom, centrées, dans l'encre du plus fort contraste avec un fond de la palette. Un nom sans lettres — vide, ou fait d'émojis seulement — dessine le style des formes. |
| `shapes` | Un fond et trois cercles, carrés ou triangles dans les autres couleurs de la palette, placés, dimensionnés et tournés par le germe. |
| `pixels` | Un fond et une grille de cinq carrés sur cinq, en miroir de gauche à droite, dans l'encre du plus fort contraste. |

Chaque style puise dans son propre flux du germe : le même germe donne des avatars sans lien dans deux styles, et le
même avatar dans un style, quels que soient la taille, le contour ou l'étiquette.

## Accessibilité

| L'avatar a | Il est écrit | Les technologies d'assistance |
|---|---|---|
| Une étiquette, ou à défaut un nom | `role="img" aria-label="…"`, avec un `<title>` | L'annoncent comme une image portant ce nom. |
| Ni l'un ni l'autre | `aria-hidden="true" focusable="false"` | Le sautent, comme elles le doivent à côté d'un nom déjà écrit. |

À côté du nom du membre dans une liste, omettez l'étiquette et l'avatar est décoratif. Seul, comme lien vers un profil,
donnez-lui une étiquette. Les initiales gardent un contraste d'au moins 4,5:1 avec leur fond, le niveau WCAG AA du texte
normal.

Dans une `<img>`, c'est l'attribut `alt` qui nomme l'avatar : `alt=""` pour un avatar décoratif.

```twig
<img src="{{ avatar_uri }}" alt="" width="32" height="32">
```

## L'afficher dans une page

Un SVG en ligne ne demande aucune requête. Deux avatars du même germe, du même style et du même contour dans une page
partagent l'identifiant de leur découpe, dont la forme est la même ; deux avatars différents ne le partagent jamais.

Une URI `data:` convient à une `<img>` et à une politique de sécurité du contenu qui autorise `img-src data:`. Pour
servir les avatars comme des fichiers, répondez avec `Content-Type: image/svg+xml` et une longue durée de cache : un
avatar ne change qu'avec son germe ou ses options.
