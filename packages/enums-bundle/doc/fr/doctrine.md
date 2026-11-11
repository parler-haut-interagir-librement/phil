<!-- phil-dev source-sha256=a9fa58c744bb889528cf30e0b8fe4de0ef3de100d99a7326f42e94f8f6e47975 -->
# Doctrine

Doctrine fait correspondre un cas unique avec `enumType`. Un ensemble de cas a besoin de son propre type : le bundle
enregistre un type DBAL par nom que déclare la configuration, lorsque `doctrine/dbal` est installé.

```yaml
# config/packages/phil_enums.yaml
phil_enums:
    doctrine:
        sets:
            permissions: { enum: App\Security\Permission, storage: bitmask }
            channels: { enum: App\Notification\Channel, storage: json }
```

```php
use Doctrine\ORM\Mapping as ORM;
use Phil\Enums\Collection\EnumSet;

#[ORM\Entity]
class Member
{
    /** @var EnumSet<Permission> */
    #[ORM\Column(type: 'permissions')]
    private EnumSet $permissions;

    /** @var EnumSet<Channel>|null */
    #[ORM\Column(type: 'channels', nullable: true)]
    private ?EnumSet $channels = null;
}
```

| Stockage | Colonne | Valeur stockée | Exige |
|---|---|---|---|
| `bitmask` | entier | Les valeurs des cas combinées par un OU binaire : `5` pour `Read` et `Delete`. | Une enum adossée à des entiers qui sont des puissances de deux : les indicateurs binaires du `EnumSet` de `phil/enums`. |
| `json` | JSON | La liste des valeurs (les noms pour une enum pure) : `["email","sms"]`. | N'importe quelle enum. |

- **`null` reste `null`**, dans les deux sens.
- **L'écriture d'une valeur qui n'est pas un `EnumSet` de l'enum du type échoue**, avec le `InvalidType` de DBAL.
- **La lecture d'une valeur stockée qui ne désigne aucun cas échoue**, avec le `ValueNotConvertible` de DBAL : un bit
  qu'aucun cas ne représente, une valeur qu'aucun cas n'a, du JSON qui n'est pas une liste. Un ensemble n'est jamais lu
  en perdant un cas en silence.
- **Les types sont enregistrés une seule fois par processus**, au démarrage du bundle ; un nom de type déjà présent dans
  le registre de DBAL reste tel quel.

**Ne renumérotez jamais un indicateur et ne changez jamais une valeur** une fois que des ensembles sont stockés : les
valeurs sont le format de stockage. Ajouter un cas à l'enum est sans danger ; retirer un cas rend illisibles les rangées
qui le contiennent jusqu'à leur migration.

**Vérifiez les valeurs stockées avant de livrer un changement** : `bin/console phil:enums:check-sets` lit chaque valeur
distincte de chaque colonne `EnumSet` que les entités font correspondre, et liste celles qui ne désignent plus de cas,
soit les rangées qui ne pourraient plus être chargées. Lancez-la sur une copie de la production avant de déployer un
changement à une telle enum, et après la migration qui réécrit les rangées ; elle échoue tant qu'elle en liste.

Interroger une colonne de masque de bits (les membres qui peuvent supprimer) se fait avec le `BIT_AND()` natif de DQL,
qui n'exige aucune extension :

```php
$em->createQuery('SELECT m FROM App\Member m WHERE BIT_AND(m.permissions, :delete) > 0')
    ->setParameter('delete', Permission::Delete->value);
```

Une colonne JSON s'interroge avec les fonctions JSON de la base de données.
