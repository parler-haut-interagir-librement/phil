# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\SocialCardsBundle\PhilSocialCardsBundle` | bundle | The configuration and the services |
| `Phil\SocialCardsBundle\Application\SharePage` | `final class`, reset | The values the request sets |
| `Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource` | interface | The values of a model |
| `Phil\SocialCardsBundle\Application\CardTemplates` | `final class` | The templates of the configuration, read once |
| `Phil\SocialCardsBundle\Application\CardRequest` | `final readonly class` | What a card is drawn from |
| `Phil\SocialCardsBundle\Application\Contract\CardRequestStore` | interface | The requests by fingerprint |
| `Phil\SocialCardsBundle\Application\Contract\CardQueue` | interface | The cards drawn out of the request |
| `Phil\SocialCardsBundle\Application\SocialCards` | `final readonly class` | The fingerprint and image of a page's card |
| `Phil\SocialCardsBundle\Application\Contract\PageFetcher` | interface | The head of a page of another site |
| `Phil\SocialCardsBundle\Application\FetchedPage` | `final readonly class` | A fetched page |
| `Phil\SocialCardsBundle\Application\LinkPreviews` | `final readonly class` | The previews, cached |
| `Phil\SocialCardsBundle\Application\Exception\UnknownCardTemplate` | `final class` | A template the configuration does not declare |
| `Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory` | `final readonly class` | The four layers of a page's metadata |
| `Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController` | `final readonly class` | `/social-cards/{fingerprint}.png` |
| `Phil\SocialCardsBundle\Infrastructure\Http\GuardedPageFetcher` | `final readonly class` | The head of a page, through phil/safe-http |
| `Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController` | `final readonly class` | `/social-cards/link-preview`, the preview of a pasted link |
| `Phil\SocialCardsBundle\Infrastructure\Store\DirectoryCardRequestStore` | `final readonly class` | The requests as JSON files |
| `Phil\SocialCardsBundle\Infrastructure\Twig\SocialMetaExtension` | `final readonly class` | `social_meta()` and `social_page()` |
| `Phil\SocialCardsBundle\Infrastructure\Twig\Components\LinkPreviewComponent` | component | `PhilSocialCards:LinkPreview` |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard` | `final readonly class` | The message: a fingerprint |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCardHandler` | `final readonly class` | Draws the card of a message |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\MessengerCardQueue` | `final readonly class` | The queue on the message bus |
| `Phil\SocialCardsBundle\Infrastructure\Console\CheckSocialTagsCommand` | command | `phil:social-cards:check` |
| `Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages` | `final readonly class` | A medium as a page's image |
| `Phil\SocialCardsBundle\Infrastructure\DependencyInjection\SocialCardsServices` | `final class` | The registration of the services |
| `Phil\SocialCardsBundle\Infrastructure\DependencyInjection\RequiredServicesPass` | compiler pass | The optional services without theirs, removed |

| File | Role |
|---|---|
| `config/routes.php` | The route of the cards |
| `config/link_preview_routes.php` | The route of the pasted links, imported apart |
| `assets/src/link_preview_controller.js` | The `link-preview` Stimulus controller, and `firstLink()` |
| `templates/components/LinkPreview.html.twig` | The template of the component |

| Service | Public |
|---|---|
| `SharePage`, `ShareMetadataFactory`, `SocialCards`, `SocialCardController`, `LinkPreviews`, `LinkPreviewController` | yes |
| `phil_social_cards.safe_http_client`, the `Phil\SafeHttp\SafeHttpClient` of the previews, `SocialCardsServices::HTTP_CLIENT` | no |
| The rest | no |
