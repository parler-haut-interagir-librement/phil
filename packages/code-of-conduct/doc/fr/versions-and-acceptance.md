<!-- phil-dev source-sha256=567a99df5e0593258cfb497129062a90c5d50ec6e635b1eb742298289748f484 -->
# Versions et acceptation

## La publication

`CodeOfConduct::publish()` ajoute la version suivante du code d'une portée : le numéro 1 d'abord, puis chacune à la
suite de l'autre. Une version comprend un titre de 1 à 200 caractères sur une ligne et un texte de 1 à 100 000
caractères, sans espaces en bordure ; elle n'est jamais modifiée une fois publiée. Son `hash` est le SHA-256 de son
titre et de son texte : la preuve de ce qu'on a montré à un membre.

Une version est `ChangeKind::Major` — un changement de fond — ou `ChangeKind::Minor` — une correction, une précision.
La première version est majeure quoi que dise l'appel.

Qui peut publier est une décision de l'application : le service ne le vérifie pas. L'événement `CodeVersionPublished`
nomme la portée et le numéro ; un écouteur informe les membres d'un changement majeur.

## L'acceptation

`accept()` enregistre qu'un membre a accepté la version qu'on lui a montrée, avec son empreinte et l'heure. Seule la
version en vigueur s'accepte : une page restée ouverte pendant la publication d'une nouvelle version est refusée avec
`ActionRefused`, et l'application montre la nouvelle. Chaque acceptation est conservée.

## L'état d'un membre

`status()` répond par un `AcceptanceStatus` :

| Statut | Quand | `mayTakePart()` |
|---|---|---|
| `NoCode` | La portée n'a pas de code | Oui |
| `NotAccepted` | Le membre n'a jamais accepté le code | Non |
| `Outdated` | Le membre a accepté une version antérieure à la dernière version majeure | Non |
| `Accepted` | Le membre a accepté la dernière version majeure, ou une version ultérieure | Oui |

Un membre qui a accepté la version 2 reste `Accepted` après une version mineure 3, et devient `Outdated` après une
version majeure 4.

L'application interroge avant qu'un membre participe — écrire, s'inscrire, publier — et le laisse lire quel que soit
son état : le code retient la participation, jamais la lecture.
