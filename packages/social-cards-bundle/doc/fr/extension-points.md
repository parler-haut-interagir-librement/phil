<!-- phil-dev source-sha256=27b4985eb34c0f8afb32e9f8217f9447add00f023f4611f1f8fddcc41464a176 -->
# Points d'extension

## `ShareMetadataSource`

`Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource` — les valeurs d'une page tirées d'un modèle de
l'application, autoconfiguré avec l'étiquette `phil_social_cards.source`.

| Méthode | Contrat |
|---|---|
| `values(): array` | Les clés de `ShareMetadata::KEYS`, `card_template` et `card`, ou un tableau vide ; une valeur nulle est une valeur absente |

## `CardRequestStore`

`Phil\SocialCardsBundle\Application\Contract\CardRequestStore` — ce à partir de quoi chaque carte est dessinée, par
empreinte ; l'alias pointe vers `DirectoryCardRequestStore`.

| Méthode | Contrat |
|---|---|
| `save(string $fingerprint, CardRequest $request): bool` | Garde la demande ; indique si elle n'était pas encore gardée |
| `find(string $fingerprint): ?CardRequest` | La demande, ou null |

## `CardQueue`

`Phil\SocialCardsBundle\Application\Contract\CardQueue` — fait dessiner une carte hors de la requête ;
`MessengerCardQueue` avec Messenger, aucune sinon.

| Méthode | Contrat |
|---|---|
| `queue(string $fingerprint): void` | Met l'empreinte en file d'attente, et rien d'autre |

## `PageFetcher`

`Phil\SocialCardsBundle\Application\Contract\PageFetcher` — récupère l'en-tête d'une page d'un autre site ; l'alias
pointe vers `GuardedPageFetcher`. Un remplaçant garde ses protections.

| Méthode | Contrat |
|---|---|
| `fetch(string $url): ?FetchedPage` | Le HTML jusqu'à sa borne, l'adresse après les redirections et le jeu de caractères déclaré, ou null |

## Le client protégé

Le service `phil_social_cards.safe_http_client` est un `Phil\SafeHttp\SafeHttpClient` avec les limites de `preview`.
Une application qui associe des hôtes à des adresses, ou qui lit par un mandataire, décore `http_client` : le client
enveloppe dans sa propre garde ce que `http_client` est.

## Services sur lesquels le bundle s'appuie

| Service | Sert à |
|---|---|
| `Phil\SocialCards\Contract\CardStore` | Les cartes ; un alias de `DirectoryCardStore` |
| `request_stack`, `router` | L'adresse et la locale de la page, l'adresse de la carte |
| `messenger.default_bus` | La file d'attente, quand il existe |
| `http_client` | Le transport du client protégé, sinon un transport à lui |
| `limiter.<name>` | La limite de débit des liens collés, quand `preview.limiter` le nomme |
| `cache.app` | Les aperçus, quand il existe |
| `http_kernel`, `router.request_context` | La commande, pour un chemin de l'application |
| `Phil\MediaBundle\Infrastructure\Twig\MediaExtension` | Un média comme image, quand il existe |
| `Phil\HttpCacheTagsBundle\Application\ResponseTags` | Les étiquettes des cartes, quand il existe |
