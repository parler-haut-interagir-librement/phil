<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CoursesBundle\PhilCoursesBundle;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\VirtualClassroomBundle\PhilVirtualClassroomBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilCoursesBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
    PhilVirtualClassroomBundle::class => ['all' => true],
];
