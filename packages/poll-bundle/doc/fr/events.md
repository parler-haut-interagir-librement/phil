<!-- phil-dev source-sha256=d1d5ec418eab011fa5afd575d3e89ada53d0b81f0b069cf94bd819ce59d7538b -->
# Événements

`Polls` diffuse un événement après chaque action, par le répartiteur PSR-14 de l'application — `event_dispatcher` dans
Symfony — quand il y en a un. Chaque événement transporte l'identifiant du sondage, et rien d'autre : ni le votant, ni
les choix.

| Événement | Diffusé quand |
|---|---|
| `PollCreated` | Un sondage a été créé. |
| `VoteCast` | Un bulletin a été déposé, anonyme ou non. |
| `VoteChanged` | Un bulletin a été modifié, par son votant ou avec son reçu. |
| `VoteWithdrawn` | Un bulletin a été retiré. |
| `PollClosed` | Un gestionnaire a fermé le sondage. Un sondage qui atteint sa fermeture ne lève aucun événement : rien ne s'exécute à cet instant. |
| `PollDeleted` | Un sondage a été supprimé avec ses bulletins. |

Tous sont dans `Phil\PollBundle\Application\Event`. Un écouteur qui a besoin de plus le lit dans `Polls` avec
l'identifiant :

```php
use Phil\PollBundle\Application\Event\VoteCast;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class ForgetCachedResults
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    #[AsEventListener]
    public function onVoteCast(VoteCast $event): void
    {
        $this->cache->delete('poll-results-'.$event->poll);
    }
}
```

Une application qui envoie l'événement sur un bus de Messenger l'envoie tel quel : il ne contient qu'un identifiant.

## Dans le navigateur

Les résultats que dessine `@PhilPoll/results.html.twig` diffusent l'événement du DOM `poll:revealed` sur la fenêtre
une fois le sondage fermé, une seule fois par sondage dans un onglet du navigateur. Son `detail` contient `poll`,
l'identifiant, et `winners`, les libellés des options qui ont gagné ou qui ont été élues. Voir
[L'interface de vote](voting-interface.md#célébrer-les-résultats-révélés).
