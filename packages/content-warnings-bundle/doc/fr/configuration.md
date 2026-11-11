<!-- phil-dev source-sha256=e779cfa8a6708e8b92a1f499a5d847dc6fd8cbf52ec01fbb6b55717ed222f5b4 -->
# Configuration

```yaml
# config/packages/phil_content_warnings.yaml
phil_content_warnings:
    base_catalogue: true
    catalogue_files:
        - '%kernel.project_dir%/config/content_warnings.php'
    catalogue:
        categories:
            venues: { label: { en: Venues, fr: Lieux } }
        themes:
            crowds: { category: venues, label: { en: Crowds, fr: Foules } }
        remove: [clowns]
    default_display: fold
    fallback_locales: [en]
    preferences_store: ~
```

| Clé | Valeur par défaut | Signification |
|---|---|---|
| `base_catalogue` | `true` | Part de la liste de base de la bibliothèque ; `false` part d'un catalogue vide |
| `catalogue_files` | `[]` | Des fichiers PHP ou JSON de définitions de catalogue, appliqués dans l'ordre après la liste de base |
| `catalogue` | `{}` | Une définition appliquée en dernier |
| `default_display` | `fold` | Ce qui arrive à un contenu visé par un avertissement pour un lecteur qui n'a rien choisi : `show`, `fold` ou `hide` |
| `fallback_locales` | `[en]` | Les locales dans lesquelles un libellé est lu, dans l'ordre, lorsqu'il n'en a aucun dans la locale courante |
| `preferences_store` | `~` | L'identifiant de service du `WarningPreferencesStore` ; le magasin en session par défaut |

Une définition a les clés `replace`, `categories`, `themes`, `remove` et `remove_categories`, telles que
`phil/content-warnings` les décrit dans sa documentation du catalogue. Les paramètres des chemins de fichiers sont
résolus.

Le catalogue est construit une fois, à la compilation du conteneur : un thème inconnu à retirer, un thème sans
catégorie, une clé mal formée ou un fichier manquant lève `InvalidCatalogue` et arrête la construction ; un catalogue
brisé n'atteint donc jamais une requête. À l'exécution, le service `Phil\ContentWarnings\Catalogue` est construit une
fois par conteneur et il est immuable, donc sûr dans un *worker*.
