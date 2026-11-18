<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use DateInterval;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\ElearningPackages\Cmi5\LaunchMethod;
use Psr\Clock\ClockInterface;

/**
 * Launches a cmi5 assignable unit: a session still open for that learner and unit is abandoned, and its `abandoned`
 * statement issued; a new session is opened, its `launched` statement issued; and the unit's URL — on the content
 * origin or absolute — gets the launch parameters of cmi5: `endpoint`, `fetch`, `registration`, `activityId` and
 * `actor`, the learner named by an account of the site. A unit that asks for its own window opens in one; the others
 * are framed in the activity page.
 */
final readonly class Cmi5Launcher
{
    public function __construct(
        private LaunchSessionRepository $sessions,
        private Cmi5Statements $statements,
        private Cmi5Endpoints $endpoints,
        private ContentUrls $urls,
        private Cmi5Settings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ActivityUnavailable when the unit names no URL, or the site no home page or content origin
     */
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        $homePage = $this->settings->homePage();
        $target = $this->target($activity);
        $now = $this->clock->now();

        foreach ($this->sessions->open($enrollment->id, $activity->id) as $open) {
            $open->end(SessionState::Abandoned, $now);
            $this->sessions->save($open);
            $this->statements->issue(PlatformStatements::abandoned(Cmi5Context::of($open, $enrollment, $activity, $homePage), $open->launchedAt, $now), $enrollment->registration, $open->id->value);
        }

        $session = new LaunchSession(LaunchSessionId::generate(), $enrollment->id, $activity->id, $now, $now->add(new DateInterval(\sprintf('PT%dS', $this->settings->sessionTtl))));
        $this->sessions->save($session);
        $context = Cmi5Context::of($session, $enrollment, $activity, $homePage);
        $url = $this->withParameters($target, [
            'endpoint' => $this->endpoints->endpoint(),
            'fetch' => $this->endpoints->fetch($session->id),
            'registration' => $enrollment->registration,
            'activityId' => $context->activityIri,
            'actor' => json_encode($context->actor(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
        ]);
        $this->statements->issue(PlatformStatements::launched($context, $url, $now), $enrollment->registration, $session->id->value);

        return LaunchMethod::OwnWindow->value === $activity->stringSetting('launch_method') ? Launch::newWindow($url) : Launch::embedded($url);
    }

    private function target(Activity $activity): string
    {
        $url = $activity->stringSetting('url');

        if (null !== $url) {
            return $url;
        }

        $path = $activity->stringSetting('path') ?? throw ActivityUnavailable::because(\sprintf('The assignable unit "%s" names no URL.', $activity->id->value));

        return $this->urls->url($activity->course, $path, $activity->stringSetting('query'));
    }

    /**
     * The URL with the parameters added to its query, before its fragment.
     *
     * @param array<string, string> $parameters
     */
    private function withParameters(string $url, array $parameters): string
    {
        $fragment = '';
        $hash = strpos($url, '#');

        if (false !== $hash) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($parameters, '', '&', \PHP_QUERY_RFC3986).$fragment;
    }
}
