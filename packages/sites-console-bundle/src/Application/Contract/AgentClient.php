<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Contract;

use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;

/**
 * How the console talks to the agent of a site: each request signed by the console, each response verified with the
 * site's public key.
 */
interface AgentClient
{
    /**
     * @throws AgentFailure
     */
    public function status(Site $site): StatusDocument;

    /**
     * @throws AgentFailure
     */
    public function configure(Site $site, ConfigurationRequest $request): Outcome;

    /**
     * @throws AgentFailure
     */
    public function update(Site $site, UpdateRequest $request): Outcome;

    /**
     * @throws AgentFailure
     */
    public function keys(Site $site, KeyRequest $request): Outcome;
}
