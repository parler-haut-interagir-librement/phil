<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Infrastructure;

use Override;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Phil\AuditBundle\Application\TrailVerification;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;
use Phil\AuditBundle\Infrastructure\Scan\ScanningAuditLogReader;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerController;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\AuditViewer\Tests\Unit\Fixture\Grants;
use Phil\AuditViewer\Tests\Unit\Fixture\InMemoryRows;
use Phil\AuditViewer\Tests\Unit\Fixture\OneToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The controller over templates that print what they are given, so that each action's context is read as text.
 */
#[CoversClass(AuditViewerController::class)]
final class ControllerTest extends TestCase
{
    private InMemoryRows $rows;

    private MockClock $clock;

    private AuditTrail $trail;

    private Grants $grants;

    #[Override]
    protected function setUp(): void
    {
        $this->rows = new InMemoryRows();
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->trail = new AuditTrail($this->rows, $this->serializer(), $this->clock);
        $this->grants = new Grants([AuditViewerVoter::VIEW, AuditViewerVoter::HISTORY]);
        $this->trail->record('orders', ['from' => 'cart'], Facts::of('order.created', 'acct-7', Subject::of('order', '42')));
        $this->clock->modify('+1 minute');
        $this->trail->record('orders', ['from' => 'paid'], Facts::of('workflow.order.pay', 'acct-7', Subject::of('order', '42')));
        $this->clock->modify('+1 minute');
        $this->trail->record('members', ['plan' => 'basic'], Facts::of('member.joined', 'acct-8'));
    }

