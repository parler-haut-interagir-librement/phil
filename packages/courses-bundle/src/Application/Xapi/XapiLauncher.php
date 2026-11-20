<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use DateInterval;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\Cmi5Endpoints;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\LaunchUrl;
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
 * Launches an xAPI activity as xAPI packages expect: a session still open for that learner and activity is
 * abandoned; a new session is opened, its token given at once — the session counts as fetched, so that the token
 * works only for it and only until it ends or expires —; and the activity's URL gets the launch parameters `endpoint`,
 * `auth`, the token as HTTP Basic credentials, `actor`, the learner named by an account of the site, `registration`,
 * the enrollment's, and `activity_id`, the activity's identifier in its package. The statements then reach the same
 * xAPI endpoint as cmi5's, with the same token scheme.
 */
final readonly class XapiLauncher
{
    public function __construct(
        private LaunchSessionRepository $sessions,
        private Cmi5Tokens $tokens,
        private Cmi5Endpoints $endpoints,
        private ContentUrls $urls,
        private Cmi5Settings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ActivityUnavailable when the activity names no URL, or the site no home page or content origin
     */
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        $homePage = $this->settings->homePage();
        $target = $this->target($activity);
        $now = $this->clock->now();

        foreach ($this->sessions->open($enrollment->id, $activity->id) as $open) {
            $open->end(SessionState::Abandoned, $now);
            $this->sessions->save($open);
        }

        $session = new LaunchSession(LaunchSessionId::generate(), $enrollment->id, $activity->id, $now, $now->add(new DateInterval(\sprintf('PT%dS', $this->settings->sessionTtl))), true);
        $this->sessions->save($session);
        $context = XapiContext::of($session, $enrollment, $activity, $homePage);
        $url = LaunchUrl::with($target, [
            'endpoint' => $this->endpoints->endpoint(),
            'auth' => 'Basic '.$this->tokens->token($session->id),
            'actor' => json_encode($context->actor(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'registration' => $enrollment->registration,
            'activity_id' => $context->activityIri,
        ]);

        return LaunchMethod::OwnWindow->value === $activity->stringSetting('launch_method') ? Launch::newWindow($url) : Launch::embedded($url);
    }

    private function target(Activity $activity): string
    {
        $url = $activity->stringSetting('url');

        if (null !== $url) {
            return $url;
        }

        $path = $activity->stringSetting('path') ?? throw ActivityUnavailable::because(\sprintf('The xAPI activity "%s" names no URL.', $activity->id->value));

        return $this->urls->url($activity->course, $path, $activity->stringSetting('query'));
    }
}
