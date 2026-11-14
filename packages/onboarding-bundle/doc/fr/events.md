<!-- phil-dev source-sha256=3daf9f98293befc385b5cc8357ea99fb83b9850d7b3f4ebdb55ccd00abc4174d -->
# Événements

Chaque changement de la progression répartit un événement après son enregistrement, par le répartiteur PSR-14 s'il y
en a un. Chacun ne transporte que des identifiants : l'identifiant de l'utilisateur, le parcours et, pour une étape,
l'étape ; pour une réponse, les choix retenus, non ce qu'ils accordent.

| Événement | Quand |
|---|---|
| `StepCompleted` (`user`, `flow`, `step`) | Une étape est accomplie : par la personne, par un événement, par un vérificateur ou par l'application. |
| `QuestionAnswered` (`user`, `flow`, `step`, `choices`) | La question d'une étape reçoit une réponse, avec les identifiants des choix retenus ; `StepCompleted` suit. |
| `StepSkipped` (`user`, `flow`, `step`) | Une étape en attente est passée. |
| `FlowFinished` (`user`, `flow`) | Toutes les étapes sont faites, accomplies ou passées, pour la première fois depuis le début du parcours. |
| `FlowDismissed` (`user`, `flow`) | L'utilisateur range le parcours. |
| `FlowReset` (`user`, `flow`) | L'utilisateur recommence le parcours. |

Ils se trouvent dans `Phil\OnboardingBundle\Application\Event`. Un écouteur de `FlowFinished` peut décerner un badge,
envoyer un message de bienvenue ou ouvrir le parcours suivant :

```php
#[AsEventListener]
final readonly class WelcomeAuthor
{
    public function __invoke(FlowFinished $event): void
    {
        if ('author' === $event->flow) {
            // …
        }
    }
}
```

Un message Messenger construit à partir d'un événement transporte les mêmes identifiants et lit le reste quand il est
traité.
