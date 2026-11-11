<?php

declare(strict_types=1);

namespace Phil\Fonts\Licence;

/**
 * The licence a face states in its `name` table: the description (name 13) and the URL (name 14).
 *
 * `spdx()` recognises the licences most fonts are published under from what the font itself says. It is a hint for
 * a register or an admin screen, not a legal reading: the licence that binds is the one the font was obtained under.
 */
final readonly class Licence
{
    /** The SPDX identifier of each recognised licence, with what its URL or its description contains. */
    private const array KNOWN = [
        'OFL-1.1' => ['openfontlicense.org', 'scripts.sil.org/ofl', 'open font license'],
        'Apache-2.0' => ['apache.org/licenses/license-2.0', 'apache license, version 2.0'],
        'UFL-1.0' => ['ubuntu.com/legal/terms-and-policies/font-licence', 'ubuntu font licence'],
        'MIT' => ['opensource.org/licenses/mit', 'mit license'],
    ];

    public function __construct(
        public ?string $description = null,
        public ?string $url = null,
    ) {
    }

    /**
     * The SPDX identifier of a licence recognised in the URL or the description, case aside; null when none is.
     */
    public function spdx(): ?string
    {
        $text = strtolower($this->url."\n".$this->description);

        foreach (self::KNOWN as $identifier => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    return $identifier;
                }
            }
        }

        return null;
    }
}
