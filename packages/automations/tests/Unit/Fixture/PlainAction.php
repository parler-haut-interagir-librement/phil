<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Contract\Action;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;

/**
 * An action that cannot be undone.
 */
final class PlainAction implements Action
{
    public int $calls = 0;

    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('test.plain', 'Plain');
    }

    #[Override]
    public function execute(ActionContext $context): Payload
    {
        ++$this->calls;

        return Payload::empty();
    }
}
