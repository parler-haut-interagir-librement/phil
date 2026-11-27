# Doctrine

## `phil_currency`

A column type for a `Currency`: a fixed three-character column holding its code.

```php
#[ORM\Column(type: 'phil_currency')]
public Currency $currency;
```

Registered under `CurrencyType::NAME` (`phil_currency`) whenever DoctrineBundle is installed. A code read back
that the currency data no longer knows fails the hydration with a `ValueNotConvertible`, rather than passing
unchecked; storing anything but a `Currency` or `null` throws `InvalidType`.

## The `Money` embeddable

`Phil\Money\Money` is mapped as an embeddable: two columns, the amount in minor units as a `bigint` and the currency
as a `phil_currency`.

```php
#[ORM\Embedded(class: Money::class)]
public Money $price;
```

An embedded `price` maps to the columns `price_amount` and `price_currency`. Two columns rather than one so that a
query can sum, sort and filter amounts of one currency:

```php
$repository->createQueryBuilder('p')
    ->select('SUM(p.price.minorUnits)')
    ->where('p.price.currency = :currency')
    ->setParameter('currency', 'CAD');
```

A sum is only meaningful within one currency: always filter or group by it.

Doctrine instantiates an embeddable even when its columns are null, so an embedded `Money` cannot be absent. An
optional amount is a nullable `phil_currency` column and a nullable `bigint` column on the entity, combined into a
`?Money` by its getter.

The mapping is registered when the application configures the ORM and `doctrine_mapping` is `true`
([configuration.md](configuration.md)).

### How the mapping is declared

The mapping is written in Doctrine attributes, on `Infrastructure\Doctrine\MoneyEmbeddable`: `#[ORM\Embeddable]`, and
an `#[ORM\Column]` on each property. `phil/money` is a plain PHP library that does not know Doctrine, so the attributes
cannot sit on `Money` itself; `MoneyEmbeddable` mirrors its two properties, `minorUnits` and `currency`, and is never
instantiated. `Infrastructure\Doctrine\MoneyMappingDriver` reads those attributes with Doctrine's attribute driver
and maps `Money` with them. A compiler pass of the bundle adds that driver to the driver chain of the default entity
manager, for the class `Phil\Money\Money` alone.

### Several entity managers

The bundle maps `Money` on the default entity manager. An application whose amounts live in another manager sets
`doctrine_mapping: false` and adds the driver itself, in the `build()` of its kernel:

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
