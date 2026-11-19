<!-- phil-dev source-sha256=e1134c417fb2253502cbd1ec7a852b5049433f0de8978959be071be68c2f06a7 -->
# Lire un message

## Le lecteur

`MimeReader::read()` prend le message brut sous forme de chaîne ; `readStream()` prend un flux ouvert et lit à partir
de sa position courante, au plus les octets des limites. Les deux retournent le message sous forme de `Part`, la racine
de son arbre.

```php
$reader = new MimeReader(new Limits(maxBytes: 10_485_760));
$message = $reader->read($raw);
$message = $reader->readStream(fopen('php://stdin', 'rb'));
```

Les lignes d'en-tête se terminent à la première ligne vide, écrite avec un saut de ligne ou avec un retour de chariot
et un saut de ligne. Une partie sans ligne vide est une partie de lignes d'en-tête seulement ; une partie qui commence
par une ligne vide n'a aucun champ d'en-tête.

## Les parties

Une `Part` contient :

| Propriété | Ce qu'elle contient |
|---|---|
| `headers` | Ses champs d'en-tête, sous forme de `Headers` |
| `type` | Son type de média en minuscules ; `text/plain` quand aucun n'est valide, `message/rfc822` dans un digest |
| `parameters` | Les paramètres de son type, par nom en minuscules, décodés |
| `encoding` | Son encodage de transfert en minuscules, vide quand aucun n'est donné |
| `parts` | Ses parties enfants, quand c'est une multipartie |
| `message` | Le message qu'elle contient, quand c'est un `message/rfc822` ou un `message/global` |
| `incomplete` | Si une limite l'a coupée |

Une multipartie de n'importe quel sous-type est découpée à sa frontière. Un délimiteur compte au début d'une ligne,
suivi de rien d'autre que des espaces ; le saut de ligne qui le précède lui appartient ; le préambule et l'épilogue ne
sont pas des parties, et une multipartie dont la ligne de fermeture manque se termine avec son texte. Un conteneur
encodé, alors qu'il ne devrait pas l'être, est décodé avant la lecture de ses parties.

`walk()` donne la partie et chaque partie sous elle, en profondeur d'abord ; `walk(true)` entre aussi dans les
messages qu'elle contient. `find($type)` donne la première partie d'un type, sans entrer dans les messages contenus.

## Les champs d'en-tête

`Headers` garde les champs dans leur ordre, dépliés et sans espaces aux extrémités, leurs noms étant comparés sans
tenir compte de la casse.

| Méthode | Donne |
|---|---|
| `get($name)` | La valeur du premier champ, telle qu'écrite |
| `all($name)` | Les valeurs de chaque champ de ce nom |
| `has($name)` | Si un champ de ce nom est présent |
| `decoded($name)` | La valeur dont les mots encodés (RFC 2047) sont décodés, toujours de l'UTF-8 valide |
| `parameterized($name)` | Un `HeaderValue` : la valeur avant le premier point-virgule et ses paramètres |
| `mailboxes($name)` | La liste de `Mailbox` de chaque champ de ce nom |

Les mots encodés sont décodés en B et en Q, l'espace entre deux d'entre eux est supprimé, et les octets de mots
adjacents d'un même jeu de caractères sont joints avant la conversion, de sorte qu'un caractère coupé entre deux mots
est lu en entier. Le texte brut sur 8 bits dans un champ est conservé, son UTF-8 invalide étant remplacé.

`HeaderValue::parse()` joint dans leur ordre les sections d'un paramètre (`filename*0`, `filename*1`), décode les
pourcentages des sections étendues et les convertit depuis le jeu de caractères de la première
(`filename*=UTF-8''r%C3%A9sum%C3%A9.pdf`). Un paramètre écrit sous forme de mots encodés, comme le font certains
logiciels de courriel pour un nom de fichier, est aussi décodé.

`mailboxes()` lit les listes d'adresses : noms d'affichage entre guillemets ou non, commentaires ignorés, groupes
aplatis. Une `Mailbox` contient l'adresse telle qu'écrite et son nom d'affichage décodé ; `domain()` donne son domaine
en minuscules. L'adresse n'est pas validée : vérifiez-la avec les règles propres à l'application avant de lui envoyer
quoi que ce soit.

## Les corps

| Méthode | Donne |
|---|---|
| `content()` | Le corps dont l'encodage de transfert est défait, en octets |
| `text()` | Le corps converti de son `charset` en UTF-8 |
| `writeTo($stream)` | Écrit le corps décodé vers un flux ouvert, morceau par morceau ; retourne les octets écrits |
| `stream()` | Le corps décodé dans un flux temporaire, rembobiné, en mémoire jusqu'à 2 MiB et sur disque au-delà |

Le base64 écarte ce qui n'est pas de son alphabet et décode un dernier groupe incomplet ; le quoted-printable garde une
séquence d'échappement mal formée telle qu'écrite ; tout autre encodage donne les octets tels quels. Les parties d'un
même message partagent les octets lus : une partie en tient une plage, et ne la décode que sur demande.

`text()` lit un jeu de caractères manquant, `us-ascii` ou inconnu comme de l'UTF-8 dont les séquences invalides sont
remplacées, et l'ISO-8859-1 comme du Windows-1252, son sur-ensemble, comme le font les navigateurs. Un jeu que mbstring
ne connaît pas, comme TIS-620 ou Macintosh, est converti par iconv, qui écarte les octets que le jeu ne définit pas. Un
nom qui est un encodage de transfert ou un schéma d'entités plutôt qu'un jeu de caractères n'est pas utilisé pour la
conversion.

## Texte, HTML et pièces jointes

`textBody()` et `htmlBody()` donnent le texte de la première partie `text/plain` ou `text/html` qui n'est pas une pièce
jointe, hors des messages contenus. Le HTML est retourné comme du texte : rien dedans n'est exécuté, récupéré ni
nettoyé, ce qui est le travail d'un assainisseur de HTML avant l'affichage.

Une partie est une pièce jointe quand elle est ainsi disposée, ou quand elle est nommée sans être disposée en ligne ;
une image en ligne d'un `multipart/related` n'en est pas une. `attachments()` les liste, hors des messages contenus.
`filename()` donne le nom tiré de la disposition ou, à défaut, du type, décodé, sans répertoire, sans caractère de
contrôle ni point initial, coupé à 255 octets.

## Les rapports

Les avis de livraison (RFC 3464), les avis de disposition (RFC 8098), les rapports de rétroaction (RFC 5965) et les
parties `text/rfc822-headers` écrivent des groupes de champs séparés par des lignes vides. `fieldGroups()` les lit
comme une liste de `Headers`, les groupes vides étant ignorés :

```php
$report = $message->find('message/delivery-status');
[$perMessage, $firstRecipient] = $report->fieldGroups();
$firstRecipient->get('Final-Recipient'); // 'rfc822; gone@example.com'
$firstRecipient->get('Status');          // '5.1.1'

$returned = $message->find('text/rfc822-headers')?->fieldGroups()[0];
$returned?->get('Message-ID');
```

Un message retourné en entier est une partie `message/rfc822`, lue comme le message qu'elle contient :
`$part->message->headers`.
