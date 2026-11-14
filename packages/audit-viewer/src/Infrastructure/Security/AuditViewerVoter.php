<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\Security;

use Override;
use Phil\AuditViewer\Infrastructure\Security\Contract\AuditAccessPolicy;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who reads the audit log, who reads the history of one record, and who exports the log: `PHIL_AUDIT_VIEW` is granted
 * to the holders of the viewer's role, `PHIL_AUDIT_HISTORY` to the holders of the history role or of the viewer's,
 * and `PHIL_AUDIT_EXPORT` to the holders of the export role. The history is asked for with its `Subject`, a row with
 * its `AuditRow` and a segment with its name.
 *
 * The voter grants only what it can grant precisely: the role holds, and the application's {@see AuditAccessPolicy},
 * when there is one, allows that attribute on that subject. Anything else is refused. Under the `affirmative`
 * strategy, Symfony's default, a refusal of another voter cannot outweigh a grant: an application that decides per
 * record or per site narrows through the policy, which this voter consults before it grants, never through a voter
 * of its own.
 *
 * @extends Voter<string, mixed>
 */
final class AuditViewerVoter extends Voter
{
    public const string VIEW = 'PHIL_AUDIT_VIEW';

    public const string HISTORY = 'PHIL_AUDIT_HISTORY';

    public const string EXPORT = 'PHIL_AUDIT_EXPORT';

    public function __construct(
        private readonly AccessDecisionManagerInterface $decisions,
        private readonly string $role,
        private readonly string $exportRole,
        private readonly ?string $historyRole = null,
        private readonly ?AuditAccessPolicy $policy = null,
    ) {
    }

    #[Override]
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::HISTORY, self::EXPORT], true);
    }

    #[Override]
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $roles = match ($attribute) {
            self::EXPORT => [$this->exportRole],
            self::HISTORY => null === $this->historyRole || $this->historyRole === $this->role ? [$this->role] : [$this->historyRole, $this->role],
            default => [$this->role],
        };

        foreach ($roles as $role) {
            if (!$this->decisions->decide($token, [$role])) {
                continue;
            }

            if ($this->policy instanceof AuditAccessPolicy && !$this->policy->allows($attribute, $subject, $token)) {
                $vote?->addReason(\sprintf('%s is refused by the access policy to %s.', $attribute, $role));

                return false;
            }

            $vote?->addReason(\sprintf('%s is granted to %s.', $attribute, $role));

            return true;
        }

        $vote?->addReason(\sprintf('%s needs %s.', $attribute, implode(' or ', $roles)));

        return false;
    }
}
