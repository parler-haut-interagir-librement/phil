<!-- phil-dev source-sha256=76381941e62f8e07bc334d39583855abfe84120b6f5f2fddc0ebd89c7fac5bee -->
# Participants, propositions et calendriers de membres

Un membre est une personne de l'application, nommée comme le sont les organisateurs : un genre et une référence,
`Member::of('member', '42')`, gardés sous la forme `member:42`. Le calendrier n'en sait pas davantage : qui ils sont et
ce qu'ils peuvent faire relève de l'application, dont le contrôle d'accès décide qui peut s'inscrire, proposer ou
approuver.

## Participants

`Attendees` inscrit des membres à une occurrence, ou à toutes les occurrences en les faisant participer à l'événement
lui-même.

```php
$attendees->attend($occurrence, Member::of('member', '42'));   // one date
$attendees->attend($event, Member::of('member', '7'));         // every date
$attendees->count($occurrence);                                // both kinds
$attendees->placesLeft($occurrence);                           // null without a capacity
$attendees->attendees($occurrence, limit: 100, offset: 0);     // in the order they came
$attendees->withdraw($occurrence, Member::of('member', '42'));
```

Un événement a une capacité, `Event::limitTo()` ou les *Places* du formulaire : combien de personnes peuvent participer
à chaque occurrence, de 1 à 1 000 000, ou aucune pour ne pas limiter. Celles d'une occurrence et celles de toutes les
occurrences s'y comptent. S'inscrire de nouveau donne la participation gardée. Une liste de participants se lit une
page à la fois, 500 au plus, avec DBAL : un événement de milliers de participants ne charge que les noms qu'une page
montre.

Une participation est refusée par une `AttendanceRefused`, dont la `reason` en donne la raison :

| Raison | Quand |
|---|---|
| `cancelled` | L'événement est annulé |
| `not_published` | L'événement est une proposition non approuvée, ou refusée |
| `not_an_occurrence` | Aucune occurrence de l'événement ne commence à ce moment, ou elle a été retirée |
| `full` | La capacité est atteinte |
| `policy` | L'`AttendancePolicy` de l'application ne l'admet pas |

La capacité est lue avant que la ligne soit écrite : deux membres qui prennent la dernière place au même moment
peuvent l'obtenir tous les deux. Une application qui vend ses places les retient dans sa politique, sous un verrou qui
lui est propre.

### Participation payante

Le calendrier ne prend aucun paiement. Une `AttendancePolicy`, enregistrée sous son interface, est consultée en dernier,
une fois les vérifications du calendrier passées : une politique de l'application admet un membre lorsque sa commande
est payée, son billet réservé ou son adhésion en cours.

```php
final readonly class PaidAttendance implements AttendancePolicy
{
    public function admits(Event $event, ?DateTimeImmutable $occurrence, Member $member): bool
    {
        return $this->orders->hasPaid($member->reference, 'event:'.$event->id);
    }
}
```

## Le calendrier d'un membre

`PersonalCalendar::occurrences($member, $from, $through)` donne les occurrences propres à un membre pour une période,
dans l'ordre, une seule fois chacune :

- celles auxquelles il participe : les dates où il s'est inscrit, chaque date d'un événement auquel il participe ;
- celles des événements qu'il organise, lorsque l'application le nomme comme organisateur par les mêmes genre et
  référence ; `hosted: false` les laisse de côté ;
- celles des sources qu'il suit — le calendrier d'un organisateur, une étiquette, un lieu, un événement —, moins celles
  qu'il masque.

