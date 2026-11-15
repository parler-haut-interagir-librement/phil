# Extension points

| Service id | Default | Replace it with |
|---|---|---|
| `Phil\LegalNotices\Contract\DocumentSource` | `ConfigDocumentSource` | `source`: a database or files of the application |
| `Phil\LegalNotices\Contract\BodyRenderer` | `MarkupBody`, else `PlainTextBody` | an alias to the application's renderer, or `markup` |
| `Phil\LegalNotices\Contract\RetentionDurations` | `ScheduleRetentionDurations` when `retention.source` is set | an alias to the application's service |
| `Phil\LegalNotices\Contract\ConsentCategories` | `ConfiguredConsentCategories` when categories are written | `consent.source` |
| `Phil\LegalNotices\LegalNotices` | public, for the application's own pages | — |
| `Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider` | none | a service tagged `phil_legal_notices.footer_link`: `footerLinks()` gives the links it adds after the documents and the policies, each with `url`, `label` and `domain` |

Each port is described in the extension points of phil/legal-notices. A source of the
application may keep the versions in a database and publish them from an administration: its histories follow the
rules of `DocumentHistory`, and it dispatches `DocumentPublished` itself when it publishes one.

## Defaults from another bundle

The versions come from the configuration, through `ConfigDocumentSource`, or from a `DocumentSource` of the
application named by `source`. Sites that share a publisher, a host and the same documents can keep them in a bundle
of their own, which gives the defaults of `phil_legal_notices` in its `prependExtension()`; the configuration of each
site is merged after it, field by field for the identity, and a `source` it names replaces the bundle's.

```php
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class SharedLegalBundle extends AbstractBundle
{
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('phil_legal_notices', [
            'identity' => [
                'publisher_name' => 'Example Publishing',
                'host_name' => 'Example Hosting',
            ],
            'source' => SharedDocuments::class, // a DocumentSource the bundle registers
        ]);
    }
}
```

The bundle gives its versions through a `DocumentSource` rather than `documents`: the versions of a type written in
two configurations are added one after the other, where a source is replaced whole.

## Templates

The templates are overridden under `templates/bundles/PhilLegalNoticesBundle/`, and `pages.layout` names the layout
they extend. See [Pages](pages.md) for the context of each and the recipes of the kit they call.

| Twig | What it gives |
|---|---|
| `phil_legal_notices_links()` | The footer links, each with `url`, `label` and `domain` |
| `phil_legal_notices_kit(recipe)` | The name of a recipe of the kit under the components subdirectory: `ui:Badge` for `Badge` with `ui` |

Both are declared by attributes on `LegalNoticesExtension`, a Twig runtime service.

## Events

`DocumentPublished` is dispatched by `phil:legal-notices:announce`; see [Console](console.md).
