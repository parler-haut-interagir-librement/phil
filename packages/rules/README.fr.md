<!-- phil-dev source-sha256=0eff107534e5674681a2a00b1d74f39f6c8c9dbd637b7f35b4179a5bf76243f6 -->
# phil/rules

**Décidez de prix, de promotions, d'accès ou d'audiences par des règles stockées : conditions typées tout/au moins une/non, coût borné, trace du pourquoi, JSON versionné, aucun `eval`.**

phil/rules est une bibliothèque PHP. Une règle est un arbre de conditions fixes : `All`, `Any` et `Not` se composent, et
chaque feuille nomme un type de condition qu'un paquet déclare — un nom stable, le schéma de ses opérandes et un
évaluateur. Les listes de prix, les promotions, l'accès au contenu, les segments d'audience et les automatisations lisent
les mêmes règles sans que chacun écrive son propre langage de conditions.

- **Typé et fermé** : une règle ne nomme que des types enregistrés et ne donne que des opérandes que leur schéma
  accepte. Un type inconnu, un opérande mal orthographié ou une valeur d'un mauvais genre est refusé au chargement de la
  règle, jamais trouvé faux plus tard.
- **Aucun code dans une règle** : ni `eval`, ni langage d'expression sur une saisie d'utilisateur, ni SQL. Les faits
  viennent d'une portée que l'appelant passe et ne sont lus que lorsqu'une condition les demande ; un fait coûteux
  qu'aucune condition ne lit n'est jamais calculé.
- **Coût borné** : les octets, la profondeur, les conditions et les évaluations d'une règle sont limités, avec une
  exception typée au-delà de chaque borne. Une règle ne peut pas boucler.
- **Expliqué** : le résultat porte une trace — chaque condition évaluée ou sautée — et les feuilles qui ont décidé, avec
  le pointeur JSON de chacune et un code de raison stable.
- **JSON versionné** : `{"version": 1, "condition": …}`, lu strictement et écrit de façon canonique, de sorte qu'une
  règle stockée se relit à l'identique et possède une empreinte.

## État

| Partie | État |
|---|---|
| L'arbre de conditions : `All`, `Any`, `Not`, `Leaf` | Implémenté et testé |
| Les types de condition : le registre, les schémas d'opérandes, les six comparaisons intégrées | Implémenté et testé |
| L'évaluation avec court-circuit, trace et explication, bornée par `Limits` | Implémenté et testé |
| La forme JSON versionnée : lecteur, écrivain, empreinte | Implémenté, testé et soumis au fuzzing |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, une première règle. |
| [Règles](doc/fr/rules.md) | L'arbre, les faits, les types intégrés, la trace, les limites. |
| [Forme JSON](doc/fr/json-form.md) | La forme versionnée, ce qui est refusé, l'empreinte. |
| [Points d'extension](doc/fr/extension-points.md) | Écrire un type de condition, fournir des faits, ce qui est stable. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing, le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/rules
```

```php
use Phil\Rules\Fact\Facts;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Type\ConditionTypes;

$reader = new RuleReader(ConditionTypes::builtIn());
$rule = $reader->fromJson('{"version": 1, "condition": {"all": [
    {"type": "fact.in", "operands": {"fact": "country", "values": ["CA", "FR"]}},
    {"type": "fact.compare", "operands": {"fact": "orders", "operator": "gte", "value": 2}}
]}}');

$result = $rule->evaluate(Facts::of(['country' => 'CA', 'orders' => static fn (): int => $orders->countFor($customer)]));
$result->passed;        // true or false
$result->explanation(); // the leaves that decided, with their path and reason
```

## Prérequis

PHP 8.5 ou une version plus récente avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
