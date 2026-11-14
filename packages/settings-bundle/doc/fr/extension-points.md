<!-- phil-dev source-sha256=7d6989df2530d04152bcc40d6beefe5c5c3c9b4312610e7523eb14674a7630e2 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

## Ports écrits par l'application

### `CurrentUser`

`Phil\SettingsBundle\Application\Contract\CurrentUser` (`current_user`) : pour qui est la requête courante, par
l'identifiant sous lequel ses réglages sont enregistrés. Par défaut, l'identifiant d'utilisateur de Symfony Security.

| Méthode | Contrat |
|---|---|
| `id(): ?string` | L'identifiant de l'utilisateur, au plus 190 octets sans espace ni caractère de contrôle ; `null` pour un visiteur |

### `VisitorPreferences`

`Phil\SettingsBundle\Application\Contract\VisitorPreferences` : les valeurs que conserve un visiteur, par défaut le
témoin des préférences. Créez-lui un alias pour les conserver ailleurs. Le bundle vérifie de nouveau chaque valeur
avant de l'utiliser.

| Méthode | Contrat |
|---|---|
| `values(): array<string, bool\|string>` | Les valeurs du visiteur pour les réglages conservés pour les visiteurs, par clé |

### `SettingRepository`

`Phil\SettingsBundle\Domain\Contract\SettingRepository` : l'endroit où les valeurs sont conservées, DBAL par défaut.
Une implémentation ne conserve que ce qu'on lui donne, encodé, et fournit les valeurs d'un sujet en une lecture.

| Méthode | Contrat |
|---|---|
| `values(Subject $subject): array<string, string>` | Les valeurs encodées, par clé |
| `save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void` | Écrit et retire d'un seul coup |
| `erase(Subject $subject): list<string>` | Retire chaque valeur et donne les clés retirées, triées |

### `SettingMigrationStore`

`Phil\SettingsBundle\Domain\Contract\SettingMigrationStore` : ce que les migrations lisent et écrivent d'un sujet à
l'autre, implémenté par les stockages DBAL et en mémoire. Un stockage de l'application l'implémente à côté de
`SettingRepository`, et en fait un alias.

| Méthode | Contrat |
|---|---|
| `appliedMigrations(): list<string>` | Les versions appliquées |
| `valuesOf(string $key): list<StoredValue>` | Chaque valeur conservée de la clé, quel que soit son sujet |
| `applyMigration(string $version, list<StoredValue> $changes, DateTimeImmutable $at): void` | Écrit les valeurs, retire celles dont la valeur est nulle, et enregistre la version, d'un seul coup ; une version déjà enregistrée échoue |

### `SettingMigration`

`Phil\SettingsBundle\Domain\Contract\SettingMigration` : un changement versionné des valeurs conservées ; voir
[Migrations](migrations.md).

| Méthode | Contrat |
|---|---|
| `steps(): list<Step>` | Les étapes, dans l'ordre où elles s'exécutent |

## Déclarations

| Pour | Faites |
|---|---|
| Déclarer un réglage ou une section dans l'application | `phil_settings.settings` et `phil_settings.sections`, ou `#[AsSetting]` et `#[AsSettingSection]` sur une classe configurée automatiquement |
| Les déclarer dans un paquet | Ajoutez au début la configuration de `phil_settings` |
| Placer une section ou un réglage parmi d'autres | `before` et `after`, jamais un nombre |
| Écrire un choix sur l'élément racine de la page | `html_attribute` |
| Conserver un booléen ou un choix pour les visiteurs | `anonymous` |
| Migrer les valeurs conservées d'une déclaration qui a évolué | `#[AsSettingMigration]` sur une `SettingMigration`, ou l'étiquette `phil_settings.migration` avec `version`, `before` et `after` |

## Services qu'utilise l'application

| Service | Pour |
|---|---|
| `Phil\SettingsBundle\Application\Settings` | Lire et écrire des valeurs |
| `Phil\SettingsBundle\Application\SettingCatalog` | Les sections et les réglages déclarés, dans leur ordre |
| `Phil\SettingsBundle\Application\DocumentAttributes` | Les attributs de l'élément racine |
| `Phil\SettingsBundle\Application\Migration\SettingMigrator` | Les migrations en attente, appliquées ou essayées à blanc |
| `Phil\SettingsBundle\Infrastructure\Notifications\SettingsNotificationPreferences` | Les préférences du bundle des notifications |

## Événements

`Phil\SettingsBundle\Application\Event\SettingsChanged`, réparti sur le répartiteur d'événements après chaque
changement ou effacement, avec des identifiants seulement : voir [Réglages](settings.md#événements).

## Gabarits et traductions

| Pour | Faites |
|---|---|
| Afficher les pages dans la mise en page de l'application | `template`, qui inclut `@PhilSettings/_settings.html.twig` |
| Changer un texte | Surchargez une clé du domaine `phil_settings`, dans `translations/phil_settings.<locale>.php` ou en YAML |
| Traduire les réglages de l'application | Leurs clés `label`, `help` et `<key>.choices.<choice>`, dans leur `translation_domain` |
