<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Override;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * The working copies in the table `phil_content_working_copy`, through the entity manager of the content.
 */
final readonly class DoctrineWorkingCopyRepository implements WorkingCopyRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findAutosave(ContentReference $content, Locale $locale, string $author): ?WorkingCopy
    {
        return $this->repository()->findOneBy([...$this->of($content), 'locale' => $locale, 'author' => $author, 'name' => null]);
    }

    #[Override]
    public function findNamed(ContentReference $content, Locale $locale, string $name): ?WorkingCopy
    {
        return $this->repository()->findOneBy([...$this->of($content), 'locale' => $locale, 'name' => $name]);
    }

    #[Override]
    public function findAllNamed(ContentReference $content, Locale $locale): array
    {
        return array_values(array_filter(
            $this->repository()->findBy([...$this->of($content), 'locale' => $locale], ['name' => 'ASC']),
            static fn (WorkingCopy $copy): bool => !$copy->isAutosave(),
        ));
    }

    #[Override]
    public function findAllOf(ContentReference $content): array
    {
        return $this->repository()->findBy($this->of($content));
    }

    #[Override]
    public function add(WorkingCopy $copy): void
    {
        $this->entityManager->persist($copy);
    }

    #[Override]
    public function remove(WorkingCopy $copy): void
    {
        $this->entityManager->remove($copy);
    }

    /**
     * @return array{resourceKey: string, resourceId: string}
     */
    private function of(ContentReference $content): array
    {
        return ['resourceKey' => $content->resourceKey, 'resourceId' => $content->resourceId];
    }

    /**
     * @return EntityRepository<WorkingCopy>
     */
    private function repository(): EntityRepository
    {
        return $this->entityManager->getRepository(WorkingCopy::class);
    }
}
