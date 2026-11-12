<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\MediaBundle\PhilMediaBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    PhilMediaBundle::class => ['all' => true],
    PhilBrowserSecurityBundle::class => ['user_content' => true],
];
