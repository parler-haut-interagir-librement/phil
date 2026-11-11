<!-- phil-dev source-sha256=dccb039494c46fa5ace644d84505cce624f34ed85729960ac26636545e32c5ef -->
# Sélection des cas

Un formulaire, une contrainte de validation et un gabarit ont souvent besoin du même sous-ensemble d'une enum : les
statuts qu'un client peut choisir, les formes qu'un forfait permet. `CaseSelection` déclare ce sous-ensemble une seule
fois, à partir de quatre critères, et chaque partie du bundle le lit de la même façon :

| Critère | Conserve |
|---|---|
| `only` | Les cas parmi ceux donnés. |
| `except` | Les cas autres que ceux donnés. |
| `groups` | Les cas qui appartiennent à au moins un de ces groupes : les valeurs de leurs métadonnées `#[Group]`. |
| `whereMeta` | Les cas dont les méta-propriétés ont ces valeurs, comparées strictement : classe de méta-propriété vers valeur. |

Les critères se combinent ; un critère vide ne restreint rien, et sans aucun critère, tous les cas sont sélectionnés.
Les cas conservent l'ordre de déclaration de l'enum.

```php
use Phil\Enums\Attribute\Group;

enum OrderStatus: string
{
    #[Group('customer')] #[Group('staff')]
    case PENDING = 'pending';

    #[Group('customer')]
    case IN_TRANSIT = 'in_transit';

    #[Group('staff')]
    case CANCELLED = 'cancelled';
}

$selection = new CaseSelection(OrderStatus::class, groups: ['customer']);
$selection->cases()->all(); // [OrderStatus::PENDING, OrderStatus::IN_TRANSIT]
```

**Les groupes sont faits pour cela, c'est le rôle de `Group`** : nommez les sous-ensembles sur l'enum, à côté de ses
cas, plutôt que de répéter des listes de cas dans chaque formulaire et chaque contrainte. Un cas peut appartenir à
plusieurs groupes.

`CaseSelection::fromOptions()` construit une sélection à partir d'options faiblement typées, celles d'un formulaire ou
d'un gabarit. Il refuse avec une `InvalidArgumentException` une classe d'enum qui n'est pas une enum, une liste qui
contient autre chose que des cas, un groupe qui n'est pas une chaîne, ou une clé de `whereMeta` qui n'est pas une classe
de méta-propriété.
