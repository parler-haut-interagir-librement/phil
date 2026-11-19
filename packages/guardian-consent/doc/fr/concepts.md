<!-- phil-dev source-sha256=5beca58121692c7d0b2551206ee7915735696a3bc5b977d866e7caecbbe76632 -->
# Concepts

## Membres et tuteurs

Un membre et un tuteur sont désignés par des identifiants stables que l'application donne, comme les UUID de leurs
comptes : `MemberId` et `GuardianId`. Un identifiant survit à une adresse et est copié dans chaque enregistrement ; un
identifiant qui contient un `@` est donc refusé, tout comme un identifiant vide, un identifiant de plus de 255 octets
et un identifiant qui contient une espace ou un caractère de contrôle.

Un `Guardian` est le titulaire de l'autorité parentale, ou le tuteur, à qui l'on demande de consentir : son
identifiant et l'adresse à laquelle la confirmation est envoyée. L'adresse reste seulement sur la demande en attente.

## Inscrire un membre

`register($member, $birthDate, $jurisdiction)` lit l'âge du membre à partir de sa date de naissance, un
`CalendarDate`, selon les règles de la juridiction — celle par défaut quand aucune n'est nommée — et renvoie un
`AgeAssessment` : les années complètes écoulées, l'`AgeBracket`, et les jours où l'âge du consentement et la majorité
sont atteints.

| Tranche | Quand | Qui consent |
|---|---|---|
| `BelowConsentAge` | Sous l'âge du consentement | Le tuteur, fonctionnalité par fonctionnalité |
| `MinorAtConsentAge` | De l'âge du consentement à la majorité | Le membre |
| `Adult` | À partir de la majorité | Le membre |

Un membre sous l'âge du consentement est gardé comme un `Minor` : sa juridiction et le jour où il atteint l'âge du
consentement, jamais sa date de naissance. Pour un membre déjà gardé, ce jour est corrigé quand il est inscrit de
nouveau avec une autre date. Un membre qui a atteint l'âge du consentement n'est pas gardé : la bibliothèque n'a rien à
dire à son sujet, et ses consentements sont ceux, ordinaires, du site.

## Fonctionnalités

Une `Feature` est une partie du site à laquelle un tuteur consent séparément, nommée par le site : `messages`,
`public-profile`, `classifieds`. La liste est ouverte ; la bibliothèque n'en connaît aucune. Un nom compte de 1 à 64
lettres minuscules, chiffres, `.`, `_` ou `-`, et commence par une lettre ou un chiffre.

## Statuts

`status($member, $feature)` dit où en est le consentement d'un membre à une fonctionnalité ; `allows()` dit si la
fonctionnalité peut être utilisée.

| Statut | Signifie | Permet |
|---|---|---|
| `NotRequired` | Le membre n'est pas gardé comme mineur | Oui |
| `Granted` | Accordé par le tuteur sous l'âge du consentement, par le membre à partir de cet âge | Oui |
| `Pending` | Une demande qui couvre la fonctionnalité attend le tuteur et n'a pas expiré | Non |
| `Refused` | Rien d'accordé, ou refusé, ou retiré | Non |
| `HandoverRequired` | Le membre a atteint l'âge du consentement et n'a pas confirmé ses propres choix | Non |

Le refus est la valeur par défaut : une fonctionnalité sur laquelle personne n'a décidé est refusée.

## Demandes

`request($member, $guardian, $features, $notice)` demande à un tuteur de consentir à 1 à 64 fonctionnalités pour un
membre sous l'âge du consentement, sur une version du texte, une `NoticeVersion` que le site nomme. Elle garde une
`ConsentRequest`, remet la demande et son `ConfirmationToken` au notificateur, qui envoie le courriel, et répartit
`ConsentRequested`.

Une demande peut recevoir une réponse pendant un nombre de jours configurable, sept par défaut. Un membre a au plus
cinq demandes en attente à la fois, afin que la boîte de courriel d'un tuteur ne puisse pas être inondée.

## Le lien de confirmation

