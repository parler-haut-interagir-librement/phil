<!-- phil-dev source-sha256=490ea2c24a8ff7fe266c32b41168cdbbf5654b366d4fddeb06563ffe37a8bf40 -->
# Supprimer un compte

`AccountDeletions` supprime un compte par étapes, sur `symfony/workflow` : une demande, un délai pendant lequel la
personne peut changer d'avis, un export, puis l'effacement de chaque module, par `SubjectExport` et `SubjectErasure`.

| Transition | De | Vers | Quand |
|---|---|---|---|
| `cancel` | `requested` | `cancelled` | Pendant le délai, par la personne. |
| `export` | `requested` | `exported` | Une fois le délai écoulé. |
| `erase` | `exported` | `erased` | Juste après l'export. |

Le workflow est le service `phil_privacy.account_deletion_workflow`, nommé `phil_privacy_account_deletion`. Ses
événements sont distribués comme d'habitude, et c'est ainsi qu'une application répond à une étape :

```php
#[AsEventListener('workflow.phil_privacy_account_deletion.completed.export')]
public function deliver(CompletedEvent $event): void
{
    $deletion = $event->getSubject();
    $document = $this->archive->get($deletion->exportReference());
    // Send it to the person: their account is erased when this listener returns.
}
```

## La personne

Une demande désigne une personne par l'identifiant opaque sur lequel chaque module indexe ses enregistrements
(`DataSubject`) : un UUID, un `sub` OIDC, un numéro de client. **Jamais une adresse courriel** : la demande survit au
compte, pour qu'un effacement puisse être rejoué après la restauration d'une sauvegarde. La classe de l'utilisateur de
l'application dit quel identifiant la désigne, en implémentant `DataSubjectUser`.

## Ce qui garantit quoi

- **Une seule exécution par personne.** Chaque étape s'exécute sous un verrou de `symfony/lock`, nommé d'après une
  empreinte de l'identifiant, et la demande est relue une fois le verrou obtenu. Une annulation qui gagne la course est
  vue.
