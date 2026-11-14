<?php

declare(strict_types=1);

use Phil\QrCodeBundle\PhilQrCodeBundle;
use Phil\UxKit\PhilUxKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

// The bundles of TestKernel.
return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    // html_attr(), which the Image recipe writes its attributes with, as an application installing it registers it.
    TwigExtraBundle::class => ['all' => true],
    PhilQrCodeBundle::class => ['all' => true],
    // image_origin(), which the image recipe calls, from the PHIL UX kit.
    PhilUxKitBundle::class => ['all' => true],
];
