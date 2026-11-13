<!-- phil-dev source-sha256=a253b39316dad7eb1062e7dba2d3cbf47e932ed6ab5354879918bd526a3264c6 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Rendre une page intégrable | Donnez à sa route la valeur par défaut `_embed`, ou à son contrôleur `#[Application\Attribute\Embeddable]` |
| Lire l'intégration pour laquelle une page est servie | L'attribut de requête `_phil_embed`, ou `phil_embed()` dans Twig |
| Émettre un jeton depuis le code | `Application\EmbedTokens::issue()` |
| Écrire le code qu'un partenaire colle | `Application\EmbedCodes::snippet()`, ou `phil_embed_code()` dans Twig |
| Écrire `frame-ancestors` autrement | Implémentez `Application\Contract\FramingPolicy` et créez un alias dans une passe du compilateur qui s'exécute après celle du bundle |
| Porter les commentaires d'une page sur le forum d'un autre site | Implémentez `Application\Contract\RemoteCommentThreads` |
| Réagir au cadre | Écoutez `phil-embed:ready` et `phil-embed:resize` sur l'élément |
| Changer la façon dont le cadre se mesure | Remplacez le contrôleur `frame` dans `assets/controllers.json` par un autre qui envoie les mêmes messages |

## Les ports

```php
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\HttpFoundation\Request;

final readonly class EdgeFraming implements FramingPolicy
{
    public function allow(Request $request, Origin $origin): void
    {
        $request->attributes->set('edge_frame_ancestors', $origin->toString());
    }

    public function deny(Request $request): void
    {
        $request->attributes->set('edge_frame_ancestors', "'none'");
    }
}
```

Les invariants :

- une `FramingPolicy` laisse une réponse autorisée être encadrée par cette origine seulement, `X-Frame-Options` retiré, et
  une réponse refusée par aucune ;
- une `RemoteCommentThreads` ne donne aucun fil pour une page d'un hôte qui n'est pas autorisé, et toujours le même fil
  pour la même page.
