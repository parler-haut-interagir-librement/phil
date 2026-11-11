<!-- phil-dev source-sha256=d112a4f1ca86d8e484657582793dd604adbe14bff8a18c7f6c51c4e4e802d930 -->
# Tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, vérifie la configuration et les services qu'elle enregistre, `QrCodes` par
rapport aux moteurs de phil/qr-code, chaque contenu typé à partir de son tableau et chaque refus, l'extension Twig, le
composant, les adresses signées et le contrôleur, le moteur des blocs UJO, la passe de compilation, et les rectangles
que `PdfQrCodes` écrit dans un flux PDF.

La suite fonctionnelle, sous `tests/Functional`, démarre une petite application avec FrameworkBundle, Twig, les
composants Twig, et phil/ujo-bundle avec phil/svg-blocks-bundle quand ils sont installés : elle rend une page qui
dessine des codes de toutes les façons, suit les adresses signées jusqu'à la route — en-têtes de cache, `304`, refus
—, et dessine un bloc `qrCode` par phil/svg-blocks-bundle.

Les tests des intégrations facultatives sont sautés, chacun avec sa raison, quand leur paquet n'est pas installé : le
moteur des blocs UJO et son enregistrement sans phil/svg-blocks, le bloc `qrCode` de la suite fonctionnelle sans
phil/svg-blocks-bundle, que le noyau de test laisse alors de côté avec phil/ujo-bundle, et `PdfQrCodes` et son
enregistrement sans phil/paged-media. Installé seul, le bundle passe avec ces tests sautés ; dans le monodépôt, où
chaque paquet est présent, ils s'exécutent tous.

Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

## Tester du code qui dessine des codes

Un code est déterministe : comparez le SVG, le PNG ou la charge utile à une valeur à vous, ou construisez un `QrCodes`
avec les valeurs par défaut dont le test a besoin.
