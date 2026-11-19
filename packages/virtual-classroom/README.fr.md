<!-- phil-dev source-sha256=705d63dd95a61b0f55ab39b31d37d4089055609900d0fc47f9fcb2225ec0fd50 -->
# phil/virtual-classroom

**Des cours en ligne en direct par un seul port : ouvrir une salle sur un serveur de conférence, écrire le lien par
lequel chaque participant la rejoint — signé pour un modérateur ou un participant —, la terminer, savoir qui a assisté
et pendant combien de temps, et retrouver ses enregistrements ; BigBlueButton et Jitsi d'abord.**

phil/virtual-classroom est une bibliothèque PHP simple. Une application parle à une **`VirtualClassroom`** : elle ouvre
une salle à partir d'un `RoomSpec` — son identifiant, son titre, si elle enregistre, la durée maximale, les adresses
que le serveur rappelle —, écrit le **lien d'accès** d'un `Participant` pour un `Role`, termine la salle, demande qui y
est en ce moment, liste ses **enregistrements** et lit les **notifications** que le serveur envoie — arrivées et
départs de participants, salle terminée, enregistrement prêt — sous forme de `RoomEvent`. `AttendanceSheet` transforme
les séjours d'un participant en secondes et en part de la séance à laquelle il a assisté, les chevauchements comptés
une seule fois.

Deux adaptateurs sont fournis. **`BigBlueButton`** appelle l'API de son serveur par les contrats du client HTTP de
Symfony, chaque appel signé par la somme de contrôle du secret qu'il partage avec le serveur, chaque appel borné en
temps et en taille ; il lit la fin d'une salle, la notification d'enregistrement prêt signée en HS256 et la présence
que poste son rappel d'analyse de l'apprentissage. **`Jitsi`** écrit des liens d'accès qui portent un jeton Web JSON :
HS256 pour un serveur propre à l'application, RS256 pour JaaS, le Jitsi hébergé de 8x8 ; il lit les notifications que
JaaS signe en HMAC-SHA-256. Jitsi n'a pas d'API pour terminer une salle ni pour lister qui s'y trouve : ces appels
lèvent `Unsupported`, et `capabilities()` le dit d'avance.

Toute défaillance est une exception de la bibliothèque — `ProviderUnavailable`, `ProviderRefused`, `InvalidResponse`,
`InvalidWebhook`, `Unsupported`, `InvalidValue` — dont le message ne nomme aucun participant et ne porte aucune adresse
d'appel. Les participants circulent par l'identifiant stable de leur compte, jamais par leur adresse courriel. Les
adaptateurs ne gardent aucun état entre les appels et conviennent à un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `VirtualClassroom`, `RoomEvent`, `RoomId`, `ParticipantId`, `Participant`, `Role`, `RoomSpec`, `OpenedRoom`, `Capabilities`, `Presences`, `RoomState`, `Recording`, `Webhook` | Implémentés et testés |
| Les événements `ParticipantJoined`, `ParticipantLeft`, `RoomEnded`, `RecordingReady` et les exceptions | Implémentés et testés |
| `AttendanceSheet`, `Interval` : les secondes et la part d'une séance suivie | Implémentés et testés |
| `HttpBudget` : le temps et la taille qu'un appel peut prendre | Implémenté et testé |
| `BigBlueButton`, `ChecksumAlgorithm` : salles, liens d'accès, fin, présences, enregistrements, notifications | Implémentés et testés |
| `Jitsi` : liens d'accès pour un serveur propre à l'application ou JaaS, notifications | Implémenté et testé |
| Adaptateurs Zoom et Microsoft Teams | Non écrits |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Salles et liens d'accès](doc/fr/rooms.md) | Le port, la vie d'une salle, les rôles, les participants. |
| [BigBlueButton](doc/fr/bigbluebutton.md) | L'adaptateur : sommes de contrôle, rappels, présences, enregistrements, assiduité. |
| [Jitsi](doc/fr/jitsi.md) | L'adaptateur : jetons pour un serveur propre à l'application et JaaS, notifications, limites. |
| [Assiduité](doc/fr/attendance.md) | Les séjours, les événements et la part d'une séance suivie. |
| [Erreurs et budgets](doc/fr/errors.md) | Les exceptions, les bornes de temps et de taille, ce que les journaux peuvent garder. |
| [Sécurité](doc/fr/security.md) | Les secrets, les signatures, les renseignements personnels, et ce qui revient à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Écrire un autre adaptateur, et quand l'API est stable. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Phil\Url\Url;
use Phil\VirtualClassroom\BigBlueButton\BigBlueButton;
use Phil\VirtualClassroom\Participant;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Role;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroom\RoomSpec;

$server = new BigBlueButton($httpClient, Url::fromString('https://bbb.example.com/bigbluebutton/api/'), $secret, $clock);

$room = $server->open(new RoomSpec(RoomId::generate(), 'Algebra, week 3', record: true, maxMinutes: 90));
$link = $server->joinUrl($room, new Participant(ParticipantId::fromString('user-7'), 'Ada Lovelace'), Role::Attendee);
```

## Prérequis

PHP 8.5 ou une version plus récente avec les extensions `dom`, `libxml`, `mbstring`, `openssl` et `sodium` ; une
implémentation des contrats du client HTTP de Symfony, `symfony/http-client`, pour BigBlueButton.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
