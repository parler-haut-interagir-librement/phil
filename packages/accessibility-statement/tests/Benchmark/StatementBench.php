<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Benchmark;

use DateTimeImmutable;
use Phil\AccessibilityCheck\Finding;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Audit\AuditedPage;
use Phil\AccessibilityStatement\Audit\AuditResult;
use Phil\AccessibilityStatement\ConformanceTarget;
use Phil\AccessibilityStatement\Statement;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatement\StatementReader;

/**
 * A statement is read on each view of its page when it is kept as JSON: one of 200 non-conformities, in two languages,
 * is read and checked. Its next version is generated from an audit of 500 pages of 20 findings each. Both must stay in
 * proportion to their size.
 */
final readonly class StatementBench
{
    private string $json;

    private Statement $statement;

    private AuditResult $audit;

    private StatementGenerator $generator;

    public function __construct()
    {
        $this->generator = new StatementGenerator();
        $criteria = new ConformanceTarget()->criteria();
        $nonConformities = [];

        for ($i = 0; $i < 200; ++$i) {
            $nonConformities[] = [
                'criterion' => $criteria[$i % \count($criteria)]->value,
                'description' => ['en' => str_repeat('What fails and where. ', 10), 'fr' => str_repeat('Ce qui échoue et où. ', 10)],
                'alternative' => ['en' => 'Ask us.', 'fr' => 'Demandez-nous.'],
                'fix_by' => '2027-06-30',
                'occurrences' => $i,
                'from_audit' => 0 === $i % 2,
            ];
        }

        $data = [
            'version' => 12,
            'published_at' => '2026-11-08T10:00:00+00:00',
            'entity' => 'Example Org',
            'scope' => ['en' => 'The site.', 'fr' => 'Le site.'],
            'feedback' => ['email' => 'accessibility@example.com', 'phone' => '+1 514 555 0100', 'response_days' => 10],
            'non_conformities' => $nonConformities,
            'alternatives' => [['en' => 'Call us.', 'fr' => 'Appelez-nous.']],
            'enforcement' => ['procedure' => ['en' => 'Complain.', 'fr' => 'Plaignez-vous.'], 'url' => 'https://example.org/'],
            'evaluation' => ['evaluated_at' => '2026-11-01', 'method' => 'automated', 'pages' => 500],
        ];
        $this->json = (string) json_encode($data);
        $this->statement = StatementReader::fromArray($data);

        $cases = SuccessCriterion::cases();
        $pages = [];

        for ($page = 0; $page < 500; ++$page) {
            $findings = [];

            for ($finding = 0; $finding < 20; ++$finding) {
                $findings[] = new Finding('rule', $cases[($page + $finding) % \count($cases)], Severity::Error, new Message('key'), new Location(line: $finding + 1));
            }

            $pages[] = new AuditedPage('/page/'.$page, new Report(findings: $findings));
        }

        $this->audit = new AuditResult(new DateTimeImmutable('2026-11-07'), $pages);
    }

    /**
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchReadJson(): void
    {
        StatementReader::fromJson($this->json);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchGenerate(): void
    {
        $this->generator->generate($this->statement, $this->audit, new DateTimeImmutable('2026-11-08T12:00:00+00:00'));
    }
}
