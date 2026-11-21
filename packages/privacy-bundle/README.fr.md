<!-- phil-dev source-sha256=93dd1bdddee44be49bbfae91d7333c74707bce5083ab4ac1f18142496f80249d -->
# phil/privacy-bundle

**Déclarer les champs personnels, caviarder les journaux, respecter les droits des membres et des personnes sans compte,
appliquer les durées de conservation, tenir les registres, demander le consentement aux témoins, et anonymiser les copies
de la base, depuis le code.**

phil/privacy-bundle fait entrer le droit de la vie privée dans une application Symfony qui détient des renseignements
personnels. Un attribut `#[PersonalData]` déclare un champ personnel, et un test de l'application peut vérifier que
chacun a une règle de conservation. Un processeur Monolog caviarde les clés refusées, les chaînes de requête, les
identifiants dans les adresses, les segments de jeton des chemins, les options de ligne de commande et les messages
d'exception avant qu'une ligne soit écrite. Les réponses aux demandes d'accès et d'effacement sont assemblées à partir
d'un contrat par module, branché par l'autoconfiguration et lancé depuis la console. Le calendrier de conservation
déclare le registre des traitements — finalité, destinataires, sous-traitants et lieu de chaque stockage —, que la
commande affiche avec les champs déclarés et qui la fait échouer en CI tant qu'il reste une lacune ; il devient aussi des
ordres de purge, et une exécution est refusée quand une ligne de tâche n'a pas de purgeur. Un registre garde chaque
incident de confidentialité avec les huit paragraphes que le Québec demande pour chaque ligne, nomme les avis et les
paragraphes encore dus, garde ses corrections et garde chaque ligne au moins cinq ans ; des brouillons des avis à
l'autorité et aux personnes en sont tirés en Markdown, et personne ne les envoie sauf la personne responsable. Une copie
de la base est anonymisée colonne par colonne — courriels, noms, téléphones dans des plages fictives, adresses, IBAN,
dates décalées, empreintes, texte de remplissage — à partir de `#[Anonymize]`, des catégories déclarées et de la
configuration, avec des paquets de locale pour huit locales et une garde qui refuse de toucher une base de production.

D'emblée, un membre télécharge ses données, en archive ZIP avec un README lisible et un fichier JSON par module, et
supprime son compte depuis ses préférences. Une suppression est un workflow : une demande, un délai pendant lequel elle
peut être annulée, un export, puis l'effacement de chaque module, une seule exécution par personne, confirmée par le mode
sudo de Symfony. Une fois activé, l'effacement lui-même s'exécute comme un workflow : une étape par module, envoyée par
phil/outbox-bundle dans la transaction de la demande, réessayée et terminée séparément, les services externes accusant
réception de la leur, avec une page et une réponse JSON où la personne le suit. Une fois activé, une personne sans compte demande la même chose par un lien envoyé à son adresse,
derrière des limites de débit et avec la même réponse que l'adresse soit connue ou non. Les durées de conservation sont
appliquées par une commande ou un message planifié. Le consentement aux témoins est refusé par défaut, aussi facile à
refuser qu'à accepter, peut être retiré en tout temps, respecte le Global Privacy Control, et laisse une preuve datée de
la version du texte. Chaque témoin est déclaré dans un inventaire YAML dont sont tirés la bannière et la politique des
témoins, et un test fonctionnel échoue sur un témoin qu'il ne déclare pas. Une vidéo ou une carte d'un autre site attend
son consentement derrière un aperçu que sert le site lui-même. Une fois activé, chaque membre choisit sur la même page si
les courriels qui lui sont envoyés peuvent compter leurs ouvertures ou suivre leurs clics, chaque fin étant refusée
tant qu'il ne la coche pas.

Il existe parce que des lois sur la vie privée comme la Loi 25 du Québec donnent à une personne le droit de voir et
d'effacer ce qu'une entreprise détient à son sujet, et attendent de l'entreprise qu'elle sache ce qu'elle garde et pour
combien de temps. Garder ce savoir dans le code le garde vrai à mesure que le code change, et un développeur qui a besoin
de vraies données reçoit une copie où personne ne peut être retrouvé. Il s'adresse aux développeurs qui bâtissent ces
applications, et à la personne responsable de la vie privée qui répond aux demandes et tient le registre.

