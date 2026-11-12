<!-- phil-dev source-sha256=2474e74a0b39c268b812213710f0b0e4c4af98f8c1ba622fc47023aa1a92e741 -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\SocialCardsBundle\PhilSocialCardsBundle` | bundle | La configuration et les services |
| `Phil\SocialCardsBundle\Application\SharePage` | `final class`, réinitialisée | Les valeurs que fixe la requête |
| `Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource` | interface | Les valeurs d'un modèle |
| `Phil\SocialCardsBundle\Application\CardTemplates` | `final class` | Les gabarits de la configuration, lus une fois |
| `Phil\SocialCardsBundle\Application\CardRequest` | `final readonly class` | Ce à partir de quoi une carte est dessinée |
| `Phil\SocialCardsBundle\Application\Contract\CardRequestStore` | interface | Les demandes par empreinte |
| `Phil\SocialCardsBundle\Application\Contract\CardQueue` | interface | Les cartes dessinées hors de la requête |
| `Phil\SocialCardsBundle\Application\SocialCards` | `final readonly class` | L'empreinte et l'image de la carte d'une page |
| `Phil\SocialCardsBundle\Application\Contract\PageFetcher` | interface | L'en-tête d'une page d'un autre site |
| `Phil\SocialCardsBundle\Application\FetchedPage` | `final readonly class` | Une page récupérée |
| `Phil\SocialCardsBundle\Application\LinkPreviews` | `final readonly class` | Les aperçus, en cache |
| `Phil\SocialCardsBundle\Application\Exception\UnknownCardTemplate` | `final class` | Un gabarit que la configuration ne déclare pas |
| `Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory` | `final readonly class` | Les quatre couches des métadonnées d'une page |
| `Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController` | `final readonly class` | `/social-cards/{fingerprint}.png` |
| `Phil\SocialCardsBundle\Infrastructure\Http\GuardedPageFetcher` | `final readonly class` | L'en-tête d'une page, par phil/safe-http |
| `Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController` | `final readonly class` | `/social-cards/link-preview`, l'aperçu d'un lien collé |
| `Phil\SocialCardsBundle\Infrastructure\Store\DirectoryCardRequestStore` | `final readonly class` | Les demandes en fichiers JSON |
| `Phil\SocialCardsBundle\Infrastructure\Twig\SocialMetaExtension` | `final readonly class` | `social_meta()` et `social_page()` |
| `Phil\SocialCardsBundle\Infrastructure\Twig\Components\LinkPreviewComponent` | composant | `PhilSocialCards:LinkPreview` |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard` | `final readonly class` | Le message : une empreinte |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCardHandler` | `final readonly class` | Dessine la carte d'un message |
| `Phil\SocialCardsBundle\Infrastructure\Messenger\MessengerCardQueue` | `final readonly class` | La file d'attente sur le bus de messages |
| `Phil\SocialCardsBundle\Infrastructure\Console\CheckSocialTagsCommand` | commande | `phil:social-cards:check` |
| `Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages` | `final readonly class` | Un média comme image d'une page |
| `Phil\SocialCardsBundle\Infrastructure\DependencyInjection\SocialCardsServices` | `final class` | L'enregistrement des services |
| `Phil\SocialCardsBundle\Infrastructure\DependencyInjection\RequiredServicesPass` | passe de compilation | Les services facultatifs sans les leurs, retirés |

| Fichier | Rôle |
|---|---|
| `config/routes.php` | La route des cartes |
| `config/link_preview_routes.php` | La route des liens collés, importée à part |
| `assets/src/link_preview_controller.js` | Le contrôleur Stimulus `link-preview`, et `firstLink()` |
| `templates/components/LinkPreview.html.twig` | Le gabarit du composant |

| Service | Public |
|---|---|
| `SharePage`, `ShareMetadataFactory`, `SocialCards`, `SocialCardController`, `LinkPreviews`, `LinkPreviewController` | oui |
| `phil_social_cards.safe_http_client`, le `Phil\SafeHttp\SafeHttpClient` des aperçus, `SocialCardsServices::HTTP_CLIENT` | non |
| Le reste | non |
