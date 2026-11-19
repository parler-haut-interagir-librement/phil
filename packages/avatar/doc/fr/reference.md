<!-- phil-dev source-sha256=ea5d4b434d96064d11cb23ee41d2ebb87fc6f44c7a7ebed7a639e5cd900bfc72 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Genre | Rôle |
|---|---|---|
| `Phil\Avatar\AvatarGenerator` | `final readonly class` | Le point d'entrée : `svg()`, `dataUri()`, `styles()` |
| `Phil\Avatar\AvatarOptions` | `final readonly class` | Comment un avatar est dessiné : style, taille, contour, palette, nom, étiquette, lettres ; `accessibleName()` |
| `Phil\Avatar\Seed` | `final readonly class` | L'identifiant opaque dont un avatar est tiré |
| `Phil\Avatar\Random` | `final class` | Les choix d'un avatar, tirés de son germe : `int()`, `pick()`, `chance()` |
| `Phil\Avatar\Initials` | `final readonly class` | Les initiales d'un nom, par groupe de graphèmes : `of()`, `isEmpty()`, `isRightToLeft()` |
| `Phil\Avatar\Color` | `final readonly class` | Une couleur sRGB : `fromHex()`, `toHex()`, `luminance()`, `contrastWith()` |
| `Phil\Avatar\Palette` | `final readonly class` | Des fonds et des encres, chaque fond étant lisible : `named()`, `names()`, `fromHex()`, `inkFor()` |
| `Phil\Avatar\Shape` | énumération | `square`, `rounded`, `circle` |
| `Phil\Avatar\Canvas` | `final class` | Ce sur quoi un style dessine, en écrivant lui-même chaque élément |
| `Phil\Avatar\Contract\AvatarStyle` | interface | Une manière de dessiner des avatars, sous un nom |
| `Phil\Avatar\Style\InitialsStyle` | `final readonly class` | Les initiales sur un fond |
| `Phil\Avatar\Style\ShapesStyle` | `final readonly class` | Des cercles, des carrés et des triangles sur un fond |
| `Phil\Avatar\Style\PixelsStyle` | `final readonly class` | Une grille en miroir de cinq carrés sur cinq |
| `Phil\Avatar\Exception\AvatarException` | interface | Toute exception du paquet |
| `Phil\Avatar\Exception\InvalidAvatar` | `final class` | Un germe, une couleur, une palette, une taille, une étiquette ou un style hors des bornes |
