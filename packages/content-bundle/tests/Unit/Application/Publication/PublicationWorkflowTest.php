<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use InvalidArgumentException;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\PublicationMarkingStore;
use Phil\ContentBundle\Application\Publication\PublicationWorkflow;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

/**
 * A content in one locale published and taken offline through `symfony/workflow`, as a scheduler would.
 */
#[CoversClass(Publication::class)]
#[CoversClass(PublicationMarkingStore::class)]
#[CoversClass(PublicationWorkflow::class)]
final class PublicationWorkflowTest extends TestCase
{
    private ContentManager $manager;

    private ContentReference $news;

    #[Override]
    protected function setUp(): void
    {
        $contents = new InMemoryContents();
        $this->manager = $contents->manager;
        $this->news = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
    }

    public function testPublishingAndTakingOfflineGoThroughTheManager(): void
    {
        $workflow = $this->workflow();
        $publication = Publication::of($this->news, 'fr');

        self::assertSame(PublicationWorkflow::NAME, $workflow->getName());
        self::assertSame(['unpublished' => 1], $workflow->getMarking($publication)->getPlaces());
        self::assertSame([true, false], [$workflow->can($publication, 'publish'), $workflow->can($publication, 'unpublish')]);

        $workflow->apply($publication, 'publish');
        self::assertSame(['published', 1], $this->place('fr'));
        self::assertSame('Ouverture', $this->manager->live($this->news, 'fr')['excerptTitle'] ?? null, 'The draft is live.');

        $this->manager->edit($this->news, 'fr', ['excerptTitle' => 'Grande ouverture']);
        self::assertSame(['draft' => 1], $workflow->getMarking($publication)->getPlaces());
        $workflow->apply($publication, 'publish');
        self::assertSame(['published', 2], $this->place('fr'));

        $workflow->apply($publication, 'unpublish');
        self::assertSame(['unpublished', null], $this->place('fr'));
        $this->expectException(ContentNotFound::class);
        $this->manager->live($this->news, 'fr');
    }

    public function testALocaleWithoutADraftStandsWhereNoTransitionLeaves(): void
    {
        $workflow = $this->workflow();
        $english = Publication::of($this->news, Locale::fromString('en'));

        self::assertSame(['no_draft' => 1], new PublicationMarkingStore($this->manager)->getMarking($english)->getPlaces());
        self::assertSame(['no_draft' => 1], $workflow->getMarking($english)->getPlaces());
        self::assertFalse($workflow->can($english, 'publish'));

        $this->expectException(NotEnabledTransitionException::class);
        $workflow->apply($english, 'publish');
    }

    public function testTheDefinitionStartsWhereNoTransitionLeaves(): void
    {
        $definition = PublicationWorkflow::definition();

        self::assertSame(['no_draft'], $definition->getInitialPlaces());
        self::assertSame(['no_draft', 'unpublished', 'draft', 'published'], array_keys($definition->getPlaces()));
        self::assertSame(
            [['publish', ['unpublished'], ['published']], ['publish', ['draft'], ['published']], ['unpublish', ['published'], ['unpublished']], ['unpublish', ['draft'], ['unpublished']]],
            array_map(static fn (Transition $transition): array => [$transition->getName(), $transition->getFroms(), $transition->getTos()], $definition->getTransitions()),
        );
    }

    public function testAContentThatIsGoneIsNotFound(): void
    {
        $this->expectException(ContentNotFound::class);
        $this->workflow()->getMarking(Publication::of(ContentReference::of('example', '99'), 'fr'));
    }

    public function testAStoredPlaceThatIsNeitherPublishedNorUnpublishedChangesNothing(): void
    {
        new PublicationMarkingStore($this->manager)->setMarking(Publication::of($this->news, 'fr'), new Marking(['draft' => 1]));

        self::assertSame(['unpublished', null], $this->place('fr'));
    }

    public function testALossyBodyIsNotPublishedByDefault(): void
    {
        $this->manager->edit($this->news, 'fr', ['bodyFormat' => 'html', 'bodySource' => '<p>a<object>x</object></p>']);

        $this->expectException(PublicationLosesContent::class);

        $this->workflow()->apply(Publication::of($this->news, 'fr'), 'publish');
    }

    public function testALossyBodyIsPublishedWhenTheContextAcceptsTheLosses(): void
    {
        $this->manager->edit($this->news, 'fr', ['bodyFormat' => 'html', 'bodySource' => '<p>a<object>x</object></p>']);

        $this->workflow()->apply(Publication::of($this->news, 'fr'), 'publish', ['accept_losses' => true]);

        self::assertSame(['published', 1], $this->place('fr'));
    }

    public function testTheEventsOfTheWorkflowGuardAPublication(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.phil_content_publication.guard.publish', static function (GuardEvent $event): void {
            $event->setBlocked(true, 'Not before the launch.');
        });
        $workflow = PublicationWorkflow::create(new PublicationMarkingStore($this->manager), $dispatcher);

        self::assertFalse($workflow->can(Publication::of($this->news, 'fr'), 'publish'));
    }

    public function testOnlyAPublicationIsMoved(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The publication workflow moves a Phil\ContentBundle\Application\Publication\Publication, not a stdClass.');
        $this->workflow()->getMarking(new stdClass());
    }

    public function testAPublicationNamesALocale(): void
    {
        $publication = Publication::of($this->news, 'fr_CA');

        self::assertSame([$this->news, 'fr_CA'], [$publication->reference, $publication->locale->value]);

        $this->expectException(InvalidDimension::class);
        Publication::of($this->news, 'fr-CA');
    }

    private function workflow(): StateMachine
    {
        return PublicationWorkflow::create(new PublicationMarkingStore($this->manager));
    }

    /**
     * @return array{?string, ?int} the place of the locale's draft, and its live version
     */
    private function place(string $locale): array
    {
        foreach ($this->manager->status($this->news) as $status) {
            if ($status->locale === $locale) {
                return [$status->place, $status->liveVersion];
            }
        }

        return [null, null];
    }
}
