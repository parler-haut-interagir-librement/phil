<!-- phil-dev source-sha256=5300ab61d023859eba6053585a2420c799c66f4825a79aaa45b19018d8223988 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Être informé de chaque visite | Implémentez `Application\Contract\VisitRecorder` ; l'autoconfiguration l'étiquette `phil_short_link.visit_recorder` |
| Compter les visites dans la mesure d'audience | L'option `analytics.site`, avec phil/analytics-bundle |
| Qui est à l'origine d'une visite | Les options `visitors.link_previews` et `visitors.robots`, ou une implémentation de `Application\Contract\VisitorClassifier` dont l'alias remplace le classeur du bundle |
| Vérifier une cible avant de l'enregistrer | Implémentez `Application\Contract\TargetCheck` et passez-la à `ShortLinks`, ou activez `targets.check` |
| L'allocation de créations | Les options de `rate_limit`, une fabrique de limiteurs de débit de l'application dans `rate_limit.limiter`, ou une implémentation de `Application\Contract\CreationThrottle` |
| Où les liens sont conservés | Implémentez `Domain\Contract\ShortLinkRepository` et créez-en un alias |
| Créer des liens depuis le code | `Application\ShortLinks` : `create()`, `update()`, `enable()`, `delete()`, `get()`, `url()` |
| Répondre à une requête depuis le code | `Application\ShortLinkResolver::resolve()` |
| Les codes depuis le code | `Application\Code\SqidCodec` et `Application\Code\CodeGenerator` |
| Les codes QR depuis le code | `Application\ShortLinkQrCodes` : `svg()`, `png()` |
| Le formulaire dans les pages propres à l'application | `Infrastructure\Form\ShortLinkType`, sur un `ShortLinkDraft` |
| Les mots de l'administration | Les messages du domaine `phil_short_link` |
| Les pages de l'administration | `admin.layout`, et les gabarits sous `@PhilShortLink/admin/` remplacés dans `templates/bundles/PhilShortLinkBundle/` |
| L'horloge | Le service `clock` : le bundle ne lit jamais l'heure lui-même |

## Un classeur

`Application\Contract\VisitorClassifier` répond une `Domain\Enum\VisitorClass` à partir du `User-Agent` et des en-têtes
d'intention d'une visite, ces derniers joints par des virgules. Le bundle enregistre
`Application\Visitor\KnownVisitorClassifier` et lui donne l'alias du port ; une application pointe l'alias vers son propre
service :

```php
namespace App\ShortLink;

use Phil\ShortLinkBundle\Application\Contract\VisitorClassifier;
use Phil\ShortLinkBundle\Application\Visitor\KnownVisitorClassifier;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VisitorClassifier::class)]
final readonly class KioskAwareClassifier implements VisitorClassifier
{
    public function __construct(private KnownVisitorClassifier $known)
    {
    }

    public function classify(?string $userAgent, ?string $purpose): VisitorClass
    {
        // The kiosks of the venue scan a link to check it before showing it.
        return str_contains($userAgent ?? '', 'VenueKiosk/') ? VisitorClass::Robot : $this->known->classify($userAgent, $purpose);
    }
}
```

Un classeur lit les en-têtes qu'on lui donne et n'en garde aucun, ne retient aucun état d'un appel à l'autre et borne ce
qu'il lit : il s'exécute à chaque visite, avant la redirection.

## Un enregistreur

Un enregistreur ne reçoit que des identifiants, une fois la réponse partie :

```php
use Phil\ShortLinkBundle\Application\Contract\VisitRecorder;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class VisitMessages implements VisitRecorder
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function record(ShortLinkVisit $visit): void
    {
        $this->bus->dispatch(new LinkVisited($visit->linkId));
    }
}
```
