<!-- phil-dev source-sha256=b389d53bf3aa2384c909003bd0a312a0d42d209bb0522ff32e9393164960d1c4 -->
# Sources d'achèvement

Une formation de certificat peut être décernée sur ce qu'un autre système sait : un catalogue de formations, une
plateforme d'apprentissage, les leçons propres à l'application. Ce système garde son propre modèle de formations, de
personnes apprenantes et de progression; le bundle garde ses propres formations et certificats. Les deux se rencontrent
par un seul port, `CourseCompletions`, que le bundle interroge et auquel l'autre système répond par un identifiant.

## Le port

```php
namespace Phil\CertificatesBundle\Domain\Contract;

interface CourseCompletions
{
    /** opaque non-empty identifier of the completion, or null when not completed */
    public function completion(string $course, string $holder): ?string;
}
```

| Argument | Ce que c'est |
|---|---|
| `$course` | La source d'achèvement que nomme la formation du certificat : la clé de la formation dans l'autre système |
| `$holder` | L'identifiant stable du membre, celui que l'attribution nomme avec `holder`, jamais une adresse de courriel |
| Réponse | L'identifiant de l'achèvement dans l'autre système (une inscription, un dossier d'achèvement, une tentative), ou `null` quand le membre n'a pas achevé la formation |

La réponse est gardée telle quelle sur le certificat, comme preuve sur laquelle il a été décerné. Elle compte de 1 à 255
caractères, sans espaces ni caractères de contrôle; une réponse hors de ces règles est refusée plutôt que stockée. Une
implémentation répond à partir de son propre stockage, sur-le-champ, et ne garde rien d'une requête à l'autre.

L'application enregistre son implémentation sous l'interface :

```yaml
# config/services.yaml
services:
    Phil\CertificatesBundle\Domain\Contract\CourseCompletions:
        alias: App\Learning\CompletionsOfMyCourses
```

`phil/courses-bundle` en est une implémentation, pour les formations qu'il sert; tout autre système implémente
l'interface de la même façon, dans une classe propre à l'application qui lit ce système.

## Une formation décernée sur achèvement

Une formation nomme sa source d'achèvement quand elle est définie, puis la change ou la retire :

```php
$courses->define('first-aid', 'First aid', 'The gestures that save lives.', 'Complete every lesson of the course.', completionSource: 'first-aid-2027');

$courses->changeCompletionSource('first-aid', 'first-aid-2028'); // another key
$courses->changeCompletionSource('first-aid', null);             // the application alone decides again
```

La clé suit les mêmes règles que l'identifiant d'un achèvement. `Courses::revise()` la laisse telle qu'elle est. Une
formation sans source d'achèvement est décernée comme le décrit [Formations et attribution](courses-and-awarding.md), et
le port n'est jamais interrogé à son sujet.

## L'attribution

`Awarding::award()` interroge le port pour une formation qui nomme une source d'achèvement, une fois la formation
trouvée ouverte et les acquis conformes à ses critères :

```php
$certificate = $awarding->award('first-aid', 'Ada Lovelace', 'ada@example.org', holder: 'member-42');
$certificate->completion; // the identifier the source answered
```

L'attribution est refusée par `CertificateRefused`, sans rien stocker ni envoyer, quand :

| Cas | Message |
|---|---|
| L'attribution ne nomme aucun titulaire | `The course "first-aid" is awarded on completion: the award names the holder whose completion is asked.` |
| Aucun `CourseCompletions` n'est enregistré | `The course "first-aid" is awarded on completion, and no source of completions is configured.` |
| La source répond `null` | `The holder has not completed the course "first-aid".` |
| La source répond un identifiant hors des règles | `The completion source of the course "first-aid" answered an identifier of a completion that cannot be kept.` |
| Un certificat de la formation a déjà été décerné sur cet achèvement | `The certificate 7KQ2-M9XA-04TZ of the course "first-aid" was already awarded on that completion.` |

Un achèvement ne donne qu'un certificat d'une formation. L'achèvement est cherché, et le certificat stocké, sous le
verrou de l'indice d'état; sans verrou, l'index unique du stockage sur la formation et l'achèvement refuse la seconde de
deux attributions simultanées, qui reprend alors et trouve la première.

Les critères s'appliquent toujours : une formation décernée sur achèvement qui exige un score, des minutes ou des modules
n'est réussie que par des acquis qui les atteignent aussi. Une formation dont l'achèvement vaut pour tout n'en exige
aucun.

Une recertification décerne un nouveau certificat par des crédits de formation continue ou par des acquis, comme le
décrit [Expiration et recertification](recertification.md); elle n'interroge pas de nouveau le port, et le nouveau
certificat ne nomme aucun achèvement.

## Décerner quand l'autre système le dit

`Awarding::awardOnCompletion()` est le point d'entrée d'un écouteur de l'événement d'achèvement propre à l'autre
système. Il décerne le certificat comme le fait `award()`, et rend le certificat déjà décerné sur cet achèvement, sans
rien envoyer de nouveau, au lieu de le refuser : un écouteur qui entend deux fois le même achèvement (un message livré de
nouveau, une reprise) ne décerne qu'une fois.

```php
use Phil\CertificatesBundle\Application\Awarding;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class AwardCertificateOnCompletion
{
    public function __construct(
        private Awarding $awarding,
        private Members $members,
    ) {
    }

    public function __invoke(CourseCompleted $completed): void
    {
        $member = $this->members->get($completed->memberId);

        $this->awarding->awardOnCompletion('first-aid', $completed->memberId, $member->displayName, $member->email);
    }
}
```

`CourseCompleted` et `Members` représentent l'événement et le stockage propres à l'application. L'événement ne porte que
des identifiants; le nom et l'identité à qui l'attestation est émise sont lus là où l'application les garde, au moment
de l'attribution. Un message que l'écouteur met plutôt en file porte lui aussi des identifiants, et son *handler* lit le
reste.

`awardOnCompletion()` refuse une formation qui ne nomme aucune source d'achèvement, et une attribution dont le titulaire
n'est pas un identifiant stable. `CertificateAwarded` porte l'identifiant du certificat, son code et le *slug* de sa
formation; l'achèvement se lit sur le certificat.

## La console

```sh
bin/console phil:certificates:course first-aid "First aid" "Complete every lesson." --completion-source=first-aid-2027
bin/console phil:certificates:award first-aid "Ada Lovelace" ada@example.org --holder=member-42 --on-completion
```

[Commandes de console](console.md) décrit les options.

## Le schéma

La formation garde sa clé dans `phil_certificates_course.completion_source`, le certificat son achèvement dans
`phil_certificates_certificate.completion`, les deux nullables, avec un index unique sur `course_id` et `completion`.
Une migration générée par Doctrine ajoute les deux colonnes et l'index; les lignes déjà stockées gardent `NULL`, et leurs
formations sont décernées comme avant.
