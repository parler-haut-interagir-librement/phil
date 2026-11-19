<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\BigBlueButton;

/**
 * The hash a BigBlueButton call is signed with, among those of SHA-2 the server accepts since 2.5 — its
 * `supportedChecksumAlgorithms` lists them. SHA-1, the only one of the older servers, is not offered.
 */
enum ChecksumAlgorithm: string
{
    case Sha256 = 'sha256';
    case Sha384 = 'sha384';
    case Sha512 = 'sha512';

    public function hash(string $data): string
    {
        return match ($this) {
            self::Sha256 => hash('sha256', $data),
            self::Sha384 => hash('sha384', $data),
            self::Sha512 => hash('sha512', $data),
        };
    }
}
