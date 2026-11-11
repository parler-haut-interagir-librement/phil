<?php

declare(strict_types=1);

use Phil\ContactFormBundle\PhilContactFormBundle;
use Phil\SpamProtectionBundle\PhilSpamProtectionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilSpamProtectionBundle::class => ['all' => true],
    PhilContactFormBundle::class => ['all' => true],
];
