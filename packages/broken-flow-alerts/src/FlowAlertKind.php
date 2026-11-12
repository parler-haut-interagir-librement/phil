<?php

declare(strict_types=1);

namespace Phil\BrokenFlowAlerts;

enum FlowAlertKind: string
{
    /** Far fewer completions than the flow's rhythm leads to expect. */
    case Broken = 'broken';

    /** A flow that was broken the day before completes again. */
    case Recovered = 'recovered';
}
