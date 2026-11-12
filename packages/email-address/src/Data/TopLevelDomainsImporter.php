<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Data;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Internal\DataDirectory;
use Phil\EmailAddress\Internal\TopLevelLabel;
use Phil\EmailAddress\SyntaxError;

/**
 * Turns the IANA list of top-level domains — `tlds-alpha-by-domain.txt`, a `# Version` line naming its release, then
 * one domain a line in uppercase ASCII — into the PHP data `TopLevelDomains::bundled()` reads, and names its source
 * and release in `VERSION`. The domains are checked, written in lowercase, without duplicates and sorted, so that two
 * imports of one release give the same file.
 *
 * Every line is read and checked before anything is written: a list with a bad line changes nothing.
 */
final readonly class TopLevelDomainsImporter
{
    public const string DATA_FILE = 'top-level-domains.php';

    public const string SOURCE = 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt';

    /**
     * @return int the number of domains written
     *
     * @throws InvalidDomainList when the list names no release, a line is not a top-level domain, or a file cannot
     *                           be written
     */
    public function import(string $list, string $directory, string $source = self::SOURCE): int
    {
        DataDirectory::checkWord($source);
        $release = null;
        $labels = [];

        foreach (explode("\n", $list) as $index => $line) {
            $entry = trim($line);

            if ('' === $entry) {
                continue;
            }

            if (str_starts_with($entry, '#')) {
                // "# Version 2026111000, Last Updated Tue Nov 11 07:07:01 2026 UTC"
                if (null === $release && 1 === preg_match('/^#\s*Version\s+(\d{10})\b/', $entry, $match)) {
                    $release = $match[1];
                }

                continue;
            }

            $label = TopLevelLabel::normalize($entry);

            if (null === $label) {
                throw InvalidDomainList::entry($index + 1, SyntaxError::InvalidTopLevelDomain);
            }

            $labels[$label] = $label;
        }

        if (null === $release) {
            throw InvalidDomainList::noRelease();
        }

        // Sorting drops the keys.
        sort($labels, \SORT_STRING);

        $data = new DataDirectory($directory);
        $data->writeList(self::DATA_FILE, self::class, $labels);
        $data->recordRelease(self::DATA_FILE, $source, $release);

        return \count($labels);
    }
}
