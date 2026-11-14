<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Routing;

use Phil\OpenBadges\Status\StatusListEntry;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URLs of the application's status lists, from the route the bundle serves them at: the entry a revocable
 * credential carries, and the list a status entry points to when it is one of the application's: the last segment of
 * its path, when the URL of that list is exactly the entry's.
 */
final readonly class StatusListUrls
{
    public const string ROUTE = 'phil_open_badges_status_list';

    public const string LIST = '[A-Za-z0-9][A-Za-z0-9_-]{0,63}';

    /**
     * @param bool $allowLocalhost whether a list's URL may name `localhost` or a host under `.localhost`, as the issuer's
     *                             addresses may in development and tests
     */
    public function __construct(
        private UrlGeneratorInterface $urls,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * The status entry of the credential at the index given in the list named, for the purpose of that list.
     */
    public function entry(string $list, int $index, string $purpose = 'revocation'): StatusListEntry
    {
        return new StatusListEntry($this->url($list), $index, $purpose);
    }

    /**
     * The absolute URL the list is served at.
     */
    public function url(string $list): string
    {
        return $this->urls->generate(self::ROUTE, ['list' => $list], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * The name of the application's list at this URL, or null when the URL is not one of its lists. The URL is read by
     * `phil/url`, as a browser reads it, and the list is the last segment of its path; the URL is then compared, byte
     * for byte, with the one the route gives that name, so that no spelling of another address passes for a list's.
     */
    public function listOf(string $url): ?string
    {
        try {
            $segments = Url::fromString($url, $this->allowLocalhost)->segments();
        } catch (InvalidUrl) {
            return null;
        }

        $list = end($segments);

        return \is_string($list) && 1 === preg_match('/^'.self::LIST.'$/D', $list) && $this->url($list) === $url ? $list : null;
    }
}
