<!-- phil-dev source-sha256=78f93bd5ddf9f1cbdbf12a49ca7775276755005efa54f17be4e081045f3b20a5 -->
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

`Phil\Money\Money` est associé comme objet intégrable (`config/doctrine/Money.orm.xml`) : deux colonnes, le montant en
unités mineures sous forme de `bigint` et la devise sous forme de `phil_currency`.

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
