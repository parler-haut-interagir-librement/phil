<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommercePosBundle\PhilCommercePosBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;

return [
    FrameworkBundle::class => ['all' => true],
    AssetMapperBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilCommerceBundle::class => ['all' => true],
    PhilCommercePosBundle::class => ['all' => true],
];
