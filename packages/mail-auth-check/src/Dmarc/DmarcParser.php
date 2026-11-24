<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dmarc;

use Phil\MailAuthCheck\Alignment;
use Phil\MailAuthCheck\DmarcPolicy;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\TagList;

/**
 * Reads a DMARC record (RFC 9989): `v=DMARC1` first, then the tags `p`, `sp`, `np`, `adkim`, `aspf`, `t`, `psd` and
 * `rua`. Unknown tags, `pct` among them, are ignored, as the RFC asks; a known tag with a value outside its list is
 * refused.
 */
final readonly class DmarcParser
{
    /**
     * Whether a TXT record is a DMARC record: it starts with the tag `v=DMARC1`.
     */
    public static function isDmarc(string $text): bool
    {
        return 1 === preg_match('/^v[ \t]*=[ \t]*DMARC1[ \t]*(?:;|$)/D', $text);
    }

    /**
     * @throws InvalidRecord
     */
    public function parse(string $text): DmarcRecord
    {
        if (!self::isDmarc($text)) {
            throw InvalidRecord::because('the record does not start with "v=DMARC1".');
        }

        $tags = TagList::parse($text, 'DMARC');
        $reports = '' === ($tags['rua'] ?? '') ? [] : array_map(trim(...), explode(',', $tags['rua']));

        return new DmarcRecord(
            $this->policy($tags, 'p'),
            $this->policy($tags, 'sp'),
            $this->policy($tags, 'np'),
            $this->alignment($tags, 'aspf'),
            $this->alignment($tags, 'adkim'),
            $this->flag($tags, 't', ['y' => true, 'n' => false]) ?? false,
            $this->flag($tags, 'psd', ['y' => true, 'n' => false, 'u' => null]),
            $reports,
        );
    }

    /**
     * @param array<string, string> $tags
     */
    private function policy(array $tags, string $name): ?DmarcPolicy
    {
        if (!isset($tags[$name])) {
            return null;
        }

        return DmarcPolicy::tryFrom(strtolower($tags[$name]))
            ?? throw $this->outOfList($name, 'none, quarantine or reject');
    }

    /**
     * @param array<string, string> $tags
     */
    private function alignment(array $tags, string $name): Alignment
    {
        return Alignment::tryFrom(strtolower($tags[$name] ?? 'r')) ?? throw $this->outOfList($name, 'r or s');
    }

    /**
     * @param array<string, string> $tags
     * @param array<string, ?bool>  $values
     */
    private function flag(array $tags, string $name, array $values): ?bool
    {
        $value = strtolower($tags[$name] ?? '');

        if ('' === $value) {
            return null;
        }

        if (!\array_key_exists($value, $values)) {
            throw $this->outOfList($name, implode(' or ', array_keys($values)));
        }

        return $values[$value];
    }

    private function outOfList(string $name, string $list): InvalidRecord
    {
        return InvalidRecord::because(\sprintf('the tag "%s" takes %s.', $name, $list));
    }
}
