<!-- phil-dev source-sha256=f824188d395e1532fc6842673091998b6f5290513c84d12ee72694caf9c025af -->
# Intégrer à l'image

Une image intégrée porte son propre titre : le badge qu'un destinataire partage est la preuve.

```php
use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;

$png = PngBaker::bake($badgePng, $tokenOrSecuredDocument);
$credential = PngBaker::extract($png);      // the token or the JSON, or null

$svg = SvgBaker::bake($badgeSvg, $tokenOrSecuredDocument);
$credential = SvgBaker::extract($svg);
```

Ce qui est intégré est un titre sécurisé : un JSON Web Token compact, ou un document avec sa preuve Data Integrity (un
tableau, encodé en JSON, ou son JSON). Tout le reste lance `InvalidImage`. Une nouvelle intégration remplace le titre :
une image n'en contient qu'un, et une image qui en contient deux est refusée à la lecture. `extract()` ne vérifie que
la forme de ce qu'elle trouve ; passez-le ensuite [au vérificateur](verifying.md).

## PNG

Le titre est le texte d'un bloc `iTXt` dont le mot-clé est `openbadgecredential`, non compressé, sans étiquette de
langue, écrit juste avant le bloc `IEND`. Les autres blocs sont gardés tels quels, et les octets après `IEND` sont
abandonnés. Chaque bloc est vérifié — sa longueur, son type, son CRC — et l'image commence par `IHDR`. Un bloc de
titre compressé est refusé. Une image de plus de 16 Mio est refusée avant d'être lue.

## SVG

Le titre est un élément `openbadges:credential` dans l'espace de noms `https://purl.imsglobal.org/ob/v3p0`, premier
enfant de la racine `svg` : un jeton dans son attribut `verify`, un document dans ses données textuelles — en deux
sections quand il contient `]]>`. L'image est analysée, en UTF-8, par le DOM de PHP sans accès au réseau. Sont
refusés : un NUL, une déclaration d'entités, un sous-ensemble interne du type de document, une racine qui n'est pas un
élément `svg` du SVG, ou une image de plus de 4 Mio. L'image est réécrite avec une déclaration XML en UTF-8.

Le reste de l'image est gardé tel quel, scripts et gestionnaires d'événements compris. Un SVG servi en ligne depuis
l'origine de l'émetteur exécute ce qu'il contient : assainissez un dessin téléversé avant de l'intégrer, par exemple
avec `phil/svg-sanitizer`.
