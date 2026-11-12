<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use InvalidArgumentException;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MarkingStoreInterface;

/**
 * The place of a `Publication` is the place of its locale's latest draft row, and storing a place is publishing:
 * `published` publishes the draft, `unpublished` takes the locale offline, both through `ContentManager`, each in its
 * own transaction and under the guards of the `phil_content` workflow.
 *
 * A locale without a draft row stands in `no_draft`, from which no transition leaves.
 */
final readonly class PublicationMarkingStore implements MarkingStoreInterface
{
    public function __construct(
        private ContentManager $contents,
    ) {
    }

    #[Override]
    public function getMarking(object $subject): Marking
    {
        $publication = $this->publication($subject);

        foreach ($this->contents->status($publication->reference) as $status) {
            if ($status->locale === $publication->locale->value && null !== $status->place) {
                return new Marking([$status->place => 1]);
            }
        }

        return new Marking([PublicationWorkflow::NO_DRAFT => 1]);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function setMarking(object $subject, Marking $marking, array $context = []): void
    {
        $publication = $this->publication($subject);

        if ($marking->has(ContentWorkflow::PUBLISHED)) {
            $this->contents->publish($publication->reference, $publication->locale, true === ($context['accept_losses'] ?? false));
        } elseif ($marking->has(ContentWorkflow::UNPUBLISHED)) {
            $this->contents->unpublish($publication->reference, $publication->locale);
        }
    }

    private function publication(object $subject): Publication
    {
        if (!$subject instanceof Publication) {
            throw new InvalidArgumentException(\sprintf('The publication workflow moves a %s, not a %s.', Publication::class, $subject::class));
        }

        return $subject;
    }
}
