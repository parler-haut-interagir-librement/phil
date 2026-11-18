<?php

declare(strict_types=1);

use Phil\BotDetectionBundle\PhilBotDetectionBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\SpamProtectionBundle\PhilSpamProtectionBundle;
use Phil\UploadScanBundle\PhilUploadScanBundle;
use Phil\UrlBundle\PhilUrlBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/*
 * The bundles an application registers once it has installed phil/profile-secure-input: Symfony Flex registers the same
 * bundles.
 */
return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilUrlBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
    PhilBotDetectionBundle::class => ['all' => true],
    PhilSpamProtectionBundle::class => ['all' => true],
    PhilUploadScanBundle::class => ['all' => true],
];
