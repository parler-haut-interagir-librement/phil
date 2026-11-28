<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Closure;
use Phil\Shifts\Exception\ShiftRefused;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Application\SwapBoard;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The swap board: the places offered on shifts still open, with the buttons to take one or cancel one's own; and the
 * offer of one's place from the page of a shift.
 */
final readonly class SwapController
{
    public const string BOARD = 'phil_shifts_swaps';

    public const string OFFER = 'phil_shifts_swap_offer';

    public const string TAKE = 'phil_shifts_swap_take';

    public const string CANCEL = 'phil_shifts_swap_cancel';

    public function __construct(
        private SwapBoard $board,
        private Access $access,
        private Pages $pages,
    ) {
    }

    public function board(): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);

        return $this->page(null, Response::HTTP_OK);
    }

    public function offer(Request $request, string $id): Response
    {
        return $this->act($request, fn (string $person): mixed => $this->board->offer($id, $person, mb_substr($request->request->getString('note'), 0, 500)));
    }

    public function take(Request $request, string $offer): Response
    {
        return $this->act($request, fn (string $person): mixed => $this->board->take($offer, $person, $this->access->roles()));
    }

    public function cancel(Request $request, string $offer): Response
    {
        return $this->act($request, fn (string $person): mixed => $this->board->cancel($offer, $person));
    }

    /**
     * @param Closure(string): mixed $action
     */
    private function act(Request $request, Closure $action): Response
    {
        $this->access->deny(ShiftsVoter::VOLUNTEER);

        try {
            $this->pages->guard()->assertTrusted($request->request->getString('_token'));
            $action($this->access->person());
        } catch (ShiftRefused|RequestRefused $refused) {
            return $this->page($refused->reason, $this->pages->status($refused));
        }

        return $this->pages->redirect(self::BOARD);
    }

    private function page(?string $refused, int $status): Response
    {
        return $this->pages->render('swaps.html.twig', ['entries' => $this->board->board(), 'person' => $this->access->person(), 'refused' => $refused], $status);
    }
}
