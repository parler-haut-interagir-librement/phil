<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\AddressAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\ConstantAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\DateShiftAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\EmailAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\HashAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\IbanAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\LoremAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\NullAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PatternAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PersonNameAnonymizer;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizer\PhoneAnonymizer;
use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The anonymizers by type: the thirteen built in, and those an application adds or replaces.
 */
final readonly class Anonymizers
{
    /**
     * @param array<string, Anonymizer> $byType
     */
    private function __construct(
        private array $byType,
    ) {
    }

    public static function builtIn(): self
    {
        return new self([
            'email' => new EmailAnonymizer(),
            'firstname' => new PersonNameAnonymizer(NamePart::Given),
            'lastname' => new PersonNameAnonymizer(NamePart::Family),
            'name' => new PersonNameAnonymizer(NamePart::Full),
            'phone' => new PhoneAnonymizer(),
            'address' => new AddressAnonymizer(),
            'iban' => new IbanAnonymizer(),
            'date_shift' => new DateShiftAnonymizer(),
            'constant' => new ConstantAnonymizer(),
            'null' => new NullAnonymizer(),
            'hash' => new HashAnonymizer(),
            'lorem' => new LoremAnonymizer(),
            'pattern' => new PatternAnonymizer(),
        ]);
    }

    /**
     * These anonymizers added under their type, each replacing one of the same type.
     *
     * @param iterable<string, Anonymizer> $anonymizers
     */
    public function with(iterable $anonymizers): self
    {
        $all = $this->byType;

        foreach ($anonymizers as $type => $anonymizer) {
            if (1 !== preg_match(Anonymize::TYPE_PATTERN, $type)) {
                throw InvalidPrivacyValue::because(\sprintf('"%s" is not an anonymizer type: lowercase letters, digits and underscores, starting with a letter.', $type));
            }

            $all[$type] = $anonymizer;
        }

        return new self($all);
    }

    public function get(string $type): Anonymizer
    {
        return $this->byType[$type] ?? throw InvalidPrivacyValue::because(\sprintf('No anonymizer answers to the type "%s"; the types are %s.', $type, implode(', ', $this->types())));
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->byType);
    }
}
