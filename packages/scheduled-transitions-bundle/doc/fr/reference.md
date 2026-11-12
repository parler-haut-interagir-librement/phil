<!-- phil-dev source-sha256=9d4b6c18faa2a45837d5c3a755728ff686d167f7ce023569a5d8a8f9e8108e55 -->
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
| `Infrastructure\Workflow\WorkflowTransitionHandler`, `SubjectResolver`, `Subject` | gestionnaire | Par `symfony/workflow` |
| `Infrastructure\Scheduler\TransitionsSchedule`, `RunDueTransitions`, `RunDueTransitionsHandler` | calendrier, message | Symfony Scheduler |
| `Infrastructure\Console\RunTransitionsCommand` | commande de console | `phil:scheduled-transitions:run` |
