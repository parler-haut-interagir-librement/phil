<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A language a design is made in: the code written in the SKU (`F`, `E`) and the locale its products are named in.
 */
final readonly class LanguageVersion
{
    private function __construct(
        public string $code,
        public string $locale,
    ) {
    }

    /**
     * @param string $code   1 to 8 letters, digits, ".", "_" or "-", starting with a letter or digit
     * @param string $locale `fr`, `en`, `fr_CA`
     */
    public static function of(string $code, string $locale): self
    {
        Check::skuCode($code, 'a language version', 8);

        if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})*$/D', $locale)) {
            throw InvalidDefinition::because(\sprintf('"%s" is not a locale: a language of 2 or 3 lowercase letters, then "_" and a region or script.', $locale));
        }

        return new self($code, $locale);
    }
}
