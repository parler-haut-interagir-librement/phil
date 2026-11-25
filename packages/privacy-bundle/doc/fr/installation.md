<!-- phil-dev source-sha256=8d6e60d6499b8ce7c6331d0a0c8db2111f59a4bc4f806cda8af69e806a1af416 -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le plancher déclaré. Le code s'appuie sur des fonctions récentes du langage comme `array_any()` et les constantes de classe typées, et les tests sur l'accès aux membres de `new` sans parenthèses de PHP 8.4+. |
| `monolog/monolog` | `^3.0` | `RedactingProcessor` implémente `Monolog\Processor\ProcessorInterface` et travaille sur le `Monolog\LogRecord` immuable de Monolog 3. |
| `symfony/config` | `^8.2` | L'arbre de configuration (`DefinitionConfigurator`, `InvalidConfigurationException`). |
| `symfony/dependency-injection` | `^8.2` | La classe du bundle étend `AbstractBundle`. |
| `symfony/workflow`, `symfony/lock` | `^8.2` | La suppression de compte, une seule exécution par personne. |
| `symfony/messenger`, `symfony/clock` | `^8.2` | Des messages qui ne portent que des identifiants, et les dates d'une demande. |
| `symfony/http-kernel`, `symfony/http-foundation`, `symfony/routing`, `twig/twig` | `^8.2`, `^3.21` | Les pages et la bannière. |
| `symfony/security-core`, `symfony/security-http`, `symfony/security-csrf` | `^8.2` | Le membre, le mode sudo et les jetons de formulaire sans état. |
| `ext-sodium`, `symfony/filesystem` | `*`, `^8.2` | Les exports chiffrés. |
| `phil/url` | `^0.1@dev` | `CookielessVideo`, les lecteurs sans témoins de YouTube et de Vimeo dans les cadres tiers. |

Le bundle n'exige **pas** `symfony/monolog-bundle`, mais le processeur n'atteint vos journaux que par l'étiquette
`monolog.processor`, que lit MonologBundle. Sans MonologBundle, le service est défini et jamais utilisé ; vous pouvez
encore l'ajouter à la main à un `Monolog\Logger` (voir [log-redaction.md](log-redaction.md)).

Les commandes et l'anonymisation demandent des paquets facultatifs, suggérés par le bundle et installés par
l'application :

| Paquet | Contrainte | Pour |
|---|---|---|
| `symfony/console` | `^8.2` | Chaque commande du bundle. |
| `symfony/scheduler` | `^8.2` | Le calendrier `phil_privacy`. |
| `doctrine/dbal` | `^4.5` | La connexion anonymisée, et les tables des suppressions, des preuves, des demandes sans compte, des incidents et du consentement aux courriels suivis, leurs instants dans des colonnes `datetime_utc_immutable`. |
| `doctrine/orm` | `^3.7` | Tirer le plan d'anonymisation de `#[Anonymize]` et de `#[PersonalData]` sur les entités. |
| `symfony/asset-mapper`, `symfony/stimulus-bundle` | `^8.2`, `^3.7` | Les contrôleurs Stimulus `consent` et `embed`, servis sans étape de compilation : le bundle ajoute son `assets/src` aux chemins d'AssetMapper sous `@phil/privacy-bundle`. |

Sauvegarder la base et restaurer une copie à anonymiser est le travail de `phil/backup-bundle` ; voir
[anonymization.md](anonymization.md).

La couche du domaine (`Domain\`) ne dépend que de PHP : l'attribut, les caviardeurs et le modèle de conservation
s'utilisent dans n'importe quel code PHP, avec ou sans Symfony.

## Installer

```bash
composer require phil/privacy-bundle
```

Le paquet déclare `"minimum-stability": "dev"` et un alias de branche `dev-main` → `0.1.x-dev` ; tant qu'aucune version
étiquetée n'existe, demandez-le comme `phil/privacy-bundle:^0.1@dev`.

## Enregistrer le bundle

Avec Symfony Flex, le bundle est enregistré tout seul. Sinon, ajoutez-le à `config/bundles.php` :

```php
return [
    // ...
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Phil\PrivacyBundle\PhilPrivacyBundle::class => ['all' => true],
];
```

Enregistrez-le dans chaque environnement : le caviardage des journaux compte autant en `dev` et en `test` qu'en `prod`,
parce que ces journaux sont copiés dans des billets et des artefacts de CI.

## Passer à la version 0.4

Chaque clé que le bundle dérive du secret de l'application (`kernel.secret`) est désormais son HKDF-SHA-256 sous une
étiquette à elle (voir [Modèle de menaces](threat-model.md#les-clés)). Ce qui a été scellé ou signé sous les clés de la
version 0.3 ne s'ouvre plus :

- **Les demandes sans compte** de `phil_privacy_guest_request` se lisent comme absentes : leurs liens ne les ouvrent
  plus. Videz la table, ou laissez `link_lifetime` et la purge à chaque nouvelle demande les retirer, et demandez aux
  personnes qui attendaient de refaire leur demande.
- **Les archives d'export** gardées par `EncryptedExportArchive` (`account_deletion.export_directory`) se lisent comme
  absentes. Supprimez les fichiers de ce répertoire, ou laissez le balayage les retirer à leur expiration.
- **Le témoin de consentement** se lit comme aucun choix : chaque visiteur revoit la bannière et choisit de nouveau. Les
  preuves de `phil_privacy_consent_proof` sont gardées telles quelles.
- **Les limites de débit** des demandes sans compte repartent de zéro.

Un `kernel.secret` vide arrête désormais le bundle avec `InvalidPrivacyValue` quand l'un de ces services est construit,
au lieu de dériver des clés que n'importe qui peut calculer.

## Étapes suivantes

1. Écrivez `config/packages/phil_privacy.yaml` avec votre liste de refus — voir [configuration.md](configuration.md).
   Sans configuration, le processeur est enregistré mais ne refuse aucune clé ; les chaînes de requête et les
   informations d'utilisateur des adresses sont tout de même caviardées.
2. Déclarez les champs personnels avec `#[PersonalData]` — voir
   [personal-data-declaration.md](personal-data-declaration.md).
3. Implémentez `PersonalDataExporter` et `PersonalDataEraser` dans chaque module : avec l'autoconfiguration, le bundle
   les trouve et branche `SubjectExport`, `SubjectErasure` et leurs commandes — voir
   [extension-points.md](extension-points.md).
4. Faites implémenter `DataSubjectUser` par la classe de l'utilisateur et créez les tables : l'export et l'effacement
   d'un membre, et la page de consentement, sont déjà montés — voir [preferences.md](preferences.md) et
   [consent.md](consent.md).
5. Écrivez le calendrier de conservation, avec un `RetentionPurger` par stockage, et lancez le calendrier
   `phil_privacy` — voir [retention.md](retention.md).
6. Déclarez comment chaque champ personnel est anonymisé avec `#[Anonymize]`, ou par catégorie, et essayez
   `bin/console phil:privacy:anonymize --dry-run` — voir [anonymization.md](anonymization.md).
