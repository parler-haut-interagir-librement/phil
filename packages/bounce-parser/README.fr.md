<!-- phil-dev source-sha256=4572ff1e39b32a7f2b2d55732722e68e28be32dc131a90f7d7cad5181c45ad74 -->
# phil/bounce-parser

**Sachez ce que dit chaque message de votre boîte de rebonds, et quoi faire de l'adresse qu'il nomme.**

phil/bounce-parser lit un message reçu dans une boîte de rebonds et dit ce qu'il est : une notification d'état de remise
(RFC 3464, et RFC 6533 pour les adresses internationales), un rapport de plainte pour abus (RFC 5965), un rebond écrit
en texte par un serveur qui n'envoie pas de notification, ou une réponse automatique (RFC 3834). Il en tire chaque
destinataire, le code d'état étendu (RFC 3463), le genre de rebond — dur, temporaire, plainte, réponse automatique,
succès —, l'action pour l'application, et le message dont il parle, par son `Message-ID`, son `Feedback-ID`, un champ
propre à l'application ou l'étiquette d'un chemin de retour VERP. Des règles que l'application déclare, dans leur ordre,
décident de ce qu'aucune norme ne décrit.

Il s'adresse à toute application PHP qui envoie du courriel par son propre serveur SMTP et lit les rebonds dans une
boîte de courriel : une infolettre, les notifications d'un site, une liste de diffusion. Il transforme la boîte de
rebonds en compteurs de rebonds durs et temporaires, en désabonnements sur plainte, et en une courte file de messages à
faire lire par une personne.

C'est une bibliothèque PHP simple dont les prérequis sont `ext-ctype`, `ext-iconv`, `ext-mbstring` et
`ext-sodium`. Son lecteur MIME ne lève jamais d'exception, quoi que contienne le message, et borne son travail : octets
lus, profondeur, parties, champs d'en-tête et texte. Un rapport de plainte ne désabonne que lorsqu'un authentificateur
s'en porte garant.

La conception : `Mime\MimeParser` lit le message en `Mime\Part` ; `BounceParser` reconnaît le rapport et retourne un
`BounceReport` — `ReportFormat`, `BounceKind`, `BounceAction`, les `Recipient` avec leur `StatusCode`, le
`FeedbackReport`, l'`OriginalMessage` —, puis essaie les `Rule\Rules` ; `Verp` écrit et lit les chemins de retour ;
`Contract\ReportAuthenticator` se porte garant des rapports de plainte.

## État

| Partie | État |
|---|---|
| MIME : champs d'en-tête, mots encodés, types de média, base64, quoted-printable, multiparties, messages contenus, condensés | Implémenté et testé |
| Notifications d'état de remise (RFC 3464, RFC 6533) : chaque destinataire, action, état, diagnostic, code SMTP | Implémenté et testé |
| Codes d'état étendus (RFC 3463) : analyse, repérage dans le texte, échecs qui concernent le message plutôt que la boîte | Implémenté et testé |
| Rapports de plainte (RFC 5965, RFC 6591) : chaque champ, plainte ou non | Implémenté et testé |
| Authentification des rapports : `Authentication-Results` du serveur de l'application, ou confiance en tout rapport | Implémenté et testé |
| Rebonds écrits en texte : expéditeur, objet, première adresse, premier état ou code SMTP | Implémenté et testé |
| Réponses automatiques : `Auto-Submitted`, `X-Autoreply`, `X-Autorespond`, `Precedence: auto_reply` | Implémenté et testé |
| Le message dont parle un rapport : `Message-ID`, `Feedback-ID`, n'importe quel champ, `In-Reply-To` d'une réponse | Implémenté et testé |
| Chemins de retour VERP : étiquettes signées, étiquettes et encodage classique du destinataire | Implémenté et testé |
| Règles sur l'objet, l'expéditeur, le texte, les diagnostics ou un champ, ordonnées par nom, chacune avec son action | Implémenté et testé |
| Règles proposées pour les messages non reconnus | Pas commencé : le travail d'un bundle, avec sa file de révision |
| Lecture d'une boîte de courriel (IMAP, POP3, mbox) | Pas commencé : le travail d'un bundle ; la bibliothèque prend un message sous forme de texte |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, l'analyseur comme service. |
| [Analyse](doc/fr/parsing.md) | Ce que l'analyseur reconnaît, dans quel ordre, et le rapport qu'il retourne. |
| [Genres et actions](doc/fr/kinds-and-actions.md) | Comment un rebond devient dur ou temporaire, et l'action de chaque genre. |
| [Codes d'état](doc/fr/status-codes.md) | `StatusCode`, sa classe, son sujet et son détail, et les échecs qui concernent le message. |
| [Rapports de plainte](doc/fr/feedback-reports.md) | Les rapports de plainte, leurs champs et les authentificateurs. |
| [Retrouver le message](doc/fr/finding-the-message.md) | `OriginalMessage`, `Feedback-ID`, le champ propre à l'application, et VERP. |
| [Règles](doc/fr/rules.md) | Déclarer des règles, leurs champs et leurs portées, les ordonner, et leurs actions. |
| [MIME](doc/fr/mime.md) | Le lecteur MIME utilisé seul, et ses limites. |
| [Menaces](doc/fr/threats.md) | Ce qu'un message hostile peut tenter, et ce que la bibliothèque et l'application y font. |
| [Points d'extension](doc/fr/extension-points.md) | Le port, les arguments et les règles qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le fuzzing et le seuil de mutation, et comment tester du code qui lit des rebonds. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/bounce-parser
```

```php
use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Verp;

$verp = new Verp('bounces', 'mail.example.net');
// Each message is sent with its own return path: $verp->encode($messageId).

$parser = new BounceParser(
    rules: new Rules(Rule::containing('quota', Field::Body, 'over quota', BounceKind::Soft)),
    verp: $verp,
    authenticator: new AuthenticationResults(['mail.example.net'], ['fbl.example.com']),
);

$report = $parser->parse($rawMessage);

$report->kind;            // BounceKind::Hard
$report->action;          // BounceAction::CountHard
$report->addresses();     // ['ada@example.org']
$report->verpTag;         // the identifier the message was sent with
$report->recipients[0]->status?->toString(); // '5.1.1'
```

## Prérequis

PHP 8.5 ou plus récent, `ext-ctype`, `ext-iconv`, `ext-mbstring` et `ext-sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
