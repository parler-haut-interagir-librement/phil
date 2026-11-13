<!-- phil-dev source-sha256=3cdddd9a3c74592c38f65f618c2bf9c8339443924ac4a81068f1a258d15d4f0e -->
# phil/access-links

**Invitations par lien personnel : un jeton opaque par destinataire, une réponse modifiable jusqu'à la clôture, des rappels à ceux qui n'ont pas répondu.**

phil/access-links invite des destinataires — à un sondage, à un événement, à un vote de planification, à une enquête —
chacun par un lien personnel. Le lien porte un jeton de 256 bits issu du générateur sécurisé de PHP et rien d'autre :
ni adresse courriel, ni nom, ni campagne. Le magasin conserve le HMAC-SHA-256 du jeton sous la clé de l'application, une
liste ordonnée de clés où la première calcule l'empreinte et où chaque clé retrouve un jeton, de sorte que la clé change
sans casser les liens déjà envoyés.

Une invitation a ses moments, lus sur une horloge PSR-20 et comparés par leur ordre : le lien s'ouvre à partir d'un
moment, la réponse change jusqu'à la clôture, et le lien s'ouvre encore, pour montrer la réponse, jusqu'à son
expiration. Ouvrir un lien ne change rien — l'aperçu d'un client de courriel ne dépense rien — ; répondre enregistre la
réponse, et répondre de nouveau la change, jusqu'à la clôture. Un destinataire est nommé par un identifiant stable de
l'application, jamais par une adresse courriel : la bibliothèque ne connaît aucune adresse et n'envoie rien.

Les rappels suivent un calendrier — trois jours, puis sept jours après l'invitation — et ne visent que ceux qui n'ont
pas répondu. Un répartiteur envoie un message de Symfony Messenger par rappel échu, qui ne porte que l'identifiant de
l'invitation ; le *handler* réclame le rappel par un seul `UPDATE` conditionnel, pour que deux *workers* ne l'envoient
jamais deux fois, émet un nouveau lien et le remet à l'expéditeur de l'application, qui trouve comment joindre le
destinataire.

C'est une bibliothèque PHP simple, fondée sur `phil/url` et `psr/clock` ; Symfony Messenger n'est nécessaire que pour le
répartiteur et le *handler*. Les invitations vivent dans un magasin : en mémoire pour les tests et les scripts, ou dans
deux tables d'une connexion Doctrine DBAL sur PostgreSQL, MySQL, MariaDB ou SQLite, testées sur MySQL 8.4 et
PostgreSQL 17. Elle ne garde aucun état entre deux appels et fonctionne dans un *worker*. La page d'invitation et son
formulaire appartiennent à l'application ou à un bundle.

## État

| Partie | État |
|---|---|
| Jetons de 32 octets sécurisés en Base64 sûr pour les URL, lus strictement | Implémenté et testé |
| HMAC-SHA-256 sous une liste ordonnée d'au plus huit clés : la première calcule l'empreinte, chaque clé retrouve | Implémenté et testé |
| Invitations par campagne et par destinataire stable, uniques ; moments d'ouverture, de clôture et d'expiration | Implémenté et testé |
| Ouverture sans effet de bord, réponses modifiables jusqu'à la clôture, révocation, compteurs | Implémenté et testé |
| Liens émis de nouveau, jusqu'à seize par invitation, sur `phil/url` | Implémenté et testé |
| Calendriers de rappels, rappels aux seuls non-répondants, réclamés de façon atomique, par des messages Messenger d'identifiants | Implémenté et testé |
| Magasin en mémoire ; magasin Doctrine DBAL et son schéma, testés sur MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Cible de fuzzing du lecteur de jetons | Implémenté |
| Un bundle Symfony : services issus de la configuration, page et formulaire d'invitation, répartiteur planifié | Non écrit |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, la construction des services. |
| [Invitations](doc/fr/invitations.md) | Inviter, les moments, ouvrir, répondre, révoquer, compter. |
| [Liens](doc/fr/links.md) | Le jeton, son empreinte à clé et la rotation des clés, l'adresse du lien, les liens émis de nouveau. |
| [Rappels](doc/fr/reminders.md) | Le calendrier, le répartiteur, le message, le *handler*, l'expéditeur. |
| [Stockage](doc/fr/storage.md) | Les magasins, les tables, les écritures atomiques. |
| [Exceptions](doc/fr/exceptions.md) | `AccessLinksException` et ses six exceptions. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, arguments et données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests et la cible de fuzzing du paquet, et comment tester du code qui invite. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/access-links
```

```php
use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\InMemoryInvitationStore;
use Phil\AccessLinks\LinkFormat;
use Phil\AccessLinks\ReminderSchedule;
use Phil\AccessLinks\TokenHasher;
use Phil\Url\Url;
use Symfony\Component\Clock\NativeClock;

$links = new AccessLinks(
    new InMemoryInvitationStore(),
    new TokenHasher($_SERVER['ACCESS_LINKS_KEY']),
    new LinkFormat(Url::fromString('https://events.example/rsvp')),
    new NativeClock(),
    new ReminderSchedule(['P3D', 'P7D']),
);

$issued = $links->invite('dinner-2026', 'member-1842', new DateTimeImmutable('2026-12-01 12:00'));
$issued->url;   // https://events.example/rsvp?token=… — send it to member 1842

$access = $links->open($token);              // the invitation, and whether the answer can still change
$links->respond($token, 'yes, with one guest');
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` et `ext-sodium`, `phil/url` et `psr/clock` ;
`symfony/messenger` 8.2 ou une version plus récente pour les rappels sous forme de messages, `doctrine/dbal` 4.5 ou une
version plus récente pour le magasin en base de données.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
