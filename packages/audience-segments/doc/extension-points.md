# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Say who may be selected | Implement `Contract\Audience` |
| Remove the unsubscribed and the refusals | Implement `Contract\Exclusions` |
| Select by ticket, date or selection | Implement `Contract\Participation` |
| Select by order | Implement `Contract\Purchases` |
| Select active supporters | Implement `Contract\Supporters` |
| Select by internal tag | Implement `Contract\PeopleTags` |
| Keep the members of recalculated segments | Implement `Contract\MembershipStore` |
| Select on another fact | Implement a `Phil\Rules\Type\ConditionType` that reads the `member` fact, and add it to the registry |

Every implementation keeps nothing from one request to the next, so that one instance serves every request of a
worker. The ports receive and return opaque identifiers: a member is never identified by an address.

## The ports

| Port | Contract |
|---|---|
| `Audience::members(?string $after, int $limit): list<string>` | Up to `$limit` members after the cursor, in ascending byte order (`strcmp`); fewer means the end. |
| `Exclusions::excluded(list<string> $members): list<string>` | Those of the members who are excluded, in any order. |
| `Participation::holdsTicket()`, `attended()`, `inSelection()` | Whether the member holds a ticket of the event (of one of the types when given), took part in the date, was chosen in the selection. |
| `Purchases::bought(string $member, PurchaseFilter $filter): bool` | Whether an order of the member matches every non-empty part of the filter. |
| `Supporters::isActive(string $member, list<string> $tiers): bool` | Whether the member supports now, at one of the tiers when given. |
| `PeopleTags::hasAnyTag(string $member, list<string> $tags): bool` | Whether the member carries one of the tags. |
| `MembershipStore::members()`, `add()`, `remove()`, `recalculated()`, `lastRecalculation()` | The members kept for a segment, by pages in ascending byte order, and the date of the last run. |

The library checks what the paged ports return: a page larger than asked, out of order, at or before its cursor, or
holding an identifier out of form stops the computation with `PortContractViolated` rather than loop or skip members.

## An implemented port

The tags of a people module, kept in a table `person_tag (person_id, tag)`, answered with Doctrine DBAL:

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

The audience of a newsletter, read by pages with the same connection; the identifier is the subscriber's opaque
identifier, and the order is the byte order the port requires:

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

`COLLATE "C"` is PostgreSQL's byte order; MySQL reaches it with a binary collation on the column.

## Another condition type

A type that selects on another fact reads the `member` fact, asks its own port, and keeps its details to identifiers:

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

It is added with `AudienceConditions::types(...)->with(new CourseCompleted($completions))`. Its name must not carry
one of `Segment::FORBIDDEN_WORDS`.
