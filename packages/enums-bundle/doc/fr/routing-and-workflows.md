<!-- phil-dev source-sha256=f2309ca3b1c7200adae810c0a10055865047c03d1523bd4b34a7959b92987389 -->
# Routage, expressions et workflows

Hors des formulaires et des gabarits, les déclarations d'une enum comptent à trois endroits : un argument de contrôleur
lu à partir de la route, les expressions de la sécurité, du routage et de la validation, et Symfony Workflow.

## Arguments de contrôleur : #[ParseEnum]

Symfony lit une enum à valeurs dans un attribut de route par sa valeur exacte. `#[ParseEnum]` la lit comme le fait
`parse()` (par valeur, puis par nom, puis par `#[Alias]`), pour qu'une URL continue de fonctionner après le renommage
d'une valeur :

```php
use Phil\EnumsBundle\Infrastructure\Http\ParseEnum;

#[Route('/orders/{status}')]
public function list(#[ParseEnum(ignoreCase: true)] OrderStatus $status): Response
```

| Option | Valeur par défaut | Signification |
|---|---|---|
| `attribute` | le nom de l'argument | L'attribut de route à lire. |
| `ignoreCase` | `false` | Compare sans tenir compte de la casse des lettres ASCII. |

- **Une valeur qui n'est aucun cas donne une erreur 404**, comme une ressource absente. Il en va de même pour une valeur
  manquante, sauf si l'argument est nullable : il reçoit alors `null`.
- **N'importe quelle enum**, pure ou à valeurs, avec ou sans `AsParsableEnumTrait`.
- **Un argument qui n'est pas typé par une enum est une erreur** du contrôleur et lance une `LogicException`.

Il est enregistré avec `symfony/http-kernel`, sous forme de résolveur que l'attribut cible par son nom : il ne s'exécute
jamais pour un argument sans l'attribut.

## Fonctions d'expression

Elles sont enregistrées avec `symfony/expression-language` pour les expressions de la sécurité (`is_granted` avec une
expression), du routage (`condition`) et de la validation (`Expression`), à côté du `enum()` natif du langage, qui lit
un cas par sa constante :

| Fonction | Retourne |
|---|---|
| `enum_meta(case, 'App\\Color')` | La valeur d'une méta-propriété sur le cas. |
| `enum_in_group(case, 'staff', …)` | Si le cas appartient à l'un des groupes ; `false` pour `null`. |
| `enum_from(value, 'App\\OrderStatus')` | Le cas d'une valeur (un nom pour une enum pure), ou `null`. |

```php
$routes->add('open_orders', '/orders/{status}')
    ->condition("enum_in_group(enum_from(params['status'], 'App\\\\OrderStatus'), 'open')");
```

## Workflows tirés des transitions

Une enum dont les cas déclarent `#[TransitionsTo]` décrit déjà une machine à états. Le bundle la donne à
FrameworkBundle, pour que les gardes, les événements, l'historique et la piste d'audit de Symfony Workflow s'y
appliquent :

```yaml
phil_enums:
    workflows:
        order:
            enum: App\Order\OrderStatus
            supports: [App\Order\Order]
            property: status          # the default
```

```php
$workflow = $registry->get($order, 'order');
$workflow->can($order, 'pending_to_paid');
$workflow->apply($order, 'pending_to_paid');   // $order->status is now OrderStatus::Paid
```

| Partie du workflow | Tirée de l'enum |
|---|---|
| `type` | `state_machine` : un cas à la fois. |
| `places` | Les valeurs des cas, sous forme de chaînes. |
| `transitions` | Une par mouvement déclaré, nommée `<from>_to_<to>` d'après les valeurs. |
| `initial_marking` | Les cas vers lesquels aucun cas ne mène. |
| `marking_store` | `method` sur `property` ; Symfony écrit le cas lui-même dans une propriété typée par l'enum. |

L'enum doit être à valeurs (une place est une chaîne) et déclarer au moins une transition ; sinon, la configuration la
refuse. Le workflow est enregistré avec `symfony/workflow`. Un workflow a besoin de plus que ce que dit l'enum (des
gardes, des métadonnées, d'autres noms de transition) ? Déclarez-le plutôt dans la configuration de FrameworkBundle ; le
`AsStatefulEnumTrait` de l'enum répond toujours aux mêmes questions dans les gabarits.