- **Une étape s'arrête quand son verrou est perdu.** Le verrou dure 900 secondes et est renouvelé avant chaque module de
  l'export et de l'effacement, avant l'écriture de l'archive et avant chaque enregistrement d'une place. Une étape dont
  le verrou a expiré et a été pris par un autre *worker* s'arrête là et laisse la demande à sa dernière place enregistrée
  ([Verrous et longs travaux](export-and-erasure.md#verrous-et-longs-travaux)).
- **Un échec est repris, pas recommencé.** La place est enregistrée après chaque étape. Un export est écrit une fois. Un
  effacement qui échoue par `ErasureIncomplete` laisse la demande à `exported`, pour être relancé ; les effaceurs sont
  idempotents.
- **Les messages portent des identifiants.** `AdvanceAccountDeletion` porte l'identifiant de la demande,
  `ProcessDueAccountDeletions` et `RunRetention` rien du tout. L'export ne voyage jamais dans un message : c'est le
  dossier entier de la personne, écrit dans une `ExportArchive`, et ce qui circule est sa référence. L'archive est le
  bon de dépôt : aucun message n'a besoin du `ClaimCheckSerializer` de Messenger.
- **Les journaux portent des identifiants.** Une ligne nomme la demande et, en cas d'échec, les modules. Jamais la
  personne, et jamais le texte d'une exception, qu'un module aurait pu remplir de renseignements personnels.
- **Les transports portent des identifiants.** Un effacement échoué parvient à Messenger sous la forme
  d'`AccountErasureIncomplete`, qui nomme la demande et les modules et n'enchaîne aucune exception précédente. Messenger
  copie le message de ce qu'un gestionnaire lance, et de chaque exception précédente, dans l'`ErrorDetailsStamp` de
  l'enveloppe qu'il envoie aux transports de reprise et d'échec : l'exception d'un effaceur, comme une erreur de clé en
  double qui cite une adresse, ne les atteint jamais.

## L'exécuter

Le délai est vérifié par un balayage. Avec `symfony/scheduler` installé, le calendrier `phil_privacy` le lance :

```bash
bin/console messenger:consume scheduler_phil_privacy
```

`ProcessDueAccountDeletions` envoie un `AdvanceAccountDeletion` par demande échue, par lots de 100, et retire les
exports expirés. Acheminez ces messages vers un transport comme tout autre : un effacement qui échoue lève, si bien que
Messenger le relance, et le balayage suivant y revient de toute façon.

Sans planificateur, lancez `phil:privacy:deletions` depuis cron. La commande se termine en échec quand un effacement a
échoué, et nomme les demandes par leur identifiant.

### Un transport d'échec à part

Donnez aux messages de confidentialité un transport et un transport d'échec à eux, pour que leurs échecs soient gardés
à part de ceux de l'application, peu de temps, et retirés une fois la demande close :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            privacy:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: privacy }
                failure_transport: privacy_failed
                retry_strategy: { max_retries: 3, delay: 60000, multiplier: 4 }
            privacy_failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options: { queue_name: privacy_failed }
        routing:
            Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion: privacy
```

Un message de `privacy_failed` contient l'identifiant d'une demande et les noms des modules qui ont échoué, et ses
estampilles. Retirez-le une fois la demande close, que ce soit un balayage ou une reprise qui ait effacé la personne
depuis :

```bash
bin/console messenger:failed:remove --transport=privacy_failed 42 --force
```

et retirez le reste selon une courte rétention, depuis cron :

```bash
bin/console messenger:failed:remove --transport=privacy_failed --failed-before='-7 days' --force
```

Le balayage revient quand même à une demande dont le message a été retiré : le transport d'échec est un registre des
échecs, pas l'endroit où le travail attend.

## Comme un workflow

Avec [`erasure_workflow`](erasure-workflow.md#suppressions-de-compte) activé, l'étape d'effacement ouvre une demande
d'effacement sous l'identifiant de la suppression, une seule fois, au lieu d'exécuter chaque module dans le balayage. La
suppression reste à `exported` pendant que les modules s'exécutent, chacun réessayé séparément, et le balayage suivant
la ferme une fois la demande terminée. Une demande qu'un exploitant a fermée comme échouée laisse la suppression à
`exported`, pour que l'exploitant décide.

## L'export

L'export est l'archive ZIP d'une réponse d'accès, un `README.txt` et un fichier JSON par module
([export-and-erasure.md](export-and-erasure.md#larchive-dune-réponse-daccès)). `EncryptedExportArchive` la garde comme
fichier `0600` d'un répertoire `0700` (`account_deletion.export_directory`), scellée par libsodium sous une clé dérivée
du secret de l'application par HKDF, pendant `account_deletion.export_retention`. Chaque balayage retire celles qui
ont expiré.

## Les tables

`phil_privacy_account_deletion` contient les demandes. `PrivacySchemaListener` l'ajoute, avec les tables des preuves de
consentement, des demandes sans compte et des incidents, au schéma que génère l'ORM : `doctrine:migrations:diff` écrit
donc la migration. Une application sans Doctrine les crée à partir de `PrivacyTables::configure()`, ou nomme son propre
`account_deletion.store`.

Les identifiants, les personnes, les références d'export et les empreintes de jeton se comparent octet par octet. Sur
MySQL et MariaDB, dont les collations par défaut ignorent la casse et les accents, ces colonnes prennent `utf8mb4_bin` :
`PrivacySchemaListener` donne à `PrivacyTables::configure()` la plateforme de sa connexion, et une application qui
l'appelle elle-même passe la plateforme comme second argument. Sans elle, les personnes `Alice` et `alice` partageraient
une demande de suppression.

Une demande fermée est gardée jusqu'à ce qu'une règle de conservation sur le stockage `phil_privacy_account_deletion` la
retire : son identifiant est ce à partir de quoi un effacement est rejoué après une restauration.
