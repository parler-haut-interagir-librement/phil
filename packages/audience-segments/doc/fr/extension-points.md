<!-- phil-dev source-sha256=ffe28bc96b37a8e814fd56bfca35819a7d044b587b35154bd5b1a467af4ab3b3 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG explique comment.

| Pour | Faites |
|---|---|
| Dire qui peut être sélectionné | Implémentez `Contract\Audience` |
| Retirer les désabonnés et les refus | Implémentez `Contract\Exclusions` |
| Sélectionner par billet, date ou sélection | Implémentez `Contract\Participation` |
| Sélectionner par commande | Implémentez `Contract\Purchases` |
| Sélectionner les soutiens actifs | Implémentez `Contract\Supporters` |
| Sélectionner par étiquette interne | Implémentez `Contract\PeopleTags` |
| Conserver les membres des segments recalculés | Implémentez `Contract\MembershipStore` |
| Sélectionner sur un autre fait | Implémentez un `Phil\Rules\Type\ConditionType` qui lit le fait `member`, et ajoutez-le au registre |

Chaque implémentation ne conserve rien d'une requête à la suivante, de sorte qu'une seule instance serve toutes les
requêtes d'un *worker*. Les ports reçoivent et renvoient des identifiants opaques : un membre n'est jamais identifié par
une adresse.

## Les ports

| Port | Contrat |
|---|---|
| `Audience::members(?string $after, int $limit): list<string>` | Jusqu'à `$limit` membres après le curseur, en ordre croissant d'octets (`strcmp`) ; moins signifie la fin. |
| `Exclusions::excluded(list<string> $members): list<string>` | Ceux des membres qui sont exclus, dans n'importe quel ordre. |
| `Participation::holdsTicket()`, `attended()`, `inSelection()` | Si le membre détient un billet de l'événement (de l'un des types lorsqu'ils sont donnés), a participé à la date, a été choisi dans la sélection. |
| `Purchases::bought(string $member, PurchaseFilter $filter): bool` | Si une commande du membre correspond à chaque partie non vide du filtre. |
| `Supporters::isActive(string $member, list<string> $tiers): bool` | Si le membre soutient maintenant, à l'un des paliers lorsqu'ils sont donnés. |
| `PeopleTags::hasAnyTag(string $member, list<string> $tags): bool` | Si le membre porte l'une des étiquettes. |
| `MembershipStore::members()`, `add()`, `remove()`, `recalculated()`, `lastRecalculation()` | Les membres conservés pour un segment, par pages en ordre croissant d'octets, et la date de la dernière exécution. |

La bibliothèque vérifie ce que renvoient les ports paginés : une page plus grande que demandé, hors d'ordre, au curseur
ou avant lui, ou contenant un identifiant de mauvaise forme arrête le calcul avec `PortContractViolated` plutôt que de
boucler ou de sauter des membres.

## Un port implémenté

Les étiquettes d'un module de personnes, conservées dans une table `person_tag (person_id, tag)`, servies avec Doctrine
DBAL :

```php
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Phil\AudienceSegments\Contract\PeopleTags;

final readonly class DbalPeopleTags implements PeopleTags
{
    public function __construct(private Connection $connection)
    {
    }

    public function hasAnyTag(string $member, array $tags): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM person_tag WHERE person_id = :member AND tag IN (:tags) LIMIT 1',
            ['member' => $member, 'tags' => $tags],
            ['tags' => ArrayParameterType::STRING],
        );
    }
}
```

L'audience d'une infolettre, lue par pages avec la même connexion ; l'identifiant est l'identifiant opaque de
l'abonné, et l'ordre est l'ordre d'octets que le port exige :

```php
use Doctrine\DBAL\Connection;
use Phil\AudienceSegments\Contract\Audience;

final readonly class NewsletterSubscribers implements Audience
{
    public function __construct(private Connection $connection)
    {
    }

    public function members(?string $after, int $limit): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn(
            'SELECT member_id FROM newsletter_subscriber WHERE confirmed = 1 AND member_id > :after ORDER BY member_id COLLATE "C" LIMIT '.$limit,
            ['after' => $after ?? ''],
        );
    }
}
```

`COLLATE "C"` est l'ordre d'octets de PostgreSQL ; MySQL y parvient avec un classement binaire sur la colonne.

## Un autre type de condition

Un type qui sélectionne sur un autre fait lit le fait `member`, interroge son propre port et limite ses détails à des
identifiants :

```php
use Phil\AudienceSegments\Segment;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Type\ConditionType;

final readonly class CourseCompleted implements ConditionType
{
    public function __construct(private Completions $completions)
    {
    }

    public function name(): string
    {
        return 'course.completed';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifier('course'));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = $facts->has(Segment::MEMBER) ? $facts->get(Segment::MEMBER) : null;

        if (!\is_string($member)) {
            return Verdict::fail('member.missing');
        }

        $course = $operands->string('course');

        return $this->completions->completed($member, $course)
            ? Verdict::pass('course.completed', ['course' => $course])
            : Verdict::fail('course.not-completed', ['course' => $course]);
    }
}
```

Il s'ajoute avec `AudienceConditions::types(...)->with(new CourseCompleted($completions))`. Son nom ne doit porter aucun
des `Segment::FORBIDDEN_WORDS`.
