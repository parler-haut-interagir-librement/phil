<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * Where the lists are kept.
 */
interface MailingListRepository
{
    public function save(MailingList $list): void;

    public function get(string $id): ?MailingList;

    public function byKey(Site $site, string $key): ?MailingList;

    /**
     * @return list<MailingList> the site's lists, by key
     */
    public function ofSite(Site $site): array;
}
