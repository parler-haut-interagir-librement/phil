<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Closure;
use Override;
use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;

/**
 * An agent client that answers from closures by site, and records each call.
 */
final class FakeAgentClient implements AgentClient
{
    /** @var list<string> "action:site:id" for each call */
    public array $calls = [];

    /**
     * @param array<string, Closure(Site): StatusDocument>          $statuses
     * @param array<string, Closure(ConfigurationRequest): Outcome> $configurations
     * @param array<string, Closure(UpdateRequest): Outcome>        $updates
     * @param array<string, Closure(KeyRequest): Outcome>           $keys
     */
    public function __construct(
        public array $statuses = [],
        public array $configurations = [],
        public array $updates = [],
        public array $keys = [],
    ) {
    }

    #[Override]
    public function status(Site $site): StatusDocument
    {
        $this->calls[] = 'status:'.$site->id();

        return ($this->statuses[$site->id()] ?? throw AgentFailure::unreachable('no answer'))($site);
    }

    #[Override]
    public function configure(Site $site, ConfigurationRequest $request): Outcome
    {
        $this->calls[] = 'configuration:'.$site->id().':'.$request->id;

        return ($this->configurations[$site->id()] ?? throw AgentFailure::unreachable('no answer'))($request);
    }

    #[Override]
    public function update(Site $site, UpdateRequest $request): Outcome
    {
        $this->calls[] = 'update:'.$site->id().':'.$request->id;

        return ($this->updates[$site->id()] ?? throw AgentFailure::unreachable('no answer'))($request);
    }

    #[Override]
    public function keys(Site $site, KeyRequest $request): Outcome
    {
        $this->calls[] = 'keys:'.$site->id().':'.$request->id;

        return ($this->keys[$site->id()] ?? throw AgentFailure::unreachable('no answer'))($request);
    }
}
