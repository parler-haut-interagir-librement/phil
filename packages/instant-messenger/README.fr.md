<!-- phil-dev source-sha256=f4913b56edd47b20af9cb8b0dc9f3c5b09a57ac5c9cc25d2c488f9a52edb7d3b -->
# phil/instant-messenger

**Des identifiants de messagerie lus, affichés et liés selon chaque service : WhatsApp, Telegram, Signal, Matrix et
d'autres.**

`phil/instant-messenger` lit un identifiant de messagerie tel que les gens le tapent, ou à partir du lien propre au
service, et le garde sous forme canonique dans un `Handle`. Les services de messagerie sont des cas d'enum qui
implémentent `MessengerType`, et chacun lit, affiche et lie ses propres identifiants : un nom d'utilisateur Telegram, un
numéro de téléphone WhatsApp, un identifiant Matrix, une adresse courriel iMessage. `KnownMessenger` contient 14
services internationaux, et `Messengers` détecte le service d'un identifiant parmi les ensembles d'enums qu'un champ
accepte. **Il n'envoie pas** de messages, ne vérifie pas qu'un compte existe et ne contient pas les services d'une
région.

Il s'adresse aux applications où les gens laissent un moyen de les joindre : un profil de membre, les coordonnées d'une
boutique, la page d'un organisateur. Les services propres à une région relèvent de l'application, sous forme de sa
propre enum, acceptée à côté de `KnownMessenger`. Les identifiants se chevauchent : un nom d'utilisateur peut être lu
aussi bien par Telegram que par Line et Discord, et un numéro de téléphone par WhatsApp, Signal, Viber et iMessage. La
détection est fiable pour les liens et pour les formes distinctives comme un identifiant Matrix ; ailleurs, demandez le
service à la personne.

`phil/instant-messenger` est une simple bibliothèque PHP sur `phil/enums`, `phil/phone` et `phil/url`. `phil/phone` lit
les numéros de téléphone : chacun doit exister dans le plan de son pays, est gardé en E.164 et est affiché au format
international. Dans une application Symfony, utilisez `phil/instant-messenger-bundle`, dont le champ de formulaire
demande le service et l'identifiant. La conception suit `phil/url` : un service est n'importe quel cas de n'importe
quelle enum qui connaît ses propres identifiants, et un `Handle` est un service accompagné d'une valeur canonique.

## État

| Partie | État |
|---|---|
| `MessengerType`, et `KnownMessenger` : 14 services internationaux | Implémenté et testé |
| Les identifiants lus à partir de ce que tapent les gens et des liens des services | Implémenté et testé |
| `Handle`, et `Messengers` : la détection du service parmi des ensembles d'enums | Implémenté et testé |
| Les numéros de téléphone lus par `phil/phone`, dans le plan de leur pays, affichés au format international | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Services](doc/fr/services.md) | `MessengerType`, les services internationaux et leurs identifiants, ceux d'une application. |
| [Identifiants](doc/fr/handles.md) | `Handle`, la détection parmi des ensembles d'enums, et quand demander plutôt le service. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/instant-messenger
```

```php
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;

$handle = Handle::of(KnownMessenger::Telegram, 'https://t.me/AdaLove');
$handle->value;      // "adalove"
$handle->display();  // "@adalove"
$handle->link();     // "https://t.me/adalove"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `phil/enums` 0.3, `phil/phone` 0.1 et `phil/url` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
