<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Security;

use InvalidArgumentException;
use Override;
use Phil\CommunityNotes\Contract\Qualification;
use Phil\CommunityNotes\Note;
use Phil\Resource\ResourceReference;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The qualification asked of Symfony Security, for the signed-in member, who is the one writing or rating: an
 * attribute to write, granted on the resource the note is about, and one to rate, granted on the note. A role —
 * `ROLE_NOTE_WRITER` — is decided by the role hierarchy; any other attribute by the application's voters, which
 * receive a `QualificationSubject` naming the member and what they act on.
 *
 * It needs `symfony/security-core`, which the library does not require.
 */
final readonly class AuthorizationQualification implements Qualification
{
    public const string WRITE = 'COMMUNITY_NOTE_WRITE';

    public const string RATE = 'COMMUNITY_NOTE_RATE';

    /**
     * @throws InvalidArgumentException when an attribute is empty
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private string $writeAttribute = self::WRITE,
        private string $rateAttribute = self::RATE,
    ) {
        if ('' === $writeAttribute || '' === $rateAttribute) {
            throw new InvalidArgumentException('The attributes asked of Symfony Security to write and to rate are named.');
        }
    }

    #[Override]
    public function mayWrite(ResourceReference $member, ResourceReference $subject): bool
    {
        return $this->authorization->isGranted($this->writeAttribute, new QualificationSubject($member, $subject, null));
    }

    #[Override]
    public function mayRate(ResourceReference $member, Note $note): bool
    {
        return $this->authorization->isGranted($this->rateAttribute, new QualificationSubject($member, $note->subject, $note));
    }
}
