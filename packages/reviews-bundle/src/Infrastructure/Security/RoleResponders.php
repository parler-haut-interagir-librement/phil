<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Infrastructure\Security;

use Override;
use Phil\ReviewsBundle\Application\Contract\ResponderPolicy;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Who responds to the reviews, asked of Symfony Security: the signed-in user granted the attribute, with the subject
 * given to the voters — a role such as `ROLE_REVIEWS_RESPONDER`, or an attribute a voter of the application decides
 * by subject. Nobody responds without Security.
 */
final readonly class RoleResponders implements ResponderPolicy
{
    public const string DEFAULT_ATTRIBUTE = 'ROLE_REVIEWS_RESPONDER';

    public function __construct(
        private ?AuthorizationCheckerInterface $authorization,
        private string $attribute = self::DEFAULT_ATTRIBUTE,
    ) {
    }

    #[Override]
    public function mayRespond(SubjectRef $subject, MemberId $member): bool
    {
        return $this->authorization?->isGranted($this->attribute, $subject) ?? false;
    }
}
