<?php

declare(strict_types=1);

namespace Phil\BotDetectionBundle;

use Override;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;

/**
 * Bot detection whose only output is a robot or human verdict. The bundle carries what the design already
 * decides (the verdict, the verdict-to-friction policy, the handling of one verdict), the port of what the
 * application decides (where the aggregate counter lives), and the browser side: the `verdict` Stimulus
 * controller and its detectors, declared in `assets/package.json`. It declares no service and no configuration
 * yet.
 */
final class PhilBotDetectionBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_bot_detection';
}
