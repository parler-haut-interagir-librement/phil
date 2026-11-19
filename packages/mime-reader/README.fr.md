<!-- phil-dev source-sha256=22ffb548d276b4b74fb33a2380ec6261b7b00a65f224386bd05f9dc93d58e917 -->
# phil/mime-reader

**Des messages Internet lus avec leur structure MIME, en sécurité, quoi que l'expéditeur ait écrit.**

phil/mime-reader lit un courriel brut — tel que le reçoit une boîte de rebonds, une adresse entrante ou une archive —
en un arbre de parties : ses champs d'en-tête, dont les lignes repliées et les mots encodés sont décodés en UTF-8, ses
types de média et leurs paramètres, sections et jeux de caractères de la RFC 2231 compris, ses multiparties de tout
sous-type (`mixed`, `alternative`, `related`, `report`, `digest`), les messages qu'il contient (`message/rfc822`,
`message/global`), et les groupes de champs des rapports de livraison et des rapports de rétroaction.

Les corps restent encodés jusqu'à ce qu'on les demande à une partie : `content()` défait le base64 ou le
quoted-printable, `text()` convertit le jeu de caractères déclaré en UTF-8, et `writeTo()` envoie une pièce jointe
vers un flux morceau par morceau, sans jamais la garder décodée en entier. Les noms de fichier sont proposés sans
répertoire ni caractère de contrôle.

Le lecteur est fait pour des entrées hostiles. Il ne lance jamais d'exception sur un message : ce qu'il ne sait pas
lire est gardé comme texte. Les octets lus, l'imbrication, le nombre de parties, les champs d'en-tête d'une partie et
les paramètres d'un champ sont bornés par `Limits`, la partie qu'une limite coupe est marquée incomplète, et chaque
recherche reste dans sa partie, de sorte que le temps passé croît avec les octets du message. Rien dans un message
n'est exécuté, récupéré ni désérialisé.

C'est une bibliothèque PHP simple, sans dépendance au-delà de `ext-iconv`, `ext-mbstring` et `ext-sodium`.

## État

| Partie | État |
|---|---|
| Champs d'en-tête : dépliage, mots encodés (RFC 2047), listes d'adresses | Implémenté et testé |
| Paramètres, avec sections et jeux de caractères (RFC 2231) | Implémenté et testé |
| Multiparties de tout sous-type, imbriquées ; messages contenus ; digests | Implémenté, testé et soumis au fuzzing |
| Base64 et quoted-printable, décodés à la demande et par morceaux | Implémenté et testé |
| Jeux de caractères convertis en UTF-8 | Implémenté et testé |
| Groupes de champs des rapports et de `text/rfc822-headers` | Implémenté et testé |
| Corps texte et HTML, pièces jointes, noms de fichier sûrs, flux | Implémenté et testé |
| Limites sur les octets, la profondeur, les parties, les champs et les paramètres | Implémenté, testé et mesuré par des bancs d'essai |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Lire un message](doc/fr/reading.md) | Le lecteur, les parties, les champs d'en-tête, les corps, les pièces jointes, les rapports. |
| [Entrée hostile](doc/fr/hostile-input.md) | Les limites, ce que le lecteur refuse, ce qu'il ne fait jamais. |
| [Symfony Mime](doc/fr/symfony-mime.md) | Ce qu'offre `symfony/mime`, et pourquoi le lecteur ne s'appuie pas dessus. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure ou sur quoi elle peut compter. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et les bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/mime-reader
```

```php
use Phil\MimeReader\MimeReader;

$message = new MimeReader()->read($raw);

$message->headers->decoded('Subject');      // 'Rapport de la semaine'
$message->headers->mailboxes('From');       // [Mailbox('help@example.org', 'Support')]
$message->textBody();                       // the text, in UTF-8

foreach ($message->attachments() as $attachment) {
    $attachment->filename();                // 'résumé.pdf', never a path
    $attachment->writeTo($stream);          // decoded chunk by chunk
}

$status = $message->find('message/delivery-status');
$status?->fieldGroups()[1]->get('Status'); // '5.1.1'
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-iconv`, `ext-mbstring` et `ext-sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
