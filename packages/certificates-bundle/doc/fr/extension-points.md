<!-- phil-dev source-sha256=fbe03dbbafcea45c9e0eb1fc8ade011e2ed62db89c259d34a33f741834a4480a -->
# Points d'extension

## Les dépôts

`Phil\CertificatesBundle\Domain\Contract\CourseRepository`, `CertificateRepository` et `LearningActivityRepository`
sont l'endroit où les formations, les certificats et les activités de formation continue sont gardés. Avec
DoctrineBundle, le bundle en fait des alias de ses dépôts Doctrine, dont `save()` vide l'unité de travail. Sans lui,
l'application enregistre ses propres services sous ces interfaces :

```php
interface CertificateRepository
{
    public function save(Certificate $certificate): void;
    public function findByCode(string $code): ?Certificate;
    public function nextStatusIndex(): int;
    public function revokedIndexes(int $from, int $to): array;
    public function listed(Course $course, int $limit, int $offset = 0): array;
    public function countListed(Course $course): int;
    public function findRenewalOf(Certificate $certificate): ?Certificate;
    public function findByCompletion(Course $course, string $completion): ?Certificate;
    public function expiring(DateTimeImmutable $after, DateTimeImmutable $until, int $limit, int $offset = 0): array;
}

interface LearningActivityRepository
{
    public function save(LearningActivity $activity): void;
    public function remove(LearningActivity $activity): void;
    public function find(string $id): ?LearningActivity;
    public function findBySource(MemberId $member, string $source): ?LearningActivity;
    public function of(MemberId $member, Period $period, int $limit, int $offset = 0): array;
    public function total(MemberId $member, Period $period, ?Course $toward = null): Credits;
}
```

`expiring()` donne les certificats ni révoqués ni renouvelés qui prennent fin après `$after` et au plus tard à `$until`,
celui qui finit le premier en tête, puis par identifiant; `total()` additionne dans le stockage, de sorte que les crédits
d'un membre ne sont jamais chargés pour être additionnés. Sans `LearningActivityRepository`, une recertification qui
exige des crédits est refusée.

`findByCompletion()` donne le certificat d'une formation décerné sur cet achèvement; le stockage garde un achèvement
pour un seul certificat d'une formation.

