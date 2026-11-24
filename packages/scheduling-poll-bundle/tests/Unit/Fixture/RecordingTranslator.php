<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A translator that gives back the identifier it is asked for and keeps the parameters of the last request.
 */
final class RecordingTranslator implements TranslatorInterface
{
    /** @var array<array-key, mixed> */
    public array $parameters = [];

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[Override]
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->parameters = $parameters;

        return $id;
    }

    #[Override]
    public function getLocale(): string
    {
        return 'en';
    }
}