Le bundle ne décide ni de votre liste de refus, ni de votre vocabulaire de catégories, ni de votre calendrier de
conservation. Il ne sait pas ce que tient un module : chaque module exporte et efface ses propres enregistrements par un
port, et chaque stockage est purgé par son propre adaptateur. Il ne vérifie pas l'identité de quelqu'un qui écrit par
d'autres moyens que le site, et ne sauvegarde pas la base. Pour une trace inaltérable de qui a fait quoi, associez-le à
`phil/audit-bundle` ; pour sauvegarder la base et restaurer une copie à anonymiser, à `phil/backup-bundle`.

C'est un bundle Symfony sur Monolog, Workflow, Lock, Messenger, Yaml, les composants Security, Twig, `phil/email-address`
et `phil/url`, avec les composants Console, Scheduler, Rate Limiter et Mailer, Doctrine Persistence, DBAL et ORM en
option. C'est un squelette de ports et d'adaptateurs : il porte ce que la conception décide, et des ports comme
`PersonalDataExporter`, `PersonalDataEraser`, `RetentionPurger`, `Anonymizer` et `AnonymizationTarget` pour ce que
l'application décide.

> [!CAUTION]
> L'export et l'effacement d'un membre sont **actifs par défaut** sur tous les sites. En désactiver un retire un droit
> légal à tous les membres et exige la phrase entière de [Désactiver un droit](doc/fr/preferences.md#désactiver-un-droit) ;
> chaque construction et chaque commande de la console en avertissent ensuite.

## État

| Domaine | État |
|---|---|
| `#[PersonalData]`, `DeclaredPersonalFields`, `PersonalField` | Implémentés et testés |
| `DataSubject`, `DataCategory`, `ModuleName`, `ErasureReport` | Implémentés et testés |
| `Denylist`, `UrlRedactor`, `CommandLineRedactor`, `ContextRedactor` | Implémentés et testés |
| `RetentionPeriod`, `RetentionRule`, `RetentionSchedule`, `PurgeOrder` et les énumérations | Implémentés et testés |
| `RedactingProcessor` | Implémenté, testé, branché depuis la configuration et étiqueté `monolog.processor` |
| `SubjectExport`, `SubjectErasure` | Implémentés, testés, branchés sur chaque module étiqueté |
| `phil:privacy:export`, `phil:privacy:erase` | Implémentées et testées |
| `ProcessingRegister`, `ProcessingActivity`, `phil:privacy:register` | Implémentés et testés : le registre des traitements tiré du calendrier et des déclarations, en Markdown ou en JSON, `--strict` |
| `RetentionRun`, `RetentionJob`, `phil:privacy:retention`, le calendrier `phil_privacy` | Implémentés, testés et branchés depuis un calendrier |
| `AccountDeletions`, le workflow, `phil:privacy:deletions` | Implémentés, testés et branchés |
| Le workflow d'effacement : `ErasureRequests`, un message d'outbox par module, la page de progression et le JSON, `phil:privacy:erasure` | Implémentés, testés et branchés ; inactifs tant qu'ils ne sont pas activés |
| Le consentement aux témoins : bannière, page, fonctions Twig, contrôleur Stimulus, preuves | Implémenté, testé et branché |
| L'inventaire des témoins, la politique des témoins, `AssertsDeclaredCookies` | Implémentés, testés et branchés |
| `privacy_embed()` : les cadres tiers retenus jusqu'au consentement | Implémenté, testé et branché |
| L'export et l'effacement d'un membre depuis ses préférences, en archive ZIP | Implémentés, testés et actifs par défaut |
| Les demandes des personnes sans compte, par un lien vérifié | Implémentées, testées et branchées ; inactives tant qu'elles ne sont pas activées |
| Le registre des incidents de confidentialité, `phil:privacy:incident`, `phil:privacy:incidents` : les huit paragraphes de l'article 7 du règlement québécois (r. 3.1), corrections gardées, cinq ans au moins (article 8) | Implémentés, testés et branchés |
| Les brouillons des avis d'un incident, `phil:privacy:incident-notice` : à la Commission, ses suivis, à chaque personne et au public (r. 3.1, articles 3 à 6), en Markdown, en français et en anglais, remplis à partir du registre ; rien n'est envoyé | Implémentés, testés et branchés |
| Le consentement de chaque membre aux courriels suivis, `EmailTrackingConsents` | Implémenté, testé et branché ; inactif tant qu'il n'est pas activé |
| Les contrôleurs Stimulus par AssetMapper et StimulusBundle | Implémentés et testés |
| `PersonalDataExporter`, `PersonalDataEraser`, `ExternalPersonalDataEraser`, `RetentionScheduleSource`, `RetentionPurger`, `ErasureRequestStore` | Ports |
| `#[Anonymize]`, `#[AsAnonymizer]`, les treize anonymiseurs, les huit paquets de locale | Implémentés et testés |
| `AnonymizationPlanReader`, `AnonymizationRun`, `ProductionGuard`, `ConnectionIdentity` | Implémentés, testés et branchés |
| `phil:privacy:anonymize` | Implémentée et testée sur SQLite ; MySQL, MariaDB et PostgreSQL par Doctrine DBAL |
| `PhilPrivacyBundle` | Tout ce qui précède, avec sa configuration |

## Documentation

- [Installation](doc/fr/installation.md) — les prérequis, l'installation par Composer, l'enregistrement du bundle à
  côté de MonologBundle.
- [Configuration](doc/fr/configuration.md) — chaque clé de `phil_privacy`, sa valeur par défaut et son effet, les
  services qu'elle branche, et chaque erreur à la construction.
- [Déclarer les données personnelles](doc/fr/personal-data-declaration.md) — l'attribut `#[PersonalData]`, la lecture
  des déclarations à travers les parents, et le test d'inventaire qui échoue sur les champs non couverts.
- [Chiffrer les données personnelles au repos](doc/fr/encrypted-columns.md) — les colonnes à chiffrer avec
  `#[Encrypted]` du composant KeyManagement de Symfony, les index aveugles avec `#[BlindIndexed]` pour la recherche, et
  la rotation.
- [Caviardage des journaux](doc/fr/log-redaction.md) — ce que caviarde le processeur et pourquoi, la comparaison et les
  motifs de la liste de refus, les règles des adresses et de la ligne de commande, les exceptions, et l'ordre des
  processeurs dans Monolog.
- [Export et effacement d'une personne](doc/fr/export-and-erasure.md) — `DataSubject`, les contrats par module,
  `SubjectExport`, `SubjectErasure` et l'isolement des échecs, la façon dont le bundle les branche, le registre des
  traitements, et l'archive ZIP d'une réponse d'accès.
- [Calendrier de conservation et purges](doc/fr/retention.md) — les durées et les dates limites en UTC avec la fin de
  mois ramenée, les lignes du calendrier et leurs contradictions refusées, `RetentionRun`, et l'exécution de la tâche.
- [L'effacement comme workflow](doc/fr/erasure-workflow.md) — l'inventaire d'effacement et sa version, un message d'outbox
  par module, les états, les échecs et les rejeux, les services externes et leur accusé de réception, la page de
  progression, et les migrations pour MySQL et PostgreSQL.
- [Choisir un contrôle](doc/fr/choosing-a-control.md) — quel outil répond à quel problème et où il s'arrête : effacer,
  conserver, caviarder, pseudonymiser et anonymiser, chiffrement et index aveugles, sauvegardes.
- [Supprimer un compte](doc/fr/account-deletion.md) — le workflow, le délai et l'annulation, ce qui garantit une seule
  exécution par personne, le passage de balayage, et l'endroit où va l'export.
- [Consentement aux témoins](doc/fr/consent.md) — la déclaration des catégories, les fonctions Twig, les règles, le
  témoin et la preuve, le fonctionnement sans JavaScript, et le remplacement des gabarits.
- [L'inventaire des témoins](doc/fr/cookie-inventory.md) — le fichier YAML, la bannière, la page et la politique qui en
  sont tirées, et le test qui échoue sur un témoin non déclaré.
- [Cadres tiers](doc/fr/third-party-frames.md) — `privacy_embed()`, l'aperçu servi par le site, les lecteurs qui
  écrivent moins de traceurs, et le contrôleur Stimulus.
- [Demandes sans compte](doc/fr/requests-without-an-account.md) — le lien vérifié, les étapes, les protections contre
  les abus, et qui est une adresse vérifiée.
- [Le registre des incidents](doc/fr/incidents.md) — ce que demandent la loi et le règlement du Québec
  (P-39.1, art. 3.5 à 3.8 ; r. 3.1), inscrire, compléter, corriger et afficher une ligne, les avis qu'exige un risque
  sérieux, les cinq ans de conservation, et la migration de ses tables.
- [Les brouillons des avis d'un incident](doc/fr/incident-notices.md) — les avis à la Commission, leurs suivis, à chaque
  personne et au public (r. 3.1, art. 3 à 6), les rubriques remplies à partir du registre et celles laissées à
  compléter, qui décide et qui envoie, et le remplacement des gabarits.
- [Courriels suivis](doc/fr/email-tracking.md) — le consentement de chaque membre au comptage des ouvertures et au suivi
  des clics, ce que demande un expéditeur, la version du texte, et son export et son effacement.
- [Cadre juridique](doc/fr/legal-framework.md) — ePrivacy et la CNIL, la loi 25 du Québec, le RGPD et l'Omnibus
  numérique proposé, et ce que fait la configuration par défaut pour chacun.
- [Export et effacement depuis les préférences](doc/fr/preferences.md) — les routes, le membre courant, le mode sudo,
  et ce qu'il faut pour désactiver un droit.
- [Anonymisation](doc/fr/anonymization.md) — le plan tiré des attributs, des catégories et de la configuration, chaque
  anonymiseur et ses options, les paquets de locale, la commande, la garde de production, et une copie à partager.
- [Points d'extension](doc/fr/extension-points.md) — les ports méthode par méthode, les anonymiseurs et les paquets de
  locale maison, les invariants que garde un adaptateur, des exemples d'adaptateurs, et les identifiants de service sur
  lesquels compter.
- [Tests](doc/fr/testing.md) — comment le bundle est testé, lancer ses tests dans un monodépôt ou seul, et comment
  tester vos adaptateurs et votre configuration.
- [Référence de l'API](doc/fr/reference.md) — chaque type public par couche, avec son rôle et son état.

## Démarrage rapide

```bash
composer require phil/privacy-bundle:^0.1@dev
```

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    log_redaction:
        denylist: ['*password*', '*token*', '*secret*', '*authorization*', '*cookie*', query_string]
        token_path_prefixes: ['/p/']
        short_options: { p: password }
```

```php
final class Member
{
    #[PersonalData('email', retention: 'R-002')]
    private string $email;
}

DeclaredPersonalFields::uncoveredIn(Member::class, $schedule); // [] once every field has a rule
```

```php
#[PersonalData('email', retention: 'R-002')]
#[Anonymize('email', options: ['domain' => 'example.org'])]
private string $email;
```

```bash
bin/console phil:privacy:anonymize --dry-run           # the plan, and what it leaves in clear
bin/console phil:privacy:anonymize --confirm           # never in production
```

Une entrée de configuration mal formée fait échouer la construction du conteneur, pas la première ligne de journal.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-sodium` et `ext-intl`, `monolog/monolog` 3, Symfony 8.2 et Twig 3. Les
demandes sans compte demandent `symfony/rate-limiter`, et `symfony/mailer` pour l'expéditeur du bundle, dont les liens mènent à
`public_origin` ou à un hôte que `framework.trusted_hosts` accepte : en production, le bundle refuse de démarrer sans
l'un ou l'autre (voir [Où mène le lien](doc/fr/requests-without-an-account.md#où-mène-le-lien)). MonologBundle est
nécessaire pour que le processeur atteigne vos journaux par son étiquette, un pare-feu pour qu'un membre atteigne ses
préférences, et une base de données pour les suppressions de compte, sauf si un stockage est nommé. Voir
[Installation](doc/fr/installation.md).

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
