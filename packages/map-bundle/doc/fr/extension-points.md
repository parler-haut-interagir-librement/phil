<!-- phil-dev source-sha256=959a8b284a5dc3bd710a7ea436f79a7764dd76c5e4fec2419c11407c64e871b1 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Utiliser d'autres tuiles | Fixez `tiles.source` et `tiles.url`, ou implémentez `Application\Contract\BaseLayer` et créez-en un alias |
| Donner aux kiosques leurs états à partir de vos propres réservations | Implémentez `Application\Contract\BoothAvailability` et nommez-le dans `booths.availability` |
| Découper de grandes images avec une autre bibliothèque | Implémentez `Application\Contract\ImageTiler` et créez-en un alias |
| Changer les mots | Traduisez le domaine `phil_map`, ou surchargez ses messages |
| Changer le balisage de la liste | Surchargez `@PhilMap/map.html.twig` dans `templates/bundles/PhilMapBundle/map.html.twig` |
| Décorer davantage la carte | Écoutez les événements de Symfony UX Map sur l'élément de la carte, comme le fait le contrôleur du bundle |
| Changer les couleurs | Surchargez les classes de `phil-map/map.css`, ou les jetons de la feuille de style de PHIL UX |

## Les ports

```php
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Domain\Enum\BoothState;

final readonly class StandBookings implements BoothAvailability
{
    public function __construct(private StandRepository $stands) {}

    public function states(array $booths, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $states = [];

        foreach ($booths as $booth) {
            $states[$booth->id] = $this->stands->isBooked($booth->id, $start, $end) ? BoothState::Taken : BoothState::Free;
        }

        return $states;
    }
}
```

Les invariants :

- un `BoothAvailability` donne un état pour chaque kiosque, sous son identifiant, ne réserve ni ne retient rien, et
  déclare inconnu un kiosque sans ressource ;
- un `BaseLayer` n'envoie jamais les témoins ni le référent du visiteur à un hôte autre que celui du site ;
- un `ImageTiler` lit la taille d'une image avant ses pixels, écrit `<z>/<x>/<y>.png` sous son répertoire et rien en
  dehors, et donne sans mise à l'échelle les tuiles du zoom natif.
