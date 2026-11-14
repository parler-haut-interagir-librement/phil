<!-- phil-dev source-sha256=b7e575cf74cc5887d7fc58064e74cb3a3d5dd572796861a8b18f01acbb5ac029 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Setting\SettingDefinition` | Un réglage : clé, type, valeur par défaut, règles, portée, section, libellés, ordre ; vérifie, encode et décode une valeur |
| `Domain\Setting\SectionDefinition` | Une section : clé, libellés, ordre |
| `Domain\Setting\SettingType` | `boolean`, `integer`, `text`, `choice`, `choices` |
| `Domain\Setting\Scope` | `user`, `site` |
| `Domain\Setting\Keys` | Les formes des clés, des choix et des attributs |
| `Domain\Setting\Ordering` | L'ordre des déclarations, `before` et `after` |
| `Domain\ValueObject\Subject` | Un utilisateur, par identifiant, ou le site |
| `Domain\Contract\SettingRepository` | L'endroit où les valeurs sont conservées |
| `Domain\Contract\SettingMigration`, `SettingMigrationStore` | Une migration des valeurs conservées, et ce qu'elle lit et écrit |
| `Domain\Migration\Step`, `StepKind`, `StoredValue` | Les étapes d'une migration — renommer, convertir, valeur par défaut changée, retirer — et une valeur conservée telle qu'elle la lit et l'écrit |
| `Domain\Attribute\AsSetting`, `AsSettingSection`, `AsSettingMigration` | Les déclarations par attribut |
| `Domain\Exception\SettingsException`, `InvalidSetting`, `InvalidSettingValue`, `UnknownSetting` | Les exceptions du bundle |

## Application

| Classe | Rôle |
|---|---|
| `Settings` | Lecture typée avec la valeur par défaut, aperçu, écriture, restauration, oubli ; réinitialisé entre les requêtes |
| `SettingPreview` | Un réglage avant un enregistrement : sa valeur actuelle, la valeur proposée, et si elles diffèrent |
| `Migration\SettingMigrator`, `Migration\MigrationReport` | Les migrations appliquées une fois, dans leur ordre, ou essayées à blanc, et ce que chacune a changé |
| `SettingCatalog` | Les déclarations, dans leur ordre |
| `DocumentAttributes` | Les attributs de l'élément racine de la page |
| `BuiltInSettings` | Les sections du bundle, les préférences d'affichage et les préférences de notification |
| `Visitor\PreferenceCookieFormat` | Le témoin des préférences d'un visiteur, lu et écrit |
| `Event\SettingsChanged` | Un changement, par identifiants |
| `Contract\CurrentUser`, `Contract\VisitorPreferences` | Les ports : voir [Points d'extension](extension-points.md) |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Doctrine\DbalSettingRepository`, `SettingTables`, `SettingSchemaListener` | Le stockage DBAL |
| `Memory\InMemorySettingRepository` | Le stockage en mémoire, pour les tests |
| `Security\SecurityCurrentUser` | L'utilisateur courant tiré de Symfony Security |
| `Http\SettingsController`, `Http\PreferenceCookie`, `Http\Routes` | Les pages et le témoin |
| `Form\SettingsForm` | Le formulaire d'une section |
| `Twig\SettingsExtension` | `phil_setting()` et `phil_document_attributes()` |
| `Notifications\SettingsNotificationPreferences` | Les préférences du bundle des notifications |
| `Privacy\SettingsPrivacy` | L'exportation et l'effacement du bundle de la vie privée |
| `Console\MigrateSettingsCommand` | `phil:settings:migrate`, avec `--dry-run` |
| `DependencyInjection\SettingsServices`, `DeclaredSettingsPass`, `SettingMigrationsPass` | Les services, les déclarations par attribut, et les migrations par version et par ordre |

## Fichiers

| Fichier | Rôle |
|---|---|
| `config/routes.php`, `config/routes/site.php` | Les préférences et les réglages du site |
| `templates/settings.html.twig`, `templates/_settings.html.twig` | La page et sa partie |
| `translations/phil_settings.en.php`, `phil_settings.fr.php` | Les textes, en anglais et en français |
| `assets/src/document_controller.js` | Le contrôleur Stimulus des préférences d'affichage |
