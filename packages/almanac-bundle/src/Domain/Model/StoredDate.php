<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Symfony\Component\Uid\Uuid;

/**
 * A computed date kept in the database, for an administrator to add or change: a definition of `phil/almanac` with its
 * rules as text, read back when the almanac asks.
 *
 * Its fields are checked by building its definition, at every change: a stored date is always one the almanac reads.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_almanac_date')]
#[ORM\UniqueConstraint(name: 'phil_almanac_date_key', columns: ['date_key'])]
final class StoredDate
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(name: 'date_key', length: 100)]
    private string $key;

    #[ORM\Column(name: 'title', length: 200)]
    private string $title;

    #[ORM\Column(name: 'rule', length: 200)]
    private string $rule;

    #[ORM\Column(name: 'until_rule', length: 200, nullable: true)]
    private ?string $until;

    #[ORM\Column(name: 'days', type: 'smallint')]
    private int $days;

    #[ORM\Column(name: 'all_day')]
    private bool $allDay;

    /** @var list<string> */
    #[ORM\Column(name: 'tags', type: 'json')]
    private array $tags;

    public function __construct(Definition $definition)
    {
        $this->id = Uuid::v7();
        $this->key = $definition->key;
        $this->change($definition);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * Takes another definition's title, rules, length and tags; the key stays.
     *
     * @throws InvalidRule when the definition is another date's: a stored date keeps its key
     */
    public function change(Definition $definition): void
    {
        if ($definition->key !== $this->key) {
            throw InvalidRule::because(\sprintf('The date "%s" keeps its key; "%s" is another date.', $this->key, $definition->key));
        }

        $this->title = $definition->title;
        $this->rule = (string) $definition->rule;
        $this->until = $definition->until instanceof Rule ? (string) $definition->until : null;
        $this->days = $definition->days;
        $this->allDay = $definition->allDay;
        $this->tags = $definition->tags;
    }

    public function definition(): Definition
    {
        return new Definition(
            $this->key,
            $this->title,
            RuleParser::parse($this->rule),
            null === $this->until ? null : RuleParser::parse($this->until),
            $this->days,
            $this->allDay,
            $this->tags,
        );
    }
}
