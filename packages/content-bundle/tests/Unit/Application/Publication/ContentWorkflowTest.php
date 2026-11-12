<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Phil\ContentBundle\Application\Publication\ContentWorkflow;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;

#[CoversClass(ContentWorkflow::class)]
final class ContentWorkflowTest extends TestCase
{
    /**
     * @param list<string> $enabled
     */
    #[DataProvider('enabledTransitions')]
    public function testEachPlaceEnablesItsTransitions(string $place, array $enabled): void
    {
        $workflow = ContentWorkflow::create();
        $row = $this->row($place);

        foreach ([ContentWorkflow::PUBLISH, ContentWorkflow::UNPUBLISH, ContentWorkflow::CREATE_DRAFT, ContentWorkflow::REMOVE_DRAFT] as $transition) {
            self::assertSame(\in_array($transition, $enabled, true), $workflow->can($row, $transition), $place.' → '.$transition);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function enabledTransitions(): iterable
    {
        yield 'unpublished' => ['unpublished', ['publish']];
        yield 'draft' => ['draft', ['publish', 'unpublish', 'remove_draft']];
        yield 'published' => ['published', ['unpublish', 'create_draft']];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function transitions(): iterable
    {
        yield 'publish an unpublished row' => ['unpublished', 'publish', 'published'];
        yield 'publish a draft' => ['draft', 'publish', 'published'];
        yield 'unpublish a published row' => ['published', 'unpublish', 'unpublished'];
        yield 'unpublish a draft' => ['draft', 'unpublish', 'unpublished'];
        yield 'create a draft' => ['published', 'create_draft', 'draft'];
        yield 'remove a draft' => ['draft', 'remove_draft', 'published'];
    }

    public function testARowWithoutPlaceStartsUnpublished(): void
    {
        $row = $this->row(null);

        self::assertTrue(ContentWorkflow::create()->can($row, ContentWorkflow::PUBLISH));
        self::assertSame('unpublished', $row->getWorkflowPlace());
    }

    #[DataProvider('transitions')]
    public function testATransitionMovesTheRow(string $from, string $transition, string $to): void
    {
        $row = $this->row($from);

        ContentWorkflow::create()->apply($row, $transition);

        self::assertSame($to, $row->getWorkflowPlace());
    }

    public function testATransitionThatIsNotEnabledIsRefused(): void
    {
        $this->expectException(TransitionRefused::class);
        $this->expectExceptionMessageIsOrContains('The transition "create_draft" is not allowed from the place "unpublished" of "fr/draft/1".');

        ContentWorkflow::create()->apply($this->row('unpublished'), ContentWorkflow::CREATE_DRAFT);
    }

    public function testARowWithoutTheCapabilityHasNoWorkflow(): void
    {
        $row = new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));
        $workflow = ContentWorkflow::create();

        self::assertFalse($workflow->can($row, ContentWorkflow::PUBLISH));
        $this->expectException(TransitionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The row class "%s" is not WorkflowCapable, so it cannot be published.', BareDimensionContent::class));

        $workflow->apply($row, ContentWorkflow::PUBLISH);
    }

    /**
     * The application guards a transition with a listener on the events of `symfony/workflow`.
     */
    public function testAGuardOfTheApplicationRefusesATransition(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.phil_content.guard.publish', static function (GuardEvent $event): void {
            $event->setBlocked(true);
        });
        $workflow = ContentWorkflow::create($dispatcher);

        self::assertSame('phil_content', $workflow->workflow()->getName());
        self::assertFalse($workflow->can($this->row('unpublished'), ContentWorkflow::PUBLISH));
    }

    public function testTheDefinitionStartsUnpublished(): void
    {
        $definition = ContentWorkflow::definition();

        self::assertSame(['unpublished'], $definition->getInitialPlaces());
        self::assertSame(['unpublished' => 'unpublished', 'draft' => 'draft', 'published' => 'published'], $definition->getPlaces());
        self::assertCount(6, $definition->getTransitions());
    }

    private function row(?string $place): ExampleDimensionContent
    {
        $row = new ExampleDimensionContent(new ExampleRoot(), DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
        $row->setWorkflowPlace($place);

        return $row;
    }
}
