<!-- phil-dev source-sha256=c298b31da09aeb37c2d6f44144b13ac8577d3dcdfeae5b79b29606b43ae381ab -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

Le paquet est un seul adaptateur : `WeasyPrintEngine`, le `Phil\PagedMediaBundle\Application\Contract\PrintEngine` de
`phil/paged-media-bundle`. Il ne déclare aucun port à lui.

| Pour | Faites |
|---|---|
| L'utiliser | Enregistrez-le sous le port : voyez [Brancher le service](installation.md#brancher-le-service) |
| Pointer vers une autre commande | `$binary` de `WeasyPrintEngine` : un nom trouvé dans le `PATH`, ou un chemin |
| Changer la durée permise d'une impression | `$timeout` de `WeasyPrintEngine`, en secondes |
| Donner des images et des polices aux documents | `$assets` de `WeasyPrintEngine`, un `LocalAssets` sur un répertoire |
| Changer les limites des fichiers | `$maxFileBytes` et `$maxTotalBytes` de `LocalAssets` |
| Choisir la norme, l'intention de sortie, les balises, les formulaires et les images du PDF | `$pdf` de `WeasyPrintEngine`, un `PdfOptions` : voyez [Sortie PDF](pdf-output.md) |
| Imprimer deux sortes de PDF | Deux services de moteur, chacun avec ses `PdfOptions` |
| Vérifier la commande au déploiement | `WeasyPrintEngine::assertSupported()` |
| Utiliser un autre moteur | Écrivez un autre `PrintEngine`, ou décorez celui-ci comme service : `WeasyPrintEngine` est finale |

Les arguments qui confinent la commande sont fixes, et `PdfOptions` n'ajoute que des valeurs vérifiées : le confinement
est ce qui rend un document inoffensif. Voyez [Sécurité](security.md).
