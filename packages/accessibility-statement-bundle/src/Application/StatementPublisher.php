<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Application;

use DateTimeInterface;
use DateTimeZone;
use Phil\AccessibilityStatement\Exception\AccessibilityStatementException;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementReader;
use Phil\AccessibilityStatementBundle\Application\Exception\PublicationRefused;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Domain\Exception\StatementNotStored;
use Psr\Clock\ClockInterface;

/**
 * What the administration page publishes: a document of the next version, read as the configuration's statement is,
 * offered first as a draft — the version the latest audit gives when there is one, otherwise the version in force
 * renumbered and dated now, in UTC — for the person to review and publish.
 */
final readonly class StatementPublisher
{
    public function __construct(
        private StatementRepository $statements,
        private ClockInterface $clock,
        private ?StatementRegenerator $regenerator = null,
    ) {
    }

    /**
     * The next version as JSON, or null when no version is published to start from.
     */
    public function draft(): ?string
    {
        $next = null;

        try {
            $next = $this->regenerator?->preview()->toArray();
        } catch (AccessibilityStatementException) {
            // No audit to read, or an audit that does not give a statement: the version in force is the draft.
        }

        if (null === $next) {
            $latest = $this->statements->latest();

            if (!$latest instanceof Statement) {
                return null;
            }

            $next = ['version' => $latest->version + 1, 'published_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM)] + $latest->toArray();
        }

        return json_encode($next, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Publishes the document as the next version, never changed afterwards; the reader refuses a document over 1 MiB.
     *
     * @throws PublicationRefused
     */
    public function publish(string $document): Statement
    {
        try {
            $statement = StatementReader::fromJson($document);
        } catch (AccessibilityStatementException $accessibilityStatementException) {
            throw PublicationRefused::unreadable($accessibilityStatementException);
        }

        $latest = $this->statements->latest();
        $next = ($latest instanceof Statement ? $latest->version : 0) + 1;

        if ($statement->version !== $next) {
            throw PublicationRefused::notNext($statement->version, $next);
        }

        try {
            $this->statements->add($statement);
        } catch (StatementNotStored $statementNotStored) {
            throw PublicationRefused::notStored($statementNotStored);
        }

        return $statement;
    }
}
