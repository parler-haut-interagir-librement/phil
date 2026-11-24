<?php

declare(strict_types=1);

use Phil\EmailAddressBundle\PhilEmailAddressBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilEmailAddressBundle::class => ['all' => true],
];
