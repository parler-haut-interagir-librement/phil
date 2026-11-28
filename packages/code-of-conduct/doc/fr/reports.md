<!-- phil-dev source-sha256=aca78e96def7dbae2bdea0f9dd39cea4bad9df93290a96559133b4b075311a2a -->
# Signalements

Tout chemin vers les signalements passe par `Reports`. Chaque méthode reçoit l'identifiant d'une personne que
l'application a déjà authentifiée : le service décide ce que cette personne peut faire, jamais qui elle est. Un
identifiant compte de 1 à 255 octets sans caractère de contrôle ; utilisez un identifiant stable, jamais une adresse
courriel.

## Le dépôt

`file()` reçoit la portée, le membre qui dépose le signalement ou `null` pour un signalement anonyme, les membres qu'il
nomme — 20 au plus — et ce qui s'est passé, de 1 à 20 000 caractères. Elle enregistre la version du code en vigueur,
scelle le texte et sauvegarde le signalement comme `ReportStatus::Received`. L'événement `ReportFiled` nomme le
signalement et sa portée ; un écouteur avise les responsables qui peuvent le traiter.

Un signalement qu'aucun responsable ne pourrait recevoir — la portée n'en a aucun, ou le signalement les nomme tous —
est refusé avec `ActionRefused`, et l'application dirige le membre vers le contact hors du site.

## L'accusé de réception

`receipt()` montre au membre qui a déposé un signalement qu'il a été reçu, où il en est et depuis quand : jamais les
notes, l'issue ni qui le traite. Un signalement anonyme n'a pas d'accusé de réception.

## Qui traite un signalement

Une personne traite un signalement quand elle fait partie des responsables de sa portée, tels que
`Contract\ResponsiblePeople` les liste maintenant — 100 au plus —, que le signalement ne la nomme pas et qu'elle ne
s'est pas retirée. Toute autre personne reçoit `ReportUnavailable`, dont le message est le même que le signalement
n'existe pas ou qu'on ne puisse pas le voir.

| Chemin | Ce qu'il fait | Événement |
|---|---|---|
| `queue()` | Les signalements de la portée que la personne peut traiter, du plus ancien au plus récent, de 1 à 100 à la fois, par statut | Aucun |
| `read()` | Le signalement, son texte et ses notes, ouverts | `ReportViewed`, avant l'ouverture des textes |
| `addNote()` | Une note de 1 à 10 000 caractères, scellée ; 500 notes par signalement au plus | `ReportNoteAdded` |
| `review()` | Met en révision un signalement reçu, réglé ou rejeté | `ReportStatusChanged` |
| `resolve()` | Clôt un signalement en révision avec une `Outcome` | `ReportStatusChanged` avec l'issue |
| `dismiss()` | Clôt un signalement reçu ou en révision sans issue | `ReportStatusChanged` |
| `recuse()` | La personne se retire : dès lors, elle ne voit plus le signalement | `ReportRecused` |

Une page de `queue()` omet les signalements que la personne ne peut pas traiter ; elle peut donc en contenir moins que
demandé. La page suivante commence après `ReportPage::$next`, `null` sur la dernière page.

## Statuts et issues

| De | Peut devenir |
|---|---|
| `Received` | `InReview`, `Dismissed` |
| `InReview` | `Resolved`, `Dismissed` |
| `Resolved`, `Dismissed` | `InReview` |

Une issue accompagne seulement `Resolved` : `NoAction`, `Correction`, `Warning`, `TemporaryExclusion` ou
`PermanentExclusion`. Le paquet l'enregistre ; l'application l'applique par sa modération, à partir de l'événement
`ReportStatusChanged`.

## Le scellement des textes

Le texte d'un signalement et chaque note sont scellés par `KeyManagement\KeyManagementTextSealer`, le chiffrement
d'enveloppe du composant KeyManagement de Symfony : une clé de données neuve par texte, enveloppée par la clé
maîtresse, le texte chiffré localement avec AES-256-GCM. Le contexte authentifié avec un texte nomme le signalement, et
la note pour une note : un texte déplacé vers un autre signalement ne s'ouvre pas, et `read()` le refuse avec
`UnreadableText`. Le magasin garde en clair la portée, les personnes nommées, l'auteur, les statuts et les dates, afin
de pouvoir trouver et lister les signalements.

`ReportView` et `NoteView` n'exposent jamais leurs textes : `var_dump()`, `print_r()` et les débogueurs affichent
`[sensitive]`.

## Les événements

Chaque événement ne porte que des identifiants — le signalement, la portée, la note, la personne, le statut, l'issue —,
jamais un texte. `ReportViewed` est la trace de qui a ouvert quel signalement ; une application qui garde une piste
d'audit l'écoute.
