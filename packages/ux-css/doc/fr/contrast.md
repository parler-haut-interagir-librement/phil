<!-- phil-dev source-sha256=0845147c595a82b85330319bb0c82d217d33a73100677f6e98a60beb901b6655 -->
# La règle de contraste

Les jetons de phil-ux sont testés contre une règle de contraste, et un site passe la même règle sur ses surcharges.

## La règle

- **La palette** : chaque ton tient dans sRGB ; deux tons dont les numéros diffèrent de 50 ou plus atteignent 4,5:1,
  de 40 ou plus 3:1, quelles que soient leurs teintes.
- **La page**, dans les deux schémas : le texte, le texte atténué et le lien atteignent 4,5:1 sur la surface et sur la
  surface surélevée ; la surface atteint 4,5:1 sur le texte, pour une infobulle inversée ; l'anneau de focus atteint
  3:1 sur les deux surfaces. Les deux niveaux les plus hauts du calendrier d'activité atteignent 3:1 sur la surface,
  et deux niveaux d'écart 3:1 l'un sur l'autre.
- **La grille**, pour chaque rôle, dans les deux schémas : `on-quiet` sur `fill-quiet`, `on-loud` sur `fill-loud` et
  `on-quiet` sur les deux surfaces, pour un contrôle fantôme ou en contour et un message d'erreur, atteignent 4,5:1 ;
  `border-loud` et `fill-loud`, pour le bord d'un contrôle, une jauge et un interrupteur, atteignent 3:1 sur les deux
  surfaces.

Les rapports sont ceux des WCAG 2.2, critères de succès 1.4.3 et 1.4.11 : la luminance relative de chaque couleur,
convertie d'OKLCH par OKLab en sRGB linéaire et écrêtée à la gamme.

## Vérifier les surcharges d'un site

```php
use Phil\UxCss\Contrast\ContrastRule;
use Phil\UxCss\Contrast\Pair;
use Phil\UxCss\Token\TokenSheet;

$sheet = TokenSheet::fromFiles(
    'vendor/phil/ux-css/css/tokens/palette.css',
    'vendor/phil/ux-css/css/tokens/colors.css',
    'assets/styles/colors.css',          // the site's overrides, read last
);

$violations = new ContrastRule([
    new Pair('--app-color-badge', '--phil-color-surface', ContrastRule::NON_TEXT),
])->check($sheet);

foreach ($violations as $violation) {
    echo $violation, "\n";               // --phil-color-link on --phil-color-surface (dark): 3.12:1, below 4.5:1
}
```

Dans un test PHPUnit, vérifiez que la liste est vide.

`TokenSheet` ne lit que les déclarations, la dernière d'un nom l'emportant ; il suit `var()`, leurs replis et
`light-dark()`, la première valeur dans le schéma clair et la seconde dans le sombre. Une propriété qui n'aboutit pas à
une couleur `oklch()`, ou qui boucle, ne se résout en rien, et une paire qui en a besoin est signalée.
