<!-- phil-dev source-sha256=73d25bc4451be4d3372cb64c56165d3f044a5764033a31c2bf71d08da73d050a -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `PhilScheduledTransitionsBundle` | `final class`, `AbstractBundle` | Alias `phil_scheduled_transitions` ; `HANDLER_TAG`, `RESOLVER_TAG` |
| `Domain\Model\ScheduledTransition` | entité | Une transition, son moment et ses tentatives |
| `Domain\Transition\TransitionStatus` | enum | En attente, faite, échouée, annulée |
| `Phil\Resource\ResourceReference` | `final readonly class`, de `phil/resource` | Une sorte et un identifiant |
| `Domain\Exception\SchedulerException`, `InvalidSchedule`, `NoHandler` | exceptions | Tout ce que lance le bundle |
| `Domain\Repository\ScheduledTransitionRepository` | interface | Le port |
| `Application\Scheduler` | `final readonly class` | Planifier, annuler, exécuter |
| `Application\TransitionHandler` | interface | Applique les transitions |
| `Application\RunReport` | `final readonly class` | Ce qu'a fait une exécution |
| `Infrastructure\Doctrine\DoctrineScheduledTransitionRepository` | dépôt | Le port sur Doctrine ORM |
| `Infrastructure\Doctrine\BinaryKeysListener` | écouteur | Les clés de `phil_scheduled_transition` en `utf8mb4_bin` sur MySQL et MariaDB, au `loadClassMetadata` de l'ORM ([installation.md](installation.md#collation-des-clés)) |
| `Infrastructure\Workflow\WorkflowTransitionHandler`, `SubjectResolver`, `Subject` | gestionnaire | Par `symfony/workflow` |
| `Infrastructure\Scheduler\TransitionsSchedule`, `RunDueTransitions`, `RunDueTransitionsHandler` | calendrier, message | Symfony Scheduler |
| `Infrastructure\Console\RunTransitionsCommand` | commande de console | `phil:scheduled-transitions:run` |
