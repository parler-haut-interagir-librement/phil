<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Data;

use Phil\EmailAddress\Exception\InvalidDomainList;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\Internal\DataDirectory;
use Phil\EmailAddress\Internal\DomainName;

/**
 * Turns a list of disposable domains — one domain a line, `#` starting a comment — into the PHP data `DisposableDomains::bundled()`
 * reads, and names its source and release in `VERSION`. The domains are checked, written in their ASCII form, without
 * duplicates and sorted, so that two imports of one release give the same file.
 *
 * Every line is read and checked before anything is written, and each file is written beside its place and then
 * moved there: a list with a bad line changes nothing, and no reader ever sees half a file.
 */
final readonly class DisposableDomainsImporter
{
    public const string DATA_FILE = 'disposable-domains.php';

    public const string SOURCE = 'https://github.com/disposable-email-domains/disposable-email-domains';

    /**
     * @param string $release the commit or date of the list imported, without spaces
     *
     * @return int the number of domains written
     *
     * @throws InvalidDomainList when a line is not a domain, the release or source holds a space, or a file cannot
     *                           be written
     */
    public function import(string $list, string $directory, string $release, string $source = self::SOURCE): int
    {
        DataDirectory::checkWord($release);
        DataDirectory::checkWord($source);
        $domains = [];

        foreach (explode("\n", $list) as $index => $line) {
            $entry = trim(explode('#', $line)[0]);

            if ('' === $entry) {
                continue;
            }

            try {
                $ascii = DomainName::of($entry)->ascii;
                $domains[$ascii] = $ascii;
            } catch (InvalidEmailAddress $invalid) {
                throw InvalidDomainList::entry($index + 1, $invalid->reason);
            }
        }

        // Sorting drops the keys.
        sort($domains, \SORT_STRING);

        $data = new DataDirectory($directory);
        $data->writeList(self::DATA_FILE, self::class, $domains);
        $data->recordRelease(self::DATA_FILE, $source, $release);

        return \count($domains);
    }
}
