<!-- phil-dev source-sha256=833cfb022d491958ae894ebdfd3be1f3a784b4c63dc34db72fde84c441fe8643 -->
# Expiration et recertification

Un certificat peut prendre fin, et une formation aussi. Avant la fin d'un certificat, le bundle en avertit
l'application, qui rappelle son titulaire; celui-ci est alors recertifié, avec des crédits de formation continue
accumulés entre-temps ou avec de nouveaux acquis, et reçoit un nouveau certificat lié à celui qu'il renouvelle.

## La fin d'un certificat et d'une formation

Le `validityMonths` d'une formation donne la durée de validité de ses certificats; son `endsAt`, le moment où la
formation elle-même prend fin. Un certificat est valide jusqu'au plus tôt des deux (les mois comptés depuis son
attribution), et pour toujours quand aucun n'est défini. À partir de son `endsAt`, une formation ne décerne plus de
certificats et n'en renouvelle aucun.

```php
$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.',
    new PassCriteria(minimumScore: 70), validityMonths: 24, endsAt: new DateTimeImmutable('2030-01-01 UTC'));
```

Le `status()` d'un certificat à un moment donné est `CertificateStatus::Valid`, `Expired` une fois son `validUntil`
atteint, ou `Revoked` une fois révoqué, quelles que soient ses dates. Expiré et révoqué sont distincts : un certificat
expiré a été valide pendant toute sa période et le reste sur sa page et dans son PDF, son attestation se vérifie encore
pour cette période, et son bit reste inactif dans la liste d'état; seule une révocation l'active. L'attestation porte son
`validUntil` : les vérificateurs d'ailleurs apprennent donc la fin sans interroger l'émetteur.

## Rappels avant la fin

`Phil\CertificatesBundle\Application\ExpiryReminders` envoie `CertificateExpiring` quand un certificat, ni révoqué ni
renouvelé, arrive à moins d'un seuil de sa fin : 60, 30 et 7 jours par défaut, `reminders.days` dans la configuration.
Chaque seuil est rappelé une fois (le certificat garde le moment du dernier rappel de son titulaire), et un certificat
vu pour la première fois sous plusieurs seuils est rappelé une fois, au plus proche. Le bundle n'envoie rien :
l'application écoute l'événement et rappelle le titulaire par le moyen qu'elle choisit.

```php
#[AsEventListener]
final readonly class RemindHolder
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CertificateExpiring $expiring): void
    {
        $this->bus->dispatch(new RemindHolderMessage($expiring->certificateId, $expiring->daysLeft));
    }
}
```

L'événement porte l'identifiant du certificat, son code, le *slug* de sa formation et les jours entiers qui restent;
rien sur le titulaire. `phil:certificates:remind` exécute les rappels une fois (depuis cron ou le planificateur de
l'application, une fois par jour), `--limit` rappels au plus par exécution; `ExpiryReminders::remind()` fait de même
depuis le code de l'application.

## Recertification

```php
$renewal = $awarding->recertify('7KQ2-M9XA-04TZ', 'ada@example.org');
$renewal = $awarding->recertify('7KQ2-M9XA-04TZ', 'ada@example.org', new Attainment(score: 85));
```

`Awarding::recertify()` décerne un nouveau certificat de la même formation, qui renouvelle celui de ce code :

- l'identité donnée est celle à laquelle la première attestation a été délivrée, vérifiée contre son empreinte salée;
  une autre est refusée;
- une formation avec `recertificationCredits` exige ce nombre de crédits de formation continue, accumulés par le
  titulaire du certificat depuis son attribution jusqu'à maintenant; le certificat doit nommer son titulaire;
- une formation sans crédits exige des acquis, vérifiés contre ses critères; ceux qu'on donne à une formation avec
  crédits sont vérifiés aussi;
- un certificat révoqué, un certificat déjà renouvelé et le certificat d'une formation retirée ou terminée ne sont pas
  renouvelés.

Le nouveau certificat a son propre code, son propre indice d'état et sa propre attestation, signée pour la même identité
sous un nouveau sel; il garde le nom, le titulaire et l'affichage du premier, sauf si `listed` dit autrement. Il est
valide à partir de la fin du premier quand cette fin est encore à venir, de sorte qu'un renouvellement anticipé ne fait
rien perdre, et à partir de maintenant sinon. Le premier certificat reste tel quel, valide jusqu'à sa propre fin; le
registre lie chacun à l'autre (« Renews », « Renewed by »), et `CertificateRepository::findRenewalOf()` trouve le
renouvellement. `CertificateAwarded`, puis `CertificateRenewed` (qui nomme le certificat renouvelé) sont envoyés.

Un refus lève `CertificateRefused`, son message disant ce qui manque : les crédits accumulés par rapport à ceux qu'on
exige, un titulaire pour qui les compter, des acquis.

## Crédits de formation continue

`Phil\CertificatesBundle\Application\ContinuingEducation` garde les activités que chaque membre a suivies (un atelier,
une conférence, une formation ailleurs) avec les crédits qu'elles rapportent : des heures, ou des crédits, selon le
compte de l'application, au centième.

```php
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\Period;

$education->record('member-42', 'Workshop on triage', Credits::fromString('3.5'),
    completedAt: new DateTimeImmutable('2026-11-12 UTC'), course: 'first-aid', source: 'workshop-17');

$education->total('member-42', new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC')));
$education->progress('7KQ2-M9XA-04TZ');    // ['gathered' => Credits, 'required' => ?Credits, 'missing' => ?Credits]
```

| Champ | Règle |
|---|---|
| Membre | L'identifiant stable que l'application donne au membre, jusqu'à 191 caractères sans espace; une adresse de courriel est refusée |
| Titre | De 1 à 255 caractères |
| Crédits | Plus de 0, jusqu'à 9 999 999,99 |
| Terminée le | N'importe quel moment; maintenant si on l'omet |
| Formation | La formation dont la recertification les compte; toutes les formations si on l'omet |
| Source | D'où elle vient, jusqu'à 255 caractères, unique pour le membre : la consigner de nouveau renvoie l'activité déjà consignée |

Une période comprend son début et exclut sa fin. Le total au profit d'une formation compte les activités de cette
formation et celles qui n'en nomment aucune. `activities()` liste les activités d'un membre sur une période, les plus
récentes d'abord, 500 au plus; `remove()` en retire une par son identifiant. `LearningActivityRecorded`, qui porte les
identifiants de l'activité et du membre, est envoyé une fois l'activité stockée.

Un certificat nomme son titulaire quand il est décerné avec `holder:` (ou `--holder` sur la console); ses crédits sont
alors comptés pour sa recertification. `phil:certificates:credit` et `phil:certificates:credits` les consignent et les
montrent depuis la console ([Commandes de console](console.md#crédits-de-formation-continue)).
