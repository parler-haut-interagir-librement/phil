<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translator that knows a few messages in French, gives back the identifier of the others, and keeps the domain and
 * the locale of each call.
 */
final class RecordingTranslator implements TranslatorInterface
{
    /** @var list<array{string, ?string, ?string}> */
    public array $calls = [];

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->calls[] = [$id, $domain, $locale];

        return match ($id) {
            'training.title' => 'Heures et coûts de formation',
            'training.hours' => 'Heures',
            default => $id,
        };
    }

    #[Override]
    public function getLocale(): string
    {
        return 'fr';
    }
}
