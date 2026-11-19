<!-- phil-dev source-sha256=c1e9492af0a78b28f358dc8a31341b847f6cce3ccf94e0c162b66e6d7c9864d6 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Ajouter un style, ou remplacer l'un de ceux du paquet | Implémentez `Contract\AvatarStyle` et passez-le à `AvatarGenerator` |
| Dessiner aux couleurs de l'application | Construisez une `Palette` et passez-la dans `AvatarOptions` |
| Nommer les avatars dans la langue de la page | Passez le `label` traduit dans `AvatarOptions` |

## Un style de l'application

Un style a un nom — de 1 à 32 lettres minuscules, chiffres ou `-` — et dessine sur une `Canvas` avec les choix d'un
flux `Random` que fixent le germe et le nom du style :

```php
use Phil\Avatar\Canvas;
use Phil\Avatar\Contract\AvatarStyle;
use Phil\Avatar\Random;

final readonly class RingsStyle implements AvatarStyle
{
    public function name(): string
    {
        return 'rings';
    }

    public function draw(Canvas $canvas, Random $random): void
    {
        $background = $random->pick($canvas->palette->backgrounds);
        $canvas->background($background);
        $ink = $canvas->palette->inkFor($background);

        for ($ring = 3; $ring >= 1; --$ring) {
            $canvas->circle(50.0, 50.0, $ring * 14.0, 1 === $ring % 2 ? $ink : $background);
        }
    }
}

$avatars = new AvatarGenerator([new RingsStyle()]);
```

La toile mesure 100 unités sur 100. Elle dessine un fond, des cercles, des rectangles tournés autour de leur centre,
des polygones et une ligne de texte centrée, et écrit elle-même chaque élément : un style ne peut pas mettre son propre
balisage dans un avatar. Ses `palette` et `initials` sont ceux des options. Un style du même nom que l'un de ceux du
paquet le remplace.

Un style dessine le même avatar pour le même germe seulement s'il prend chaque choix dans le `Random` qu'on lui donne :
`int()`, `pick()` et `chance()`, jamais `random_int()` ni l'heure.
