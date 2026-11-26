<!-- phil-dev source-sha256=c0fbe598820cfe48f7e2ff3962e2c5bb7f96acc8c61646dec24e4150d2c2fff5 -->
# Identifiants dans les contrôleurs et le Serializer

Deux intégrations facultatives permettent à un identifiant de franchir la frontière de l'application avec son type :

- `Infrastructure\HttpKernel\IdentifierValueResolver` transforme un paramètre de route en la classe d'identifiant que
  déclare un argument de contrôleur, avec `symfony/http-kernel` ;
- `Infrastructure\Serializer\IdentifierNormalizer` écrit un identifiant sous sa forme canonique de chaîne et le
  reconstruit, avec `symfony/serializer`.

Le bundle enregistre chacune quand son composant est installé, et seulement alors. Rien n'est configuré.

Les deux emploient la chaîne canonique, la représentation interne de l'identifiant. Elles conviennent à une API ou à
une interface d'administration, où cette forme peut être vue. Quand un identifiant ne doit pas être montré tel quel
(une URL publique, un jeton, un export), l'application le fait correspondre à la forme qu'elle décide, comme le dit
[identifiers.md](identifiers.md), et exclut cet argument.

## Arguments de contrôleur

Typez l'argument avec la classe d'identifiant, et nommez-le comme le paramètre de route :

```php
use App\Ordering\Domain\OrderId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders/{order}')]
    public function show(OrderId $order): Response
    {
        // $order is an OrderId, built with OrderId::fromString().
    }
}
```

Ni `#[MapEntity]`, ni entité, ni dépôt n'interviennent : le contrôleur reçoit l'identifiant et demande au bus de
requêtes ce dont il a besoin.

### Ce que fait le résolveur

| L'attribut de requête nommé comme l'argument | Résultat |
|---|---|
| Une chaîne UUIDv7 | Une instance de la classe déclarée |
| Un identifiant de la classe déclarée, mis là par un écouteur | Cet identifiant, tel quel |
| Toute autre chaîne : pas un UUID, un UUID d'une autre version, vide | `NotFoundHttpException` : un **404** |
| Un identifiant d'une autre classe, un entier, un tableau, un autre objet | `NotFoundHttpException` : un **404** |
| Absent, ou `null` | Rien : les résolveurs propres de Symfony donnent la valeur par défaut ou `null`, ou signalent l'argument comme non résolu |

Une valeur qui n'est pas un identifiant de la classe déclarée ne désigne aucune ressource : la réponse est un 404,
jamais un 500. Le message de l'exception nomme l'argument, non la valeur reçue ; l'`InvalidValue` levée par
`fromString()`, quand il y en a une, est conservée comme exception précédente pour les journaux.

Le résolveur laisse l'argument aux autres résolveurs quand son type n'est pas une sous-classe concrète de
`Identifier` : pas de type, un scalaire, `Identifier` lui-même, une sous-classe abstraite, une union. Il laisse aussi
de côté un argument variadique.

Le résolveur ne lit que les attributs de requête, là où le routeur met les paramètres de route. Une chaîne de requête
ou un corps est lu par `#[MapQueryParameter]`, `#[MapQueryString]` ou `#[MapRequestPayload]` de Symfony ; les deux
derniers construisent leur objet avec le Serializer, et emploient donc le normaliseur ci-dessous.

### S'exclure

Le résolveur est étiqueté `controller.argument_value_resolver` sous le nom de sa classe. Un argument s'en exclut avec
l'attribut de Symfony :

```php
use Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

public function legacy(#[ValueResolver(IdentifierValueResolver::class, disabled: true)] ?OrderId $order = null): Response
```

Le résolveur lit aussi lui-même cet attribut : l'exclusion tient donc avec le noyau en mode débogage, où Symfony
enveloppe chaque résolveur dans un `TraceableValueResolver` et ne le reconnaît plus par le nom de sa classe.

## Le Serializer

```php
final readonly class OrderView
{
    public function __construct(public OrderId $id, public string $reference) {}
}

$serializer->serialize(new OrderView($orderId, 'A-1042'), 'json');
// {"id":"0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e","reference":"A-1042"}

$view = $serializer->deserialize($json, OrderView::class, 'json');
// $view->id is an OrderId
```

Sans le normaliseur, l'`ObjectNormalizer` écrit un identifiant sous la forme `{"value": …}` et ne peut pas en
reconstruire un, puisque le constructeur de `Identifier` est privé.

| Sens | Entrée | Résultat |
|---|---|---|
| Normalisation | Tout `Identifier` | Son `toString()` : la forme canonique RFC 4122, en minuscules |
| Dénormalisation | Une chaîne UUIDv7, vers une sous-classe concrète de `Identifier` | Une instance de cette classe |
| Dénormalisation | Toute autre chaîne, ou une valeur qui n'est pas une chaîne | `NotNormalizableValueException` |
| Dénormalisation | Vers `Identifier` lui-même, une sous-classe abstraite ou une autre classe | `supportsDenormalization()` est faux ; `denormalize()` lève l'`InvalidArgumentException` du Serializer |

La `NotNormalizableValueException` porte le chemin que signale le Serializer (`deserialization_path`), le type attendu
`string`, et le message `The data is not an identifier: a UUIDv7 string is expected.` Son message peut être montré à
l'utilisateur (`canUseMessageForUser()` est vrai) : ce qui a échoué est une donnée qu'un client a envoyée. Il ne nomme
ni la valeur ni la classe. Avec `#[MapRequestPayload]`, le refus devient une réponse 422, comme pour tout autre champ.

`getSupportedTypes()` retourne `[Identifier::class => true]` : la réponse ne dépend que du type, et le Serializer la
met en cache. Le normaliseur a la priorité par défaut, devant l'`ObjectNormalizer`.

## Services

| Identifiant de service | Étiquette | Enregistré quand |
|---|---|---|
| `Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver` | `controller.argument_value_resolver`, `name: Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver` | `Symfony\Component\HttpKernel\Controller\ValueResolverInterface` existe |
| `Phil\DddBundle\Infrastructure\Serializer\IdentifierNormalizer` | `serializer.normalizer` | `Symfony\Component\Serializer\Normalizer\NormalizerInterface` existe |

Les deux sont sans état et privées : une seule instance sert toutes les requêtes d'un *worker*.
