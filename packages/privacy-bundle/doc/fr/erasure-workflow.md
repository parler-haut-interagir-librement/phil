<!-- phil-dev source-sha256=5dfcf45893a55f0fa79835a784d55d1c0087fe7a10a4fa750390a78ab983ba44 -->
# L'effacement comme workflow

**État : implémenté et testé.** Exige phil/outbox-bundle sur la connexion du bundle, Symfony Messenger et l'horloge.
Inactif par défaut.

`phil:privacy:erase` et `SubjectErasure` exécutent l'effaceur de chaque module dans un seul processus, l'un après
l'autre : un module qui échoue fait échouer tout l'effacement, et le relancer relance chaque module. Le workflow
d'effacement exécute plutôt chaque module séparément : une étape par module, un message par étape, réessayée seule,
terminée une seule fois, avec une page de progression que la personne peut suivre.

## L'activer

```yaml
# config/packages/phil_privacy.yaml
phil_privacy:
    erasure_workflow:
        enabled: true
        lease: 900                          # seconds a worker holds a step, 1 to 86400
        operator_role: ROLE_PRIVACY_OPERATOR
        store: ~                            # an ErasureRequestStore service; null, the tables of the bundle

# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default                     # the connection of phil_privacy's tables

# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\PrivacyBundle\Application\Message\EraseModule: async
```

La construction du conteneur échoue quand le workflow est activé sans phil/outbox-bundle, sans Messenger, sans l'horloge
ou sans magasin. Une fois activé, les suppressions de compte et les demandes faites sans compte effacent par ce moyen.

## Ce qu'efface une demande

L'**inventaire d'effacement** est la liste des modules qui détiennent des renseignements personnels et les effacent,
comme les déclarent leurs effaceurs : chaque `PersonalDataEraser` et chaque `ExternalPersonalDataEraser` du conteneur.
Sa **version** est une empreinte des noms de modules triés et de ce que chacun efface sur place ou par un service
externe, 16 chiffres hexadécimaux.

Une demande garde la version de l'inventaire avec lequel elle a été ouverte, et ses modules sont ses étapes : un module
déclaré plus tard ne change jamais ce qu'efface une demande ouverte, et un module retiré plus tard est vu au lieu
d'être ignoré. Ouvrir une demande pour des modules nommés seulement refuse un module que l'inventaire ne déclare pas.

```php
$request = $erasures->open($subject);                          // every module
$request = $erasures->open($subject, ['forum', 'billing']);    // these only; an unknown name throws InvalidPrivacyValue
$request = $erasures->open($subject, null, $deletion->id);     // under a known identifier, opened once
```

## Un message par module

Ouvrir une demande écrit, en une seule transaction de la connexion : la demande, une étape en attente par module, et un
enregistrement d'outbox par module, de type `phil_privacy.erase_module`. Un enregistrement porte l'identifiant de la
demande et le nom du module, rien d'autre ; sa clé d'idempotence est `<request>:<module>`. Si une partie échoue, rien
n'est conservé.

L'outbox relaie chaque enregistrement comme un message `EraseModule`. Relayé au répartiteur d'événements, le bundle le
remet au bus de Messenger ; avec le pont Messenger de l'outbox, il atteint directement le bus. Un enregistrement dont la
demande n'existe plus est écarté, jamais mort : le codec lance `ForgottenRecord`.

## Les états

Chaque étape est une ligne unique pour la paire de la demande et du module :

| Étape | Signification |
|---|---|
| `pending` | Attend son message : enregistrée avec la demande, rendue après une tentative échouée, ou remise en place par un rejeu |
| `running` | Confiée à un *worker* qui exécute l'effaceur du module, ou envoyée à un service externe qui n'a pas accusé réception |
| `completed` | Le module a effacé la personne ; son rapport est conservé, stockage par stockage |
| `needs_attention` | Le module n'a pas pu terminer seul ; la raison dit pourquoi |

L'état de la demande se déduit de ses étapes, sauf `failed` :

| Demande | Signification |
|---|---|
| `running` | Au moins un module est en attente ou en cours, et aucun ne demande d'attention |
| `needs_attention` | Au moins un module demande de l'attention |
| `completed` | Chaque module est terminé |
| `failed` | Un exploitant a fermé la demande, décidant qu'elle ne peut pas être terminée |

