# Reference

| Type | Kind | Role |
|---|---|---|
| `PhilContentWarningsBundle` | `final class`, `AbstractBundle` | The configuration and the services |
| `Application\CatalogueFactory` | `final readonly class` | Builds the catalogue from the configuration |
| `Application\CatalogueLabels` | `final readonly class` | Labels and choices in the current locale |
| `Application\WarningScreen` | `final readonly class` | The decision for the current reader |
| `Application\Contract\WarningPreferencesStore` | interface | Where the current reader's preferences are kept |
| `Application\Exception\PreferencesNotSaved` | `final class`, `RuntimeException` | Preferences with no one to save them for |
| `Infrastructure\Session\SessionWarningPreferences` | `final readonly class` | Preferences in the HTTP session |
| `Infrastructure\Memory\InMemoryWarningPreferences` | `final class` | Preferences in memory, reset between requests |
| `Infrastructure\Doctrine\ContentWarningsColumnType` | `final class`, DBAL `Type` | The column type `phil_content_warnings` |
| `Infrastructure\Form\ContentWarningsType` | `final class`, form type | Picks the warnings of a piece of content |
| `Infrastructure\Form\ContentWarningsTransformer` | `final class` | Warnings to theme keys and back |
| `Infrastructure\Form\WarningPreferencesType` | `final class`, form type | A reader's preferences |
| `Infrastructure\Form\WarningPreferencesTransformer` | `final class` | Preferences to displays and back |
| `Infrastructure\Twig\ContentWarningsExtension` | `final readonly class` | The Twig functions |
