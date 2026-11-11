<!-- phil-dev source-sha256=3917ce55eb2c6472f78413d5cdd2d25467d7ecf1f1134bfaad9f867adfa66c96 -->
# Événements du domaine

`Phil\DddBundle\Domain\Event\DomainEvent` est l'interface d'un fait passé à l'intérieur d'un contexte délimité.

```php
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
```

## Déclarer un événement

```php
namespace App\Ordering\Domain\Event;

use DateTimeImmutable;
use Phil\DddBundle\Domain\Event\DomainEvent;

final readonly class OrderPlaced implements DomainEvent
{
    public function __construct(
        public string $orderId,
        private DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
```

Prenez l'heure d'un `Psr\Clock\ClockInterface` injecté là où l'événement est levé, jamais de `new
DateTimeImmutable()`, pour que les tests la contrôlent. Le bundle ne fournit aucune interface d'horloge à lui (voir
[out-of-scope.md](out-of-scope.md)).

## Règles

- **Un événement du domaine n'est pas un événement d'intégration.** Il reste dans le contexte qui l'a levé. Ce qui
  franchit la limite d'un contexte passe par les contrats publiés du contexte émetteur et par une couche
  anticorruption du côté du consommateur ; le bundle ne définit ni l'un ni l'autre.
- **Les événements avertissent, ils ne reconstituent pas.** L'état est stocké comme état. Il n'y a ni magasin
  d'événements ni rejeu : un agrégat n'est jamais reconstruit à partir de ses événements.
- **Un événement est un nom et une heure ; le reste appartient au contexte.** L'interface ne demande que
  `occurredAt()`, pour que chaque contexte conçoive la charge utile de ses événements.

## Ce qui est implémenté, et ce qui ne l'est pas

| Partie | État |
|---|---|
| Interface `DomainEvent` | Implémenté |
| Enregistrement des événements sur un agrégat (une interface qu'implémente l'agrégat) | **Non commencé** |
| Répartition des événements enregistrés après le *flush* de Doctrine, dans la transaction du *handler* et avant son commit | **Non commencé** |
| Une configuration documentée de boîte de réception Messenger pour les consommateurs d'événements d'intégration | **Non commencé** |

Tant que la répartition n'existe pas, une application qui en a besoin enregistre les événements sur ses agrégats et
les répartit elle-même, sur un bus à elle. Deux contraintes sont bonnes à connaître pour le faire :

- Répartissez dans la transaction, après le *flush* et avant le commit, pour qu'un événement ne soit jamais vu pour une
  écriture qui a fait l'objet d'un retour arrière.
- Ne comptez pas sur `DispatchAfterCurrentBusStamp` de Messenger pour cela : cette estampille déplace la répartition
  hors de la transaction du *handler*, et Symfony 8.2 la refuse sur un transport doté d'une boîte de réception, sauf si
  le message passe par une boîte d'envoi.

Une interface d'enregistrement sera une interface plutôt qu'un trait : un trait partagé qui garde une liste privée
d'événements serait une classe de base d'agrégat sous un autre nom.
