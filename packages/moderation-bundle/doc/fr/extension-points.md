<!-- phil-dev source-sha256=0ca8695afc35a18ad111923ff97c177e46f18c48c2847fde2c2e54330581367d -->
# Points d'extension

Le bundle est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Rendre le contenu d'un paquet signalable | Implémentez `Application\Contract\ModerationTarget` ; l'autoconfiguration l'étiquette `phil_moderation.target` |
| Modérer les profils | Fournissez un service pour `Application\Contract\MemberProfiles` |
| Trouver le membre courant d'une autre façon | Donnez à `Application\Contract\CurrentMember` un alias vers votre service |
| Décider autrement qui modère | Donnez à `Application\Contract\CurrentModerator` un alias vers votre service |
| Brancher les mots surveillés | Donnez à `Application\Contract\WordScreen` un alias vers l'adaptateur |
| Faire confiance à des membres pour masquer | Donnez à `Application\Contract\TrustedHiders` un alias vers l'adaptateur des niveaux de confiance |
| Épargner les membres établis d'un incident | Donnez à `Phil\IncidentMode\Contract\EstablishedMembers` de `phil/incident-mode` un alias vers votre service, et énumérez les restrictions dans `incident.spare_established` ([Mode d'incident](incident-mode.md#les-membres-établis)) |
| Retenir du contenu selon une autre règle | Donnez à `Application\Contract\SandboxPolicy` un alias vers votre service |
| Réagir à la modération | Écoutez `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember` et `StatementIssued` |
| Informer un membre d'un exposé des motifs | Nommez un type de `phil/notifications-bundle` dans `statements.notification_type`, ou écoutez `StatementIssued` |
| Fonder les raisons sur les conditions ou sur la loi | Configurez `statements.grounds` |
| Changer une page | Remplacez le gabarit sous `templates/bundles/PhilModerationBundle/` |
| Changer un libellé | Traduisez la clé dans le domaine `phil_moderation` |

Un alias déclaré dans le `config/services.yaml` de l'application remplace celui du bundle.
