<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use LogicException;
use Phil\AccessCodes\Exception\CodesAlreadyTaken;
use SplFileObject;

/**
 * A newly created batch, holding its codes in clear for one use: written once as CSV, or taken once as a list for
 * printing. Then they are forgotten, and nothing — not the store, not this object — can give them again.
 *
 * The object refuses to be serialized or cloned, and hides its codes from `var_dump()`, so that a cache, a session or a
 * log does not keep them by accident.
 */
final class CreatedBatch
{
    /** @var list<non-empty-string>|null the codes in canonical form, until taken */
    private ?array $codes;

    /**
     * @param list<non-empty-string> $codes
     */
    public function __construct(
        public readonly Batch $batch,
        public readonly CodeFormat $format,
        array $codes,
    ) {
        $this->codes = $codes;
    }

    /**
     * Whether the codes are still here, not yet taken or written.
     */
    public function hasCodes(): bool
    {
        return null !== $this->codes;
    }

    /**
     * The codes, once, as people read them — `7KQ2-M9XD-H4TB-R` —, or in canonical form.
     *
     * @return list<string>
     *
     * @throws CodesAlreadyTaken when they were taken or written before
     */
    public function takeCodes(bool $display = true): array
    {
        $codes = $this->codes ?? throw CodesAlreadyTaken::forBatch($this->batch->id);
        $this->codes = null;

        return $display ? array_map($this->format->display(...), $codes) : $codes;
    }

    /**
     * Writes the codes, once, as CSV to an open file: a header line, then one line per code as people read it, with
     * its prefilled redemption link when links are given. Returns how many codes were written.
     *
     * @throws CodesAlreadyTaken when they were taken or written before
     */
    public function writeCsv(SplFileObject $file, ?RedemptionLinks $links = null): int
    {
        $codes = $this->takeCodes(display: false);
        $file->fwrite($this->csvLine($links instanceof RedemptionLinks ? ['code', 'link'] : ['code']));

        foreach ($codes as $code) {
            $file->fwrite($this->csvLine($links instanceof RedemptionLinks ? [$this->format->display($code), $links->for($code)->toString()] : [$this->format->display($code)]));
        }

        return \count($codes);
    }

    /**
     * One CSV line as `fputcsv()` writes it without an escape character: a field holding the separator, a quote, a
     * space, a tab or a line break goes between quotes, its quotes doubled.
     *
     * @param list<string> $fields
     */
    private function csvLine(array $fields): string
    {
        return implode(',', array_map(
            static fn (string $field): string => false === strpbrk($field, ",\"\n\r\t ") ? $field : '"'.str_replace('"', '""', $field).'"',
            $fields,
        ))."\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['batch' => $this->batch, 'codes' => null === $this->codes ? 'taken' : \sprintf('%d codes, hidden', \count($this->codes))];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('A created batch holds codes in clear: it is never serialized.');
    }

    public function __clone()
    {
        throw new LogicException('A created batch holds codes in clear: it is never cloned.');
    }
}
