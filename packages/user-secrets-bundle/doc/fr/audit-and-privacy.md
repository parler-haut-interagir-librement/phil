<!-- phil-dev source-sha256=b1c803aaa727ac5550625dba3d048d5826e1f284f6cae9d183f0294ecff19e56 -->
# Audit et vie privée

## Les événements

Chaque changement et chaque lecture est un `SecretEvent` : son `kind`, le `owner`, le `name` du secret, le moment `at`
tiré de l'horloge et, pour une lecture, le `purpose` de la tâche. Il ne transporte jamais de valeur.

| Nature | Quand |
|---|---|
| `stored` | Une valeur a été stockée, nouvelle ou en remplacement de la précédente |
| `revealed` | Une tâche du propriétaire a lu la valeur |
| `refreshed` | Un jeton OAuth expiré a été actualisé et le nouveau stocké |
| `revoked` | Le propriétaire a révoqué le secret et sa valeur a été effacée |
| `removed` | La ligne du secret a été supprimée |
| `erased` | Le secret a été supprimé avec le reste des secrets de son propriétaire |
| `rekeyed` | La valeur a été scellée de nouveau sous la clé courante |
| `revoked_at_service` | Le service qui a émis le jeton OAuth l'a révoqué, après sa révocation, sa suppression ou son effacement ici |
| `service_revocation_failed` | Le service n'a pas révoqué le jeton OAuth ; il a disparu ici malgré tout, et l'échec a été journalisé |

Les événements sont transmis après l'enregistrement du changement ; ceux de la révocation auprès du service viennent
après l'événement du changement local.

## Écrire un auditeur

Un auditeur implémente `SecretAuditor` ; la configuration automatique lui donne son étiquette, et chaque auditeur
reçoit chaque événement :

```php
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Audit\SecretEventKind;
use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;

final readonly class NotifyOwnerOfNewKey implements SecretAuditor
{
    public function record(SecretEvent $event): void
    {
        if (SecretEventKind::Stored === $event->kind) {
            // tell the owner a key was added to their account
        }
    }
}
```

Quand un auditeur lève une exception, celle-ci parvient à l'appelant, une fois le changement enregistré.

## L'auditeur journaliseur

Avec `log_events: true`, `LoggerAuditor` écrit chaque événement dans le service `logger`, au niveau `info` :

```text
User secret {secret} of {owner}: {event}.   {"event": "revealed", "owner": "…", "secret": "openai", "at": "…", "purpose": "…"}
```

L'identifiant du propriétaire est un renseignement personnel : routez le canal là où l'application conserve ses
journaux d'audit.

## Exportation et effacement avec phil/privacy-bundle

Quand `phil/privacy-bundle` est installé, le bundle enregistre `UserSecretsPrivacy` comme module `user_secrets`, la
personne concernée étant le propriétaire :

- **accès et portabilité** : l'exportation liste les secrets de la personne — nom, service, nature et dates — et
  jamais une valeur, qui ne pourrait voyager qu'en clair ;
- **effacement** : chaque secret de la personne est supprimé, le stockage `phil_user_secret` est déclaré `deleted`, puis
  ses jetons OAuth sont révoqués auprès des services que sert un `TokenRevoker` de l'application ; un service qui
  échoue n'arrête pas l'effacement, comme l'explique [stocker et lire les secrets](secrets.md#révocation-auprès-du-service).
  Le relancer ne supprime rien de plus et donne le même rapport.

Le module implémente les contrats `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` et
`PersonalDataEraser` du bundle de vie privée, qui le trouve par leurs étiquettes.

```sh
bin/console phil:privacy:export member-42
bin/console phil:privacy:erase member-42 --confirm
```

Sans le bundle de vie privée, appelez `UserSecrets::eraseOwner()` là où l'application supprime un compte.
