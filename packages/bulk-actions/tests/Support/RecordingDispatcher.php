<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Support;

use Phil\BulkActions\Contract\RunDispatcher;
use Phil\BulkActions\Run\RunId;

final class RecordingDispatcher implements RunDispatcher
{
    /** @var list<string> */
    public array $dispatched = [];

    public function dispatch(RunId $run): void
    {
        $this->dispatched[] = $run->value;
    }
}
