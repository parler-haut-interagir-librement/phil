<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\EasyAdmin;

use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditPage;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\AuditViewer\Infrastructure\Csv\AuditCsv;
use Phil\AuditViewer\Infrastructure\Http\AuditFilters;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The pages of the audit log in an EasyAdmin back office, under every dashboard: the log with its filters, one row,
 * the history of one record, the state of the hash chain and the verification of a segment, and the CSV export, which
 * is recorded in the trail before a line is written. Every page asks for `PHIL_AUDIT_VIEW` — with the row or the
 * segment it shows —, the history of a record for `PHIL_AUDIT_HISTORY` with its `Subject`, the export for
 * `PHIL_AUDIT_EXPORT`, and nothing here changes a row of the log. The chain page groups the segments by site when
 * the trail keeps one per tenant.
 *
 * What a page lists is asked for row by row too, so that an `AuditAccessPolicy` that narrows by record or by site
 * holds on every list: each row of a page of the log for `PHIL_AUDIT_VIEW` with its `AuditRow`, each segment of the
 * chain page for `PHIL_AUDIT_VIEW` with its name, each row of the export for `PHIL_AUDIT_EXPORT` with its `AuditRow`.
 * A row refused is left out; a page may then hold fewer rows than its size, and its link to the next page stays.
 */
