<!-- phil-dev source-sha256=679b2b442a6432a610a5e9a9ed0060624e7796f17b4a92e411373f41c43e23ad -->
# Retrouver le message

Un rapport concerne un message que l'application a envoyé ; l'application le retrouve pour savoir de quel envoi, de
quelle campagne et de quel destinataire parle le rapport.

## Le message retourné

`BounceReport::$original` est un `OriginalMessage` construit à partir de la première partie qui contient un message —
`message/rfc822`, `message/global` — ou ses champs d'en-tête — `text/rfc822-headers`, `message/global-headers`. Pour une
réponse automatique qui ne retourne rien, c'est le `In-Reply-To` de la réponse, en tant que `Message-ID`.

| Méthode | Donne |
|---|---|
| `messageId()` | Le `Message-ID` sans ses chevrons |
| `feedbackId()` | Le `Feedback-ID` selon lequel certains fournisseurs de boîtes de courriel regroupent les plaintes |
| `header(string)` | N'importe quel champ : un champ propre à l'application, comme un identifiant de l'envoi |
| `headers` | Tous les champs, sous forme de `Mime\Headers` |

Les serveurs ne retournent souvent que les champs d'en-tête, parfois rien : un champ propre à l'application dans le
message, ou son `Message-ID`, est le chemin de retour le plus sûr — pourvu que ce soit un identifiant, et non un
renseignement personnel.

## VERP

Un chemin de retour d'enveloppe variable (VERP) donne à chaque message son propre chemin de retour : l'adresse
qu'atteint son rebond le nomme, quoi que le rebond dise ou omette.

```php
$verp = new Verp('bounces', 'mail.example.net');

$verp->encodeSigned('m-42', $key);          // bounces+m-42.<32 hex digits>@mail.example.net
$verp->encode('m-42');                      // bounces+m-42@mail.example.net
$verp->encodeRecipient('ada@example.org');  // bounces+ada=example.org@mail.example.net

$verp->decodeSigned($report->verpTag, $key);                       // 'm-42', or null when the tag was not signed
$verp->decode('<bounces+m-42@mail.example.net>');                // 'm-42'
$verp->decodeRecipient('bounces+ada=example.org@mail.example.net'); // 'ada@example.org'
```

L'étiquette ne contient que les caractères d'un atome, sans deux points de suite. Le séparateur est l'un de `+ - = _ .`
— `+` par défaut — et le serveur de courriel remet toute adresse `bounces+…` à la boîte de rebonds. Quand un `Verp` lui
est donné, l'analyseur lit l'étiquette dans les champs `Delivered-To`, `X-Original-To` et `Envelope-To` du rebond, dans
cet ordre, et la place dans `BounceReport::$verpTag`. Ce sont les champs que le serveur de réception ajoute avec le
destinataire de l'enveloppe ; `To` n'est pas lu, puisque l'expéditeur l'écrit. Un serveur qui ajoute un autre champ, ou
aucun de ceux-ci, reçoit sa propre liste :

```php
$parser = new BounceParser(verp: $verp, envelopeFields: ['X-Envelope-To']);
```

N'y inscrire qu'un champ qu'écrit le propre serveur de courriel de l'application : un champ qu'il laisse passer depuis le
message appartient à l'expéditeur.

N'importe qui peut écrire à un chemin de retour. Une étiquette sur laquelle l'application agit — un rebond dur compté,
un destinataire supprimé — doit être une étiquette qu'elle seule peut écrire : `encodeSigned()` ajoute à l'identifiant
un HMAC-SHA256 de l'identifiant et de la boîte sous la clé de l'application, d'au moins 16 octets, tronqué à 128 bits ;
`decodeSigned()` ne rend l'identifiant que si la signature correspond, comparée en temps constant. Une étiquette VERP
classique nomme le destinataire en clair, devant chaque serveur en route, et n'importe qui peut en écrire une :
`encodeRecipient()` suffit à trier la boîte, pas à décider une suppression.
