<!-- phil-dev source-sha256=b309ab28ec5580b792d52a4bab010be567208ddb5c1e98c18d8378e022aeaa15 -->
# phil/money-bundle

**Des montants d'argent dans les formulaires, la validation, les gabarits Twig et les entités Doctrine de Symfony, sans
nombres à virgule flottante.**

phil/money-bundle intègre `phil/money` à une application Symfony. Il garde, par configuration, les devises que
l'application accepte. Il ajoute un type de formulaire qui lit un montant dans la locale de la requête sans jamais
passer par un nombre à virgule flottante, deux contraintes de validation, un filtre Twig `format_money` avec deux
fonctions schema.org, et des associations Doctrine qui stockent une devise dans une colonne et un montant dans deux. Il
ne convertit pas les devises, ne récupère pas de taux de change, n'arrondit pas les montants et ne fixe pas les prix :
les montants restent dans leur devise, et un montant plus fin que sa devise est refusé plutôt qu'arrondi.

Il s'adresse à une application Symfony où un administrateur saisit des prix, un visiteur les voit et des entités les
stockent. Le code hors de Symfony, ou une couche de domaine qui ne fait que calculer, n'a besoin que de `phil/money`.

C'est un bundle Symfony. Il se branche aux composants Form et Validator, à Twig lorsque Twig est installé, et à Doctrine
ORM lorsque DoctrineBundle est enregistré, en associant `Money` comme objet intégrable.

La configuration est vérifiée à la construction du conteneur, de sorte qu'un code de devise inconnu arrête le noyau en
nommant le code. Chaque montant traverse le bundle sous forme de `Money`, du champ de formulaire jusqu'à la colonne de
la base de données.

## État

| Partie | État |
|---|---|
| Configuration `phil_money` et `AcceptedCurrencies` | Implémenté et testé |
| Type de formulaire `MoneyType`, lu et écrit dans la locale de la requête | Implémenté et testé |
| Contraintes `AcceptedCurrency` et `MoneyRange`, messages en anglais et en français | Implémenté et testé |
| Filtre Twig `format_money`, fonctions Twig `money_schema` et `price_schema` | Implémenté et testé |
| Type de colonne `phil_currency` et l'objet intégrable `Money` | Implémenté et testé |
| Un test avec noyau d'un formulaire, des contraintes, de Twig et de SQLite | Implémenté |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et l'enregistrement du bundle. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut, et les services qu'enregistre le bundle. |
| [Formulaires](doc/fr/forms.md) | `MoneyType` : les options, les champs qu'il affiche, la lecture du montant et ses erreurs. |
| [Validation](doc/fr/validation.md) | `AcceptedCurrency` et `MoneyRange`, avec leurs messages. |
| [Twig](doc/fr/twig.md) | Le filtre `format_money`, et `format_currency`. |
| [Doctrine](doc/fr/doctrine.md) | Le type de colonne `phil_currency` et l'objet intégrable `Money`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester des formulaires et des entités qui contiennent de l'argent. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/money-bundle
```

```yaml
# config/packages/phil_money.yaml
phil_money:
    currencies: [CAD, USD]
```

```php
#[ORM\Embedded(class: Money::class)]
#[AcceptedCurrency]
#[MoneyRange(min: '0.01')]
public Money $price;

$builder->add('price', MoneyType::class);
```

```twig
{{ product.price|format_money }}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, `phil/money`, et les composants form, validator et intl de Symfony 8.2.
Facultatifs : `twig/twig` pour le filtre, `doctrine/dbal` et `doctrine/orm` pour les associations.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
