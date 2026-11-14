<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Writes back the key, its domain and its parameters.
 */
final class EchoTranslator implements TranslatorInterface
{
    /**
     * @param array<array-key, mixed> $parameters
     */
    #[Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return \sprintf('%s@%s%s', $id, $domain ?? 'messages', [] === $parameters ? '' : json_encode($parameters, \JSON_THROW_ON_ERROR));
    }

    #[Override]
    public function getLocale(): string
    {
        return 'en';
    }
}
