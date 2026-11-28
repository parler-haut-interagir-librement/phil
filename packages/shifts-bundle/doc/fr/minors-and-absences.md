<!-- phil-dev source-sha256=d14a3b028d11f9cac584a157907d014e4b0b94cb98a7bee7bd82c854506be31c -->
# Mineurs et absences

Deux règles facultatives gardent l'accès aux quarts : un bénévole **mineur** rejoint un quart une fois enregistré le
consentement d'un parent ou d'un tuteur, et un bénévole dont les **absences non annoncées** atteignent un seuil ne
rejoint plus un quart de lui-même. Les deux sont désactivées par défaut ; chacune conserve le minimum de données qui
suffit.

## Les bénévoles mineurs

### L'activer

Le bundle ne stocke aucune date de naissance. L'application donne celles qu'elle connaît par un service `BirthDates`, et
le nomme :

```php
use Phil\ShiftsBundle\Application\Contract\BirthDates;

final readonly class MemberBirthDates implements BirthDates
{
    public function __construct(private Members $members) {}

    public function of(string $volunteer): ?\DateTimeImmutable
    {
        return $this->members->find($volunteer)?->birthDate;
    }
}
```

```yaml
phil_shifts:
    minors:
        birth_dates: App\Shifts\MemberBirthDates
        age_of_majority: 18
```

Sans `birth_dates`, personne n'est mineur et rien n'est demandé. Un bénévole dont l'application ne donne pas la date de
naissance, `null`, n'est pas mineur : une application qui exige la date de naissance la demande à l'inscription.

### Qui est mineur

Un bénévole est mineur **pour un quart** quand, le jour où le quart commence dans le `time_zone` configuré, il n'a pas
atteint `age_of_majority`, de 1 à 30, 18 par défaut. Seule la date du calendrier de la date de naissance compte, jamais
son heure ni son décalage. Un bénévole qui a 18 ans le samedi d'un congrès est mineur au quart du vendredi soir et
majeur à celui du samedi matin. Un bénévole né un 29 février atteint l'âge le 1er mars d'une année qui n'a pas de
29 février.

`Minors::isMinor($volunteer, $on)` répond pour n'importe quel instant, aujourd'hui par défaut.

### Le contact d'un tuteur

`Minors` enregistre le contact d'un tuteur et son consentement ; l'application l'appelle depuis son propre formulaire,
celui d'un coordonnateur ou celui du tuteur :

```php
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Domain\Exception\InvalidGuardianContact;

try {
    $minors->record('volunteer-42', 'Marie Tremblay', '+1 514 555-0100', null, $coordinator);
    $minors->consent('volunteer-42', $coordinator);
} catch (InvalidGuardianContact $refused) {
    $form->addError(new FormError($translator->trans($refused->reason, domain: 'phil_shifts')));
}
```

| Champ | Conservé | Bornes |
|---|---|---|
| Nom | toujours | de 1 à 120 caractères, aucun caractère de contrôle |
| Téléphone | quand il est donné | des chiffres, des espaces, `+`, `(`, `)`, `.` et `-`, de 4 à 32 caractères |
| Courriel | quand il est donné | un seul `@`, un point dans le domaine, 254 caractères au plus |
| Qui l'a enregistré | toujours | l'identifiant d'une personne |
| Le consentement | une fois donné | son instant, selon l'horloge du serveur, et qui l'a enregistré |

Un numéro de téléphone ou une adresse de courriel au moins est exigé. Le bundle ne conserve ni adresse, ni date de
naissance, ni lien de parenté.

| Méthode | Rôle |
|---|---|
| `record($volunteer, $name, $phone, $email, $recordedBy)` | Enregistre le contact, ou le corrige ; un consentement déjà donné est conservé. |
| `consent($volunteer, $recordedBy)` | Enregistre le consentement maintenant ; refusé avec `shifts.no_guardian` sans contact. |
| `withdrawConsent($volunteer)` | Retire le consentement, conserve le contact. |
| `contactOf($volunteer)` | Le `GuardianContact`, ou null. |
| `forget($volunteer)` | Supprime le contact et son consentement, une fois le bénévole majeur ou parti. |
| `mayJoin($volunteer, $shift)`, `assertMayJoin()` | Si le bénévole peut rejoindre le quart. |

### Ce que la règle garde

Un mineur sans le consentement d'un tuteur est refusé avec `shifts.guardian_consent` quand il s'inscrit, se dit
intéressé, prend une offre du tableau d'échange, ou quand un coordonnateur l'affecte ; l'attribution parmi les
intéressés l'écarte. Retirer un consentement, ou oublier un contact, ne retire personne d'un quart : le coordonnateur
décide.

## Les absences non annoncées

### Enregistrer une absence

Une fois un quart commencé, un coordonnateur enregistre qu'un bénévole qui occupait une place ne s'est pas présenté sans
l'avoir dit, depuis la page de la liste ou par `Absences` :

| Méthode | Rôle |
|---|---|
| `record($shiftId, $volunteer, $coordinator)` | Enregistre l'absence ; false quand elle l'était déjà. Refusée avec `shifts.not_started` avant le début, `shift.not_on_roster` pour un bénévole qui n'occupait aucune place. |
| `forgive($shiftId, $volunteer)` | Reprend une absence enregistrée par erreur. |
| `count($volunteer)` | Les absences qui comptent : enregistrées pendant la fenêtre, non levées. |
| `isBlocked($volunteer)`, `assertMayJoinFreely()` | Si le bénévole a atteint le seuil. |
| `lift($volunteer, $organiser)` | Lève le blocage : les absences qui comptaient ne comptent plus. |
| `ofVolunteer($volunteer)`, `absentFrom($shiftId)` | Les absences d'un bénévole, les levées aussi ; les bénévoles absents d'un quart. |

Un bénévole qui s'est désisté avant le quart n'est pas absent : il n'est plus sur la liste.

### Le seuil

```yaml
phil_shifts:
    absences:
        threshold: 3
        window_days: 365
```

Sans `threshold`, les absences sont enregistrées et comptées, et ne bloquent rien. Avec lui, de 1 à 100, un bénévole
dont les absences des derniers `window_days`, de 1 à 3 650, atteignent le seuil ne rejoint plus un quart **de
lui-même** : s'inscrire, se dire intéressé et prendre une offre sont refusés avec `shifts.absences`, et l'attribution
l'écarte. Un coordonnateur peut encore l'affecter.

Un organisateur lève le blocage depuis la page de la liste, où le bénévole porte l'insigne « Bloqué par ses absences »,
ou par `Absences::lift()`. Une absence levée conserve son instant et la personne qui l'a levée, et ne compte plus. Une
absence plus ancienne que la fenêtre cesse de compter d'elle-même.

## Vie privée

Les tables `phil_shifts_guardians` et `phil_shifts_absences` contiennent des identifiants, le nom du tuteur et ses
moyens de le joindre, et des instants. Avec phil/privacy-bundle, l'export d'un bénévole contient le contact de son tuteur
avec le consentement et ses absences, et l'effacement supprime les deux : voir [Stockage et vie privée](storage-and-privacy.md).

Une application qui conserve le contact d'un tuteur lui dit pourquoi et pour combien de temps, et appelle `forget()` une
fois le bénévole majeur ou parti.
