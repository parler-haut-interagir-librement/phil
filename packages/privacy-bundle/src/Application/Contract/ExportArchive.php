<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use DateTimeImmutable;

/**
 * Keeps the export written before an account is erased, for as long as the person needs to fetch it.
 *
 * **The export never travels in a message.** It is the person's whole file: it is written here, and what moves between
 * processes is its reference. An adapter keeps it out of the logs and removes it at its expiry.
 */
interface ExportArchive
{
    public function put(string $reference, string $content, DateTimeImmutable $expiresAt): void;

    public function get(string $reference): ?string;

    /**
     * @return int how many exports were removed
     */
    public function purgeExpired(DateTimeImmutable $now): int;
}
