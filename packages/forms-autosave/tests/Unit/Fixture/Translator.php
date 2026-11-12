<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates by writing the domain and the identifier.
 */
final class Translator implements TranslatorInterface
{
    /**
     * @param array<mixed> $parameters
     */
    #[Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $domain.'/'.$id;
    }

    #[Override]
    public function getLocale(): string
    {
        return 'fr';
    }
}
