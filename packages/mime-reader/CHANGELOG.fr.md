<!-- phil-dev source-sha256=627eb78aa169c0b1be08320f49185e38fa4f8bf460fb7c7a5cc3ed7a3282fb10 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/mime-reader` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `MimeReader`, qui lit un message Internet (RFC 5322) et sa structure MIME (RFC 2045, 2046) à partir d'une chaîne ou
  d'un flux : les multiparties de chaque sous-type découpées à leurs frontières, `message/rfc822` et `message/global`
  lus comme le message qu'ils contiennent, les parties de `multipart/digest` lues comme messages par défaut, les
  conteneurs encodés décodés d'abord. Il ne lance jamais d'exception sur un message.
- `Limits` : les octets, la profondeur, les parties, les champs d'en-tête et les paramètres lus dans un message ; une
  partie qu'une limite coupe est marquée incomplète. Chaque recherche reste dans sa partie.
- `Part` : les champs d'en-tête, le type de média et ses paramètres, l'encodage de transfert, les parties enfants et le
  message contenu ; `content()`, `text()`, `writeTo()` et `stream()`, qui décodent le base64 et le quoted-printable sur
  demande et par morceaux et convertissent les jeux de caractères en UTF-8, par mbstring ou, pour les jeux qu'il ne
  connaît pas, par iconv ; `disposition()`, `filename()` sans aucun dossier, `isAttachment()`, `textBody()`,
  `htmlBody()`, `attachments()`, `walk()`, `find()`, `fieldGroups()` pour les champs d'état de livraison, d'avis de
  disposition et de rapport de rétroaction, et `create()` pour les parties construites à partir de valeurs.
- `Headers` : les champs dépliés dans leur ordre, les noms comparés sans tenir compte de la casse, `decoded()` pour les
  mots encodés (RFC 2047), `parameterized()` et `mailboxes()`.
- `HeaderValue` : une valeur et ses paramètres, les sections et les jeux de caractères (RFC 2231) joints et décodés.
- `Mailbox` : une adresse telle qu'écrite, son nom d'affichage décodé et son domaine.
- `MimeReaderException`, `InvalidLimits` et `InvalidStream`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- La cible de fuzzing du lecteur et les bancs d'essai de messages de forme ordinaire et hostile.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