Le notificateur construit le lien à partir du texte du jeton. Le lien mène à une page qui appelle `open($token)` et
montre la demande — le membre, les fonctionnalités, la version du texte — avec un formulaire ; suivre le lien ne décide
rien en soi, de sorte qu'un analyseur de courriels qui l'ouvre ne change rien. Le formulaire est envoyé à un contrôleur
qui appelle `decide($token, $granted)` : les fonctionnalités accordées, les autres de la demande refusées.

`decide()` épuise le jeton, enregistre une preuve par fonctionnalité de la demande et répartit `ConsentDecided`. Un
jeton mal formé, inconnu, déjà utilisé ou falsifié lève `InvalidToken`, avec le même message dans tous les cas ; le
jeton d'une demande expirée lève `ExpiredRequest`. Une demande dont la réponse arrive après que le membre a atteint
l'âge du consentement est épuisée sans preuve, et `ConsentNotRequired` est levée.

## Retrait

`revoke($member, $feature, $guardian)` enregistre un retrait : la fonctionnalité est refusée dès lors, et
`ConsentRevoked` est répartie. Un tuteur peut retirer un consentement quand il a déjà décidé d'un des consentements du
membre, ou quand le lien de tuteur le nomme comme gérant le compte du membre ; toute autre personne obtient
`NotAGuardian`. L'application authentifie le tuteur avant de l'appeler.

Une nouvelle demande peut être envoyée après un refus ou un retrait : la fonctionnalité est `Pending` pendant
l'attente.

## Preuves

Une `ConsentProof` enregistre une décision sur une fonctionnalité : le membre, la fonctionnalité, la `Decision`
(`Granted`, `Refused`, `Revoked`), l'`Actor` (`Guardian` ou `Member`), l'identifiant du tuteur, la version du texte, la
demande, et l'instant, en UTC. Les preuves ne font jamais qu'être ajoutées : la dernière preuve d'une fonctionnalité,
celle de l'acteur dont le consentement s'applique, dit où elle en est.

## Le passage de relais

Le jour où un membre atteint l'âge du consentement, dans le fuseau horaire de sa juridiction, les consentements du
tuteur cessent de s'appliquer : chaque fonctionnalité est `HandoverRequired` jusqu'à ce que le membre confirme ses
propres choix. `handOver($member, $granted, $notice)` enregistre les preuves propres du membre pour les fonctionnalités
qu'il accorde, marque l'enregistrement comme remis, abandonne les demandes encore en attente d'un tuteur, et répartit
`HandoverCompleted`. Appelée de nouveau, elle enregistre les changements : une fonctionnalité accordée auparavant et
omise est retirée.

Ni une demande, ni une réponse, ni un retrait par un tuteur n'est accepté à partir de ce jour.

## Le balayage

`sweep($limit)` s'exécute une fois par jour, depuis un planificateur. Il efface les demandes restées sans réponse
au-delà de leur conservation — 30 jours après leur expiration, par défaut —, en répartissant `RequestErased` pour
chacune, et informe les membres qui ont atteint l'âge du consentement : le notificateur envoie l'avis,
l'enregistrement garde le moment, et `HandoverDue` est répartie. Chaque partie traite au plus `$limit`
enregistrements, 100 par défaut et 1000 au plus ; un arriéré plus grand est traité par les exécutions suivantes.

## Événements

| Événement | Quand | Porte |
|---|---|---|
| `ConsentRequested` | Une demande a été envoyée | La demande, le membre |
| `ConsentDecided` | Un tuteur a répondu | La demande, le membre |
| `ConsentRevoked` | Un tuteur a retiré un consentement | Le membre, la fonctionnalité |
| `RequestErased` | Une demande sans réponse a été effacée | La demande, le membre |
| `HandoverDue` | Un membre a atteint l'âge du consentement et en a été informé | Le membre |
| `HandoverCompleted` | Un membre a confirmé ses propres choix | Le membre |

Les événements ne portent que des identifiants, jamais une adresse ni une date de naissance : ils peuvent voyager tels
quels sur une file de messages, et un écouteur lit le reste dans les dépôts.
