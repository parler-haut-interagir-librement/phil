<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Routing;

use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\GuestRequestController;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of the pages. The bundle mounts them itself; none of their addresses carries anything about a person: the
 * link of a request made without an account carries a random identifier and a single-use secret, never an address.
 */
final class PrivacyRoutes
{
    public const string PREFERENCES = 'phil_privacy_preferences';

    public const string EXPORT = 'phil_privacy_export';

    public const string ERASURE = 'phil_privacy_erasure';

    public const string EMAIL_TRACKING = 'phil_privacy_email_tracking';

    public const string ERASURE_CANCEL = 'phil_privacy_erasure_cancel';

    public const string CONSENT = 'phil_privacy_consent';

    public const string CONSENT_DECIDE = 'phil_privacy_consent_decide';

    public const string COOKIES = 'phil_privacy_cookies';

    public const string GUEST_REQUEST = 'phil_privacy_guest_request';

    public const string GUEST_CONFIRM = 'phil_privacy_guest_confirm';

    private function __construct()
    {
    }

    public static function collection(bool $preferences = true): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add(self::CONSENT, self::route('/consent', ConsentController::class, 'page', ['GET']));
        $routes->add(self::CONSENT_DECIDE, self::route('/consent', ConsentController::class, 'decide', ['POST']));
        $routes->add(self::COOKIES, self::route('/cookies', ConsentController::class, 'cookies', ['GET']));

        // Answered with a 404 while `guest_requests.enabled` is false.
        $routes->add(self::GUEST_REQUEST, self::route('/request', GuestRequestController::class, 'request', ['GET', 'POST']));
        $routes->add(self::GUEST_CONFIRM, self::route('/request/{id}', GuestRequestController::class, 'confirm', ['GET', 'POST'])->setRequirement('id', '[0-9a-f]{32}'));

        if ($preferences) {
            $routes->add(self::PREFERENCES, self::route('', PreferencesController::class, 'index', ['GET']));
            $routes->add(self::EXPORT, self::route('/export', PreferencesController::class, 'export', ['POST']));
            // Answered with a 404 while `email_tracking.enabled` is false.
            $routes->add(self::EMAIL_TRACKING, self::route('/email-tracking', PreferencesController::class, 'emailTracking', ['POST']));
            $routes->add(self::ERASURE, self::route('/erasure', PreferencesController::class, 'erasure', ['GET', 'POST']));
            $routes->add(self::ERASURE_CANCEL, self::route('/erasure/cancel', PreferencesController::class, 'cancel', ['POST']));
        }

        return $routes;
    }

    /**
     * @param class-string $controller
     * @param list<string> $methods
     */
    private static function route(string $path, string $controller, string $action, array $methods): Route
    {
        return new Route($path, ['_controller' => [$controller, $action]], methods: $methods);
    }
}
