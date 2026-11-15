<!-- phil-dev source-sha256=66a69e5da1dce1f6f80bd2193ab99b945dc1b419481da444c82b1777cfa0b3fa -->
# Points d'extension

| Identifiant de service | Par défaut | À remplacer par |
|---|---|---|
| `Phil\LegalNotices\Contract\DocumentSource` | `ConfigDocumentSource` | `source` : une base de données ou des fichiers de l'application |
| `Phil\LegalNotices\Contract\BodyRenderer` | `MarkupBody`, sinon `PlainTextBody` | un alias vers le moteur de rendu de l'application, ou `markup` |
| `Phil\LegalNotices\Contract\RetentionDurations` | `ScheduleRetentionDurations` quand `retention.source` est défini | un alias vers le service de l'application |
| `Phil\LegalNotices\Contract\ConsentCategories` | `ConfiguredConsentCategories` quand des catégories sont écrites | `consent.source` |
| `Phil\LegalNotices\LegalNotices` | public, pour les pages propres de l'application | — |
| `Phil\LegalNoticesBundle\Application\Contract\FooterLinkProvider` | aucun | un service étiqueté `phil_legal_notices.footer_link` : `footerLinks()` donne les liens qu'il ajoute après les documents et les politiques, chacun avec `url`, `label` et `domain` |

Chaque port est décrit dans les points d'extension de phil/legal-notices. Une source de l'application peut garder les
versions dans une base de données et les publier depuis une administration : ses historiques suivent les règles de
`DocumentHistory`, et elle distribue elle-même `DocumentPublished` quand elle en publie une.

## Des valeurs par défaut venues d'un autre bundle

Les versions viennent de la configuration, par `ConfigDocumentSource`, ou d'un `DocumentSource` de l'application nommé
par `source`. Des sites qui partagent un éditeur, un hébergeur et les mêmes documents peuvent les garder dans un bundle
qui leur est propre, lequel donne les valeurs par défaut de `phil_legal_notices` dans son `prependExtension()` ; la
configuration de chaque site est fusionnée après elle, champ par champ pour l'identité, et un `source` qu'elle nomme
remplace celui du bundle.

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

Le bundle donne ses versions par un `DocumentSource` plutôt que par `documents` : les versions d'un type écrites dans
deux configurations s'ajoutent l'une après l'autre, alors qu'une source est remplacée en entier.

## Gabarits

Les gabarits se redéfinissent sous `templates/bundles/PhilLegalNoticesBundle/`, et `pages.layout` nomme la mise en
page qu'ils étendent. Voir [Pages](pages.md) pour le contexte de chacun et les recettes du kit qu'ils appellent.

| Twig | Ce qu'il donne |
|---|---|
| `phil_legal_notices_links()` | Les liens du pied de page, chacun avec `url`, `label` et `domain` |
| `phil_legal_notices_kit(recipe)` | Le nom d'une recette du kit sous le sous-répertoire des composants : `ui:Badge` pour `Badge` avec `ui` |

Les deux sont déclarées par des attributs sur `LegalNoticesExtension`, un service d'exécution Twig.

## Événements

`DocumentPublished` est distribué par `phil:legal-notices:announce` ; voir [Console](console.md).
