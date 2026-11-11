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

`Phil\Money\Money` is mapped as an embeddable (`config/doctrine/Money.orm.xml`): two columns, the amount in minor
units as a `bigint` and the currency as a `phil_currency`.

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