    public function testEveryPageNeedsTheViewersAttribute(): void
    {
        $controller = $this->controller();
        $this->grants->granted = [];

        foreach ([
            'PHIL_AUDIT_VIEW' => static fn (): mixed => $controller->log(new Request()),
            'PHIL_AUDIT_VIEW ' => static fn (): mixed => $controller->row('orders', 1),
            'PHIL_AUDIT_HISTORY' => static fn (): mixed => $controller->history('order', '42', new Request()),
            'PHIL_AUDIT_VIEW  ' => $controller->chain(...),
            'PHIL_AUDIT_VIEW   ' => static fn (): mixed => $controller->verify('orders'),
        ] as $attribute => $page) {
            try {
                $page();
                self::fail('A page opened without its attribute.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertSame(\sprintf('The audit log needs %s.', trim($attribute)), $accessDeniedException->getMessage());
            }
        }

        self::assertEquals([[AuditViewerVoter::VIEW, null], [AuditViewerVoter::VIEW, null], [AuditViewerVoter::HISTORY, Subject::of('order', '42')], [AuditViewerVoter::VIEW, null], [AuditViewerVoter::VIEW, 'orders']], $this->grants->asked, 'The history is asked for with its record, a segment with its name.');

        $this->grants->granted = [AuditViewerVoter::VIEW];
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageIsOrContains('The audit log needs PHIL_AUDIT_EXPORT.');
        $controller->export(new Request());
    }

    public function testTheLogPage(): void
    {
        $controller = $this->controller();
        $response = $controller->log(new Request(['actor' => 'acct-7']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('log|orders#2,orders#1|last|errors:|tz:America/Toronto|export:no|csrf:phil_audit_viewer_export', $response->getContent());
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        self::assertSame('log|members#1,orders#2|next|errors:|tz:America/Toronto|export:no|csrf:phil_audit_viewer_export', $controller->log(new Request())->getContent());

        $this->grants->granted[] = AuditViewerVoter::EXPORT;
        self::assertStringContainsString('|export:yes|', $controller->log(new Request())->getContent());
        self::assertStringContainsString('|export:no|', (string) $this->controller(trail: false)->log(new Request())->getContent(), 'No export without a trail to record it in.');

        $refused = $controller->log(new Request(['actor' => 'a@b', 'segment' => 'Orders']));
        self::assertSame(400, $refused->getStatusCode());
        self::assertSame('log|none|errors:actor,segment|tz:America/Toronto|export:yes|csrf:phil_audit_viewer_export', $refused->getContent());
    }

    public function testARowPage(): void
    {
        $controller = $this->controller();

        self::assertSame('row|orders#2|fields:from=paid|tz:America/Toronto', $controller->row('orders', 2)->getContent());
        self::assertSame([AuditViewerVoter::VIEW, null], $this->grants->asked[0], 'The log first, before a row is read.');
        self::assertSame([AuditViewerVoter::VIEW, $this->rows->rows['orders'][2]], $this->grants->asked[1], 'Then the row itself.');
        self::assertSame('row|orders#2|fields:none|tz:America/Toronto', $this->controller(payloads: false)->row('orders', 2)->getContent());
        self::assertTrue($controller->row('orders', 2)->headers->hasCacheControlDirective('no-store'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No such row of the audit log.');
        $controller->row('orders', 9);
    }

    public function testAHistoryPage(): void
    {
        $controller = $this->controller();
        $response = $controller->history('order', '42', new Request());

        self::assertSame('history|viewer|order 42|orders#1,orders#2|last|fields:from=cart;from=paid|tz:America/Toronto', $response->getContent());
        self::assertSame('history|viewer|order 42|orders#2|last|fields:from=paid|tz:America/Toronto', $controller->history('order', '42', new Request(['cursor' => $this->rows->rows['orders'][1]->instant().'~orders~1']))->getContent());
        self::assertSame('history|viewer|order 42|orders#1,orders#2|last|fields:-;-|tz:America/Toronto', $this->controller(payloads: false)->history('order', '42', new Request())->getContent());
        $this->grants->granted = [AuditViewerVoter::HISTORY];
        self::assertStringStartsWith('history|history only|order 42|', (string) $controller->history('order', '42', new Request())->getContent(), 'The history alone, without the log.');
        $this->grants->granted = [AuditViewerVoter::VIEW, AuditViewerVoter::HISTORY];

        foreach ([['Order', '42', []], ['order', 'a b', []], ['order', '42', ['cursor' => 'nonsense']]] as [$type, $id, $query]) {
            try {
                $controller->history($type, $id, new Request($query));
                self::fail('A history was read from what does not read.');
            } catch (BadRequestHttpException $badRequestHttpException) {
                self::assertNotSame('', $badRequestHttpException->getMessage());
            }
        }
    }

    public function testTheChainPages(): void
    {
        $controller = $this->controller();

        self::assertSame('chain|[]members:unverified,orders:unverified|tz:America/Toronto', $controller->chain()->getContent());
        self::assertSame('segment|orders:holds|head:2|tz:America/Toronto', $controller->verify('orders')->getContent());

        $sites = new AuditTrail($this->rows, $this->serializer(), $this->clock, siteSegments: true);
        $zeta = $sites->record('orders', [], Facts::of('order.created', null, null, 'zeta'));
        $acme = $sites->record('orders', [], Facts::of('order.created', null, null, 'acme'));
        self::assertSame(\sprintf('chain|[]members:unverified,orders:unverified[acme]%s:unverified[zeta]%s:unverified|tz:America/Toronto', $acme->segment, $zeta->segment), $controller->chain()->getContent(), 'The shared segments first, then each site by its tenant.');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('No such segment of the audit log.');
        $controller->verify('payments');
    }

    public function testAnExportIsRecordedBeforeItIsWritten(): void
    {
        $this->grants->granted = [AuditViewerVoter::EXPORT];
        $controller = $this->controller();

        try {
            $controller->export(new Request(request: ['_token' => 'forged']));
            self::fail('An export ran without its token.');
        } catch (BadRequestHttpException $badRequestHttpException) {
            self::assertSame('The export form has expired: load the page again.', $badRequestHttpException->getMessage());
        }

        try {
            $controller->export(new Request(request: ['_token' => 'valid', 'actor' => 'a@b', 'until' => 'x']));
            self::fail('An export ran with filters that do not read.');
        } catch (BadRequestHttpException $badRequestHttpException) {
            self::assertSame('These filters do not read: actor, until.', $badRequestHttpException->getMessage());
        }

        self::assertArrayNotHasKey('audit', $this->rows->rows, 'Nothing refused is recorded.');

        $response = $controller->export(new Request(request: ['_token' => 'valid', 'segment' => 'orders']));
        $journal = $this->rows->rows['audit'][1];
        self::assertSame(['audit.export', 'acct-1', '{"filter_segment":"orders","format":"csv","limit":2}'], [$journal->facts->action, $journal->facts->actor, $journal->payload]);

        ob_start();
        $response->sendContent();
        $lines = explode("\r\n", trim((string) ob_get_clean()));
        self::assertCount(3, $lines);
        self::assertStringContainsString(',orders,2,', $lines[1]);
        self::assertStringContainsString(',orders,1,', $lines[2]);
        self::assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=audit-20261108-100200.csv', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($response->headers->hasCacheControlDirective('private'));

        $anonymous = $this->controller(actors: false)->export(new Request(request: ['_token' => 'valid']));
        self::assertNull($this->rows->rows['audit'][2]->facts->actor);
        ob_start();
        $anonymous->sendContent();
        self::assertCount(3, explode("\r\n", trim((string) ob_get_clean())), 'At most the limit, the export of a moment ago first.');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('The export is recorded in the trail, which has no serializer.');
        $this->controller(trail: false)->export(new Request(request: ['_token' => 'valid']));
    }

    public function testAPolicyThatNarrowsByRowOrBySiteHoldsOnEveryList(): void
    {
        $sites = new AuditTrail($this->rows, $this->serializer(), $this->clock, siteSegments: true);
        $acme = $sites->record('orders', ['from' => 'acme'], Facts::of('order.created', 'acct-7', null, 'acme'));
        $this->clock->modify('+1 minute');
        $zeta = $sites->record('orders', ['from' => 'zeta'], Facts::of('order.created', 'acct-7', null, 'zeta'));
        // Only acme's rows and segment, and none of another site or of no site.
        $this->grants->granted = [AuditViewerVoter::VIEW, AuditViewerVoter::EXPORT];
        $this->grants->policy = static fn (mixed $attribute, mixed $subject): bool => match (true) {
            $subject instanceof AuditRow => 'acme' === $subject->facts->tenant,
            \is_string($subject) => $subject === $acme->segment,
            default => null === $subject,
        };
        $controller = $this->controller();

        self::assertSame(\sprintf('log|%s#1|next|errors:|tz:America/Toronto|export:yes|csrf:phil_audit_viewer_export', $acme->segment), $controller->log(new Request(['actor' => 'acct-7']))->getContent(), "Zeta's row is left out; the link to the next page stays.");
        self::assertContains([AuditViewerVoter::VIEW, $zeta], $this->grants->asked, 'Each row listed is asked for with itself.');
        self::assertSame(\sprintf('chain|[acme]%s:unverified|tz:America/Toronto', $acme->segment), $controller->chain()->getContent(), "Neither the shared segments nor zeta's.");

        $this->grants->asked = [];
        $response = $controller->export(new Request(request: ['_token' => 'valid', 'actor' => 'acct-7']));
        ob_start();
        $response->sendContent();
        $lines = explode("\r\n", trim((string) ob_get_clean()));
        self::assertCount(2, $lines, "The header and acme's row: zeta's is refused, and the limit of two rows read is reached.");
        self::assertStringContainsString(\sprintf(',%s,1,', $acme->segment), $lines[1]);
        self::assertSame([[AuditViewerVoter::EXPORT, null], [AuditViewerVoter::EXPORT, $zeta], [AuditViewerVoter::EXPORT, $acme]], $this->grants->asked, 'The export as a whole, then each row it reads, no more than the limit.');
    }

    private function controller(bool $trail = true, bool $actors = true, bool $payloads = true): AuditViewerController
    {
        $twig = new Environment(new ArrayLoader([
            '@PhilAuditViewer/log.html.twig' => 'log|{% if page %}{{ page.rows|map(r => r.segment ~ "#" ~ r.sequence)|join(",") }}|{{ page.next ? "next" : "last" }}{% if page.rows and page.rows|keys != range(0, page.rows|length - 1) %}|NOT-A-LIST{% endif %}{% else %}none{% endif %}|errors:{{ filters.errors|join(",") }}|tz:{{ timezone }}|export:{{ exportable ? "yes" : "no" }}|csrf:{{ csrf_id }}',
            '@PhilAuditViewer/row.html.twig' => 'row|{{ row.segment }}#{{ row.sequence }}|fields:{% if fields is null %}none{% else %}{% for k, v in fields %}{{ k }}={{ v }}{% endfor %}{% endif %}|tz:{{ timezone }}',
            '@PhilAuditViewer/history.html.twig' => 'history|{{ viewer ? "viewer" : "history only" }}|{{ subject.type }} {{ subject.id }}|{{ page.rows|map(r => r.segment ~ "#" ~ r.sequence)|join(",") }}|{{ page.next ? "next" : "last" }}|fields:{{ fields|map(f => f is null ? "-" : f|map((v, k) => k ~ "=" ~ v)|join(","))|join(";") }}|tz:{{ timezone }}',
            '@PhilAuditViewer/chain.html.twig' => 'chain|{% for site, segments in sites %}[{{ site }}]{{ segments|map(s => s.segment ~ ":" ~ s.state().value)|join(",") }}{% endfor %}|tz:{{ timezone }}',
            '@PhilAuditViewer/segment.html.twig' => 'segment|{{ status.segment }}:{{ status.state().value }}|head:{{ status.head.sequence }}|tz:{{ timezone }}',
        ]), ['strict_variables' => true]);

        return new AuditViewerController(
            $twig,
            new AuditLog(new ScanningAuditLogReader($this->rows)),
            new ChainStatus($this->rows, new TrailVerification($this->rows)),
            $this->grants,
            new OneToken(AuditViewerController::CSRF_ID),
            $this->clock,
            'America/Toronto',
            2,
            'audit',
            2,
            $trail ? $this->trail : null,
            $actors ? new class implements ActorResolver {
                #[Override]
                public function current(): string
                {
                    return 'acct-1';
                }
            } : null,
            $payloads ? new class implements PayloadReader {
                #[Override]
                public function fields(AuditRow $row): array
                {
                    /** @var array<string, scalar|null> */
                    return json_decode($row->payload, true, flags: \JSON_THROW_ON_ERROR);
                }
            } : null,
        );
    }

    private function serializer(): CanonicalSerializer
    {
        return new class implements CanonicalSerializer {
            #[Override]
            public function version(): string
            {
                return 'json-1';
            }

            #[Override]
            public function serialize(array $fields): string
            {
                ksort($fields);

                return json_encode($fields, \JSON_THROW_ON_ERROR);
            }
        };
    }
}
