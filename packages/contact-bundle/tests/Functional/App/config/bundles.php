<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\ContactBundle\PhilContactBundle;
use Phil\InstantMessengerBundle\PhilInstantMessengerBundle;
use Phil\PhoneBundle\PhilPhoneBundle;
use Phil\UrlBundle\PhilUrlBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilPhoneBundle::class => ['all' => true],
    PhilUrlBundle::class => ['all' => true],
    PhilInstantMessengerBundle::class => ['all' => true],
    PhilContactBundle::class => ['all' => true],
];
