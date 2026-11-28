<!-- phil-dev source-sha256=b5592c7557069a5993ac2663d2947d48a270f5a414e95ab3b83ac02ce1ec8362 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Les ports que l'application implémente

| Port | Ce qu'il répond | Où il est lu |
|---|---|---|
| `Contract\CodeVersionStore` | Les versions de chaque portée : la version courante, une version par numéro, le dernier numéro majeur ; ajoute une version | `CodeOfConduct`, `Reports::file()` |
| `Contract\AcceptanceStore` | L'acceptation de la plus haute version par un membre ; ajoute une acceptation | `CodeOfConduct` |
| `Contract\ReportStore` | Les signalements par identifiant et par portée, du plus ancien au plus récent ; leurs notes | `Reports` |
| `Contract\ResponsiblePeople` | Les responsables d'une portée, 100 au plus | `Reports` |
| `Contract\TextSealer` | Scelle et ouvre un texte sous un contexte | `Reports` ; `KeyManagement\KeyManagementTextSealer` |

`Memory\InMemoryCodeVersionStore`, `Memory\InMemoryAcceptanceStore` et `Memory\InMemoryReportStore` implémentent les
magasins en mémoire, pour les tests et les démonstrations ; ils gardent leurs enregistrements pendant la vie de l'objet.

Une implémentation du magasin de versions garde une clé unique sur la portée et le numéro, afin que deux publications
simultanées ne réussissent jamais toutes les deux. Une implémentation du magasin de signalements lit une page en une
seule requête et ne journalise jamais un texte scellé.

## Ce qu'une application peut remplacer

| Pour | Faites |
|---|---|
| Sceller avec autre chose que KeyManagement de Symfony | Implémentez `Contract\TextSealer` avec un chiffrement authentifié qui lie le contexte ; jamais un passage du texte en clair |
| Appliquer une issue | Écoutez `Event\ReportStatusChanged` et agissez par la modération de l'application |
| Garder une piste d'audit de qui a lu quel signalement | Écoutez `Event\ReportViewed` |
| Informer les membres d'un changement majeur | Écoutez `Event\CodeVersionPublished` et lisez la version |
| Retenir la participation | Interrogez `CodeOfConduct::status()` avant une écriture, et montrez le code quand `mayTakePart()` est faux |
