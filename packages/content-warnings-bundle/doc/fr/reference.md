<!-- phil-dev source-sha256=c4a0da48ad343e94eac1537f84696d387a6fc5863b903082be0bb4730d71d501 -->
# Référence

| Type | Sorte | Rôle |
|---|---|---|
| `PhilContentWarningsBundle` | `final class`, `AbstractBundle` | La configuration et les services |
| `Application\CatalogueFactory` | `final readonly class` | Construit le catalogue à partir de la configuration |
| `Application\CatalogueLabels` | `final readonly class` | Les libellés et les choix dans la locale courante |
| `Application\WarningScreen` | `final readonly class` | La décision pour le lecteur courant |
| `Application\Contract\WarningPreferencesStore` | interface | L'endroit où les préférences du lecteur courant sont conservées |
| `Application\Exception\PreferencesNotSaved` | `final class`, `RuntimeException` | Des préférences sans personne pour qui les enregistrer |
| `Infrastructure\Session\SessionWarningPreferences` | `final readonly class` | Les préférences dans la session HTTP |
| `Infrastructure\Memory\InMemoryWarningPreferences` | `final class` | Les préférences en mémoire, réinitialisées entre les requêtes |
| `Infrastructure\Doctrine\ContentWarningsColumnType` | `final class`, `Type` DBAL | Le type de colonne `phil_content_warnings` |
| `Infrastructure\Form\ContentWarningsType` | `final class`, type de formulaire | Choisit les avertissements d'un contenu |
| `Infrastructure\Form\ContentWarningsTransformer` | `final class` | Des avertissements aux clés de thèmes, et inversement |
| `Infrastructure\Form\WarningPreferencesType` | `final class`, type de formulaire | Les préférences d'un lecteur |
| `Infrastructure\Form\WarningPreferencesTransformer` | `final class` | Des préférences aux affichages, et inversement |
| `Infrastructure\Twig\ContentWarningsExtension` | `final readonly class` | Les fonctions Twig |
