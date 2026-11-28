<?php

declare(strict_types=1);

use Phil\ShiftsBundle\Infrastructure\Http\CoordinatorController;
use Phil\ShiftsBundle\Infrastructure\Http\SwapController;
use Phil\ShiftsBundle\Infrastructure\Http\VolunteerController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The pages of the shifts, under the prefix the application gives them:
 *
 *     phil_shifts:
 *         resource: '@PhilShiftsBundle/config/routes.php'
 *         prefix: /shifts
 */
return static function (RoutingConfigurator $routes): void {
    $shift = ['id' => '[a-z0-9][a-z0-9_.-]{0,63}'];
    $offer = ['offer' => '[a-z0-9][a-z0-9_.-]{0,63}'];

    $routes->add(VolunteerController::BOARD, '/')->controller([VolunteerController::class, 'board'])->methods(['GET']);
    $routes->add(VolunteerController::MINE, '/mine')->controller([VolunteerController::class, 'mine'])->methods(['GET']);
    $routes->add(SwapController::BOARD, '/swaps')->controller([SwapController::class, 'board'])->methods(['GET']);
    $routes->add(SwapController::TAKE, '/swaps/{offer}/take')->controller([SwapController::class, 'take'])->methods(['POST'])->requirements($offer);
    $routes->add(SwapController::CANCEL, '/swaps/{offer}/cancel')->controller([SwapController::class, 'cancel'])->methods(['POST'])->requirements($offer);
    $routes->add(CoordinatorController::INDEX, '/manage')->controller([CoordinatorController::class, 'index'])->methods(['GET']);
    $routes->add(CoordinatorController::CREATE, '/manage/new')->controller([CoordinatorController::class, 'create'])->methods(['GET', 'POST']);
    $routes->add(CoordinatorController::APPROVE, '/manage/swaps/{offer}/approve')->controller([CoordinatorController::class, 'approve'])->methods(['POST'])->requirements($offer);
    $routes->add(CoordinatorController::REFUSE, '/manage/swaps/{offer}/refuse')->controller([CoordinatorController::class, 'refuse'])->methods(['POST'])->requirements($offer);
    $routes->add(CoordinatorController::ROSTER, '/manage/{id}')->controller([CoordinatorController::class, 'roster'])->methods(['GET'])->requirements($shift);
    $routes->add(CoordinatorController::EDIT, '/manage/{id}/edit')->controller([CoordinatorController::class, 'edit'])->methods(['GET', 'POST'])->requirements($shift);
    $routes->add(CoordinatorController::ASSIGN, '/manage/{id}/assign')->controller([CoordinatorController::class, 'assign'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::REMOVE, '/manage/{id}/remove')->controller([CoordinatorController::class, 'remove'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::ALLOCATE, '/manage/{id}/allocate')->controller([CoordinatorController::class, 'allocate'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::ABSENT, '/manage/{id}/absent')->controller([CoordinatorController::class, 'absent'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::FORGIVE, '/manage/{id}/forgive')->controller([CoordinatorController::class, 'forgive'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::LIFT, '/manage/{id}/lift')->controller([CoordinatorController::class, 'lift'])->methods(['POST'])->requirements($shift);
    $routes->add(CoordinatorController::DELETE, '/manage/{id}/delete')->controller([CoordinatorController::class, 'delete'])->methods(['POST'])->requirements($shift);
    $routes->add(VolunteerController::SHOW, '/shift/{id}')->controller([VolunteerController::class, 'show'])->methods(['GET'])->requirements($shift);
    $routes->add(VolunteerController::SIGN_UP, '/shift/{id}/sign-up')->controller([VolunteerController::class, 'signUp'])->methods(['POST'])->requirements($shift);
    $routes->add(VolunteerController::INTEREST, '/shift/{id}/interest')->controller([VolunteerController::class, 'interest'])->methods(['POST'])->requirements($shift);
    $routes->add(VolunteerController::WITHDRAW, '/shift/{id}/withdraw')->controller([VolunteerController::class, 'withdraw'])->methods(['POST'])->requirements($shift);
    $routes->add(SwapController::OFFER, '/shift/{id}/offer')->controller([SwapController::class, 'offer'])->methods(['POST'])->requirements($shift);
};
