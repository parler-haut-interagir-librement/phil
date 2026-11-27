<!-- phil-dev source-sha256=034d445f63a0805cdbd7e4788716500572f9cec760c14d4340abaa2acecb4022 -->
# Doctrine

## `phil_currency`

Un type de colonne pour un `Currency` : une colonne fixe de trois caractères qui contient son code.

```php
#[ORM\Column(type: 'phil_currency')]
public Currency $currency;
```

Enregistré sous `CurrencyType::NAME` (`phil_currency`) dès que DoctrineBundle est installé. Un code relu que les données
des devises ne connaissent plus fait échouer l'hydratation avec un `ValueNotConvertible`, au lieu de passer sans
vérification ; stocker autre chose qu'un `Currency` ou `null` lance `InvalidType`.

## L'objet intégrable `Money`

`Phil\Money\Money` est associé comme objet intégrable : deux colonnes, le montant en unités mineures sous forme de
`bigint` et la devise sous forme de `phil_currency`.

```php
#[ORM\Embedded(class: Money::class)]
public Money $price;
```

Un `price` intégré correspond aux colonnes `price_amount` et `price_currency`. Deux colonnes plutôt qu'une, pour qu'une
requête puisse additionner, trier et filtrer les montants d'une même devise :

```php
$repository->createQueryBuilder('p')
    ->select('SUM(p.price.minorUnits)')
    ->where('p.price.currency = :currency')
    ->setParameter('currency', 'CAD');
```

Une somme n'a de sens qu'à l'intérieur d'une même devise : filtrez ou regroupez toujours par devise.

Doctrine instancie un objet intégrable même quand ses colonnes sont nulles, de sorte qu'un `Money` intégré ne peut pas
être absent. Un montant facultatif est une colonne `phil_currency` nullable et une colonne `bigint` nullable sur
l'entité, combinées en un `?Money` par son accesseur.

L'association est enregistrée lorsque l'application configure l'ORM et que `doctrine_mapping` vaut `true`
([configuration.md](configuration.md)).

### Comment le mappage est déclaré

Le mappage s'écrit en attributs Doctrine, sur `Infrastructure\Doctrine\MoneyEmbeddable` : `#[ORM\Embeddable]`, et un
`#[ORM\Column]` sur chaque propriété. `phil/money` est une simple bibliothèque PHP qui ne connaît pas Doctrine : les
attributs ne peuvent donc pas se trouver sur `Money` elle-même. `MoneyEmbeddable` reprend ses deux propriétés,
`minorUnits` et `currency`, et n'est jamais instanciée. `Infrastructure\Doctrine\MoneyMappingDriver` lit ces attributs
avec le pilote d'attributs de Doctrine et s'en sert pour associer `Money`. Une passe de compilation du bundle ajoute ce
pilote à la chaîne de pilotes du gestionnaire d'entités par défaut, pour la seule classe `Phil\Money\Money`.

### Plusieurs gestionnaires d'entités

Le bundle associe `Money` sur le gestionnaire d'entités par défaut. Une application dont les montants sont dans un autre
gestionnaire définit `doctrine_mapping: false` et ajoute le pilote elle-même, dans le `build()` de son noyau :

```php
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Doctrine\MoneyMappingDriver;
use Symfony\Component\DependencyInjection\Definition;

protected function build(ContainerBuilder $container): void
{
    // The parameter holds the name of the entity manager.
    $container->setParameter('app.billing_entity_manager', 'billing');
    $container->addCompilerPass(new DoctrineOrmMappingsPass(
        new Definition(MoneyMappingDriver::class),
        [Money::class],
        ['app.billing_entity_manager'],
    ));
}
```
