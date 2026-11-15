<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Contract;

use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * Where the templates administrators and members write are kept: a site's own, and those shared by every site.
 */
interface TemplateRepository
{
    public function save(Template $template): void;

    /**
     * The site's template of a key, else the shared template of that key.
     */
    public function find(Site $site, string $key): ?Template;

    public function remove(?Site $site, string $key): void;
}
