<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\FormsBundle\PhilFormsBundle;
use Phil\SpamProtectionBundle\PhilSpamProtectionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    PhilSpamProtectionBundle::class => ['all' => true],
    PhilFormsBundle::class => ['all' => true],
];
