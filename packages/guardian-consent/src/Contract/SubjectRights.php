<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Contract;

/**
 * The library's part of an access or an erasure request, for the privacy tooling of the application to call. The
 * subject is the opaque identifier of a person, read as a member and as a guardian alike.
 */
interface SubjectRights
{
    /**
     * What the library keeps on the person, as JSON-encodable data; an empty array when it keeps nothing.
     *
     * @return array<string, mixed>
     */
    public function export(string $subject): array;

    /**
     * Erases what the library keeps on the person and returns how many records it erased or changed. Running it again
     * on a person already erased succeeds and returns 0.
     */
    public function erase(string $subject): int;
}
