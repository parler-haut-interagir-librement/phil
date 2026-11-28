<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Phil\ShiftsBundle\Application\Contract\CurrentPerson;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The checks of the pages: a vote of the bundle's voter, and the person signed in with their roles. A refusal is
 * Symfony Security's, which sends a visitor to sign in and a person signed in to a 403.
 */
final readonly class Access
{
    public function __construct(
        private AuthorizationCheckerInterface $authorization,
        private CurrentPerson $person,
    ) {
    }

    /**
     * @throws AccessDeniedException when the voter refuses
     */
    public function deny(string $attribute, mixed $subject = null): void
    {
        if (!$this->authorization->isGranted($attribute, $subject)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$attribute]);
            $exception->setSubject($subject);

            throw $exception;
        }
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->authorization->isGranted($attribute, $subject);
    }

    /**
     * @throws AccessDeniedException when nobody is signed in
     */
    public function person(): string
    {
        return $this->person->current() ?? throw new AccessDeniedException();
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->person->roles();
    }
}
