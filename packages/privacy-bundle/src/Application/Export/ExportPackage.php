<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Export;

use Phil\PrivacyBundle\Application\Contract\ExportReadme;
use Psr\Clock\ClockInterface;

/**
 * An access answer as one ZIP archive a person can open: a `README.txt` that says what it is and what each file holds,
 * then one JSON file per module, `<module>.json`, in module order.
 */
final readonly class ExportPackage
{
    public const string README = 'README.txt';

    public function __construct(
        private ExportReadme $readme,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $sections by module name, as `SubjectExport` assembles them
     *
     * @return string the archive's bytes
     */
    public function zip(array $sections): string
    {
        $now = $this->clock->now();
        $archive = new ZipFile($now);
        $archive->add(self::README, $this->readme->render(array_map(\count(...), $sections), $now));

        foreach ($sections as $module => $data) {
            // A module that keeps nothing is an empty object, as any other section is an object.
            $json = [] === $data ? '{}' : json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            $archive->add($module.'.json', $json."\n");
        }

        return $archive->bytes();
    }
}
