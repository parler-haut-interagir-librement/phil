<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\ExternalPersonalDataEraser;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;
use RuntimeException;

/**
 * An external service that keeps what it was asked to erase, and the key of each request.
 */
final class RecordingExternalEraser implements ExternalPersonalDataEraser
{
    /**
     * @var list<array{string, string}>
     */
    public array $requests = [];

    public bool $fails = false;

    public function __construct(
        private readonly string $module = 'mailing',
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString($this->module);
    }

    #[Override]
    public function request(DataSubject $subject, string $idempotencyKey): void
    {
        if ($this->fails) {
            throw new RuntimeException('The service refused ada@example.org.');
        }

        $this->requests[] = [$subject->value, $idempotencyKey];
    }
}
