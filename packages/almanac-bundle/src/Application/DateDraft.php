<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Application;

use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A computed date as a form fills it: its key, title, rules as text, length and tags. It is valid when the almanac can
 * read it, and `definition()` then gives it.
 */
final class DateDraft
{
    private const string KEY = '/^[a-z0-9][a-z0-9_.-]{0,99}$/D';

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Regex(pattern: self::KEY, message: 'phil_almanac.key')]
    public ?string $key = null;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $rule = null;

    #[Assert\Length(max: 200)]
    public ?string $until = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 366)]
    public ?int $days = 1;

    public bool $allDay = true;

    /** @var list<string> */
    public array $tags = [];

    public static function of(Definition $definition): self
    {
        $draft = new self();
        $draft->key = $definition->key;
        $draft->title = $definition->title;
        $draft->rule = (string) $definition->rule;
        $draft->until = $definition->until instanceof Rule ? (string) $definition->until : null;
        $draft->days = $definition->days;
        $draft->allDay = $definition->allDay;
        $draft->tags = $definition->tags;

        return $draft;
    }

    /**
     * @throws InvalidRule
     */
    public function definition(): Definition
    {
        return new Definition(
            (string) $this->key,
            (string) $this->title,
            RuleParser::parse((string) $this->rule),
            null === $this->until || '' === trim($this->until) ? null : RuleParser::parse($this->until),
            $this->days ?? 1,
            $this->allDay,
            $this->tags,
        );
    }

    /**
     * Each rule read on its own, then — when every field is valid on its own — the date as a whole: a refusal names
     * the field it comes from, and is not said twice.
     */
    #[Assert\Callback]
    public function validateRules(ExecutionContextInterface $context): void
    {
        foreach (['rule' => $this->rule, 'until' => $this->until] as $field => $text) {
            if (null === $text || '' === trim($text)) {
                continue;
            }

            try {
                RuleParser::parse($text);
            } catch (InvalidRule $invalidRule) {
                $context->buildViolation('phil_almanac.rule')->setParameter('{{ reason }}', $invalidRule->getMessage())->atPath($field)->addViolation();

                return;
            }
        }

        if (null === $this->key || 1 !== preg_match(self::KEY, $this->key) || null === $this->title || '' === trim($this->title) || null === $this->rule || '' === trim($this->rule) || null === $this->days || $this->days < 1 || $this->days > 366) {
            return;
        }

        try {
            $this->definition();
        } catch (InvalidRule $invalidRule) {
            $context->buildViolation('phil_almanac.date')->setParameter('{{ reason }}', $invalidRule->getMessage())->addViolation();
        }
    }
}
