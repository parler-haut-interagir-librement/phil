<!-- phil-dev source-sha256=2180e228068add6a2dfe10f4564de7a4d4e46316349f59221c0a9a75da7fd76e -->
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
| Offrir le mode d'incident dans la file | Enregistrez `phil/incident-mode-bundle` avec son administration activée ([Mode d'incident](incident-mode.md)) |
| Retenir du contenu selon une autre règle | Donnez à `Application\Contract\SandboxPolicy` un alias vers votre service |
| Réagir à la modération | Écoutez `ReportFiled`, `DecisionTaken`, `ContentHeld`, `ContentHiddenByMember` et `StatementIssued` |
| Informer un membre d'un exposé des motifs | Nommez un type de `phil/notifications-bundle` dans `statements.notification_type`, ou écoutez `StatementIssued` |
| Fonder les raisons sur les conditions ou sur la loi | Configurez `statements.grounds` |
| Changer une page | Remplacez le gabarit sous `templates/bundles/PhilModerationBundle/` |
| Changer un libellé | Traduisez la clé dans le domaine `phil_moderation` |

Un alias déclaré dans le `config/services.yaml` de l'application remplace celui du bundle.
