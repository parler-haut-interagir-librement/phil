<?php

declare(strict_types=1);

use Phil\DocumentImportBundle\PhilDocumentImportBundle;
use Phil\UploadScanBundle\PhilUploadScanBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilUploadScanBundle::class => ['all' => true],
    PhilDocumentImportBundle::class => ['all' => true],
];
