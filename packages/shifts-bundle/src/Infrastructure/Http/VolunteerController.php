<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Closure;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Roster;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The volunteers' pages: the shifts to come, critical ones lacking volunteers first; a shift with its places and the
 * buttons to sign up, say one is interested or withdraw; and one's own shifts with the minutes served.
 */
final readonly class VolunteerController
{
    public const string BOARD = 'phil_shifts_board';

    public const string SHOW = 'phil_shifts_show';

    public const string MINE = 'phil_shifts_mine';

    public const string SIGN_UP = 'phil_shifts_sign_up';

    public const string INTEREST = 'phil_shifts_interest';

    public const string WITHDRAW = 'phil_shifts_withdraw';

    public function __construct(
        private Rosters $rosters,
        private Access $access,
        private Pages $pages,
    ) {
    }

    public function board(): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);
        $rosters = $this->rosters->upcoming();
        usort($rosters, static fn (Roster $a, Roster $b): int => (int) $b->isUnderstaffed() <=> (int) $a->isUnderstaffed());

        return $this->pages->render('board.html.twig', ['rosters' => $rosters, 'person' => $this->access->person()]);
    }

    public function show(string $id): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);

        return $this->page($this->find($id), null, Response::HTTP_OK);
    }

    public function mine(): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);
        $person = $this->access->person();

        return $this->pages->render('mine.html.twig', ['person' => $person, 'rosters' => $this->rosters->ofVolunteer($person), 'served' => $this->rosters->served($person)]);
    }

    public function signUp(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $person): Roster => $this->rosters->signUp($id, $person, $this->access->roles()));
    }

    public function interest(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $person): Roster => $this->rosters->expressInterest($id, $person, $this->access->roles()));
    }

    public function withdraw(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $person): Roster => $this->rosters->withdraw($id, $person));
    }

    /**
     * @param Closure(string): Roster $action
     */
    private function act(Request $request, string $id, Closure $action): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);
        $roster = $this->find($id);

        try {
            $this->pages->guard()->assertTrusted($request->request->getString('_token'));
            $action($this->access->person());
        } catch (ShiftRefused|RequestRefused $refused) {
            return $this->page($this->rosters->find($id) ?? $roster, $refused->reason, $this->pages->status($refused));
        }

        return $this->pages->redirect(self::SHOW, ['id' => $id]);
    }

    private function page(Roster $roster, ?string $refused, int $status): Response
    {
        return $this->pages->render('shift.html.twig', ['roster' => $roster, 'open' => $this->rosters->isOpen($roster), 'person' => $this->access->person(), 'refused' => $refused], $status);
    }

    private function find(string $id): Roster
    {
        return $this->rosters->find($id) ?? throw new NotFoundHttpException('No shift has this identifier.');
    }
}
