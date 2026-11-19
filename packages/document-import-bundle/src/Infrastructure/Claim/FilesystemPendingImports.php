<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Infrastructure\Claim;

use JsonException;
use Override;
use Phil\DocumentImport\Format;
use Phil\DocumentImportBundle\Application\Contract\PendingImports;
use Phil\DocumentImportBundle\Application\PendingImport;
use RuntimeException;

/**
 * The files waiting for their import in a directory the web server does not serve, readable by their owner only: the
 * bytes of each in `<claim>.document`, its name and format in `<claim>.json`. A claim is 32 random hexadecimal
 * digits; any other claim names no file, so a claim never reaches outside the directory. Keeps nothing in memory.
 */
final readonly class FilesystemPendingImports implements PendingImports
{
    private const string CLAIM = '/^[0-9a-f]{32}$/D';

    public function __construct(
        private string $directory,
    ) {
    }

    #[Override]
    public function keep(PendingImport $import): string
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw new RuntimeException(\sprintf('The directory "%s" of the pending imports cannot be created.', $this->directory));
        }

        $claim = sodium_bin2hex(random_bytes(16));
        $this->write($claim.'.document', $import->contents);
        $this->write($claim.'.json', json_encode(['format' => $import->format->value, 'filename' => $import->filename], \JSON_THROW_ON_ERROR));

        return $claim;
    }

    #[Override]
    public function take(string $claim): ?PendingImport
    {
        if (1 !== preg_match(self::CLAIM, $claim)) {
            return null;
        }

        $contents = $this->read($claim.'.document');
        $about = $this->about($claim.'.json');

        if (null === $contents || null === $about) {
            return null;
        }

        $format = \is_string($about['format'] ?? null) ? Format::tryFrom($about['format']) : null;

        if (!$format instanceof Format) {
            return null;
        }

        return new PendingImport($contents, $format, \is_string($about['filename'] ?? null) ? $about['filename'] : null);
    }

    #[Override]
    public function release(string $claim): void
    {
        if (1 !== preg_match(self::CLAIM, $claim)) {
            return;
        }

        foreach ([$claim.'.document', $claim.'.json'] as $file) {
            if (is_file($this->directory.'/'.$file)) {
                unlink($this->directory.'/'.$file);
            }
        }
    }

    private function write(string $file, string $bytes): void
    {
        $path = $this->directory.'/'.$file;

        if (false === @file_put_contents($path, $bytes, \LOCK_EX)) {
            throw new RuntimeException(\sprintf('The pending import "%s" cannot be written.', $file));
        }

        chmod($path, 0o600);
    }

    /**
     * What the JSON file holds, `null` when it is missing, damaged, or deeper than the two levels it is written with.
     *
     * @return array<mixed>|null
     */
    private function about(string $file): ?array
    {
        $json = $this->read($file);

        if (null === $json) {
            return null;
        }

        try {
            $about = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($about) ? $about : null;
    }

    private function read(string $file): ?string
    {
        $path = $this->directory.'/'.$file;
        $bytes = is_file($path) ? file_get_contents($path) : false;

        return false === $bytes ? null : $bytes;
    }
}
