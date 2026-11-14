<?php

declare(strict_types=1);

use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;

return [
    FrameworkBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
];
