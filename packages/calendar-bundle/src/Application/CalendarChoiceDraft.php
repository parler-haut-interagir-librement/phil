<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Exception\InvalidChoice;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A choice as `CalendarChoiceType` fills it: the source, its value, and whether it is hidden rather than followed.
 */
final class CalendarChoiceDraft
{
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'sources'])]
    public ?string $source = null;

    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: ChoiceSource::MAX_LENGTH)]
    public ?string $value = null;

    public bool $hidden = false;

    /**
     * @return list<string>
     */
    public static function sources(): array
    {
        return array_map(static fn (ChoiceSource $source): string => $source->value, ChoiceSource::cases());
    }

    #[Assert\Callback]
    public function validateValue(ExecutionContextInterface $context): void
    {
        $source = ChoiceSource::tryFrom((string) $this->source);

        if (!$source instanceof ChoiceSource || null === $this->value || '' === trim($this->value)) {
            return;
        }

        try {
            $source->checked($this->value);
        } catch (InvalidChoice) {
            $context->buildViolation('calendar.choice')->atPath('value')->setTranslationDomain('validators')->addViolation();
        }
    }
}
