<!-- phil-dev source-sha256=21b53357546f6899773b3245165ca09ebe1015763b21e945e44e8b50bfb972bd -->
# Formulaires

`Phil\MoneyBundle\Infrastructure\Form\MoneyType` édite un `?Money`.

```php
$builder->add('price', MoneyType::class);                                   // the accepted currencies
$builder->add('price', MoneyType::class, ['currency' => 'CAD']);            // one currency, no choice
$builder->add('price', MoneyType::class, ['currencies' => ['CAD', 'USD']]); // these currencies
```

## Options

| Option | Valeur par défaut | Sens |
|---|---|---|
| `currency` | `null` | Un seul code de devise : le formulaire n'affiche aucun choix et le montant soumis est dans cette devise, même quand le formulaire a été rempli à partir d'un montant dans une autre devise |
| `currencies` | `null` | Les codes proposés, dans l'ordre ; par défaut, les codes acceptés. Un seul code est fixe, comme avec `currency`. |
| `invalid_message` | `This value is not a valid money amount.` | L'erreur d'un montant illisible |
| `required` | `true` | Transmis au champ `amount` |

Un code inconnu dans `currency` ou `currencies` lance `UnknownCurrency` à la construction du formulaire ; une liste
`currencies` vide est refusée. Proposer une devise n'est pas l'accepter : associez le champ à `#[AcceptedCurrency]`
quand `currencies` est plus large que la configuration.

## Les champs

- **`amount`**, un `TextType` avec `inputmode="decimal"` : un clavier numérique sur un téléphone, sans la lecture
  aveugle à la locale de `<input type="number">`.
- **`currency`**, un `ChoiceType` de codes étiquetés par leur nom dans la locale de la requête (`dollar canadien`),
  affiché seulement quand plus d'une devise est proposée.

Aucun des deux n'a de libellé propre : le libellé du formulaire couvre la paire. Le préfixe de bloc est `phil_money`,
pour un thème de formulaire qui dispose les deux champs sur une ligne.

## La lecture du montant

Le montant est lu sous forme de chaîne dans la locale de la requête, et jamais par un nombre à virgule flottante :

| Locale | Accepté | Lu comme |
|---|---|---|
| `fr_CA` | `1 234,50`, `1234.50`, `−12,5` | `1234.50`, `1234.50`, `-12.5` |
| `en` | `1,234.50`, `12.5`, `1 234.50` | `1234.50`, `12.5`, `1234.50` |
| `de` | `1.234,50`, `1.234` | `1234.50`, `1234` |
| `en_IN` | `12,34,567.50` | `1234567.50` |

| Locale | Refusé | Pourquoi |
|---|---|---|
| `en` | `12,50` | Un séparateur de milliers devant deux chiffres |
| `de` | `12.50` | Le point est le séparateur de milliers en `de`, et deux chiffres ne forment pas un groupe |
| `en` | `1,2,3`, `1234,567`, `1,234 567` | Des groupes de la mauvaise taille, ou deux sortes de groupement |

Le séparateur décimal est celui de la locale, et aussi un point quand le point n'est pas le séparateur de milliers de la
locale. Le groupement n'est accepté qu'avant le séparateur décimal et en groupes des tailles de la locale (trois
chiffres, ou trois puis deux en `en_IN`), séparés par le séparateur de milliers de la locale ou par une espace, le même
partout. Un séparateur placé ailleurs rend le montant illisible au lieu d'être retiré, de sorte que `12,50` tapé en `en`
n'est jamais lu comme 1250. Le signe moins `−` vaut `-`. Le montant est ensuite lu par `Money::ofDecimal()` dans la
devise choisie. Une locale dont le séparateur de milliers est le point lit `1.234` comme mille deux cent trente-quatre,
comme l'entend une personne qui écrit dans cette locale.

Un montant vide donne `null`. Un montant illisible, ou plus fin que ce que compte la devise (`12,345` dollars), fait
échouer le formulaire avec `invalid_message` ; le bundle traduit le message par défaut en français. Une devise qui n'est
pas proposée fait échouer le champ `currency` avec l'erreur propre au choix.

Le montant est réaffiché avec le séparateur décimal de la locale et sans groupement, pour que le champ relise ce qu'il
affiche : `-1234,50` en `fr_CA`.

## Un champ vide

Le choix `currency` commence sur la devise acceptée par défaut quand elle est proposée, sur la première devise proposée
sinon. Avec une configuration `currencies` ouverte, le choix propose les devises en usage (ayant cours légal dans au
moins un pays aujourd'hui) et non les codes historiques que connaissent aussi les données CLDR ; passez `currencies`
pour en proposer d'autres.
