<!-- phil-dev source-sha256=de68924bc66ecdd76fb2e03bd1ae05102d5c8f4e128231351e767c4a1cb5214a -->
# phil/structured-data-bundle

**Un graphe schema.org par page d'un site Symfony, rassemblé à partir de la configuration, des autres paquets et des
gabarits, puis imprimé dans un seul élément de script JSON-LD.**

phil/structured-data-bundle intègre phil/structured-data à une application Symfony. Chaque requête a son graphe. La
configuration décrit le site comme un `WebSite`, son propriétaire comme une `Organization` ou l'une de ses sortes, et
chaque page comme une `WebPage` qui fait partie du site. Les fournisseurs des autres paquets et de l'application
ajoutent leurs nœuds, configurés automatiquement, tout comme les écouteurs de l'événement `CollectStructuredData`. Les
contrôleurs et les gabarits ajoutent les nœuds de la page elle-même : un événement, un produit, un livre. Le fil
d'Ariane d'un menu de phil/menu-bundle devient la `BreadcrumbList` de la page. Les nœuds au même `@id` se fusionnent :
un fournisseur qui ajoute le téléphone de l'organisation complète celle de la configuration. La fonction Twig
`structured_data()` imprime le tout dans un seul élément. C'est un bloc de données qu'une politique de sécurité du
contenu n'exécute pas : une page garde ainsi son cache partagé. Un gabarit peut lui passer un *nonce*, ou le site peut
prendre le *nonce* de script de phil/browser-security-bundle. Une page qui montre une fiche dont le titulaire l'a
retirée des moteurs de recherche, comme la nomme phil/indexing-consent, n'imprime aucun JSON-LD. Avec
phil/social-cards-bundle, la page est partagée avec le titre, la description et l'image de ses propres nœuds.
`phil:structured-data:check` lit le JSON-LD d'une page, à son adresse ou dans un fichier enregistré, et signale ce qui
lui manque.

Utilisez-le dans un site Symfony dont les pages doivent être lues par les moteurs de recherche, où l'identité du site,
le fil d'Ariane et le contenu viennent de paquets différents. Quand le bundle est installé, phil/contact-bundle et
phil/calendar-bundle versent leurs fiches et leurs événements dans le graphe au lieu d'imprimer leurs propres éléments.

Il se branche sur la pile de requêtes, le répartiteur d'événements, Twig et la console. Le graphe d'une page est
conservé par sa requête et réinitialisé par le noyau entre deux requêtes d'un *worker*.

## État

| Partie | État |
|---|---|
| Le graphe de chaque requête, ses fournisseurs et l'événement `CollectStructuredData` | Implémenté et testé |
| Le site, son propriétaire et la page, tirés de la configuration | Implémenté et testé |
| Le fil d'Ariane d'un menu de phil/menu-bundle | Implémenté et testé |
| `structured_data()`, `structured_data_add()`, `structured_data_about()` | Implémenté et testé |
| Le *nonce* donné, d'un attribut de requête ou de la politique de phil/browser-security-bundle | Implémenté et testé |
| Aucun JSON-LD pour une fiche retirée des moteurs de recherche (phil/indexing-consent) | Implémenté et testé |
| Le titre, la description et l'image de phil/social-cards-bundle lus dans le graphe | Implémenté et testé |
| Réinitialisation entre les requêtes, dans un *worker* | Implémenté et testé |
| `phil:structured-data:check` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'enregistrement. |
| [Configuration](doc/fr/configuration.md) | Le site, son propriétaire, la page, la langue, le fil d'Ariane, le *nonce*. |
| [Le graphe d'une page](doc/fr/graph.md) | D'où viennent les nœuds, dans quel ordre, comment un *worker* garde les pages séparées, et une fiche retirée des moteurs de recherche. |
| [Twig](doc/fr/twig.md) | `structured_data()`, `structured_data_add()` et `structured_data_about()`. |
| [Vérifier une page](doc/fr/check.md) | `phil:structured-data:check`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```yaml
# config/packages/phil_structured_data.yaml
phil_structured_data:
    website:
        name: Example Press
        search_url: 'https://example.com/recherche?q={search_term_string}'
    organization:
        name: Example Press
        logo: 'https://example.com/logo.png'
    breadcrumb:
        menu: main
```

```twig
{# templates/base.html.twig, at the end of the body #}
{{ structured_data() }}
```

```php
use Phil\StructuredData\Contract\NodeCollector;
use Phil\IndexingConsent\IndexingPolicy;
use Phil\StructuredData\Schema;

public function show(Tournament $tournament, NodeCollector $graph, Request $request): Response
{
    $graph->add(Schema::event($tournament->name(), $tournament->start(), $tournament->hall()));
    // A tournament its organiser withdrew from search engines prints no JSON-LD.
    $request->attributes->set(IndexingPolicy::REQUEST_ATTRIBUTE, $tournament->indexing());
    // …
}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, `phil/structured-data`, `phil/indexing-consent` et `phil/url`. Twig pour les
fonctions, `symfony/http-client` pour vérifier une page à son adresse, `phil/menu-bundle` pour le fil d'Ariane,
`phil/browser-security-bundle` pour le *nonce* de sa politique de sécurité du contenu.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Example Press; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
