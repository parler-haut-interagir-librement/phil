<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Http;

use DateTimeImmutable;
use Phil\LegalNotices\DocumentHistory;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\DurationWords;
use Phil\LegalNotices\LegalDocumentVersion;
use Phil\LegalNotices\LegalNotices;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The public pages of a document: the version in force at its own address, each version the public sees at an address
 * that never changes, and the history of the versions. The privacy policy also lists the retention periods and the
 * consent categories, unless the configuration leaves them to its body.
 */
final readonly class DocumentController
{
    public const string ROUTE_PREFIX = 'phil_legal_notices_';

    public const string CURRENT_CACHE = 'max-age=300, public';

    public const string VERSION_CACHE = 'max-age=3600, public';

    public function __construct(
        private LegalNotices $notices,
        private Environment $twig,
        private ClockInterface $clock,
        private string $layout,
        private bool $retentionSection = true,
        private bool $consentSection = true,
    ) {
    }

    /**
     * The name of a route of a document: its page (`''`), its `history` or a `version`.
     */
    public static function route(DocumentType $type, string $page = ''): string
    {
        return self::ROUTE_PREFIX.$type->value.('' === $page ? '' : '_'.$page);
    }

    public function current(Request $request, string $type): Response
    {
        $document = $this->type($type);
        $version = $this->notices->inForce($document, $this->clock->now()) ?? throw new NotFoundHttpException('The document has no version in force.');

        return $this->show($request, $version, self::CURRENT_CACHE);
    }

    public function version(Request $request, string $type, int $number): Response
    {
        $version = $this->notices->history($this->type($type))->find($number) ?? throw new NotFoundHttpException('No such version.');

        return $this->show($request, $version, self::VERSION_CACHE);
    }

    public function history(Request $request, string $type): Response
    {
        $history = $this->notices->history($this->type($type));

        if ([] === $history->publicVersions()) {
            throw new NotFoundHttpException('The document has no version yet.');
        }

        return $this->render($request, 'history', ['type' => $history->type, 'versions' => $history->publicVersions()] + $this->moments($history), self::CURRENT_CACHE);
    }

    private function show(Request $request, LegalDocumentVersion $version, string $cacheControl): Response
    {
        $locale = $request->getLocale();
        $privacy = DocumentType::PrivacyPolicy === $version->type;

        return $this->render($request, 'document', [
            'type' => $version->type,
            'version' => $version,
            'html' => $this->notices->render($version, $locale),
            'summary' => $version->summary($locale, $request->getDefaultLocale()),
            'retention' => $privacy && $this->retentionSection ? $this->retention($locale) : [],
            'consent' => $privacy && $this->consentSection ? $this->consent($locale, $request->getDefaultLocale()) : [],
        ] + $this->moments($this->notices->history($version->type)), $cacheControl);
    }

    /**
     * @return array{now: DateTimeImmutable, in_force: LegalDocumentVersion|null, upcoming: LegalDocumentVersion|null}
     */
    private function moments(DocumentHistory $history): array
    {
        $now = $this->clock->now();

        return ['now' => $now, 'in_force' => $history->inForce($now), 'upcoming' => $history->upcoming($now)];
    }

    /**
     * @return list<array{label: string, period: string}>
     */
    private function retention(string $locale): array
    {
        $rows = [];

        foreach ($this->notices->retention() as $duration) {
            $rows[] = ['label' => $duration->label($locale), 'period' => DurationWords::describe($duration, $locale)];
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, description: string|null, necessary: bool, cookies: list<string>}>
     */
    private function consent(string $locale, string $fallback): array
    {
        $rows = [];

        foreach ($this->notices->consent() as $category) {
            $rows[] = ['label' => $category->label($locale, $fallback), 'description' => $category->description($locale, $fallback), 'necessary' => $category->necessary, 'cookies' => $category->cookies];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(Request $request, string $template, array $context, string $cacheControl): Response
    {
        return new Response(
            $this->twig->render('@PhilLegalNotices/'.$template.'.html.twig', $context + ['layout' => $this->layout]),
            Response::HTTP_OK,
            ['Cache-Control' => $cacheControl, 'Content-Language' => str_replace('_', '-', $request->getLocale())],
        );
    }

    private function type(string $type): DocumentType
    {
        return DocumentType::tryFrom($type) ?? throw new NotFoundHttpException('No such document.');
    }
}
