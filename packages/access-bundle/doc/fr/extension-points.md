<!-- phil-dev source-sha256=a7d9aec94dc2b7deee88a19c3830bd5fa991eb3c978e5f99b9293758e7004f7e -->
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
| Réagir à une adhésion ou à « voir comme ce membre » | Écoutez `MemberJoinedGroup`, `MemberLeftGroup`, `ViewAsStarted`, `ViewAsStopped` |
| Changer la bannière ou le résumé | Remplacez leurs gabarits sous `templates/bundles/PhilAccessBundle/components/` |
| Changer les niveaux, les groupes, la visibilité par défaut, le budget | La [configuration](configuration.md) |
