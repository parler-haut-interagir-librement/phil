<!-- phil-dev source-sha256=c13b79e1af206352aee526a215e8d77390e48241e3550d562268cbb490cd437a -->
# Le graphe d'une page

`PageStructuredData` contient le graphe de la page en cours de service. C'est le `NodeCollector` du conteneur : tout
service qui décrit quelque chose sur une page demande `Phil\StructuredData\Contract\NodeCollector` et y ajoute son
nœud.

## D'où viennent les nœuds

| Source | Quand | Exemple |
|---|---|---|
| `DefaultNodes`, tiré de la configuration | En premier, une fois par page | L'`Organization`, le `WebSite`, la `WebPage` |
| Chaque `StructuredDataProvider` | Après lui, une fois par page, dans l'ordre de leurs contraintes `before` et `after` | La `BreadcrumbList` d'un menu, le téléphone de l'organisation |
| Les écouteurs de `CollectStructuredData` | Après les fournisseurs | Un nœud qui dépend de la route |
| `NodeCollector::add()` | Chaque fois qu'un contrôleur, un gabarit ou un paquet en ajoute un | L'`Event` de la page d'un événement, une fiche de contact |

Les fournisseurs et les écouteurs sont interrogés la première fois que le graphe est imprimé ; ce qui est ajouté
ensuite atteint quand même le graphe, s'il est imprimé de nouveau. Les nœuds se fusionnent par `@id` à la place du
premier, et une valeur donnée plus tard remplace la précédente : ce qu'ajoute la page l'emporte sur ce que donnent les
fournisseurs, qui l'emporte sur la configuration. Un fournisseur qui donne
`Node::of('Organization', ['telephone' => '+15145550100'], $page->siteId('organization'))` complète l'organisation de
la configuration.

## La page

Chaque fournisseur reçoit un `PageContext` :

| Membre | Contient |
|---|---|
| `$baseUrl` | L'adresse du site, terminée par `/` : `base_url`, sinon le schéma, l'hôte et le chemin de base de la requête |
| `$url` | L'adresse de la page sans sa chaîne de requête : l'adresse de base et le chemin de la requête |
| `$locale` | La langue de la requête comme étiquette BCP 47, `fr-CA` ; `null` pour une locale qui n'en est pas une |
| `$request` | La requête principale |
| `siteId($fragment)` | `https://example.com/#organization` |
| `pageId($fragment)` | `https://example.com/jeux/tournoi#event` |

Les identifiants que donne le bundle sont `#website` et `#organization` à l'adresse de base, `#webpage` et
`#breadcrumb` à l'adresse de la page. Un paquet qui fournit le contenu principal d'une page le nomme d'après sa sorte à
l'adresse de la page, `#event`, `#book`, `#code`, pour qu'un autre paquet puisse y faire référence.

## Requêtes, fragments et workers

Une page correspond à la requête principale. Un fragment rendu par une sous-requête — un ESI, un `render()` — s'ajoute
au graphe de sa page. Ce qu'une page a rassemblé est conservé par sa requête, dans une table faible, et disparaît donc
avec elle. Hors d'une requête — une commande, un *handler* de message qui produit le rendu d'un gabarit — un seul
graphe est conservé.

`PageStructuredData` porte l'étiquette `kernel.reset` : entre deux requêtes d'un *worker*, celui de FrankenPHP ou un
autre, le noyau oublie chaque graphe. Aucun nœud de la page d'un visiteur ne peut atteindre celle du suivant.

## Une fiche retirée des moteurs de recherche

Une page qui montre une fiche dont le titulaire l'a retirée des moteurs de recherche — un `HasIndexingConsent` de
phil/indexing-consent dont l'`Indexing` est `NotIndexed` ou `Unlisted` — n'imprime aucune donnée structurée :
`structured_data()` donne une chaîne vide et ne demande aucun *nonce*. La page est aussi étroite que la plus stricte
de :

- les fiches nommées par `PageStructuredData::about()`, ou par `structured_data_about()` dans un gabarit ; un objet
  sans le contrat est listé ;
- l'`Indexing` que porte sa requête principale sous `IndexingPolicy::REQUEST_ATTRIBUTE`, défini par le contrôleur qui
  connaît la fiche. Un `Indexing`, ou sa valeur en texte, est lu ; toute autre valeur est lue comme `Unlisted`, pour
  qu'une erreur ne remette jamais en vue une fiche retirée.

| Méthode | Donne |
|---|---|
| `about(object ...$records): void` | Nomme les fiches que montre la page |
| `indexing(): Indexing` | La portée de la page |
| `isPrintable(): bool` | Si `Reader::StructuredData` est permis |

Le graphe est tout de même construit, pour les métadonnées de partage de la page, que les moteurs de recherche ne
lisent pas. Une fiche n'élargit jamais la page : une fois qu'une fiche retirée est nommée, une fiche listée ne remet
pas le JSON-LD. La portée est réinitialisée avec le graphe, entre deux requêtes d'un *worker*.

## Partager une page

Quand phil/social-cards-bundle est installé, `PageShareMetadata` est une source de ses métadonnées de partage,
configurée automatiquement : une page est partagée avec ce que son graphe dit d'elle.

| Clé | Lue dans |
|---|---|
| `title` | `headline`, puis `name` |
| `description` | `description` |
| `image` | `image`, puis `primaryImageOfPage` : une adresse absolue, ou une `ImageObject` donnée ou référencée |

L'entité principale est lue en premier — la `mainEntity` de la `WebPage` de la page, ou sinon le nœud doté d'un
`mainEntityOfPage` —, puis la `WebPage`, pour ce qui manque à l'entité. Une page dont les nœuds ne donnent aucun des
trois n'ajoute rien. Une image qu'une page partagée ne peut pas nommer, comme une adresse relative, est omise plutôt
que de bloquer la page.

Cette source passe avant ce que la requête définit avec `SharePage` ou `social_page()` et ce qu'un gabarit passe à
`social_meta()` : ceux-ci ont le dernier mot. Le graphe lu est celui que la page imprime, pour que ses fournisseurs ne
soient interrogés qu'une fois par page.
