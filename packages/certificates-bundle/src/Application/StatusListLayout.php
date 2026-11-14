<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use Phil\CertificatesBundle\Domain\Exception\InvalidValue;

/**
 * How the certificates' status indexes fill the issuer's status lists: `$size` indexes per list, the lists named
 * `<prefix>-0`, `<prefix>-1` and so on, so that the register never runs out of indexes.
 */
final readonly class StatusListLayout
{
    public const string PREFIX = '[A-Za-z0-9][A-Za-z0-9_-]{0,49}';

    public function __construct(
        public string $prefix = 'certificates',
        public int $size = 131_072,
    ) {
        if (1 !== preg_match('/^'.self::PREFIX.'$/D', $prefix)) {
            throw InvalidValue::because(\sprintf('The prefix of the status lists is up to 50 letters, digits, dashes and underscores; "%s" is not.', $prefix));
        }

        if ($size < 1) {
            throw InvalidValue::because('A status list holds at least one index.');
        }
    }

    /**
     * The list a status index falls in, and its index in that list.
     *
     * @return array{string, int}
     */
    public function locate(int $index): array
    {
        return [$this->prefix.'-'.intdiv($index, $this->size), $index % $this->size];
    }

    /**
     * The status indexes a list of the layout holds, from the first included to the last excluded; null for a list
     * of another name.
     *
     * @return array{int, int}|null
     */
    public function range(string $list): ?array
    {
        $number = str_starts_with($list, $this->prefix.'-') ? substr($list, \strlen($this->prefix) + 1) : '';

        if (1 !== preg_match('/^(?:0|[1-9]\d{0,8})$/D', $number)) {
            return null;
        }

        $from = (int) $number * $this->size;

        return [$from, $from + $this->size];
    }
}
