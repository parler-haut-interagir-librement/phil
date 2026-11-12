<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Workflow;

use Override;
use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Contract\SubjectContent;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\Workflow\PublicationGuard;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;

#[CoversClass(PublicationGuard::class)]
final class PublicationGuardTest extends TestCase
{
    public function testAnAccessibleContentIsPublished(): void
    {
        $event = $this->event('<img src="a.png" alt="A cat">');

        new PublicationGuard(new AccessibilityChecker(), $this->content())($event);

        self::assertFalse($event->isBlocked());
    }

    public function testAnInaccessibleContentIsHeldBack(): void
    {
        $event = $this->event('<img src="a.png"><a href="/x"></a><h2>a</h2><h4>b</h4>');

        new PublicationGuard(new AccessibilityChecker(), $this->content())($event);

        $blocker = $this->blocker($event);

        self::assertTrue($event->isBlocked());
        self::assertSame(PublicationGuard::BLOCKED, $blocker->getCode());
        self::assertSame('The accessibility check found 2 problem(s) to fix before publishing, starting with: The image a.png has no text alternative: describe it in an alt text, or give it alt="" when it is only decoration.', $blocker->getMessage());
        self::assertInstanceOf(Report::class, $blocker->getParameters()['report']);
        self::assertSame('en', $blocker->getParameters()['report']->locale);
    }

    public function testTheSeverityTheLocaleAndTheLanguageAreTheGuards(): void
    {
        $event = $this->event('<h2>a</h2><h4>b</h4><a href="/x">hier</a>');

        new PublicationGuard(new AccessibilityChecker(), $this->content(), Severity::Warning, 'fr', 'de')($event);

        $blocker = $this->blocker($event);

        self::assertSame('Le contrôle d’accessibilité a trouvé 2 problème(s) à corriger avant de publier, à commencer par : Ce titre h4 suit un h2 : un niveau est sauté.', $blocker->getMessage());

        $quiet = $this->event('<h2>a</h2><h4>b</h4>');
        new PublicationGuard(new AccessibilityChecker(), $this->content())($quiet);

        self::assertFalse($quiet->isBlocked());
    }

    public function testAGuardWithoutSeverityNeverBlocks(): void
    {
        $event = $this->event('<img src="a.png">');

        new PublicationGuard(new AccessibilityChecker(), $this->content(), null)($event);

        self::assertFalse($event->isBlocked());
    }

    public function testASubjectWithoutContentIsLeftAlone(): void
    {
        $event = $this->event(null);

        new PublicationGuard(new AccessibilityChecker(), $this->content())($event);

        self::assertFalse($event->isBlocked());
    }

    public function testAUjoDocumentIsChecked(): void
    {
        $event = $this->event(new Document([new Block('i', 'image', ['file' => ['url' => '/a.png']])]));

        new PublicationGuard(new AccessibilityChecker(new Policy(language: 'en')), $this->content())($event);

        self::assertTrue($event->isBlocked());
    }

    public function testAContentTheCheckerRefusesIsHeldBack(): void
    {
        $event = $this->event('<p>too long</p>');

        new PublicationGuard(new AccessibilityChecker(new Policy(maxBytes: 4)), $this->content())($event);

        $blocker = $this->blocker($event);

        self::assertSame(PublicationGuard::REFUSED, $blocker->getCode());
        self::assertSame('The content holds 15 bytes; at most 4 are checked.', $blocker->getMessage());
        self::assertSame('workflow.phil_content.guard.publish', PublicationGuard::CONTENT_PUBLISH);
    }

    private function blocker(GuardEvent $event): TransitionBlocker
    {
        foreach ($event->getTransitionBlockerList() as $blocker) {
            return $blocker;
        }

        self::fail('The transition is not blocked.');
    }

    private function event(string|Document|null $content): GuardEvent
    {
        $subject = new stdClass();
        $subject->content = $content;

        return new GuardEvent($subject, new Marking(['draft' => 1]), new Transition('publish', 'draft', 'published'));
    }

    private function content(): SubjectContent
    {
        return new class implements SubjectContent {
            #[Override]
            public function contentOf(object $subject): string|Document|null
            {
                $content = $subject instanceof stdClass ? $subject->content : null;

                return \is_string($content) || $content instanceof Document ? $content : null;
            }
        };
    }
}
