<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Http;

use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Domain\Contract\LoanRepository;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Infrastructure\Form\CopyType;
use Phil\LibraryBundle\Infrastructure\Form\TitleData;
use Phil\LibraryBundle\Infrastructure\Form\TitleType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The administration of the catalogue, for the staff: a title added or its record edited, a copy added to a title, and
 * the list of the loans overdue. Its pages keep their own routes, role and forms; an EasyAdmin back office links to them.
 */
final readonly class AdminController
{
    public const string NEW_TITLE_ROUTE = 'phil_library_admin_title_new';

    public const string EDIT_TITLE_ROUTE = 'phil_library_admin_title_edit';

    public const string NEW_COPY_ROUTE = 'phil_library_admin_copy_new';

    public const string OVERDUE_ROUTE = 'phil_library_admin_overdue';

    /** The most loans the overdue list shows. */
    public const int OVERDUE = 500;

    public function __construct(
        private Pages $pages,
        private FormFactoryInterface $forms,
        private Catalogue $catalogue,
        private LoanRepository $loans,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $role,
    ) {
    }

    public function newTitle(Request $request): Response
    {
        $this->pages->denyUnlessGranted($this->role);

        return $this->titleForm($request, new TitleData(), null);
    }

    public function editTitle(Request $request, string $id): Response
    {
        $this->pages->denyUnlessGranted($this->role);

        try {
            $title = $this->catalogue->title($id);
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        }

        return $this->titleForm($request, TitleData::of($title->record()), $title->id);
    }

    public function newCopy(Request $request, string $id): Response
    {
        $this->pages->denyUnlessGranted($this->role);

        try {
            $title = $this->catalogue->title($id);
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        }

        $form = $this->forms->create(CopyType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{barcode: string, condition: CopyCondition, stockroomItem: ?string} $data */
            $data = $form->getData();

            try {
                $this->catalogue->addCopy($title->id, trim($data['barcode']), $data['condition'], $this->optional($data['stockroomItem']));

                return $this->seeOther(CatalogueController::TITLE_ROUTE, $title->id);
            } catch (InvalidLibraryData $invalid) {
                $form->addError(new FormError($invalid->getMessage()));
            }
        }

        return $this->pages->render('admin/copy_form', ['form' => $form->createView(), 'title' => $title], $this->status($form->isSubmitted()));
    }

    public function overdue(): Response
    {
        $this->pages->denyUnlessGranted($this->role);
        $now = $this->clock->now();

        return $this->pages->render('admin/overdue', [
            'loans' => array_values(array_filter($this->loans->dueBefore($now, self::OVERDUE), static fn (Loan $loan): bool => $loan->isOverdue($now))),
            'now' => $now,
        ]);
    }

    private function titleForm(Request $request, TitleData $data, ?string $id): Response
    {
        $form = $this->forms->create(TitleType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $title = null === $id ? $this->catalogue->add($data->record()) : $this->catalogue->describe($id, $data->record());

                return $this->seeOther(CatalogueController::TITLE_ROUTE, $title->id);
            } catch (InvalidLibraryData $invalid) {
                $form->addError(new FormError($invalid->getMessage()));
            }
        }

        return $this->pages->render('admin/title_form', ['form' => $form->createView(), 'id' => $id], $this->status($form->isSubmitted()));
    }

    private function seeOther(string $route, string $id): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, ['id' => $id]), Response::HTTP_SEE_OTHER);
    }

    /**
     * The status of a form shown again: refused when it was sent.
     */
    private function status(bool $submitted): int
    {
        return $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;
    }

    private function optional(?string $value): ?string
    {
        $value = null === $value ? '' : trim($value);

        return '' === $value ? null : $value;
    }
}
