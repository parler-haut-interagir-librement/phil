<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Http;

use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\UnknownSection;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\Contract\FaqRepository;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\Markup\MarkupFormat;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The administration of the questions: the sections and their questions in one language, with their state and their
 * votes; the forms that write a section or a question; the buttons that move, publish and delete; the totals of the
 * votes and of the searches without answer. Every change goes through `FaqEditor`, whose refusals the forms show.
 */
final readonly class FaqAdminController
{
    public const string INDEX_ROUTE = 'phil_faq_admin_index';

    public const string STATISTICS_ROUTE = 'phil_faq_admin_statistics';

    /** The formats an answer is written in from the administration. */
    public const array FORMATS = [MarkupFormat::Markdown, MarkupFormat::Html, MarkupFormat::Text];

    /** The outcomes the index reports after a change: one of these, never what a crafted link puts in the address. */
    private const array OUTCOMES = ['saved', 'deleted', 'moved'];

    private const int UNANSWERED_SHOWN = 100;

    public function __construct(
        private AdminGuard $guard,
        private FaqEditor $editor,
        private FaqRepository $repository,
        private UnansweredSearchRepository $unanswered,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->guard->denyUnlessGranted();
        $locale = $this->guard->locale($request);
        $titles = $this->repository->sectionTranslations();
        $texts = $this->repository->questionTranslations(false);
        $rows = [];

        foreach ($this->repository->sections() as $section) {
            $questions = [];

            foreach ($this->repository->questionsOf($section) as $question) {
                $questions[] = ['question' => $question, 'translation' => $texts[$question->id][$locale->value] ?? null];
            }

            $title = $titles[$section->id][$locale->value] ?? null;
            $rows[] = ['section' => $section, 'title' => $title?->title(), 'questions' => $questions];
        }

        $done = $request->query->getString('done');

        return $this->guard->render('index', [
            'rows' => $rows,
            'locale' => $locale->value,
            'done' => \in_array($done, self::OUTCOMES, true) ? $done : '',
        ]);
    }

    public function newSection(Request $request): Response
    {
        $this->guard->denyUnlessGranted();
        $locale = $this->guard->locale($request);
        $values = ['title' => '', 'key' => ''];

        if ($request->isMethod('POST')) {
            $this->guard->guardForm($request);
            $values = ['title' => $request->request->getString('title'), 'key' => trim($request->request->getString('key'))];

            try {
                $this->editor->addSection($locale, $values['title'], '' === $values['key'] ? null : $values['key']);

                return $this->done($locale, 'saved');
            } catch (InvalidFaq $invalid) {
                return $this->guard->render('section', ['section' => null, 'values' => $values, 'locale' => $locale->value, 'error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->guard->render('section', ['section' => null, 'values' => $values, 'locale' => $locale->value, 'error' => null]);
    }

    public function editSection(Request $request, string $id): Response
    {
        $this->guard->denyUnlessGranted();
        $locale = $this->guard->locale($request);
        $section = $this->section($id);
        $values = ['title' => $this->repository->sectionTranslation($section, $locale->value)?->title() ?? '', 'key' => $section->key()];

        if ($request->isMethod('POST')) {
            $this->guard->guardForm($request);
            $values['title'] = $request->request->getString('title');

            try {
                $this->editor->retitleSection($section->id, $locale, $values['title']);

                return $this->done($locale, 'saved');
            } catch (InvalidFaq $invalid) {
                return $this->guard->render('section', ['section' => $section, 'values' => $values, 'locale' => $locale->value, 'error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->guard->render('section', ['section' => $section, 'values' => $values, 'locale' => $locale->value, 'error' => null]);
    }

    public function moveSection(Request $request, string $id): RedirectResponse
    {
        $this->guard->guardForm($request);
        $section = $this->section($id);
        $before = $this->before($this->repository->sections(), $section, $request->request->getString('direction'));

        if (false !== $before) {
            $this->editor->placeSection($section->id, $before?->id);
        }

        return $this->done($this->guard->locale($request), 'moved');
    }

    public function deleteSection(Request $request, string $id): RedirectResponse
    {
        $this->guard->guardForm($request);

        try {
            $this->editor->removeSection($id);
        } catch (UnknownSection) {
            throw new NotFoundHttpException();
        }

        return $this->done($this->guard->locale($request), 'deleted');
    }

    public function newQuestion(Request $request, string $section): Response
    {
        $this->guard->denyUnlessGranted();
        $locale = $this->guard->locale($request);
        $parent = $this->section($section);
        $values = ['text' => '', 'answer' => '', 'format' => MarkupFormat::Markdown->value, 'published' => true];

        if ($request->isMethod('POST')) {
            $this->guard->guardForm($request);
            $values = $this->submitted($request);

            try {
                $this->editor->addQuestion($parent->id, $locale, $values['text'], $values['answer'], $values['format'], $values['published']);

                return $this->done($locale, 'saved');
            } catch (InvalidFaq $invalid) {
                return $this->questionForm($parent, null, $values, $locale, $invalid->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->questionForm($parent, null, $values, $locale, null);
    }

    public function editQuestion(Request $request, string $id): Response
    {
        $this->guard->denyUnlessGranted();
        $locale = $this->guard->locale($request);
        $question = $this->question($id);
        $translation = $this->repository->questionTranslation($question, $locale->value);
        $values = $translation instanceof QuestionTranslation
            ? ['text' => $translation->text(), 'answer' => $translation->answer()->source, 'format' => $translation->answer()->format, 'published' => $translation->isPublished()]
            : ['text' => '', 'answer' => '', 'format' => MarkupFormat::Markdown->value, 'published' => false];

        if ($request->isMethod('POST')) {
            $this->guard->guardForm($request);
            $values = $this->submitted($request);

            try {
                $this->editor->rewriteQuestion($question->id, $locale, $values['text'], $values['answer'], $values['format'], $values['published']);

                return $this->done($locale, 'saved');
            } catch (InvalidFaq $invalid) {
                return $this->questionForm($question->section(), $question, $values, $locale, $invalid->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $this->questionForm($question->section(), $question, $values, $locale, null);
    }

    public function moveQuestion(Request $request, string $id): RedirectResponse
    {
        $this->guard->guardForm($request);
        $question = $this->question($id);
        $section = $question->section();
        $before = $this->before($this->repository->questionsOf($section), $question, $request->request->getString('direction'));

        if (false !== $before) {
            $this->editor->placeQuestion($question->id, $section->id, $before?->id);
        }

        return $this->done($this->guard->locale($request), 'moved');
    }

    public function publishQuestion(Request $request, string $id): RedirectResponse
    {
        $this->guard->guardForm($request);
        $locale = $this->guard->locale($request);

        try {
            $this->editor->publish($id, $locale, '1' === $request->request->getString('published'));
        } catch (UnknownQuestion) {
            throw new NotFoundHttpException();
        }

        return $this->done($locale, 'saved');
    }

    public function deleteQuestion(Request $request, string $id): RedirectResponse
    {
        $this->guard->guardForm($request);

        try {
            $this->editor->removeQuestion($id);
        } catch (UnknownQuestion) {
            throw new NotFoundHttpException();
        }

        return $this->done($this->guard->locale($request), 'deleted');
    }

    public function statistics(): Response
    {
        $this->guard->denyUnlessGranted();

        return $this->guard->render('statistics', [
            'questions' => $this->repository->questions(),
            'unanswered' => $this->unanswered->mostFrequent(self::UNANSWERED_SHOWN),
        ]);
    }

    public function forgetSearch(Request $request): RedirectResponse
    {
        $this->guard->guardForm($request);

        try {
            $locale = Locale::fromString($request->request->getString('locale'));
        } catch (InvalidFaq) {
            throw new NotFoundHttpException();
        }

        $this->unanswered->forget($locale->value, $request->request->getString('query'));

        return $this->guard->seeOther(self::STATISTICS_ROUTE);
    }

    /**
     * What to place the object before so that it goes one step up or down: the previous one, or the one after the
     * next — null for the end —; false when it is already first or last.
     *
     * @template T of Section|Question
     *
     * @param list<T> $list
     * @param T       $object
     *
     * @return T|false|null
     */
    private function before(array $list, Section|Question $object, string $direction): Section|Question|false|null
    {
        $at = (int) array_search($object, $list, true);

        if ('up' === $direction) {
            return 0 === $at ? false : $list[$at - 1];
        }

        return $at === \count($list) - 1 ? false : $list[$at + 2] ?? null;
    }

    /**
     * @return array{text: string, answer: string, format: string, published: bool}
     */
    private function submitted(Request $request): array
    {
        $format = $request->request->getString('format');
        $formats = array_map(static fn (MarkupFormat $one): string => $one->value, self::FORMATS);

        return [
            'text' => $request->request->getString('text'),
            'answer' => $request->request->getString('answer'),
            'format' => \in_array($format, $formats, true) ? $format : MarkupFormat::Markdown->value,
            'published' => '1' === $request->request->getString('published'),
        ];
    }

    /**
     * @param array{text: string, answer: string, format: string, published: bool} $values
     */
    private function questionForm(Section $section, ?Question $question, array $values, Locale $locale, ?string $error, int $status = Response::HTTP_OK): Response
    {
        return $this->guard->render('question', [
            'section' => $section,
            'question' => $question,
            'values' => $values,
            'locale' => $locale->value,
            'formats' => array_map(static fn (MarkupFormat $one): string => $one->value, self::FORMATS),
            'error' => $error,
        ], $status);
    }

    private function done(Locale $locale, string $outcome): RedirectResponse
    {
        return $this->guard->seeOther(self::INDEX_ROUTE, ['locale' => $locale->value, 'done' => $outcome]);
    }

    private function section(string $id): Section
    {
        return $this->repository->section($id) ?? throw new NotFoundHttpException();
    }

    private function question(string $id): Question
    {
        return $this->repository->question($id) ?? throw new NotFoundHttpException();
    }
}
