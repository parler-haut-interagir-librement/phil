<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CertificatesBundle\PhilCertificatesBundle;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\PagedMediaBundle\PhilPagedMediaBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilOpenBadgesBundle::class => ['all' => true],
    PhilPagedMediaBundle::class => ['all' => true],
    PhilCertificatesBundle::class => ['all' => true],
];
