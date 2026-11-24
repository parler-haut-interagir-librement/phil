<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Bridge\LinkChecker;

use Override;
use Phil\LinkChecker\ContentLinks;
use Phil\LinkChecker\LinkChecker;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\SeoCheck\Contract\LinkStatuses;
use Phil\Url\Url;

/**
 * The statuses of the links of a page, by the `LinkChecker` of phil/link-checker: its cache, its pace per host and its
 * refusal of the private networks apply, and an address it checked lately is not requested again.
 *
 * What the checker found becomes the status code the rule reads:
 *
 * - a page alive, dead or to review with an answer keeps the code it answered with — a `404` or a `410` is broken, a
 *   `5xx` a server error; the other codes to review, `401`, `403` or `429`, are left out, as the checker never counts
 *   them as dead;
 * - a host that does not exist is `404`, the page not found;
 * - a redirect is `301` when every step of it is permanent, `302` otherwise;
 * - an address refused, unreadable, or to review without an answer — a timeout, a TLS error — is left out, as the
 *   checker could not tell.
 *
 * The links are handed to the checker as the links of one content, at `PAGE`, an address of the reserved `.invalid`
 * domain that no link names: the checker never requests the page of a content.
 */
final readonly class LinkCheckerStatuses implements LinkStatuses
{
    /** The address of the content the links are checked as, never requested. */
    public const string PAGE = 'https://seo-check.invalid/';

    /** The identifier of that content, for the checker's report. */
    public const string CONTENT = 'seo-check';

    public function __construct(
        private LinkChecker $checker,
    ) {
    }

    #[Override]
    public function statuses(array $links): array
    {
        $html = implode('', array_map(static fn (Url $link): string => '<a href="'.htmlspecialchars($link->toString(), \ENT_QUOTES).'"></a>', $links));
        $report = $this->checker->checkContent(new ContentLinks(self::CONTENT, Url::fromString(self::PAGE), $html));
        $statuses = [];

        // The checker lists the links in document order, the order they were given in.
        foreach ($report->links as $index => $checked) {
            $code = $this->code($checked->result);

            if (null !== $code) {
                $statuses[$links[$index]->toString()] = $code;
            }
        }

        return $statuses;
    }

    private function code(LinkResult $result): ?int
    {
        return match ($result->status) {
            LinkStatus::PermanentRedirect => 301,
            LinkStatus::TemporaryRedirect => 302,
            LinkStatus::Alive => $result->code,
            LinkStatus::Dead => $result->code ?? 404,
            LinkStatus::Review => null !== $result->code && $result->code >= 500 ? $result->code : null,
            default => null,
        };
    }
}
