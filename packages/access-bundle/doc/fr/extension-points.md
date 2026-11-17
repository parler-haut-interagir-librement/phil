<!-- phil-dev source-sha256=d05aa8879ad566ac23bce9580afc8ce24a81416dc9b8a3cf5fc34b10ac057f18 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Nommer autrement le membre d'une requête | Implémentez `Application\Contract\CurrentMember` et nommez le service dans `phil_access.current_member` |
| Conserver le membre dont on voit le site ailleurs que dans la session | Créez un alias de `Application\Contract\ViewAsStore` vers votre service |
| Ajouter des types ou des relations au modèle | Implémentez `Application\Contract\ModelContributor` ; l'autoconfiguration l'étiquette `phil_access.model_contributor` |
| Permettre de vérifier une entité | Implémentez `Application\Contract\AccessibleItem` |
| Conserver les tuples ailleurs | Créez un alias de `Phil\ResourcePermissions\Contract\TupleStore` vers votre magasin |
| Partitionner les tuples par une couche de locataires à vous | Créez un alias de `Phil\ResourcePermissions\Contract\TupleScope` vers votre portée |
| Laisser un contrôleur changer quelque chose pendant une vue, ou en refuser un qui ne fait que lire | Marquez-le `#[AllowedDuringViewAs]` ou `#[RefusedDuringViewAs]` |
| Refuser un changement hors d'un contrôleur pendant une vue | Appelez `ViewAs::refuseWhileViewing()` |
| Réagir à une adhésion ou à « voir comme ce membre » | Écoutez `MemberJoinedGroup`, `MemberLeftGroup`, `ViewAsStarted`, `ViewAsStopped` |
| Changer la bannière ou le résumé | Remplacez leurs gabarits sous `templates/bundles/PhilAccessBundle/components/` |
| Changer les niveaux, les groupes, la visibilité par défaut, le budget | La [configuration](configuration.md) |
