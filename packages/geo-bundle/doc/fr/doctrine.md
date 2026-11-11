<!-- phil-dev source-sha256=ba8d881901692fbbeed4cfff1c87b5cbfa4f1cb373fca3e0fe9ee90c448bf85e -->
# Doctrine

Avec l'ORM configuré et `doctrine_mapping` laissé activé, `Phil\Geo\Coordinates` est un objet intégrable de deux
colonnes `float` :

```php
#[ORM\Embedded(class: Coordinates::class)]
public Coordinates $position;   // columns position_latitude and position_longitude
```

Indexez les deux colonnes pour chercher près d'un point : restreignez à la zone englobante, puis gardez la distance
exacte.

```php
$box = BoundingBox::around($here, Distance::kilometers(10));
$candidates = $repository->createQueryBuilder('s')
    ->where('s.position.latitude BETWEEN :south AND :north')
    ->andWhere('s.position.longitude BETWEEN :west AND :east')
    ->setParameters(['south' => $box->south, 'north' => $box->north, 'west' => $box->west, 'east' => $box->east])
    ->getQuery()->getResult();
```

## Une position facultative

L'objet intégrable est toujours une position : ses deux colonnes sont `NOT NULL`, et Doctrine ne peut pas omettre un
objet intégré. Une propriété typée `?Coordinates` et intégrée écrirait `NULL` dans ces colonnes. Un lieu qui peut ne pas
avoir de position garde deux colonnes nullables qui lui sont propres et construit les `Coordinates` quand il a les
deux :

```php
#[ORM\Column(nullable: true)]
private ?float $latitude = null;

#[ORM\Column(nullable: true)]
private ?float $longitude = null;

public function getPosition(): ?Coordinates
{
    return null === $this->latitude || null === $this->longitude ? null : Coordinates::of($this->latitude, $this->longitude);
}

public function setPosition(?Coordinates $position): void
{
    [$this->latitude, $this->longitude] = [$position?->latitude, $position?->longitude];
}
```

`CoordinatesType` avec `'required' => false` se lie à `position` par ces deux méthodes. La requête par zone englobante
ci-dessus lit `s.latitude` et `s.longitude`, et ignore les lieux sans position.

## Près du visiteur

Chercher près du visiteur envoie sa position au serveur ; pour la garder dans le navigateur, publiez les lieux
(brouillés au besoin) et laissez [le contrôleur](distance-sort.md) les trier.
