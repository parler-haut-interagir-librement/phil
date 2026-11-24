<!-- phil-dev source-sha256=c6a26641f28e628147f391089ae5fc8543e341e40d4ded8c733d35cb3eb35372 -->
# Sondages et réponses

`SchedulingPolls` est le service de l'application : il crée les sondages, inscrit les répondants, ouvre leurs liens et
enregistre leurs réponses. Il ne garde aucun état entre deux appels et est sûr dans un *worker* de longue durée.

## Créer un sondage

```php
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\SchedulingPollBundle\Application\ProposedSlot;
use Phil\SchedulingPollBundle\Application\SchedulingPollDraft;

$poll = $polls->create(new SchedulingPollDraft(
    'Game night',
    [$slot1, $slot2, $slot3],                       // ProposedSlot: a start and an end, excluded, in any time zone
    'America/Toronto',                              // the time zone the dates are shown in
    new DateTimeImmutable('2026-11-28 12:00 UTC'),  // the deadline
    description: 'Bring dice.',
    location: 'The back room',
    organizerSeesAnswers: true,
    results: ResultsVisibility::Always,
), ResourceReference::of('user', 'ada'));
```
| Valeur | Limites |
|---|---|
| Titre | De 1 à 200 caractères, sans les espaces de bordure |
| Description, lieu | 4000 et 255 caractères au plus ; un blanc vaut aucun |
| Dates | De 2 à 100, chacune se terminant après son début et au plus quatorze jours plus tard, deux ne commençant pas au même moment |
| Fuseau horaire | Un nom de la base de données IANA, comme `America/Toronto` |
| Échéance | Après la création |

Une valeur hors limites lance `InvalidSchedulingPoll`, dont le message dit quelle limite. Les instants sont conservés en
UTC et comparés par ordre, jamais par égalité ; le fuseau horaire ne fait que les afficher. L'organisateur est une
référence de `phil/resource` : lui seul invite, émet de nouveau des liens, révoque, clôt et voit les réponses de tous.

## Les répondants et leurs liens

Un répondant se joint depuis la page du sondage — la route `phil_scheduling_poll_join` — avec son nom seulement, ou est
invité par l'organisateur :

```php
$issued = $polls->join($poll->id, 'Bob');                                  // from the poll's page
$issued = $polls->join($poll->id, 'Bob', 'bob@example.org', $account);     // an address, an account: both optional
$issued = $polls->invite($poll->id, $organizer, 'Carol', 'carol@example.org', 'fr');

$issued->url;      // the personal link, to show or send
```
| Valeur | Limites |
|---|---|
| Nom | De 1 à 100 caractères ; les caractères de contrôle et de format deviennent des espaces, le reste est rogné |
| Adresse | Facultative ; une adresse à laquelle un serveur de courriel peut livrer, lue par `phil/email-address`, son domaine en ASCII |
| Compte | Facultatif ; une référence de `phil/resource` |
| Locale | Facultative ; une étiquette de langue, `fr` ou `fr_CA`, la langue du courriel |

Un sondage clôturé, dont l'échéance est passée, ou qui a déjà `max_respondents` refuse un nouveau répondant avec
`AnswerRefused`. Un nom ou une adresse refusé ne laisse aucune invitation derrière lui.

Le lien est une invitation de `phil/access-links` à la campagne `scheduling-poll:<poll>`, son destinataire étant
l'identifiant du répondant : aucun nom ni aucune adresse n'atteint la bibliothèque. Son jeton, de 256 bits, est montré
une fois dans `IssuedLink` et conservé seulement sous forme d'empreinte à clé. Le lien change les réponses jusqu'à
l'échéance, et s'ouvre encore, pour montrer la date choisie, pendant `link_lifetime` après elle.

```php
$access = $polls->open($token);     // RespondentAccess, or null when the link opens nothing
$access->respondent;
$access->poll;
$access->slots;
$access->editable;                  // whether the answers can still change

$again = $polls->reissue($poll->id, $organizer, $respondentId);   // a lost link; the earlier ones keep opening
$polls->revoke($poll->id, $organizer, $respondentId);             // its links open nothing, its answers are not counted
```
Ouvrir un lien ne change rien : un client de courriel qui en fait un aperçu ne répond à rien. Un lien mal formé,
inconnu, révoqué ou expiré n'ouvre rien, et la page ne dit pas lequel.

## Les réponses

Chaque date est répondue `Availability::Yes`, `IfNeedBe` ou `No` :

```php
use Phil\SchedulingPollBundle\Domain\Availability;

$polls->answer($token, [
    $slot1 => Availability::Yes,
    $slot2 => Availability::IfNeedBe,
], 'Europe/Paris');                 // the time zone the respondent reads the dates in; the poll's when null
```
Une date omise est répondue non ; une date que le sondage ne propose pas, ou un fuseau horaire que la base de données
IANA n'a pas, lance `InvalidSchedulingPoll`. Répondre de nouveau remplace chaque réponse. Les réponses sont écrites par
un seul `UPDATE` conditionnel, seulement tant que le répondant n'est pas révoqué et que le sondage n'est ni clôturé ni
passé son échéance, de sorte qu'une réponse qui course la clôture est conservée avant elle ou refusée avec
`AnswerRefused`.

`AnswerForm::answers($request->request->all())` lit ce que le formulaire soumet — `answers[<slot>]` valant `yes`,
`if_need_be` ou `no` — et refuse tout le reste ; `AnswerForm::timezone()` lit `timezone`.

## Les fuseaux horaires

Chaque date est affichée dans le fuseau horaire du lecteur : celui que la page demande, sinon celui que le répondant a
choisi en dernier avec ses réponses, sinon celui du sondage. Un fuseau que la base de données IANA n'a pas est ignoré.
Les changements d'heure tombent où ils tombent : une date à 19 h à Toronto le 31 octobre 2026 est à 0 h à Paris, une
semaine plus tard à 1 h.

## Qui voit les réponses

Les vues de `SchedulingPollViews` appliquent les deux choix du sondage :

| Lecteur | Avant la clôture | Une fois clôturé |
|---|---|---|
| L'organisateur | Quand `organizerSeesAnswers` est vrai | Toujours |
| Un répondant, `ResultsVisibility::Always` | Toujours | Toujours |
| Un répondant, `AfterVoting` | Une fois qu'il a répondu | Toujours |
| Un répondant, `AfterClosing` | Jamais | Toujours |
| Un répondant, `Managers` | Jamais | Jamais |

```php
$sheet = $views->sheet($token, $timezone);              // AnswerSheet, or null: the respondent's page
$results = $views->results($poll->id, $organizer);      // ResultsView: the organizer's table
```
Une vue dont les réponses sont cachées a `visible` à false, aucun décompte et aucune ligne, et dit combien de personnes
ont répondu. Les lignes sont les répondants qui ont répondu et n'ont pas été révoqués, numérotés dans l'ordre où ils se
sont joints ; un nom effacé est `null`.

Un sondage n'a pas de commentaires : une page qui en veut utilise un module modéré, comme `phil/comments-bundle`.
