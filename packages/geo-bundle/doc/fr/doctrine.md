<!-- phil-dev source-sha256=e2b71faff9c9738fe966d7edf263e55330340326756c8fd1c378b59d8c39787a -->
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

## Comment le mappage est déclaré

Le mappage s'écrit en attributs Doctrine, sur `Infrastructure\Doctrine\CoordinatesEmbeddable` : `#[ORM\Embeddable]`,
et un `#[ORM\Column]` de type `float` sur `latitude` et sur `longitude`. `phil/geo` est une simple bibliothèque PHP qui
ne connaît pas Doctrine : les attributs ne peuvent donc pas se trouver sur `Coordinates` elle-même.
`CoordinatesEmbeddable` reprend ses deux propriétés et n'est jamais instanciée.
`Infrastructure\Doctrine\CoordinatesMappingDriver` lit ces attributs avec le pilote d'attributs de Doctrine et s'en
sert pour associer `Coordinates`.

Quand l'application configure l'ORM et que `doctrine_mapping` est activé, le bundle définit le paramètre
`phil_geo.doctrine_mapping` (`PhilGeoBundle::DOCTRINE_MAPPING`), et sa passe de compilation ajoute le pilote à la chaîne
de pilotes du gestionnaire d'entités par défaut, pour la seule classe `Phil\Geo\Coordinates`. Une application dont les
lieux sont dans un autre gestionnaire d'entités définit `doctrine_mapping: false` et ajoute le pilote elle-même, dans le
`build()` de son noyau :

```php
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Doctrine\CoordinatesMappingDriver;
use Symfony\Component\DependencyInjection\Definition;

protected function build(ContainerBuilder $container): void
{
    // The parameter holds the name of the entity manager.
    $container->setParameter('app.places_entity_manager', 'places');
    $container->addCompilerPass(new DoctrineOrmMappingsPass(
        new Definition(CoordinatesMappingDriver::class),
        [Coordinates::class],
        ['app.places_entity_manager'],
    ));
}
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
