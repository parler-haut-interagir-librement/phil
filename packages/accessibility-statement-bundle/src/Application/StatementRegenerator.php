<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Application;

use DateTimeZone;
use Phil\AccessibilityStatement\Contract\AuditSource;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatementBundle\Application\Exception\NothingToRegenerate;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use Psr\Clock\ClockInterface;

/**
 * The next version of the statement, from the version in force and the latest audit: shown, or published. It is dated
 * in UTC, whatever the time zone of the clock or of the server.
 */
final readonly class StatementRegenerator
{
    public function __construct(
        private StatementRepository $statements,
        private AuditSource $audits,
        private StatementGenerator $generator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The next version, not published.
     *
     * @throws NothingToRegenerate
     */
    public function preview(): Statement
    {
        $current = $this->statements->latest() ?? throw NothingToRegenerate::noStatement();
        $audit = $this->audits->latest() ?? throw NothingToRegenerate::noAudit();

        return $this->generator->generate($current, $audit, $this->clock->now()->setTimezone(new DateTimeZone('UTC')));
    }

    /**
     * The next version, published.
     *
     * @throws NothingToRegenerate
     * @throws StatementNotStored
     */
    public function publish(): Statement
    {
        $statement = $this->preview();
        $this->statements->add($statement);

        return $statement;
    }
}
