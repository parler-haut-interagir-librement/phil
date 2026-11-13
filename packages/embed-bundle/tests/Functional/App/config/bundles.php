<?php

declare(strict_types=1);

use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\EmbedBundle\PhilEmbedBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/*
 * The environment "security" adds phil/browser-security-bundle, through which the framing then goes.
 */
return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilBrowserSecurityBundle::class => ['security' => true],
    PhilEmbedBundle::class => ['all' => true],
];
