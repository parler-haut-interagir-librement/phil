<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Http;

use DateTimeImmutable;
use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Contract\TitleSearch;
use Phil\LibraryBundle\Application\Exception\NotFound;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Phil\LibraryBundle\Infrastructure\Form\SearchType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public pages of the catalogue: the search, a list filtered by a form sent by GET, paged by links, without
 * JavaScript; and a title's page, its record and its copies, each on the shelf, out until a date, or withdrawn.
 */
final readonly class CatalogueController
{
    public const string SEARCH_ROUTE = 'phil_library_catalogue';

    public const string TITLE_ROUTE = 'phil_library_title';

    public function __construct(
        private Pages $pages,
        private FormFactoryInterface $forms,
        private TitleSearch $search,
        private Catalogue $catalogue,
        private Loans $loans,
        private ClockInterface $clock,
        private int $perPage,
    ) {
    }

    public function search(Request $request): Response
    {
        $form = $this->forms->create(SearchType::class);
        $form->handleRequest($request);

        $criteria = new SearchCriteria();
        $status = Response::HTTP_OK;

        if ($form->isSubmitted()) {
            try {
                $criteria = $form->isValid() ? $this->criteria($form->getData()) : $criteria;
            } catch (InvalidLibraryData) {
            }

            $status = $form->isValid() ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        $result = $this->search->search($criteria, $this->page($request->query->all()['page'] ?? null), $this->perPage);

        return $this->pages->render('catalogue', [
            'form' => $form->createView(),
            'result' => $result,
            'query' => array_filter($request->query->all(), static fn (mixed $value, int|string $key): bool => 'page' !== $key && \is_string($value), \ARRAY_FILTER_USE_BOTH),
        ], $status);
    }

    public function title(string $id): Response
    {
        try {
            $title = $this->catalogue->title($id);
            $copies = $this->catalogue->copies($id);
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException($notFound->getMessage(), $notFound);
        }

        $now = $this->clock->now();

        return $this->pages->render('title', [
            'title' => $title,
            'record' => $title->record(),
            'copies' => array_map(fn (Copy $copy): array => ['copy' => $copy, 'out' => $this->out($copy, $now)], $copies),
            'now' => $now,
        ]);
    }

    /**
     * The loan that keeps the copy now, if any: active, or a reservation whose span holds this moment.
     */
    private function out(Copy $copy, DateTimeImmutable $now): ?Loan
    {
        return array_find($this->loans->holding($copy), static fn (Loan $loan): bool => LoanStatus::Active === $loan->status() || $loan->startsAt()->getTimestamp() <= $now->getTimestamp());
    }

    /**
     * @throws InvalidLibraryData when a value is out of bounds
     */
    private function criteria(mixed $data): SearchCriteria
    {
        $data = \is_array($data) ? $data : [];
        $kind = $data['kind'] ?? null;

        return new SearchCriteria(
            $this->string($data['q'] ?? null),
            $kind instanceof TitleKind ? $kind : null,
            $this->integer($data['players'] ?? null),
            $this->integer($data['minutes'] ?? null),
            $this->integer($data['age'] ?? null),
            availableOnly: true === ($data['available'] ?? false),
        );
    }

    /**
     * The page asked for, 1 to 1000; the first one for anything else.
     */
    private function page(mixed $value): int
    {
        return \is_string($value) && 1 === preg_match('/^[1-9]\d{0,2}$|^1000$/D', $value) ? (int) $value : 1;
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return \is_int($value) ? $value : null;
    }
}
