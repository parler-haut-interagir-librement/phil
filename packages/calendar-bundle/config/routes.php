<?php

declare(strict_types=1);

use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Infrastructure\Http\CalendarFeedController;
use Phil\CalendarBundle\Infrastructure\Http\PersonalFeedController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The calendar's iCalendar feed, and a member's own feed at the address of their secret, imported by the application:
 *
 *     # config/routes/phil_calendar.yaml
 *     phil_calendar:
 *         resource: '@PhilCalendarBundle/config/routes.php'
 *         prefix: /calendar
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('phil_calendar_ics', '/calendar.ics')->controller([CalendarFeedController::class, 'ics'])->methods(['GET']);
    $routes->add('phil_calendar_personal_ics', '/personal/{token}.ics')
        ->controller([PersonalFeedController::class, 'ics'])
        ->requirements(['token' => PersonalFeeds::PATTERN])
        ->methods(['GET']);
};
