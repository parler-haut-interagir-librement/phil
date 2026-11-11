<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Infrastructure\Session;

use Override;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Exception\UnreadableWarnings;
use Phil\ContentWarnings\WarningPreferences;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;
use Phil\ContentWarningsBundle\Application\Exception\PreferencesNotSaved;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Preferences kept in the visitor's HTTP session, member or not, as the array `WarningPreferences::toArray()` writes.
 *
 * Reading never starts a session: a visitor without one gets the defaults. Preferences the session holds but that
 * cannot be read back — a theme key renamed, a value tampered with — give the defaults too.
 */
final readonly class SessionWarningPreferences implements WarningPreferencesStore
{
    public const string SESSION_KEY = 'phil_content_warnings.preferences';

    public function __construct(
        private RequestStack $requests,
        private Display $default = Display::Fold,
    ) {
    }

    #[Override]
    public function current(): WarningPreferences
    {
        $request = $this->requests->getMainRequest();
        $stored = $request instanceof Request && $request->hasPreviousSession() ? $request->getSession()->get(self::SESSION_KEY) : null;

        if (\is_array($stored)) {
            try {
                return WarningPreferences::fromArray($stored);
            } catch (UnreadableWarnings) {
                // The defaults below.
            }
        }

        return WarningPreferences::defaults($this->default);
    }

    #[Override]
    public function save(WarningPreferences $preferences): void
    {
        $request = $this->requests->getMainRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            throw PreferencesNotSaved::because('there is no session');
        }

        $request->getSession()->set(self::SESSION_KEY, $preferences->toArray());
    }
}