`nextStatusIndex()` répond l'indice qui suit le plus élevé déjà donné, et le stockage garde les indices et les codes
uniques : deux attributions simultanées ne peuvent donc pas en partager un. La seconde est refusée plutôt que de
partager un bit d'état. `Awarding` demande l'indice et enregistre le certificat sous le verrou de `lock_factory` quand
il y en a un : un dépôt n'a donc pas besoin de verrou propre. Quand le stockage refuse un certificat dont l'indice, le
code ou l'achèvement de la formation appartient à un autre, `save()` lève `Phil\CertificatesBundle\Domain\Exception\StatusIndexTaken`, une fois le
stockage prêt pour une autre tentative, et `Awarding` réessaie avec l'indice suivant
([Formations et attribution](courses-and-awarding.md#deux-attributions-à-la-fois)). Le dépôt Doctrine le fait avec le
registre `doctrine`, dont il réinitialise d'abord le gestionnaire.

## La source des achèvements

`Phil\CertificatesBundle\Domain\Contract\CourseCompletions` dit si un membre a achevé la formation d'un autre système,
par la clé que la formation du certificat nomme comme source d'achèvement et l'identifiant stable du membre :

```php
interface CourseCompletions
{
    /** opaque non-empty identifier of the completion, or null when not completed */
    public function completion(string $course, string $holder): ?string;
}
```

Le bundle n'en enregistre aucune. Quand l'application fait de l'interface un alias d'un service qui lui est propre,
`Awarding` l'interroge pour les formations qui nomment une source d'achèvement et garde sur le certificat
l'identifiant qu'elle répond; sans elle, ces formations ne décernent rien. [Sources d'achèvement](completion-sources.md)
décrit le port, les attributions sur achèvement et un écouteur de l'événement d'achèvement propre à l'application.

## Les URL

`Phil\CertificatesBundle\Application\Contract\CertificateUrls` donne les URL absolues vers lesquelles pointe un
certificat : la page de sa formation, qui identifie la réalisation de l'attestation, sa propre page, que le code QR
ouvre, et son entrée d'état. Le `RoutedCertificateUrls` du bundle les construit à partir des routes. Une application
qui sert le registre ailleurs (un autre hôte, ses propres pages) fait de l'interface un alias d'un service qui lui est
propre.

## La mise en page et les gabarits

`register.layout` nomme le gabarit Twig que les pages étendent, avec les blocs `title` et `content`. Les pages
elles-mêmes se remplacent comme n'importe quel gabarit de bundle, sous `templates/bundles/PhilCertificatesBundle/`.

## Les traductions

Les pages et le PDF lisent le domaine de traduction `phil_certificates`. Une application ajoute une langue, ou change un
texte, dans son propre fichier `translations/phil_certificates.<locale>.*`.

## D'autres listes d'état

`status_lists.others` nomme les `StatusLists` propres à l'application, interrogées pour les listes dont le nom ne suit
pas la disposition. Elles servent à une application qui publie l'état d'autres badges à côté de ses certificats.

## Les événements

| Événement | Quand | Porte |
|---|---|---|
| `Phil\CertificatesBundle\Domain\Event\CertificateAwarded` | Un certificat est décerné et stocké | `certificateId`, `code`, `course` |
| `Phil\CertificatesBundle\Domain\Event\CertificateRevoked` | Un certificat est révoqué et stocké ainsi | `certificateId`, `code`, `course` |
| `Phil\CertificatesBundle\Domain\Event\CertificateRenewed` | Le certificat d'une recertification est stocké, après son `CertificateAwarded` | `certificateId`, `code`, `course`, `renewedCertificateId` |
| `Phil\CertificatesBundle\Domain\Event\CertificateExpiring` | Un certificat atteint un seuil de `reminders.days` | `certificateId`, `code`, `course`, `daysLeft` |
| `Phil\CertificatesBundle\Domain\Event\LearningActivityRecorded` | Une activité de formation continue est stockée | `activityId`, `member` |

Ils passent par le répartiteur PSR-14 de l'application, `event_dispatcher`, quand il y en a un; `ExpiryReminders` en a besoin.
Tous ne portent que des identifiants : un écouteur lit le reste dans le dépôt, et un message qu'il met en file porte lui
aussi des identifiants.

## Le sujet d'impression

Chaque impression de `CertificatePdf` nomme un `PrintSubject` du type `certificate` (`CertificatePdf::PRINT_SUBJECT`) et
l'identifiant du certificat, par le `PrintStarting` de `phil/paged-media-bundle`. Un certificat n'est pas une œuvre sous
licence : une application qui vérifie les licences de ses impressions avec `phil/licensing-bundle` ajoute ce type aux
sujets qu'elle imprime sans vérification.

```yaml
# config/packages/phil_licensing.yaml
phil_licensing:
    printing:
        unlicensed_subjects: [certificate]
```

Le bundle ne l'ajoute pas lui-même et ne dépend pas de `phil/licensing-bundle` : quels documents un site imprime sans
licence relève de sa décision. Un site sans ce bundle n'a rien à configurer; un écouteur qui lui est propre sur
`PrintStarting` lit `$event->subject->type` et laisse passer `certificate` de la même façon.

## Les services

`Courses`, `Awarding`, `Revocation`, `ExpiryReminders`, `ContinuingEducation` et `CertificatePdf` sont enregistrés par
leur classe et peuvent être décorés : par exemple, un décorateur d'`Awarding` qui envoie son certificat à la personne
apprenante par courriel.
