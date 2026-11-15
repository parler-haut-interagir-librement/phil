<?php

declare(strict_types=1);

namespace Phil\Lti\Service\Nrps;

use Generator;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\LinkHeader;
use Phil\Lti\Http\UrlQuery;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\NrpsEndpoint;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Service\Scope;
use Phil\Lti\Service\ServiceCall;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * The Names and Role Provisioning Service of a platform, called by the tool with the claim of a launch: the members of
 * the context, a page at a time, the next page found in the `Link` header.
 */
final readonly class NrpsClient
{
    /**
     * The most pages `members()` reads: a context larger than this many pages is read by `page()` and its `next`.
     */
    public const int MAX_PAGES = 100;

    public const int MAX_MEMBERS_PER_PAGE = 1000;

    public function __construct(
        private ServiceCall $call,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * A page of the members, from the claim's address or from the `next` of an earlier page.
     *
     * @throws ServiceFailed|InvalidData
     */
    public function page(PlatformRegistration $platform, NrpsEndpoint $endpoint, ?string $role = null, ?int $limit = null, ?Url $page = null): MembershipPage
    {
        $url = $page ?? $endpoint->membershipsUrl;
        $query = array_filter(['role' => $role, 'limit' => null === $limit ? null : (string) $limit], static fn (?string $value): bool => null !== $value);

        if (!$page instanceof Url && [] !== $query) {
            $url = UrlQuery::with($url, $query, $this->allowLocalhost);
        }

        $response = $this->call->send($platform, [Scope::MEMBERSHIP_READ_ONLY], 'GET', $url, MediaType::MEMBERSHIP_CONTAINER);
        $fields = new Fields(ServiceCall::json($response), inToken: false, allowLocalhost: $this->allowLocalhost);
        $members = [];

        foreach ($fields->objectList('members', self::MAX_MEMBERS_PER_PAGE) as $member) {
            $members[] = Member::fromArray($member->data);
        }

        return new MembershipPage(
            Context::read($fields->object('context')),
            $members,
            LinkHeader::find($response->headers('link'), 'next', $url, $this->allowLocalhost),
            LinkHeader::find($response->headers('link'), 'differences', $url, $this->allowLocalhost),
        );
    }

    /**
     * Every member, page after page, up to `MAX_PAGES` pages.
     *
     * @return Generator<int, Member>
     *
     * @throws ServiceFailed|InvalidData
     */
    public function members(PlatformRegistration $platform, NrpsEndpoint $endpoint, ?string $role = null, ?int $limit = null): Generator
    {
        $page = $this->page($platform, $endpoint, $role, $limit);
        $read = 1;

        while (true) {
            foreach ($page->members as $member) {
                yield $member;
            }

            if (!$page->next instanceof Url) {
                return;
            }

            if (self::MAX_PAGES === $read) {
                throw ServiceFailed::because(\sprintf('The members of the context span more than %d pages.', self::MAX_PAGES));
            }

            $page = $this->page($platform, $endpoint, page: $page->next);
            ++$read;
        }
    }
}