#[AdminRoute(path: '/audit', name: 'audit')]
final readonly class AuditViewerController
{
    public const string CSRF_ID = 'phil_audit_viewer_export';

    private const array SEGMENT = ['segment' => '[a-z0-9][a-z0-9._-]{0,63}'];

    public function __construct(
        private Environment $twig,
        private AuditLog $log,
        private ChainStatus $chain,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $csrf,
        private ClockInterface $clock,
        private string $timezone,
        private int $pageSize,
        private string $exportSegment,
        private int $exportLimit,
        private ?AuditTrail $trail = null,
        private ?ActorResolver $actors = null,
        private ?PayloadReader $payloads = null,
    ) {
    }

    #[AdminRoute(path: '/', name: 'log', options: ['methods' => ['GET']])]
    public function log(Request $request): Response
    {
        $this->deny(AuditViewerVoter::VIEW);
        $filters = AuditFilters::read($request->query, new DateTimeZone($this->timezone), $this->pageSize);
        $page = $filters->query instanceof AuditQuery ? $this->log->page($filters->query) : null;

        if ($page instanceof AuditPage) {
            // A policy that narrows by row or by site holds on the list too, not only on the page of one row.
            $page = new AuditPage(array_values(array_filter($page->rows, fn (AuditRow $row): bool => $this->authorization->isGranted(AuditViewerVoter::VIEW, $row))), $page->next);
        }

        return $this->render('@PhilAuditViewer/log.html.twig', [
            'filters' => $filters,
            'page' => $page,
            'timezone' => $this->timezone,
            'exportable' => $this->trail instanceof AuditTrail && $this->authorization->isGranted(AuditViewerVoter::EXPORT),
            'csrf_id' => self::CSRF_ID,
        ], [] === $filters->errors ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    #[AdminRoute(path: '/row/{segment}/{sequence}', name: 'row', options: ['methods' => ['GET'], 'requirements' => self::SEGMENT + ['sequence' => '[1-9]\d{0,17}']])]
    public function row(string $segment, int $sequence): Response
    {
        $this->deny(AuditViewerVoter::VIEW);
        $row = $this->log->row($segment, $sequence) ?? throw new NotFoundHttpException('No such row of the audit log.');
        $this->deny(AuditViewerVoter::VIEW, $row);

        return $this->render('@PhilAuditViewer/row.html.twig', ['row' => $row, 'fields' => $this->payloads?->fields($row), 'timezone' => $this->timezone]);
    }

    #[AdminRoute(path: '/history/{type}/{id}', name: 'history', options: ['methods' => ['GET'], 'requirements' => ['type' => '[a-z][a-z0-9._-]{0,63}', 'id' => '[A-Za-z0-9][A-Za-z0-9._:-]{0,127}']])]
    public function history(string $type, string $id, Request $request): Response
    {
        try {
            $subject = Subject::of($type, $id);
            $this->deny(AuditViewerVoter::HISTORY, $subject);
            $token = $request->query->getString('cursor');
            $page = $this->log->history($subject, '' === $token ? null : Cursor::fromToken($token), $this->pageSize);
        } catch (InvalidValue $invalidValue) {
            throw new BadRequestHttpException($invalidValue->getMessage(), $invalidValue);
        }

        return $this->render('@PhilAuditViewer/history.html.twig', [
            'subject' => $subject,
            'page' => $page,
            'fields' => array_map(fn (AuditRow $row): ?array => $this->payloads?->fields($row), $page->rows),
            'timezone' => $this->timezone,
            'viewer' => $this->authorization->isGranted(AuditViewerVoter::VIEW),
        ]);
    }

    #[AdminRoute(path: '/chain', name: 'chain', options: ['methods' => ['GET']])]
    public function chain(): Response
    {
        $this->deny(AuditViewerVoter::VIEW);

        $sites = [];

        foreach ($this->chain->segments() as $status) {
            if (!$this->authorization->isGranted(AuditViewerVoter::VIEW, $status->segment)) {
                continue;
            }

            $sites[$status->site() ?? ''][] = $status;
        }

        ksort($sites, \SORT_STRING);

        return $this->render('@PhilAuditViewer/chain.html.twig', ['sites' => $sites, 'timezone' => $this->timezone]);
    }

    #[AdminRoute(path: '/chain/{segment}', name: 'verify', options: ['methods' => ['GET'], 'requirements' => self::SEGMENT])]
    public function verify(string $segment): Response
    {
        $this->deny(AuditViewerVoter::VIEW, $segment);
        $status = $this->chain->verify($segment);

        if (!$status->head instanceof AuditRow) {
            throw new NotFoundHttpException('No such segment of the audit log.');
        }

        return $this->render('@PhilAuditViewer/segment.html.twig', ['status' => $status, 'timezone' => $this->timezone]);
    }

    /**
     * The rows of the filters as CSV, at most `export_limit` of them. The export is recorded first — who, when, with
     * which filters and which limit —, so that a download cut short is in the trail too.
     */
    #[AdminRoute(path: '/export', name: 'export', options: ['methods' => ['POST']])]
    public function export(Request $request): StreamedResponse
    {
        $this->deny(AuditViewerVoter::EXPORT);
        $trail = $this->trail ?? throw new NotFoundHttpException('The export is recorded in the trail, which has no serializer.');

        if (!$this->csrf->isTokenValid(new CsrfToken(self::CSRF_ID, $request->request->getString('_token')))) {
            throw new BadRequestHttpException('The export form has expired: load the page again.');
        }

        $filters = AuditFilters::read($request->request, new DateTimeZone($this->timezone), $this->pageSize);
        $query = $filters->query ?? throw new BadRequestHttpException(\sprintf('These filters do not read: %s.', implode(', ', $filters->errors)));

        $trail->record($this->exportSegment, ['format' => 'csv', 'limit' => $this->exportLimit] + $filters->journal(), Facts::of('audit.export', $this->actors?->current()));

        $response = new StreamedResponse(function () use ($query): void {
            $output = fopen('php://output', 'w');

            if (false !== $output) {
                AuditCsv::write($output, $this->exported($query), $this->exportLimit);
                fclose($output);
            }
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', \sprintf('audit-%s.csv', $this->clock->now()->format('Ymd-His'))));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    /**
     * The rows of the export the policy allows, each asked for `PHIL_AUDIT_EXPORT` with its `AuditRow`. At most
     * `export_limit` rows are read, allowed or not, so that a policy that refuses most of them never has the export
     * read the whole log.
     *
     * @return iterable<AuditRow>
     */
    private function exported(AuditQuery $query): iterable
    {
        $read = 0;

        foreach ($this->log->each($query) as $row) {
            if (++$read > $this->exportLimit) {
                return;
            }

            if ($this->authorization->isGranted(AuditViewerVoter::EXPORT, $row)) {
                yield $row;
            }
        }
    }

    private function deny(string $attribute, mixed $subject = null): void
    {
        if (!$this->authorization->isGranted($attribute, $subject)) {
            throw new AccessDeniedException(\sprintf('The audit log needs %s.', $attribute));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        $response = new Response($this->twig->render($template, $context), $status);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
