<!-- phil-dev source-sha256=93fc94d4a1b952f03812e422bca305ee1004b60fc9a167c3a91f317221a23148 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

## Les ports que l'application implémente

| Port | Ce qu'il répond | Où il est lu |
|---|---|---|
| `Contract\SafetyInfoStore` | Les enregistrements scellés, un par événement et participant ; la suppression, par lots, de ceux gardés jusqu'avant une date | `SafetyInfoVault` |
| `Contract\EventRoles` | Le `Role` d'une personne pour un événement : responsable, équipe ou aucun | `read()`, `breakGlass()` |
| `Contract\AccessWindows` | La `AccessWindow` d'un événement, ou `null` | `submit()`, `read()`, `breakGlass()` |
| `Contract\AccessJournal` | Les lectures par quelqu'un d'autre, écrites avant l'ouverture ; les dernières d'un participant | `read()`, `breakGlass()`, `readingsOf()` |
| `Contract\SafetyInfoSealer` | Scelle et ouvre un texte sous un contexte | `SafetyInfoVault` ; `KeyManagement\KeyManagementSealer` |

`Memory\InMemorySafetyInfoStore` et `Memory\InMemoryAccessJournal` implémentent le magasin et le journal en mémoire,
pour les tests et les démonstrations ; ils gardent leurs enregistrements pendant la vie de l'objet.

Une implémentation du magasin garde une clé unique sur l'événement et le participant, supprime par lots d'au plus
`$limit` lignes et ne journalise jamais le texte scellé. Une implémentation du journal échoue bruyamment plutôt que
d'abandonner un enregistrement : le coffre arrête la lecture quand elle lance une exception.

## Ce qu'une application peut remplacer

| Pour | Faites |
|---|---|
| Sceller avec autre chose que KeyManagement de Symfony | Implémentez `Contract\SafetyInfoSealer` avec un chiffrement authentifié qui lie le contexte ; jamais un passage du texte en clair |
| Aviser les responsables d'un bris de glace | Écoutez `Event\SafetyInfoRead` et agissez quand sa raison est `AccessReason::BreakGlass` |
| Effacer l'information d'un membre qui part | Appelez `erase()` pour chacun de ses événements depuis le parcours d'effacement de l'application |
| Purger plus ou moins à la fois | Passez `$limit` à `purge()`, de 1 à 10 000 |
