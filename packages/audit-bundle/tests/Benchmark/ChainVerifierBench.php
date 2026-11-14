<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\AuditBundle\Domain\Chain\Anchor;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\ChainVerifier;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\RowKind;
use Phil\AuditBundle\Domain\Chain\SiteSegment;

/**
 * Verification recomputes the hash of every row of a segment, which can hold millions of rows, and checks every
 * anchor against the chain: the growth must stay linear in the rows — the segment of one site, whose every row is
 * checked for its tenant, too —, and the lost anchor and restore paths must not turn quadratic in the anchors.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"audit"})
 */
final class ChainVerifierBench
{
    private const string SEGMENT = 'orders';

    private string $segment = self::SEGMENT;

    private readonly ChainVerifier $verifier;

    /**
     * @var list<AuditRow>
     */
    private array $rows = [];

    /**
     * @var list<Anchor>
     */
    private array $anchors = [];

    public function __construct()
    {
        $this->verifier = new ChainVerifier();
    }

    /**
     * Builds only the segment the parameter set asks for: tens of thousands of rows are not rebuilt for nothing.
     *
     * @param array{rows?: int, anchors?: int, site?: bool} $params
     */
    public function setUp(array $params): void
    {
        $start = new DateTimeImmutable('2026-11-01 00:00 UTC');

        if (isset($params['rows'])) {
            // An intact segment, anchored every 100 rows; the segment of one site, each row checked for its tenant.
            $count = $params['rows'];
            $this->segment = ($params['site'] ?? false) ? SiteSegment::of(self::SEGMENT, 'acme') : self::SEGMENT;
            $this->rows = $this->chain($count, $start, 0);

            for ($i = 99; $i < $count; $i += 100) {
                $this->anchors[] = new Anchor($this->segment, $this->rows[$i]->recordedAt->modify('+1 second'), $this->rows[0]->hash, $this->rows[$i]->hash);
            }

            return;
        }

        // A rewritten segment of 2 000 rows with 50 discontinuities: every anchor but the last lost its head, and no
        // discontinuity explains any of them, so each lost anchor scans every anchor and every discontinuity.
        $count = $params['anchors'] ?? 10;
        $this->rows = $this->chain(2_000, $start, 40);
        $last = $this->rows[1_999];

        for ($i = 0; $i < $count - 1; ++$i) {
            $this->anchors[] = new Anchor(self::SEGMENT, $last->recordedAt->modify(\sprintf('+%d seconds', $i + 1)), $this->rows[0]->hash, hash('sha256', 'lost '.$i));
        }

        $this->anchors[] = new Anchor(self::SEGMENT, $last->recordedAt->modify('+1 day'), $this->rows[0]->hash, $last->hash);
    }

    /**
     * @ParamProviders("segments")
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchIntactSegment(): void
    {
        $this->verifier->verify($this->segment, $this->rows, $this->anchors);
    }

    /**
     * @ParamProviders("anchorCounts")
     *
     * @Revs(3)
     *
     * @Iterations(3)
     */
    public function benchLostAnchors(): void
    {
        $this->verifier->verify($this->segment, $this->rows, $this->anchors);
    }

    /**
     * @return Generator<string, array{rows: int, site?: bool}>
     */
    public function segments(): Generator
    {
        yield '1 000 rows, 10 anchors' => ['rows' => 1_000];
        yield '10 000 rows, 100 anchors' => ['rows' => 10_000];
        yield '10 000 rows of one site, 100 anchors' => ['rows' => 10_000, 'site' => true];
        yield '50 000 rows, 500 anchors' => ['rows' => 50_000];
    }

    /**
     * @return Generator<string, array{anchors: int}>
     */
    public function anchorCounts(): Generator
    {
        yield '2 000 rows, 10 anchors (9 lost)' => ['anchors' => 10];
        yield '2 000 rows, 100 anchors (99 lost)' => ['anchors' => 100];
        yield '2 000 rows, 1 000 anchors (999 lost)' => ['anchors' => 1_000];
    }

    /**
     * @param int $discontinuityEvery a discontinuity row every so many rows, or 0 for none
     *
     * @return list<AuditRow>
     */
    private function chain(int $count, DateTimeImmutable $start, int $discontinuityEvery): array
    {
        $rows = [];
        $head = null;

        for ($i = 1; $i <= $count; ++$i) {
            $kind = 0 !== $discontinuityEvery && 0 === $i % $discontinuityEvery ? RowKind::Discontinuity : RowKind::Event;
            $facts = self::SEGMENT === $this->segment ? null : Facts::none()->inTenant('acme');
            $head = AuditRow::append($head, $this->segment, $kind, $start->modify(\sprintf('+%d seconds', $i)), 'v1', \sprintf('{"order":%d,"amount":%d,"currency":"CAD"}', $i, $i * 7), $facts);
            $rows[] = $head;
        }

        return $rows;
    }
}
