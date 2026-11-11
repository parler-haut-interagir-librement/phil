<?php

declare(strict_types=1);

namespace Phil\OpenBadges;

use Phil\OpenBadges\Exception\InvalidBadge;

/**
 * The endorsements a credential, an achievement or a profile carries: each an `EndorsementCredential` a third party
 * signed, as the secured document of a Data Integrity proof (`endorsement`) or as a compact JSON Web Token
 * (`endorsementJwt`).
 */
final class Endorsements
{
    private function __construct()
    {
    }

    /**
     * The `endorsement` and `endorsementJwt` fields, each only when it holds something.
     *
     * @param list<array<string, mixed>|string> $endorsements
     *
     * @return array{endorsement?: non-empty-list<array<string, mixed>>, endorsementJwt?: non-empty-list<string>}
     */
    public static function fields(array $endorsements): array
    {
        $documents = [];
        $tokens = [];

        foreach ($endorsements as $endorsement) {
            if (\is_string($endorsement)) {
                if (2 !== substr_count($endorsement, '.')) {
                    throw InvalidBadge::because('An endorsement given as a string is a compact JSON Web Token.');
                }

                $tokens[] = $endorsement;
            } elseif (\in_array('EndorsementCredential', (array) ($endorsement['type'] ?? []), true) && \is_array($endorsement['proof'] ?? null)) {
                $documents[] = $endorsement;
            } else {
                throw InvalidBadge::because('An endorsement given as a document is a signed EndorsementCredential, with its proof.');
            }
        }

        return array_filter(['endorsement' => $documents, 'endorsementJwt' => $tokens], static fn (array $list): bool => [] !== $list);
    }
}
