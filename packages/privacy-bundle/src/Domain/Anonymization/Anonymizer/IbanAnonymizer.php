<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * `iban` — an account number of the IBAN shape whose check digits are right, so that a validator in the copied
 * application accepts it, and whose bank and account are drawn at random: it names no real account but may, by
 * chance, match one, and is never used to send money.
 *
 * Option: `country`, an ISO 3166-1 code among those below; by default the locale pack's country, which must be one
 * of them.
 */
final readonly class IbanAnonymizer implements Anonymizer
{
    /**
     * The shape of each country's basic bank account number: `#` a digit, `?` an uppercase letter.
     */
    private const array BBAN = [
        'AT' => '################',
        'BE' => '############',
        'CH' => '#################',
        'DE' => '##################',
        'ES' => '####################',
        'FR' => '#######################',
        'GB' => '????##############',
        'IE' => '????##############',
        'IT' => '?######################',
        'LU' => '################',
        'NL' => '????##########',
        'PT' => '#####################',
    ];

    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        $country = $context->options->string('country', $context->locale->country);
        $shape = self::BBAN[$country] ?? throw InvalidPrivacyValue::because(\sprintf('The iban anonymizer has no account shape for "%s"; give it a "country" option among %s.', $country, implode(', ', array_keys(self::BBAN))));
        $bban = $context->fill($shape);

        return $country.\sprintf('%02d', 98 - $this->modulo97($bban.$country.'00')).$bban;
    }

    /**
     * The remainder by 97 of the number the letters and digits stand for, letters counting 10 to 35, read digit by
     * digit so that it never overflows an integer.
     */
    private function modulo97(string $characters): int
    {
        $remainder = 0;

        foreach (str_split($characters) as $character) {
            $value = \intval($character, 36);
            $remainder = ($remainder * ($value > 9 ? 100 : 10) + $value) % 97;
        }

        return $remainder;
    }
}
