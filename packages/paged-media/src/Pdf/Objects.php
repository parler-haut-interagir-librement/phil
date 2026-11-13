<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Pdf;

/**
 * The numbered objects of a PDF file and its cross-reference table.
 *
 * @internal
 */
final class Objects
{
    /** @var array<int, string> each object's body, by number */
    private array $bodies = [];

    private int $next = 1;

    /**
     * A number for an object whose body is set later, once the objects it refers to are numbered.
     */
    public function reserve(): int
    {
        $this->bodies[$this->next] = '';

        return $this->next++;
    }

    public function add(string $body): int
    {
        $number = $this->reserve();
        $this->bodies[$number] = $body;

        return $number;
    }

    public function set(int $number, string $body): void
    {
        $this->bodies[$number] = $body;
    }

    /**
     * @param bool $identified whether the trailer carries an identifier, the hash of the objects, as PDF/A requires
     */
    public function write(string $header, int $root, int $info, bool $identified): string
    {
        $pdf = $header;
        $offsets = '';

        foreach ($this->bodies as $number => $body) {
            $offsets .= \sprintf("%010d 00000 n \n", \strlen($pdf));
            $pdf .= \sprintf("%d 0 obj\n%s\nendobj\n", $number, $body);
        }

        $size = \count($this->bodies) + 1;
        $hash = strtoupper(hash('xxh128', $pdf));
        $identifier = $identified ? \sprintf(' /ID [<%1$s> <%1$s>]', $hash) : '';

        return $pdf.\sprintf("xref\n0 %d\n0000000000 65535 f \n%strailer\n<< /Size %d /Root %d 0 R /Info %d 0 R%s >>\nstartxref\n%d\n%%%%EOF\n", $size, $offsets, $size, $root, $info, $identifier, \strlen($pdf));
    }
}
