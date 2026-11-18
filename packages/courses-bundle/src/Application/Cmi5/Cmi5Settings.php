<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * The settings of the cmi5 launches: the home page of the accounts that name the learners in the statements, which
 * also roots the IRIs of the activities and courses, and how long a session's token holds.
 */
final readonly class Cmi5Settings
{
    public const int SESSION_TTL = 14_400;

    private ?string $homePage;

    /**
     * @param ?string $homePage   an absolute address of the site; null leaves cmi5 launches refused
     * @param int     $sessionTtl how many seconds a session's token holds
     *
     * @throws InvalidCourse when the home page is not an absolute address, or the lifetime not positive
     */
    public function __construct(
        ?string $homePage,
        public int $sessionTtl = self::SESSION_TTL,
        bool $allowLocalhost = false,
    ) {
        if ($sessionTtl < 60) {
            throw InvalidCourse::because('A cmi5 session holds for 60 seconds at least.');
        }

        try {
            $this->homePage = null === $homePage ? null : Url::fromString($homePage, $allowLocalhost)->toString();
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidCourse::because(\sprintf('The home page of the cmi5 accounts is not an address: %s', $invalidUrl->getMessage()));
        }
    }

    /**
     * @throws ActivityUnavailable when no home page is configured
     */
    public function homePage(): string
    {
        return $this->homePage ?? throw ActivityUnavailable::because('The cmi5 statements name the learners by an account of the site, whose home page "phil_courses.cmi5.actor_home_page" does not give.');
    }
}
