<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Http;

use Phil\TranslationBundle\Application\Comments;
use Phil\TranslationBundle\Application\Exception\MachineTranslationFailed;
use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Application\Progress;
use Phil\TranslationBundle\Application\SourceDocument;
use Phil\TranslationBundle\Application\SourceDocuments;
use Phil\TranslationBundle\Application\TranslationPolicy;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Exception\InvalidXliff;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SourceChange;
use Phil\TranslationMemory\Xliff\MergeResult;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The translation screen: the segments of a document in a target language, with what the memory proposes, the
 * glossary's terms and issues, the quality checks, what changed under a stale target, the target a regional variant
 * inherits, and the comments; a form per segment, a form per comment, machine translation when it is configured, the
 * export of an XLIFF file and the import of one.
 *
 * The screen answers once the application names its documents ({@see SourceDocuments}) and who may translate and
 * review ({@see TranslationPolicy}), and puts the routes behind its firewall. A form posted from another site is
 * refused ({@see SameOriginGuard}). After a change, the browser is sent back to the segment with a `303`. The screen
 * and the export are `GET` requests that store nothing: they show the segments as a preparation would leave them, and
 * the first change stores that preparation.
 */
final readonly class TranslationController
{
    /**
     * The largest XLIFF file imported, in bytes.
     */
    public const int MAX_UPLOAD = 10_485_760;

    /**
     * What the page says when the machine translator fails, whatever it answered.
     */
    public const string MACHINE_FAILED = 'Machine translation failed: nothing was proposed. Try again later.';

    /**
     * The action a segment's form asks for, and the outcome the screen reports after it.
     */
    private const array ACTIONS = ['save' => 'saved', 'suggest' => 'suggested', 'review' => 'reviewed', 'confirm' => 'confirmed', 'clear' => 'cleared', 'dismiss' => 'checked'];

    /**
     * The outcomes the screen reports: those of the segments' forms, of the comments and of machine translation.
     */
    private const array OUTCOMES = [...self::ACTIONS, 'comment' => 'commented', 'uncomment' => 'uncommented', 'machine' => 'machine-translated'];

    public function __construct(
        private Translations $translations,
        private Comments $comments,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private SameOriginGuard $guard,
        private string $layout,
        private ?SourceDocuments $sources = null,
        private ?TranslationPolicy $policy = null,
    ) {
    }

    public function page(Request $request, string $document, string $language): Response
    {
        [$source, $target, $policy] = $this->open($document, $language);
        $done = $request->query->getString('done');

        return $this->render($document, $source, $target, $policy, ['done' => \in_array($done, self::OUTCOMES, true) ? $done : '', 'count' => max(0, $request->query->getInt('count'))]);
    }

    public function save(Request $request, string $document, string $language): Response
    {
        $this->guard->assert($request);
        [$source, $target, $policy] = $this->open($document, $language);
        $key = $request->request->getString('key');
        $action = $request->request->getString('action');
        $text = $request->request->getString('target');

        if (!isset(self::ACTIONS[$action])) {
            throw new BadRequestHttpException(\sprintf('"%s" is not an action of the translation screen.', $action));
        }

        if ('review' === $action && !$policy->canReview($document, $target)) {
            throw new AccessDeniedHttpException('Reviewing is not allowed here.');
        }

        try {
            $this->translations->prepare($document, $source->content, $source->language, $target);
            match ($action) {
                'save' => $this->translations->translate($document, $target, $key, $text),
                'suggest' => $this->translations->suggest($document, $target, $key, $text),
                'review' => $this->translations->review($document, $target, $key),
                'confirm' => $this->translations->confirm($document, $target, $key),
                'dismiss' => $this->translations->dismiss($document, $target, $key, $request->request->getString('check')),
                default => $this->translations->clear($document, $target, $key),
            };
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        } catch (InvalidSegment|InvalidConfiguration|InvalidRecord $invalid) {
            return $this->render($document, $source, $target, $policy, ['error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($document, $target, self::ACTIONS[$action], $key);
    }

    /**
     * Adds a comment to a segment, or removes one; removing is for reviewers.
     */
    public function comment(Request $request, string $document, string $language): Response
    {
        $this->guard->assert($request);
        [$source, $target, $policy] = $this->open($document, $language);
        $key = $request->request->getString('key');

        try {
            if ('remove' === $request->request->getString('action')) {
                if (!$policy->canReview($document, $target)) {
                    throw new AccessDeniedHttpException('Removing a comment is not allowed here.');
                }

                $this->comments->remove($document, $target, $request->request->getInt('comment'));

                return $this->back($document, $target, self::OUTCOMES['uncomment'], $key);
            }

            if (!$this->holds($document, $source, $target, $key)) {
                throw NotFound::segment($document, $target->value, $key);
            }

            $this->comments->add($document, $target, $key, $request->request->getString('body'));
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        } catch (InvalidRecord $invalidRecord) {
            return $this->render($document, $source, $target, $policy, ['error' => $invalidRecord->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->back($document, $target, self::OUTCOMES['comment'], $key);
    }

    /**
     * Proposes the targets of the machine translator to the untranslated segments of the document.
     */
    public function machine(Request $request, string $document, string $language): Response
    {
        $this->guard->assert($request);
        [$source, $target, $policy] = $this->open($document, $language);

        if (!$this->translations->canMachineTranslate()) {
            throw new NotFoundHttpException('Machine translation is off.');
        }

        try {
            $this->translations->prepare($document, $source->content, $source->language, $target);
            $count = $this->translations->machineTranslate($document, $target);
        } catch (MachineTranslationFailed) {
            // What the service answered — an address, a quota, a key — stays out of the page.
            return $this->render($document, $source, $target, $policy, ['error' => self::MACHINE_FAILED], Response::HTTP_BAD_GATEWAY);
        }

        return new RedirectResponse($this->url(Routes::PAGE, $document, $target, ['done' => self::OUTCOMES['machine'], 'count' => $count]), Response::HTTP_SEE_OTHER);
    }

    public function export(string $document, string $language): Response
    {
        [$source, $target] = $this->open($document, $language);
        $filename = str_replace(':', '-', $document).'.'.$target->value.'.xlf';

        return new Response($this->translations->exportPreview($document, $source->content, $source->language, $target), Response::HTTP_OK, [
            'Content-Type' => 'application/xliff+xml; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        ]);
    }

    public function import(Request $request, string $document, string $language): Response
    {
        $this->guard->assert($request);
        [$source, $target, $policy] = $this->open($document, $language);
        $file = $request->files->get('xliff');

        if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > self::MAX_UPLOAD) {
            return $this->render($document, $source, $target, $policy, ['error' => \sprintf('Choose an XLIFF file of at most %d MiB.', self::MAX_UPLOAD >> 20)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->translations->prepare($document, $source->content, $source->language, $target);
            $report = $this->translations->import($document, $target, $file->getContent(), $policy->canReview($document, $target));
        } catch (InvalidXliff|InvalidLanguage $invalid) {
            return $this->render($document, $source, $target, $policy, ['error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render($document, $source, $target, $policy, ['report' => $report]);
    }

    /**
     * The document and target language of a request, once the screen is configured and the user may translate.
     *
     * @return array{SourceDocument, LanguageTag, TranslationPolicy}
     */
    private function open(string $document, string $language): array
    {
        $policy = $this->policy;
        $source = $this->sources?->find($document);

        if (!$source instanceof SourceDocument || !$policy instanceof TranslationPolicy) {
            throw new NotFoundHttpException(\sprintf('There is no document "%s" to translate.', $document));
        }

        try {
            $target = LanguageTag::of($language);
        } catch (InvalidLanguage $invalidLanguage) {
            throw new NotFoundHttpException($invalidLanguage->getMessage(), $invalidLanguage);
        }

        if ($target->equals($source->language) || !$policy->canTranslate($document, $target)) {
            throw new AccessDeniedHttpException(\sprintf('Translating "%s" into "%s" is not allowed here.', $document, $target->value));
        }

        return [$source, $target, $policy];
    }

    /**
     * Whether the document, cut as a preparation would cut it, holds a segment of that key.
     */
    private function holds(string $document, SourceDocument $source, LanguageTag $target, string $key): bool
    {
        return array_any($this->translations->preview($document, $source->content, $source->language, $target)->segments, static fn (Segment $segment): bool => $segment->key === $key);
    }

    /**
     * @param array{done?: string, count?: int, error?: string, report?: MergeResult} $context
     */
    private function render(string $document, SourceDocument $source, LanguageTag $target, TranslationPolicy $policy, array $context, int $status = Response::HTTP_OK): Response
    {
        $segments = $this->translations->preview($document, $source->content, $source->language, $target)->segments;
        $glossary = $this->translations->glossary($source->language, $target);
        $records = $this->translations->records($document, $target);
        $comments = $this->comments->forDocument($document, $target);
        $parent = $this->translations->parentOf($target);
        $inherited = [];

        foreach ($parent instanceof LanguageTag ? $this->translations->resolved($document, $parent) : [] as $segment) {
            if ($segment->isUsable()) {
                $inherited[$segment->key] = $segment;
            }
        }

        $changes = new SourceChange();
        $rows = array_map(function (Segment $segment) use ($source, $target, $glossary, $records, $comments, $inherited, $changes): array {
            $record = $records[$segment->key] ?? null;
            $flags = $record instanceof SegmentRecord ? $record->flags() : new Flags();
            $fallback = $inherited[$segment->key] ?? null;

            return [
                'segment' => $segment,
                'anchor' => $this->anchor($segment->key),
                'change' => $changes->of($segment),
                'suggestions' => $segment->isUsable() ? [] : $this->translations->matches($segment, $source->language, $target),
                'terms' => $glossary->find($segment->source),
                'issues' => null === $segment->target ? [] : $glossary->check($segment->source, $segment->target),
                'checks' => $this->translations->check($segment, $source->language, $target, $flags),
                'flags' => $flags->all(),
                'machine' => $record instanceof SegmentRecord && $record->isMachine() && null !== $segment->target,
                'inherited' => !$segment->isUsable() && $fallback instanceof Segment && $fallback->fingerprint->equals($segment->fingerprint) ? $fallback->target : null,
                'comments' => $comments[$segment->key] ?? [],
            ];
        }, $segments);

        return new Response($this->twig->render('@PhilTranslation/translate.html.twig', [
            'layout' => $this->layout,
            'title' => '' === $source->title ? $document : $source->title,
            'document' => $document,
            'source_language' => $source->language->value,
            'target_language' => $target->value,
            'parent_language' => $parent?->value,
            'rows' => $rows,
            'progress' => Progress::of($segments),
            'can_review' => $policy->canReview($document, $target),
            'can_machine_translate' => $this->translations->canMachineTranslate(),
            'urls' => [
                'save' => $this->url(Routes::SAVE, $document, $target),
                'comment' => $this->url(Routes::COMMENT, $document, $target),
                'machine' => $this->url(Routes::MACHINE, $document, $target),
                'export' => $this->url(Routes::EXPORT, $document, $target),
                'import' => $this->url(Routes::IMPORT, $document, $target),
            ],
            'csrf_cookie' => $this->guard->cookieName(),
            'done' => '',
            'error' => null,
            'report' => null,
            ...$context,
        ]), $status);
    }

    private function back(string $document, LanguageTag $target, string $outcome, string $key): RedirectResponse
    {
        return new RedirectResponse($this->url(Routes::PAGE, $document, $target, ['done' => $outcome]).'#'.$this->anchor($key), Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, int|string> $query
     */
    private function url(string $route, string $document, LanguageTag $target, array $query = []): string
    {
        return $this->urls->generate($route, ['document' => $document, 'language' => $target->value, ...$query]);
    }

    private function anchor(string $key): string
    {
        return 'segment-'.hash('xxh3', $key);
    }
}
