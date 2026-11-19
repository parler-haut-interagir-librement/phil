<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Http;

use Phil\LibraryBundle\Application\Contract\MemberResolver;
use Phil\LibraryBundle\Application\Loans;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The member's own loans, the latest first: those reserved and out with their due dates — the overdue ones marked —,
 * and the history the library still keeps. The member is who the application's resolver says; without one, the page
 * is not found.
 */
final readonly class MemberLoansController
{
    public const string ROUTE = 'phil_library_my_loans';

    public function __construct(
        private Pages $pages,
        private ?MemberResolver $members,
        private Loans $loans,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws NotFoundHttpException when the application names no member resolver
     * @throws AccessDeniedException when the request is nobody's
     */
    public function __invoke(): Response
    {
        if (!$this->members instanceof MemberResolver) {
            throw new NotFoundHttpException('The application names no member resolver.');
        }

        $member = $this->members->current() ?? throw new AccessDeniedException();

        return $this->pages->render('loans', ['loans' => $this->loans->ofMember($member), 'now' => $this->clock->now()]);
    }
}
