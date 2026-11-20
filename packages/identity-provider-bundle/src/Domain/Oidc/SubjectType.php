<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * What the provider gives clients as the `sub` of an account (OpenID Connect Core, section 8): its internal identifier,
 * an opaque identifier the same for every client, or a pairwise identifier, different for each sector, so that two
 * clients of different sectors cannot match their people by it.
 */
enum SubjectType: string
{
    /** The account's internal identifier. */
    case Account = 'account';

    /** An opaque identifier derived from the account, the same for every client: the `public` type of the discovery. */
    case Opaque = 'public';

    /** An opaque identifier derived from the account and the sector of the client. */
    case Pairwise = 'pairwise';
}
