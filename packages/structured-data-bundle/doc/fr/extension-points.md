<!-- phil-dev source-sha256=850612f7941474fd524f5a45dd160a76d2392d1af680eef9329a623aed07db71 -->
# Points d'extension

## `StructuredDataProvider`

`Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider` — fournit des nœuds à chaque page. Configuré
automatiquement, avec l'étiquette `phil_structured_data.provider`, après `DefaultNodes`.

| Méthode | Contrat |
|---|---|
| `nodes(PageContext $page): iterable<Node>` | Les nœuds que ce fournisseur connaît pour la page ; demandés une fois par page, à la première impression du graphe |

Un fournisseur qui doit s'exécuter avant ou après un autre l'indique sur son étiquette, avec `before` ou `after`.

## `ScriptNonceProvider`

`Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider` — le *nonce* de la politique de sécurité du
contenu de la page, pour un site qui en veut un sur chaque élément JSON-LD. Aucun n'est enregistré par défaut : un
élément JSON-LD est un bloc de données qu'une politique n'exécute pas, et un *nonce* écarterait la page des caches
partagés. Le bundle enregistre `RequestAttributeNonce` avec `nonce.request_attribute`, et `ContentSecurityPolicyNonce`
avec `nonce.content_security_policy`. Configuré automatiquement, avec l'étiquette
`phil_structured_data.nonce_provider`.

| Méthode | Contrat |
|---|---|
| `scriptNonce(): ?string` | Le *nonce* de la page courante en texte base64, ou `null` quand ce fournisseur n'en a aucun |

## `CollectStructuredData`

`Phil\StructuredDataBundle\Application\Event\CollectStructuredData` — réparti une fois par page, après les
fournisseurs. Un écouteur lit `$event->page` et appelle `$event->add(Node ...)`.

## `NodeCollector`

`Phil\StructuredData\Contract\NodeCollector`, alias de `PageStructuredData` : ce que demande un contrôleur, un service
ou un autre bundle pour ajouter les nœuds de la page. Un bundle qui ne fait qu'ajouter des nœuds dépend de
phil/structured-data, jamais de ce bundle, et demande le collecteur avec `NULL_ON_INVALID_REFERENCE`.

## La portée d'une page

Une fiche de l'application implémente `HasIndexingConsent` de phil/indexing-consent ; son contrôleur met son
`Indexing` sur la requête sous `IndexingPolicy::REQUEST_ATTRIBUTE`, ou appelle `PageStructuredData::about()`, et la
page n'imprime aucun JSON-LD quand la fiche est retirée des moteurs de recherche. Un bundle qui montre des fiches
définit l'attribut de requête : il dépend de phil/indexing-consent, jamais de ce bundle.

## Configuration et services

| Quoi | Comment |
|---|---|
| Le site, son propriétaire, la page | La [configuration](configuration.md) |
| Les règles du validateur qu'utilise la commande | Remplacez le service `Phil\StructuredData\Validation\Validator`, par exemple par `new Validator()->withRules(...)` |
| Le client HTTP de la commande | Le service `http_client` |

## Ce dont le bundle dépend

Les services `request_stack` et `event_dispatcher`, tous deux facultatifs ; les extensions par attributs de Twig ;
l'étiquette `kernel.reset` ; le `MenuExtension` de phil/menu-bundle pour le fil d'Ariane ; le `ShareMetadataSource` de
phil/social-cards-bundle, que `PageShareMetadata` implémente quand ce paquet est installé ; `Indexing`,
`IndexingPolicy` et `HasIndexingConsent` de phil/indexing-consent ; le `ContentSecurityPolicyContext` de
phil/browser-security-bundle, avec `nonce.content_security_policy`.
