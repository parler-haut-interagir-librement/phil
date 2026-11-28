<!-- phil-dev source-sha256=a5ba2944b6e69b07ac590e94f79419287cc87f9ad608216a62a704cd61e4d728 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Ajouter une condition qu'une règle peut nommer | Implémentez `Type\ConditionType`, et enregistrez-la avec `ConditionTypes::with()` |
| Donner les faits d'un sujet | Passez `Fact\Facts::of()`, ou implémentez `Fact\FactSource` |
| Offrir les types dans un éditeur ou une description d'API | Lisez `ConditionTypes::describe()` |
| Changer une borne | Passez-la à `Limits` |

Chaque implémentation ne conserve rien d'une évaluation à la suivante, de sorte qu'une seule instance serve toutes les
requêtes d'un *worker*.

## Écrire un type de condition

Un type de condition a un nom stable — des mots en minuscules joints par `.`, `-` ou `_`, 64 octets au plus, écrit dans
les règles stockées et jamais modifié une fois publié —, le schéma de ses opérandes et un évaluateur qui renvoie un
`Verdict` :

```php
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Type\ConditionType;

final readonly class CartTotal implements ConditionType
{
    public function name(): string
    {
        return 'cart.total';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::integer('at_least_cents', min: 0), Operand::choice('currency', ['CAD', 'EUR']));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $cart = $facts->has('cart') ? $facts->get('cart') : null;

        if (!$cart instanceof Cart) {
            return Verdict::fail('cart.missing');
        }

        $holds = $cart->currency === $operands->string('currency') && $cart->totalCents >= $operands->int('at_least_cents');

        return $holds ? Verdict::pass('cart.enough') : Verdict::fail('cart.too-low', ['currency' => $cart->currency]);
    }
}
```

Les opérandes arrivent vérifiés : un évaluateur les lit par les accesseurs typés de `Operands` et ne les valide jamais
de nouveau. Un type ne lit les faits que par la source qu'on lui donne, n'appelle aucune règle à son tour et limite ses
détails à des identifiants et des codes plutôt qu'à des valeurs personnelles.

## Genres d'opérande

| Constructeur | Genre | Conservé comme |
|---|---|---|
| `Operand::text()` | UTF-8 sans caractères de contrôle, borné en octets | `string` |
| `Operand::identifier()` | une lettre ou un chiffre, puis des lettres, des chiffres, `_`, `.`, `:`, `/` ou `-` ; 128 octets | `string` |
| `Operand::integer()` | un entier, entre des bornes facultatives | `int` |
| `Operand::number()` | un nombre fini | `float` |
| `Operand::boolean()` | `true` ou `false` | `bool` |
| `Operand::choice()` | l'un des textes listés | `string` |
| `Operand::date()` | une date du calendrier `YYYY-MM-DD` | `string` |
| `Operand::scalar()` | un texte, un entier, un nombre fini ou un booléen | tel que donné |
| `Operand::identifierList()` | une liste non vide d'identifiants distincts | `list<string>` |
| `Operand::scalarList()` | une liste non vide de scalaires distincts | `list<scalar>` |

Chacun accepte `required: false` pour un opérande facultatif ; les listes acceptent `maxItems` (100 par défaut, 1 000 au
plus) et les textes `maxLength` (255 octets par défaut, 10 000 au plus).

## Éditeurs et API

`ConditionTypes::describe()` liste chaque type avec ses opérandes — nom, genre, caractère requis, choix et bornes — de
sorte qu'un éditeur visuel ou une description d'API offre exactement ce que le lecteur accepte.
