<!-- phil-dev source-sha256=ab82f628aba36f632ded82ddb0d3e568d0d4b3974a48c1c13f7ad933b21e31cf -->
# Points d'extension

Le bundle a **un port** : `Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter`. Il ne déclare pour lui aucun
identifiant de service ni aucune étiquette ; l'application enregistre une instance d'adaptateur par surface et la passe
au `VerdictHandler` de cette surface ([configuration.md](configuration.md)).

## `RobotVerdictCounter`

```php
namespace Phil\BotDetectionBundle\Domain\Counter;

interface RobotVerdictCounter
{
    public function increment(): void;
}
```

### Contrat

| Méthode | Contrat |
|---|---|
| `increment(): void` | Ajoute un verdict robot au compte agrégé de la surface pour laquelle cette instance est branchée. Appelée par `VerdictHandler` pour `Verdict::Robot` seulement, une fois par verdict |

### Invariants

- **Rien qui concerne une personne n'entre dans le compteur.** La méthode ne prend aucun argument, par conception ; un
  adaptateur ne doit pas non plus aller chercher la requête, la session, l'utilisateur ou l'adresse du client courants
  pour enrichir le compte.
- **Une instance par surface.** Un compte par surface ou par site est une instance distincte, jamais un argument.
- **Les fenêtres appartiennent à l'adaptateur.** Répartir les incréments dans des fenêtres de temps (par minute, par
  heure) est la façon de lire un taux ; la clé de fenêtre est un moment, jamais une personne.
- **Le compte est rapporté hors des données par personne** : un tableau de bord d'alertes, une jauge de métriques, ou un
  client qui pousse un nombre par fenêtre vers un autre service.
- **Un échec ne doit pas refuser la requête.** Un verdict ne refuse jamais (voir [friction.md](friction.md)) ; un
  système de compteur en panne devrait perdre l'incrément plutôt que de faire échouer la requête qui le portait.

### Exemples d'adaptateurs

Un compteur réparti par minute dans un pool de cache PSR-6/Symfony (par exemple Valkey ou Redis) :

```php
use Phil\BotDetectionBundle\Domain\Counter\RobotVerdictCounter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;

final readonly class CacheRobotVerdictCounter implements RobotVerdictCounter
{
    public function __construct(
        private string $surface,              // e.g. 'bot.robot_verdicts.sign_up'
        private AdapterInterface $pool,
        private ClockInterface $clock,
    ) {}

    public function increment(): void
    {
        try {
            $item = $this->pool->getItem($this->surface.'.'.$this->clock->now()->format('YmdHi'));
            $item->set(((int) $item->get()) + 1)->expiresAfter(86400);
            $this->pool->save($item);
        } catch (\Throwable) {
            // lose one increment rather than fail the request
        }
    }
}
```

(Une lecture suivie d'une écriture dans un cache n'est pas atomique ; sous charge, préférez un `INCR` atomique côté
système.)

Un compteur pour les tests, comme ceux qu'utilisent les tests du bundle :

```php
final class InMemoryRobotVerdictCounter implements RobotVerdictCounter
{
    public int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }
}
```

## Ce qui n'est pas un point d'extension

- `FrictionPolicy` est `final` avec un constructeur privé : les deux constructeurs nommés `robotOnly()` et
  `robotOrUnknown()` sont les seules politiques.
- `Friction` et `Verdict` sont des énumérations ; leurs cas sont fixes. Il n'y a aucun cas de refus à ajouter.
- `VerdictHandler` est `final` ; composez-le plutôt que de l'étendre.
