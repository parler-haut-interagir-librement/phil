<!-- phil-dev source-sha256=81fae583ad4dc9ed93fe2ea7ed8581ab72bcd0c15273b0e4ca7b05653f179768 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `Key` | `final class` | La règle des clés de thèmes et de catégories |
| `LocalizedText` | `final readonly class` | Un texte par locale, lu avec des replis |
| `Category` | `final readonly class` | Un groupe de thèmes |
| `Theme` | `final readonly class` | Un thème pour lequel un contenu peut recevoir un avertissement |
| `Catalogue` | `final readonly class` | Les thèmes et les catégories, dans l'ordre |
| `CatalogueLoader` | `final class` | Construit un catalogue à partir d'une définition, ou en applique une à un catalogue |
| `BaseCatalogue` | `final class` | La liste de base en six langues |
| `Severity` | enum à valeurs de chaîne | L'intensité d'un thème dans un contenu |
| `ContentWarning` | `final readonly class` | Un avertissement : un thème, une sévérité, une note |
| `ContentWarnings` | `final readonly class` | Les avertissements d'un contenu, conservés sous forme de tableau |
| `Display` | enum à valeurs de chaîne | Afficher, replier ou masquer |
| `Preference` | `final readonly class` | Un affichage à partir d'une sévérité |
| `WarningPreferences` | `final readonly class` | Les préférences d'un lecteur, conservées sous forme de tableau |
| `WarningMatcher` | `final readonly class` | Décide pour un contenu et un lecteur |
| `Decision` | `final readonly class` | L'affichage d'un contenu et les avertissements qui l'ont donné |
| `Exception\ContentWarningsException` | interface | Chaque exception de la bibliothèque |
| `Exception\InvalidCatalogue` | `final class`, `InvalidArgumentException` | Une définition, une clé ou un libellé qui enfreint une règle |
| `Exception\InvalidWarning` | `final class`, `InvalidArgumentException` | Un avertissement, une liste ou une préférence qui enfreint une règle |
| `Exception\UnreadableWarnings` | `final class`, `UnexpectedValueException` | Des avertissements ou des préférences stockés qu'il est impossible de relire |
