<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

/**
 * Implemented by the application's user class, for the default `CurrentDataSubject`.
 *
 * **A dedicated method, because a user identifier is often an e-mail address**, and a person is designated by a stable,
 * opaque identifier: a UUID, an OIDC `sub`, a customer number.
 */
interface DataSubjectUser
{
    public function dataSubjectIdentifier(): string;
}
