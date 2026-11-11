<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Tests\Unit;

use Override;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * A current locale that stays what it is given, in place of Symfony's `LocaleSwitcher`.
 */
final class CurrentLocale implements LocaleAwareInterface
{
    public function __construct(
        private string $locale,
    ) {
    }

    #[Override]
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    #[Override]
    public function getLocale(): string
    {
        return $this->locale;
    }
}
