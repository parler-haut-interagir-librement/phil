<!-- phil-dev source-sha256=ac244b0239ba9db1e448d9e2d9d3c76cf78ed61760f9ed0795cdcae334dfd62b -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Dire qui est un membre établi | Implémentez `Phil\IncidentMode\Contract\EstablishedMembers` et nommez-la dans `established_members`, ou pointez l'alias de l'interface vers elle |
| Nommer le membre de la requête | Implémentez `Application\Contract\CurrentMember` et nommez-la dans `current_member`, ou pointez l'alias de l'interface vers elle |
| Ajouter, remplacer ou laisser de côté des préréglages | `presets`, `without_presets` et `built_in_presets` |
| Suspendre quelque chose qui est propre à l'application | Nommez une restriction dans un préréglage, puis vérifiez-la avec `#[SuspendedDuring]`, `_suspended_during`, `Checkpoint` ou `suspended_during` |
| Réagir à un incident | Écoutez `Phil\IncidentMode\Event\IncidentStarted`, `IncidentExtended` et `IncidentLifted` sur le répartiteur d'événements de Symfony |
| Garder les incidents ailleurs | Implémentez `Phil\IncidentMode\Contract\ActivationStore` et pointez l'alias de l'interface vers elle |
| Lever à la fin d'une autre façon | Implémentez `Phil\IncidentMode\Contract\LiftScheduler` et pointez l'alias de l'interface vers elle |
| Démarrer, prolonger ou lever depuis le code | `Phil\IncidentMode\IncidentMode`, autocâblé |
| Changer les mots | Les messages du domaine `phil_incident_mode`, dans `translations/` de l'application |
| Changer les pages | `layout`, et les gabarits sous `@PhilIncidentMode/` remplacés dans `templates/bundles/PhilIncidentModeBundle/` |
| Montrer un avis propre à l'application | Les fonctions Twig `phil_incident_suspensions()` et `phil_incident_sentence()` |
| Choisir le moment | Le service `clock` : le bundle ne lit jamais l'heure lui-même |

## Qui est établi

```php
namespace App\Trust;

use Phil\IncidentMode\Contract\EstablishedMembers as Port;

final readonly class EstablishedMembers implements Port
{
    public function __construct(private TrustLevels $levels)
    {
    }

    public function isEstablished(string $member): bool
    {
        return $this->levels->of($member) >= TrustLevels::REGULAR;
    }
}
```

```yaml
phil_incident_mode:
    established_members: App\Trust\EstablishedMembers
    spare_established: [new-posts, new-comments]
```

Il n'est interrogé que lorsque la réponse en dépend : pour un membre, jamais pour un visiteur, et seulement à propos
d'une restriction qu'un incident suspend et que la configuration épargne.

## Écouter les incidents

```php
use Phil\IncidentMode\Event\IncidentStarted;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class TellTheTeam
{
    public function __invoke(IncidentStarted $event): void
    {
        // Post to the team's channel, clear a cache…
    }
}
```

Chaque événement est envoyé une seule fois, même quand deux processus lèvent le même incident en même temps.
