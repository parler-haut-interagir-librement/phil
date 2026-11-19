<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Security;

use Override;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `is_granted('PHIL_LICENSING_EXPORT', subject)`: denied while the export guard finds a violation, each violation's
 * code given as a reason of the vote. It says nothing of who may export: the application's own voters decide that, and
 * the access decision strategy combines them.
 *
 * @extends Voter<string, SubjectReference|Licensable>
 */
final class ExportVoter extends Voter
{
    public const string EXPORT = 'PHIL_LICENSING_EXPORT';

    public function __construct(
        private readonly ExportGuard $guard,
    ) {
    }

    #[Override]
    public function supportsAttribute(string $attribute): bool
    {
        return self::EXPORT === $attribute;
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EXPORT === $attribute && ($subject instanceof SubjectReference || $subject instanceof Licensable);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $report = $this->guard->check($subject);

        foreach ($report->violations as $violation) {
            $vote?->addReason($violation->code->value);
        }

        return $report->isCompliant();
    }
}