La raison d'une étape qui demande de l'attention appartient à une liste fermée, de sorte qu'elle ne nomme ni valeur ni
exception : `retries_exhausted`, `unknown_module` (le module n'est plus déclaré) ou `subject_unavailable` (la demande
faite sans compte dont elle provient n'existe plus).

## Exécuter une étape

Un *worker* qui reçoit `EraseModule` :

1. laisse tel quel une demande inconnue ou fermée, et refuse un module que la demande ne nomme pas ;
2. marque l'étape comme demandant de l'attention quand aucun effaceur ne déclare plus le module ;
3. confie l'étape à un bail de `lease` secondes, quand elle est en attente, demande de l'attention, ou s'exécute sous un
   bail écoulé. Une étape terminée, une étape confiée à un autre *worker* et une étape qui attend son accusé de
   réception sont laissées telles quelles : un message livré deux fois et une demande reprise n'exécutent jamais de
   nouveau un module terminé ;
4. détermine la personne : l'identifiant que garde la demande, ou, pour une demande faite sans compte, l'adresse
   scellée dans la demande dont elle provient, par `GuestSubjects` ;
5. exécute l'effaceur et termine l'étape avec son rapport. Un rapport classé sous le nom d'un autre module est un
   échec.

Le bail appartient au *worker* : choisissez-le plus long que l'effaceur le plus lent, sans quoi un autre *worker*
exécute de nouveau le module, ce que permettent les effaceurs, étant idempotents.

## Les échecs

Un module qui échoue rend son étape, en attente, avec une tentative de plus comptée, et le *worker* lance
`ModuleErasureFailed`, afin que Messenger essaie de nouveau le message. Cette exception nomme la demande et le module,
jamais l'échec du module lui-même, qui peut citer les données de la personne : Messenger garde le message d'une
exception dans les enveloppes qu'il envoie aux transports de nouvelle tentative et d'échec. La ligne de journal ne
donne que la classe de l'échec.

`ErasureFailureListener` écoute `WorkerMessageFailedEvent`. Tant que `willRetry()` est vrai, il attend ; quand Messenger
abandonne, il marque l'étape, et donc la demande, comme demandant de l'attention.

À partir de là, un exploitant :

- **rejoue** la demande une fois la cause corrigée : les étapes qui demandent de l'attention ou qui attendent un accusé
  de réception retournent en attente, un message est envoyé pour chaque étape en attente, et la demande est de nouveau
  en cours. Rejouer le message depuis le transport d'échec (`messenger:failed:retry`) exécute aussi l'étape et remet la
  demande en cours ;
- **ferme la demande comme échouée**, une décision que seul un exploitant prend. Une demande échouée n'exécute plus rien.

```
bin/console phil:privacy:erasure 3f2a…                    # the request, module by module
bin/console phil:privacy:erasure 3f2a… --replay
bin/console phil:privacy:erasure 3f2a… --fail --confirm
```

## Les services externes

Un module dont l'effacement est exécuté par un service externe, comme un service d'envoi de courriels, implémente
`ExternalPersonalDataEraser` :

```php
final class MailingEraser implements ExternalPersonalDataEraser
{
    public function module(): ModuleName
    {
        return ModuleName::fromString('mailing');
    }

    public function request(DataSubject $subject, string $idempotencyKey): void
    {
        $this->client->deleteContact($this->contactOf($subject), idempotencyKey: $idempotencyKey);
    }
}
```

Le *worker* envoie l'effacement avec la clé d'idempotence de l'étape, la même quel que soit le nombre d'exécutions de
l'étape, et l'étape reste `running`, sans bail, jusqu'à ce que le service accuse réception. L'application remet l'accusé
de réception au workflow, depuis le webhook que le service appelle, après avoir vérifié que l'appel vient du service :

```php
$erasures->acknowledge($idempotencyKey, ErasureReport::of('mailing', ['contacts' => ErasureEffect::Deleted]));
```

`acknowledge()` retourne `false` pour une clé sous laquelle aucune étape n'attend, ou pour un rapport d'un autre
module. Une étape qui n'obtient jamais son accusé de réception est envoyée de nouveau, avec la même clé, par un rejeu.

## La page de progression

`GET <prefix>/erasure/requests/{id}` montre la demande module par module ; `GET <prefix>/erasure/requests/{id}.json`
donne la même chose en JSON :

```json
{
    "id": "3f2a…",
    "state": "running",
    "inventory_version": "9c1d0e7a52b64f13",
    "requested_at": "2026-11-20T10:00:00+00:00",
    "closed_at": null,
    "completed": 1,
    "total": 2,
    "modules": [
        {"module": "billing", "state": "completed", "reason": null, "completed_at": "2026-11-20T10:00:05+00:00",
         "effects": {"invoices": {"effect": "survives", "personal_data_left": true}}},
        {"module": "forum", "state": "pending", "reason": null, "completed_at": null, "effects": {}}
    ]
}
```

Elle est montrée à la personne visée par la demande, à un utilisateur à qui `operator_role` est accordé et, pour une
demande faite sans compte, à quiconque détient le secret de son lien de progression, `?token=…`. Tout autre reçoit le
même 404 que pour une demande qui n'existe pas. La réponse n'est jamais conservée par un cache, n'envoie aucun référent
et n'est pas indexée. Elle ne nomme jamais la personne.

## Demandes faites sans compte

Avec le workflow activé, ouvrir le lien d'une demande d'effacement faite sans compte ouvre une demande d'effacement au
lieu d'exécuter chaque module d'un coup. La page qui suit donne le lien de progression, avec un secret propre, dont
seule l'empreinte est conservée.

La demande d'effacement garde l'identifiant de la demande dont elle provient, jamais l'adresse. Les messages ne portent
que l'identifiant de la demande d'effacement ; le *worker* lit l'adresse dans la demande dont elle provient, dont
l'adresse reste scellée dans sa table. Cette demande est conservée, son lien ne l'ouvrant plus, jusqu'à ce que
l'effacement soit fermé, ou 90 jours au plus (`GuestRequests::WORKFLOW_RETENTION`), puis retirée : une étape qui
s'exécute après sa disparition demande de l'attention.

## Suppressions de compte

Avec le workflow activé, l'étape d'effacement d'une suppression de compte ouvre une demande d'effacement sous
l'identifiant de la suppression, une seule fois. La suppression reste à `exported` pendant que la demande s'exécute, et
le balayage suivant la ferme une fois la demande terminée. Une demande fermée comme échouée laisse la suppression à
`exported`, pour que l'exploitant décide.

## Les tables

`phil_privacy_erasure_request` garde une ligne par demande ; `phil_privacy_erasure_step` une ligne par module d'une
demande, sa clé primaire étant la paire, sa clé d'idempotence unique. `PrivacySchemaListener` les ajoute au schéma que
génère l'ORM, de sorte que `doctrine:migrations:diff` écrit la migration. Sans l'ORM, la migration est :

MySQL et MariaDB :

```sql
CREATE TABLE phil_privacy_erasure_request (id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, subject VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, guest_request VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, inventory_version VARCHAR(16) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, state VARCHAR(16) NOT NULL, requested_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, access_hash VARCHAR(64) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, INDEX idx_phil_privacy_erasure_subject (subject), INDEX idx_phil_privacy_erasure_state (state, requested_at), PRIMARY KEY (id));
CREATE TABLE phil_privacy_erasure_step (request_id VARCHAR(32) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, module VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, state VARCHAR(16) NOT NULL, attempts INT DEFAULT 0 NOT NULL, lease VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_bin`, lease_until DATETIME DEFAULT NULL, idempotency_key VARCHAR(97) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_bin`, effects LONGTEXT DEFAULT NULL, reason VARCHAR(32) DEFAULT NULL, updated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_phil_privacy_erasure_step_key (idempotency_key), PRIMARY KEY (request_id, module));
```

PostgreSQL :

```sql
CREATE TABLE phil_privacy_erasure_request (id VARCHAR(32) NOT NULL, subject VARCHAR(255) DEFAULT NULL, guest_request VARCHAR(32) DEFAULT NULL, inventory_version VARCHAR(16) NOT NULL, state VARCHAR(16) NOT NULL, requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, access_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY (id));
CREATE INDEX idx_phil_privacy_erasure_subject ON phil_privacy_erasure_request (subject);
CREATE INDEX idx_phil_privacy_erasure_state ON phil_privacy_erasure_request (state, requested_at);
CREATE TABLE phil_privacy_erasure_step (request_id VARCHAR(32) NOT NULL, module VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, attempts INT DEFAULT 0 NOT NULL, lease VARCHAR(32) DEFAULT NULL, lease_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, idempotency_key VARCHAR(97) NOT NULL, effects TEXT DEFAULT NULL, reason VARCHAR(32) DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (request_id, module));
CREATE UNIQUE INDEX uniq_phil_privacy_erasure_step_key ON phil_privacy_erasure_step (idempotency_key);
```

Chaque changement d'une étape est un seul `UPDATE` qui nomme les états de départ, et le bail qu'il détient quand il en
détient un : deux *workers* n'exécutent jamais la même étape tant que son bail court, sur toutes les plateformes, sans
verrouiller de lignes.

Une demande fermée est conservée jusqu'à ce qu'une règle de conservation sur le stockage `phil_privacy_erasure_request`
la retire, avec ses étapes ; une demande qui n'est pas fermée n'est jamais purgée. Elle ne nomme la personne que par
l'identifiant de son compte, et c'est à partir d'elle qu'un effacement est rejoué après une restauration. Ses
enregistrements d'outbox partent avec la rétention propre de l'outbox, et un enregistrement dont la demande a été
purgée d'abord est écarté.
