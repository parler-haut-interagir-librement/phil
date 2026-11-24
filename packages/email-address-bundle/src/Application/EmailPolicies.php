<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application;

use Phil\EmailAddressBundle\Application\Contract\CurrentSite;
use Phil\EmailAddressBundle\Application\Exception\UnknownEmailList;

/**
 * The policy an address is checked under: the bundle's, then the current site's laid over it, then the list's laid
 * over both. A site the configuration does not name adds nothing; a list it does not name is an error of the
 * application, never a silent default.
 */
final readonly class EmailPolicies
{
    /**
     * @param array<string, EmailPolicy> $sites by the site's key
     * @param array<string, EmailPolicy> $lists by the list's name
     */
    public function __construct(
        private EmailPolicy $defaults = new EmailPolicy(),
        private array $sites = [],
        private array $lists = [],
        private ?CurrentSite $site = null,
    ) {
    }

    /**
     * @throws UnknownEmailList when the list is not configured
     */
    public function for(?string $list = null): EmailPolicy
    {
        $policy = $this->defaults;
        $site = $this->site?->key();

        if (null !== $site && isset($this->sites[$site])) {
            $policy = $this->sites[$site]->over($policy);
        }

        if (null === $list) {
            return $policy;
        }

        if (!isset($this->lists[$list])) {
            throw new UnknownEmailList($list, array_keys($this->lists));
        }

        return $this->lists[$list]->over($policy);
    }
}
