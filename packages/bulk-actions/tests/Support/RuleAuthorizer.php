<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\Authorizer;
use Phil\BulkActions\Contract\BulkAction;

/**
 * Grants every action to the actors of $runners, and every item but those of $deniedItems.
 */
final class RuleAuthorizer implements Authorizer
{
    /** @var list<string> */
    public array $runners = ['ana'];

    /** @var list<string> */
    public array $deniedItems = [];

    /** @var list<string> what was asked, as "run:actor:target:action" or "apply:actor:attribute:item" */
    public array $asked = [];

    public function mayRun(string $actor, string $target, BulkAction $action): bool
    {
        $this->asked[] = 'run:'.$actor.':'.$target.':'.$action->name();

        return \in_array($actor, $this->runners, true);
    }

    public function mayApply(string $actor, BulkAction $action, object $item): bool
    {
        $id = $item instanceof Product ? $item->id : '?';
        $this->asked[] = 'apply:'.$actor.':'.$action->attribute().':'.$id;

        return !\in_array($id, $this->deniedItems, true);
    }
}
