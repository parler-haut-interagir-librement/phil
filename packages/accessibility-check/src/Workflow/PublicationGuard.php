<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Workflow;

use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Contract\SubjectContent;
use Phil\AccessibilityCheck\Exception\AccessibilityCheckException;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\Ujo\Document;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * A guard of `symfony/workflow` that holds back a transition — a publication — while the content of its subject is not
 * accessible. Registered as a listener of a guard event, such as `workflow.phil_content.guard.publish`
 * (`CONTENT_PUBLISH`), it asks the application's `SubjectContent` for the content, checks it, and blocks the transition
 * when a finding is `$blockAt` or graver, or when the content is refused; with `$blockAt` `null`, it never blocks.
 *
 * The blocker's message is the first finding's, in `$locale`, with the number of findings; its parameters hold the
 * whole `report`, for the screen that shows why the publication was refused. The guard holds no state between two
 * events.
 */
final readonly class PublicationGuard
{
    /** The guard event of the publication of a content of `phil/content-bundle`. */
    public const string CONTENT_PUBLISH = 'workflow.phil_content.guard.publish';

    /** The code of the blocker of an inaccessible content. */
    public const string BLOCKED = 'b5e83d1a-07c4-4f29-9a6e-2d71c0f8e4b3';

    /** The code of the blocker of a content the checker refuses. */
    public const string REFUSED = '1a9c7e52-48f0-4d36-b8e1-7f3d0a6c29e5';

    /**
     * @param ?Severity $blockAt  the severity from which a finding blocks the transition; `null` never blocks
     * @param string    $locale   the locale of the blocker's message
     * @param ?string   $language the language of a content that does not say its own; the checker's policy's when
     *                            `null`
     */
    public function __construct(
        private AccessibilityChecker $checker,
        private SubjectContent $content,
        private ?Severity $blockAt = Severity::Error,
        private string $locale = 'en',
        private ?string $language = null,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        if (!$this->blockAt instanceof Severity) {
            return;
        }

        $content = $this->content->contentOf($event->getSubject());

        if (null === $content) {
            return;
        }

        try {
            $report = $content instanceof Document
                ? $this->checker->checkUjo($content, $this->language)
                : $this->checker->checkHtml($content, $this->language);
        } catch (AccessibilityCheckException $accessibilityCheckException) {
            $event->addTransitionBlocker(new TransitionBlocker($accessibilityCheckException->getMessage(), self::REFUSED));

            return;
        }

        $report = $report->inLocale($this->locale);
        $findings = $report->findings($this->blockAt);

        if ([] !== $findings) {
            $message = $report->translate(new Message('report.blocked', ['count' => \count($findings), 'message' => $report->message($findings[0])]));
            $event->addTransitionBlocker(new TransitionBlocker($message, self::BLOCKED, ['report' => $report]));
        }
    }
}
