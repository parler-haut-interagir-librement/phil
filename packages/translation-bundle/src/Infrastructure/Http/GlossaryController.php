<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\Http;

use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\TranslationPolicy;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\TermRecord;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidTbx;
use Phil\TranslationMemory\Exception\InvalidTerm;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\LanguageTag;
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
 * The glossary pages of a pair of languages: the terms in a table, a form to add or change a term, a button to remove
 * one, the export of the glossary as TBX and the import of a TBX file. Every change goes through `Glossaries`, as the
 * application's own code would; an EasyAdmin back office links to these pages ({@see \Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin}).
 *
 * The pages answer once the application says who may edit glossaries ({@see TranslationPolicy::canEditGlossary()}).
 * A form posted from another site is refused ({@see SameOriginGuard}); after a change, the browser is sent back with a
 * `303`.
 */
final readonly class GlossaryController
{
    /**
     * The largest TBX file imported, in bytes.
     */
    public const int MAX_UPLOAD = 10_485_760;

    /**
     * The outcomes the page reports.
     */
    private const array OUTCOMES = ['saved', 'removed', 'imported'];

    public function __construct(
        private Glossaries $glossaries,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private SameOriginGuard $guard,
        private string $layout,
        private ?TranslationPolicy $policy = null,
    ) {
    }

    public function page(Request $request, string $source, string $target): Response
    {
        [$sourceLanguage, $targetLanguage] = $this->open($source, $target);
        $done = $request->query->getString('done');

        return $this->render($sourceLanguage, $targetLanguage, ['done' => \in_array($done, self::OUTCOMES, true) ? $done : '', 'count' => max(0, $request->query->getInt('count'))]);
    }

    /**
     * Adds or changes a term, or removes one.
     */
    public function save(Request $request, string $source, string $target): Response
    {
        $this->guard->assert($request);
        [$sourceLanguage, $targetLanguage] = $this->open($source, $target);
        $action = $request->request->getString('action');

        try {
            match ($action) {
                'save' => $this->glossaries->put($sourceLanguage, $targetLanguage, new Term(
                    $request->request->getString('term'),
                    $this->lines($request->request->getString('translations')),
                    $this->lines($request->request->getString('forbidden')),
                    $request->request->getBoolean('case_sensitive'),
                    '' === trim($request->request->getString('note')) ? null : trim($request->request->getString('note')),
                    $this->lines($request->request->getString('forms')),
                    $this->lines($request->request->getString('translation_forms')),
                )),
                'remove' => $this->glossaries->remove($sourceLanguage, $targetLanguage, $request->request->getString('term')),
                default => throw new BadRequestHttpException(\sprintf('"%s" is not an action of the glossary.', $action)),
            };
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        } catch (InvalidTerm|InvalidRecord $invalid) {
            return $this->render($sourceLanguage, $targetLanguage, ['error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->url(Routes::GLOSSARY, $sourceLanguage, $targetLanguage, ['done' => 'save' === $action ? 'saved' : 'removed']), Response::HTTP_SEE_OTHER);
    }

    public function export(string $source, string $target): Response
    {
        [$sourceLanguage, $targetLanguage] = $this->open($source, $target);

        return new Response($this->glossaries->export($sourceLanguage, $targetLanguage), Response::HTTP_OK, [
            'Content-Type' => 'application/x-tbx+xml; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, \sprintf('glossary.%s.%s.tbx', $sourceLanguage->value, $targetLanguage->value)),
        ]);
    }

    public function import(Request $request, string $source, string $target): Response
    {
        $this->guard->assert($request);
        [$sourceLanguage, $targetLanguage] = $this->open($source, $target);
        $file = $request->files->get('tbx');

        if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() > self::MAX_UPLOAD) {
            return $this->render($sourceLanguage, $targetLanguage, ['error' => \sprintf('Choose a TBX file of at most %d MiB.', self::MAX_UPLOAD >> 20)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $count = $this->glossaries->import($sourceLanguage, $targetLanguage, $file->getContent());
        } catch (InvalidTbx|InvalidRecord $invalid) {
            return $this->render($sourceLanguage, $targetLanguage, ['error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new RedirectResponse($this->url(Routes::GLOSSARY, $sourceLanguage, $targetLanguage, ['done' => 'imported', 'count' => $count]), Response::HTTP_SEE_OTHER);
    }

    /**
     * The pair of languages of a request, once the pages are configured and the user may edit that glossary.
     *
     * @return array{LanguageTag, LanguageTag}
     */
    private function open(string $source, string $target): array
    {
        try {
            $sourceLanguage = LanguageTag::of($source);
            $targetLanguage = LanguageTag::of($target);
        } catch (InvalidLanguage $invalidLanguage) {
            throw new NotFoundHttpException($invalidLanguage->getMessage(), $invalidLanguage);
        }

        if (!$this->policy instanceof TranslationPolicy) {
            throw new NotFoundHttpException('There is no glossary to edit.');
        }

        if ($sourceLanguage->equals($targetLanguage) || !$this->policy->canEditGlossary($sourceLanguage, $targetLanguage)) {
            throw new AccessDeniedHttpException(\sprintf('Editing the glossary from "%s" to "%s" is not allowed here.', $sourceLanguage->value, $targetLanguage->value));
        }

        return [$sourceLanguage, $targetLanguage];
    }

    /**
     * The non-empty lines of a text area, trimmed.
     *
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $lines = preg_split('/\R/u', $text);

        return array_values(array_filter(array_map(trim(...), false === $lines ? [] : $lines), static fn (string $line): bool => '' !== $line));
    }

    /**
     * @param array{done?: string, count?: int, error?: string} $context
     */
    private function render(LanguageTag $sourceLanguage, LanguageTag $targetLanguage, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render('@PhilTranslation/glossary.html.twig', [
            'layout' => $this->layout,
            'source_language' => $sourceLanguage->value,
            'target_language' => $targetLanguage->value,
            'terms' => array_map(static fn (TermRecord $record): Term => $record->term(), $this->glossaries->terms($sourceLanguage, $targetLanguage)),
            'urls' => [
                'save' => $this->url(Routes::GLOSSARY_SAVE, $sourceLanguage, $targetLanguage),
                'export' => $this->url(Routes::GLOSSARY_EXPORT, $sourceLanguage, $targetLanguage),
                'import' => $this->url(Routes::GLOSSARY_IMPORT, $sourceLanguage, $targetLanguage),
            ],
            'csrf_cookie' => $this->guard->cookieName(),
            'done' => '',
            'error' => null,
            ...$context,
        ]), $status);
    }

    /**
     * @param array<string, int|string> $query
     */
    private function url(string $route, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, array $query = []): string
    {
        return $this->urls->generate($route, ['source' => $sourceLanguage->value, 'target' => $targetLanguage->value, ...$query]);
    }
}
