<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Closure;
use Phil\Shifts\Exception\InvalidShift;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\Shifts\Roster\Roster;
use Phil\Shifts\Shift\Shift;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\Message\AllocateInterest;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Infrastructure\Form\ShiftType;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The coordinators' pages: every shift to come, critical ones lacking volunteers first; the form of a shift, new or
 * corrected; a roster with its places, reserve and interested, the buttons to assign, take off and allocate, and,
 * once the shift started, to record an unannounced absence, take it back, and lift the block of a volunteer past the
 * threshold; and the offers of the swap board waiting for an approval.
 *
 * The allocation goes through Messenger when the application has a bus — `AllocateInterest`, handled at once unless
 * routed to a transport —, else it runs during the request.
 */
final readonly class CoordinatorController
{
    public const string INDEX = 'phil_shifts_manage';

    public const string CREATE = 'phil_shifts_manage_create';

    public const string EDIT = 'phil_shifts_manage_edit';

    public const string ROSTER = 'phil_shifts_manage_roster';

    public const string ASSIGN = 'phil_shifts_manage_assign';

    public const string REMOVE = 'phil_shifts_manage_remove';

    public const string ALLOCATE = 'phil_shifts_manage_allocate';

    public const string DELETE = 'phil_shifts_manage_delete';

    public const string APPROVE = 'phil_shifts_manage_approve';

    public const string REFUSE = 'phil_shifts_manage_refuse';

    public const string ABSENT = 'phil_shifts_manage_absent';

    public const string FORGIVE = 'phil_shifts_manage_forgive';

    public const string LIFT = 'phil_shifts_manage_lift';

    public function __construct(
        private Rosters $rosters,
        private SwapBoard $board,
        private FormFactoryInterface $forms,
        private Access $access,
        private Pages $pages,
        private ?MessageBusInterface $bus = null,
        private ?Absences $absences = null,
    ) {
    }

    public function index(): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);

        return $this->listing(null, Response::HTTP_OK);
    }

    public function create(Request $request): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);
        $form = $this->forms->create(ShiftType::class, ShiftType::data());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shift = ShiftType::shift($form);

            if ($shift instanceof Shift) {
                try {
                    $this->rosters->plan($shift);

                    return $this->pages->redirect(self::ROSTER, ['id' => $shift->id]);
                } catch (RequestRefused $refused) {
                    $form->addError(new FormError($refused->reason));
                }
            }
        }

        return $this->form($form, null);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);
        $roster = $this->find($id);
        $form = $this->forms->create(ShiftType::class, ShiftType::data($roster->shift), ['existing' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $shift = ShiftType::shift($form, $id);

            if ($shift instanceof Shift) {
                try {
                    $this->rosters->correct($shift);

                    return $this->pages->redirect(self::ROSTER, ['id' => $id]);
                } catch (InvalidShift $invalidShift) {
                    $form->addError(new FormError($invalidShift->getMessage()));
                } catch (RequestRefused $refused) {
                    $form->addError(new FormError($refused->reason));
                }
            }
        }

        return $this->form($form, $roster);
    }

    public function roster(string $id): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);

        return $this->rosterPage($this->find($id), null, Response::HTTP_OK);
    }

    public function assign(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $coordinator): mixed => $this->rosters->assign($id, trim($request->request->getString('volunteer')), $coordinator));
    }

    public function remove(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (): mixed => $this->rosters->withdraw($id, $request->request->getString('volunteer')));
    }

    public function allocate(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (): mixed => $this->bus instanceof MessageBusInterface ? $this->bus->dispatch(new AllocateInterest($id)) : $this->rosters->allocate($id));
    }

    public function absent(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $coordinator): mixed => $this->absences()->record($id, $request->request->getString('volunteer'), $coordinator));
    }

    public function forgive(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (): mixed => $this->absences()->forgive($id, $request->request->getString('volunteer')));
    }

    public function lift(Request $request, string $id): Response
    {
        return $this->act($request, $id, fn (string $organiser): mixed => $this->absences()->lift($request->request->getString('volunteer'), $organiser));
    }

    public function delete(Request $request, string $id): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);

        try {
            $this->pages->guard()->assertTrusted($request->request->getString('_token'));
        } catch (RequestRefused $requestRefused) {
            return $this->listing($requestRefused->reason, $this->pages->status($requestRefused));
        }

        $this->rosters->remove($id);

        return $this->pages->redirect(self::INDEX);
    }

    public function approve(Request $request, string $offer): Response
    {
        return $this->decide($request, fn (string $coordinator): mixed => $this->board->approve($offer, $coordinator));
    }

    public function refuse(Request $request, string $offer): Response
    {
        return $this->decide($request, fn (string $coordinator): mixed => $this->board->refuse($offer, $coordinator));
    }

    /**
     * @param Closure(string): mixed $action
     */
    private function act(Request $request, string $id, Closure $action): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);
        $roster = $this->find($id);

        try {
            $this->pages->guard()->assertTrusted($request->request->getString('_token'));
            $action($this->access->person());
        } catch (ShiftRefused|RequestRefused $refused) {
            return $this->rosterPage($this->rosters->find($id) ?? $roster, $refused->reason, $this->pages->status($refused));
        } catch (InvalidShift) {
            return $this->rosterPage($roster, 'shifts.invalid_volunteer', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::ROSTER, ['id' => $id]);
    }

    /**
     * @param Closure(string): mixed $action
     */
    private function decide(Request $request, Closure $action): Response
    {
        $this->access->deny(ShiftsVoter::COORDINATE);

        try {
            $this->pages->guard()->assertTrusted($request->request->getString('_token'));
            $action($this->access->person());
        } catch (ShiftRefused|RequestRefused $refused) {
            return $this->listing($refused->reason, $this->pages->status($refused));
        }

        return $this->pages->redirect(self::INDEX);
    }

    private function listing(?string $refused, int $status): Response
    {
        $rosters = $this->rosters->upcoming();
        usort($rosters, static fn (Roster $a, Roster $b): int => (int) $b->isUnderstaffed() <=> (int) $a->isUnderstaffed());

        return $this->pages->render('manage/index.html.twig', ['rosters' => $rosters, 'entries' => $this->board->board(), 'refused' => $refused], $status);
    }

    private function rosterPage(Roster $roster, ?string $refused, int $status): Response
    {
        $blocked = [];

        foreach ($roster->places as $place) {
            if (true === $this->absences?->isBlocked($place->volunteer)) {
                $blocked[] = $place->volunteer;
            }
        }

        return $this->pages->render('manage/roster.html.twig', [
            'roster' => $roster,
            'refused' => $refused,
            'started' => true === $this->absences?->hasStarted($roster),
            'absent' => $this->absences?->absentFrom($roster->shift->id) ?? [],
            'blocked' => $blocked,
        ], $status);
    }

    private function absences(): Absences
    {
        return $this->absences ?? throw new NotFoundHttpException('The absences are not recorded.');
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     */
    private function form(FormInterface $form, ?Roster $roster): Response
    {
        return $this->pages->render('manage/form.html.twig', ['form' => $form->createView(), 'roster' => $roster], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    private function find(string $id): Roster
    {
        return $this->rosters->find($id) ?? throw new NotFoundHttpException('No shift has this identifier.');
    }
}