Ce qu'un membre fréquente ou organise est toujours là : masquer une source retire seulement ce que le suivi apportait,
et masquer l'emporte sur suivre. La période est lue une fois, ses organisateurs en une seule requête, et ses
occurrences comptent dans le [budget](events.md#le-budget-dune-requête) de la requête. Dans Twig,
`calendar_personal(kind, reference, from, through)` ; sur une page, le composant avec un `member`
([Vues](views.md#le-composant)).

### Suivre et masquer

`CalendarChoices` garde ce que chaque membre suit et masque, `MAX` (200) choix au plus, dans la table
`phil_calendar_choice`, le membre par son `kind:reference`, le moment où il a choisi en UTC.

```php
$choices->follow($member, ChoiceSource::Host, 'association:42');   // a host's calendar
$choices->follow($member, ChoiceSource::Tag, 'larp');
$choices->hide($member, ChoiceSource::Venue, $venue->id);
$choices->hide($member, ChoiceSource::Event, $event->id);
$choices->forget($member, ChoiceSource::Tag, 'larp');              // neither followed nor hidden
$choices->of($member);                                               // MemberChoices
```

| Source | Valeur |
|---|---|
| `Host` | `kind:reference`, chacun de 1 à 64 lettres, chiffres, `_`, `.`, `:` ou `-` |
| `Tag` | De 1 à 100 caractères, sans caractère de contrôle |
| `Venue`, `Event` | L'identifiant, un UUID de 36 caractères minuscules |

Une valeur que sa source ne nomme pas, ou un choix au-delà de 200, est un `InvalidChoice`. Suivre une source masquée la
montre de nouveau, et masquer une source suivie modifie le même choix. `MemberChoices` les liste — `followed($source)`,
`hidden($source)` — et dit si un événement est suivi ou masqué, par lui-même, son lieu, l'un de ses organisateurs ou
l'une de ses étiquettes.

Le formulaire est `CalendarChoiceType`, vers un `CalendarChoiceDraft` que `CalendarChoices::apply($member, $draft)`
conserve ; le gabarit `@PhilCalendar/calendar/_choices.html.twig` liste les choix d'un membre avec les recettes du
kit ; voir [Formulaires et Twig](forms-twig.md#les-choix-dun-membre).

### Le flux d'un membre

`PersonalFeeds` donne à chaque membre un secret, l'unique clé de l'adresse de son propre flux iCalendar :

```php
$secret = $feeds->issue($member);    // shown once; a new one revokes the one before
$url = $urls->generate('phil_calendar_personal_ics', ['token' => $secret], UrlGeneratorInterface::ABSOLUTE_URL);
$feeds->revoke($member);             // the address answers no more
$feeds->issuedAt($member);           // in UTC, or null
```

Un secret est fait de 32 octets aléatoires écrits en 64 chiffres hexadécimaux. Seule son empreinte SHA-256 est
conservée, dans `phil_calendar_feed_token` : l'adresse ne peut pas être relue dans la base de données, de sorte qu'une
page la montre au moment de l'émission et en propose une nouvelle ensuite. Le flux contient le calendrier du membre
d'il y a un mois jusqu'à dans un an, chaque occurrence en un `VEVENT` à elle ; voir
[Le flux](feeds.md#le-flux-dun-membre).

### Vie privée

Avec `phil/privacy-bundle` installé, `CalendarPrivacy` se joint à l'exportation et à l'effacement d'une personne. La
personne concernée est la référence d'un membre du genre `privacy_member_kind`, `member` par défaut : le sujet `42` est
`member:42`. L'exportation liste ses choix, le moment où le secret de son flux a été émis — jamais le secret —, ce à
quoi il participe et les événements qu'il a proposés. L'effacement supprime ses choix, son secret et ses participations,
et les événements qu'il a proposés ne le nomment plus ; les événements restent. Relancé, il ne trouve plus rien et
rapporte la même chose.

## Propositions

Un membre propose un événement ; un modérateur l'approuve ou le refuse. `Proposals::propose($draft, $member)` crée
l'événement à partir d'un brouillon comme n'importe quel autre, tenu hors du calendrier (ses vues, son flux, ses
occurrences) jusqu'à ce qu'il soit approuvé.

```php
$event = $proposals->propose($draft, Member::of('member', '42'));
$proposals->waiting();          // the proposals by their start, 100 by default, 500 at most
$proposals->approve($event);    // published
$proposals->refuse($event);     // refused; it can be approved later
```

Les états et les transitions sont la machine à états `phil_calendar_proposal` de `symfony/workflow`, définie par
`ProposalWorkflow` :

| Transition | De | À |
|---|---|---|
| `approve` | `proposed`, `refused` | `published` |
| `refuse` | `proposed` | `refused` |

Les événements du flux de travail passent par le répartiteur d'événements de l'application : une garde sur
`workflow.phil_calendar_proposal.guard.approve` retient une approbation, un écouteur sur
`workflow.phil_calendar_proposal.completed.approve` en avise le membre. Une transition qui n'est pas permise est une
`NotEnabledTransitionException` ; `can($event, 'approve')` le demande d'abord. `Event::proposedBy()` nomme le membre.
