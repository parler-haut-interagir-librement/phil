<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

/**
 * The URLs of the JSON-LD contexts the documents of the library declare. The credentials, Open Badges and CLR
 * contexts are bundled in `data/contexts`, for Data Integrity proofs; the Multikey context is only declared by the
 * issuer's profile document.
 */
final class Context
{
    public const string CREDENTIALS_V2 = 'https://www.w3.org/ns/credentials/v2';

    public const string OPEN_BADGES = 'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json';

    public const string CLR = 'https://purl.imsglobal.org/spec/clr/v2p0/context-2.0.1.json';

    public const string MULTIKEY = 'https://w3id.org/security/multikey/v1';

    /**
     * The contexts of an `OpenBadgeCredential` and of an `EndorsementCredential`.
     */
    public const array CREDENTIAL = [self::CREDENTIALS_V2, self::OPEN_BADGES];

    private function __construct()
    {
    }
}
