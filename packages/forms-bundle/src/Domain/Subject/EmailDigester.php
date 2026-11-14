<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Subject;

/**
 * The keyed digest of an email address: what an entry keeps of the address that names the person it is about, so
 * that an access or erasure request finds the entry without the address being searchable in clear.
 */
interface EmailDigester
{
    /**
     * The digest of the address once normalized: the same address, however written, gives the same digest.
     */
    public function digest(string $email): string;
}
