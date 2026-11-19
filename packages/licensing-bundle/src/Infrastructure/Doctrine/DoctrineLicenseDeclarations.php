<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * The declarations in the table `phil_licensing_declaration`, through the entity manager.
 */
final readonly class DoctrineLicenseDeclarations implements LicenseDeclarationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function declarationOf(SubjectReference $subject): ?LicenseDeclaration
    {
        return $this->entityManager->getRepository(LicenseDeclaration::class)->findOneBy(['subjectType' => $subject->type, 'subjectId' => $subject->id]);
    }

    #[Override]
    public function add(LicenseDeclaration $declaration): void
    {
        $this->entityManager->persist($declaration);
    }

    #[Override]
    public function remove(LicenseDeclaration $declaration): void
    {
        $this->entityManager->remove($declaration);
    }
}
