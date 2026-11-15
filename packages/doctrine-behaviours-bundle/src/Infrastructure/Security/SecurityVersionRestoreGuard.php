<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Security;

use Override;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Going back to a past version takes a right of its own, asked of Symfony Security with the record as subject; without
 * the security bundle, or without a voter that grants it, it is refused. The refusal is the 403 of Symfony Security.
 */
final readonly class SecurityVersionRestoreGuard implements VersionRestoreGuard
{
    public const string DEFAULT_RIGHT = 'PHIL_VERSION_RESTORE';

    public function __construct(
        private ?AuthorizationCheckerInterface $checker,
        private string $right = self::DEFAULT_RIGHT,
    ) {
    }

    #[Override]
    public function check(Versionable $entity, int $number): void
    {
        if (true === $this->checker?->isGranted($this->right, $entity)) {
            return;
        }

        $denied = new AccessDeniedException(\sprintf('Going back to version %d takes the right %s.', $number, $this->right));
        $denied->setAttributes($this->right);
        $denied->setSubject($entity);

        throw $denied;
    }
}
