<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Audit;

use DateTimeImmutable;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * An audit of the content: when it ran, how, the pages it checked with their reports, and where its own report is
 * published, if it is.
 */
final readonly class AuditResult
{
    public const int MAX_PAGES = 10_000;

    /**
     * @param list<AuditedPage> $pages
     */
    public function __construct(
        public DateTimeImmutable $evaluatedAt,
        public array $pages,
        public EvaluationMethod $method = EvaluationMethod::Automated,
        public ?string $reportUrl = null,
    ) {
        if (\count($pages) > self::MAX_PAGES) {
            throw InvalidStatement::because('audit_pages', \sprintf('An audit holds at most %d pages.', self::MAX_PAGES));
        }
    }
}
