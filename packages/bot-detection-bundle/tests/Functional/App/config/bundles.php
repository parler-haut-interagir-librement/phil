<?php

declare(strict_types=1);

use Phil\BotDetectionBundle\PhilBotDetectionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilBotDetectionBundle::class => ['all' => true],
];
