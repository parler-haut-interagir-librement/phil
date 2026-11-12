<!-- phil-dev source-sha256=c8ad0d955600b9489111b1311b258ad434e289bc477497c4c06c148b02ad6e03 -->
# Installation

## Prérequis

| Paquet | Version | Sert à |
|---|---|---|
| PHP | 8.5 ou plus récent | |
| `phil/structured-data` | ^0.1 | Les nœuds, le graphe, le JSON-LD, le validateur |
| `phil/indexing-consent` | ^0.1 | Aucun JSON-LD pour une fiche retirée des moteurs de recherche |
| `phil/url` | ^0.1 | Les adresses de la configuration, lues comme le fait un navigateur |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | La configuration et les services |
| `symfony/http-foundation` | ^8.2 | La requête de chaque page |
| `symfony/console` | ^8.2 | `phil:structured-data:check` |
| `symfony/service-contracts` | ^3.6 | La réinitialisation entre les requêtes |
| `psr/event-dispatcher` | ^1.0 | `CollectStructuredData` |
| `twig/twig` | ^3.21, facultatif | `structured_data()` et `structured_data_add()` |
| `symfony/http-client` | ^8.2, facultatif | Vérifier une page à son adresse |
| `phil/menu-bundle` | ^0.1, facultatif | Le fil d'Ariane d'un menu |
| `phil/browser-security-bundle` | ^0.1, facultatif | Le *nonce* de script de sa politique de sécurité du contenu, avec `nonce.content_security_policy` |

## Enregistrement

```php
// config/bundles.php
return [
    // ...
    Phil\StructuredDataBundle\PhilStructuredDataBundle::class => ['all' => true],
];
```

Le bundle n'a besoin d'aucune route. Sans configuration, chaque page a sa `WebPage` et rien d'autre : le site et son
propriétaire s'activent dans la [configuration](configuration.md).

## La mise en page

Imprimez le graphe une seule fois, après que le contenu a ajouté ses nœuds — à la fin du corps de la mise en page de
base :

```twig
    {{ structured_data() }}
</body>
```

Un gabarit qui ajoute des nœuds — `contact_json_ld()`, `calendar_event_json_ld()`, `structured_data_add()` — est rendu
avant lui dans les blocs d'un gabarit enfant, puisque Twig produit le rendu des blocs de la mise en page à leur place.
