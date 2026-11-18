<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Closure;
use Override;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * A module, or the purger of a store, whose work runs a closure first: a step that lasts, or during which another
 * worker takes the lock over. Counts the times it ran.
 */
final class InterruptingModule implements PersonalDataExporter, PersonalDataEraser, RetentionPurger
{
    public int $calls = 0;

    /**
     * @param Closure(): void $during
     */
    public function __construct(
        private readonly string $module,
        private readonly Closure $during,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString($this->module);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $this->run();

        return ['subject' => $subject->value];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->run();

        return ErasureReport::of($this->module, ['records' => ErasureEffect::Deleted]);
    }

    #[Override]
    public function store(): string
    {
        return $this->module;
    }

    #[Override]
    public function purge(PurgeOrder $order): int
    {
        $this->run();

        return 1;
    }

    private function run(): void
    {
        ++$this->calls;
        ($this->during)();
    }
}
