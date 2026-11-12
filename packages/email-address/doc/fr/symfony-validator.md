<!-- phil-dev source-sha256=c77581391c41948eff38a8b6a113c8cbe264c5695d014197227333632f87473c -->
# Validator de Symfony

`Validator\DeliverableEmailAddress` est une contrainte du Validator de Symfony qui exécute `EmailValidator` sur une
propriété ou un accesseur : la syntaxe, le domaine de premier niveau, les domaines de l'application, les domaines
jetables et, sur demande, le serveur de courriel du domaine. Elle nomme le premier problème trouvé.

```php
use Phil\EmailAddress\Validator\DeliverableEmailAddress;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SignUp
{
    #[NotBlank]
    #[DeliverableEmailAddress(checkMailServer: true, deniedDomains: ['competitor.example'])]
    public string $email = '';
}
```

`null` et la chaîne vide sont valides, comme pour toute contrainte de Symfony : ajoutez `NotBlank` pour exiger une
valeur. Une valeur est une chaîne, un `Stringable` ou une `EmailAddress` ; toute autre lève
`UnexpectedValueException`.

## Options

| Option | Par défaut | Effet |
|---|---|---|
| `internationalLocalPart` | `false` | Accepte une partie avant le @ hors de l'ASCII (RFC 6531) |
| `quotedLocalPart` | `false` | Accepte une partie avant le @ entre guillemets |
| `ipLiteral` | `false` | Accepte un littéral IP comme `ada@[192.0.2.1]` |
| `knownTopLevelDomain` | `true` | Refuse un domaine sous un domaine de premier niveau que la zone racine ne délègue pas |
| `allowDisposable` | `false` | Accepte les domaines des boîtes de courriel jetables |
| `checkMailServer` | `false` | Demande au DNS si un serveur reçoit le courriel du domaine |
| `deniedDomains` | `[]` | Les domaines refusés, avec leurs sous-domaines |
| `allowedDomains` | `[]` | Les seuls domaines acceptés, avec leurs sous-domaines ; une liste vide accepte tout domaine |

Le DNS est désactivé par défaut : il fait attendre une validation sur le réseau, et un test ou un formulaire qui
valide à chaque frappe n'en veut pas. Activez-le là où une adresse doit recevoir le courriel avant toute autre chose,
à l'inscription.

## Messages et codes

| Problème | Option du message | Constante du code |
|---|---|---|
| La syntaxe | `message` | `SYNTAX_ERROR` |
| Un domaine de premier niveau inconnu | `unknownTopLevelDomainMessage` | `UNKNOWN_TOP_LEVEL_DOMAIN_ERROR` |
| Un domaine refusé | `deniedDomainMessage` | `DENIED_DOMAIN_ERROR` |
| Un domaine non permis | `domainNotAllowedMessage` | `DOMAIN_NOT_ALLOWED_ERROR` |
| Un domaine jetable | `disposableMessage` | `DISPOSABLE_ERROR` |
| Aucun serveur de courriel | `noMailServerMessage` | `NO_MAIL_SERVER_ERROR` |
| Un MX nul | `refusesMailMessage` | `REFUSES_MAIL_ERROR` |

Chaque message reçoit quatre paramètres : `{{ value }}`, la valeur ; `{{ domain }}`, son domaine en Unicode, vide
quand la syntaxe échoue ; `{{ reason }}`, la `SyntaxError` décrite, vide pour les autres problèmes ; et
`{{ suggestion }}`, l'adresse que la personne voulait sans doute écrire, ou une chaîne vide. Un formulaire montre la
suggestion comme une question, « Vouliez-vous dire … ? », et ne remplace jamais la valeur par elle.

Les messages sont en anglais et passent par le domaine `validators` du Translator, comme ceux de Symfony : une
application les traduit dans son propre `translations/validators.<locale>.xlf`. La bibliothèque ne fournit aucun
catalogue, puisqu'elle n'inscrit rien dans un conteneur.

## Le validateur

`Validator\DeliverableEmailAddressValidator` est bâti sans arguments quand le Validator le crée hors d'un conteneur :
il lit alors les listes fournies une seule fois, à la première valeur qui en a besoin, et interroge
`dns_get_record()`. Une application qui le déclare comme service, avec l'étiquette `validator.constraint_validator`,
passe ses propres listes, son résolveur et son suggesteur — un résolveur avec un cache, une liste fusionnée de domaines
jetables — comme le montre [Installation](installation.md).

Il garde les listes qu'il a lues, les mêmes pour chaque requête, et rien d'une valeur pour la suivante : une seule
instance sert toutes les requêtes d'un *worker* de longue durée.
