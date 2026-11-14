<?php

declare(strict_types=1);

use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;

return [
    FrameworkBundle::class => ['all' => true],
    AssetMapperBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    PhilPrivacyBundle::class => ['all' => true],
];
