<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Export;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\PrivacyBundle\Application\Contract\ExportReadme;

/**
 * The README of an export in plain English, for an application without Twig; with Twig, the bundle renders
 * `@PhilPrivacy/export/README.txt.twig` in the person's language.
 */
final readonly class PlainExportReadme implements ExportReadme
{
    #[Override]
    public function render(array $modules, DateTimeImmutable $madeAt): string
    {
        $lines = [
            'Your personal data',
            '==================',
            '',
            'This archive holds everything this site keeps about you, as of '.$madeAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i').' UTC.',
            'Each part of the site has its own file, in JSON: a text format that any text editor opens, and that other',
            'services can import.',
            '',
            'Files:',
            '',
        ];

        foreach ($modules as $module => $entries) {
            $lines[] = \sprintf('- %s.json: %s', $module, 0 === $entries ? 'nothing is kept about you here.' : \sprintf('%d %s.', $entries, 1 === $entries ? 'entry' : 'entries'));
        }

        $lines[] = '';
        $lines[] = 'Keep this archive somewhere safe: it is about you, and nobody else should read it.';

        return implode("\n", $lines)."\n";
    }
}
