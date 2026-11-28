<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use Phil\CodeOfConduct\Contract\CodeVersionStore;
use Phil\CodeOfConduct\Contract\ReportStore;
use Phil\CodeOfConduct\Contract\ResponsiblePeople;
use Phil\CodeOfConduct\Contract\TextSealer;
use Phil\CodeOfConduct\Event\ReportFiled;
use Phil\CodeOfConduct\Event\ReportNoteAdded;
use Phil\CodeOfConduct\Event\ReportRecused;
use Phil\CodeOfConduct\Event\ReportStatusChanged;
use Phil\CodeOfConduct\Event\ReportViewed;
use Phil\CodeOfConduct\Exception\ActionRefused;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Phil\CodeOfConduct\Exception\ReportUnavailable;
use Phil\CodeOfConduct\Exception\UnreadableText;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;

/**
 * Every path to the reports filed under a code. A member files a report, named or anonymous, and follows where it
 * stands; the responsible people of the scope read it, write notes, review it and close it. A responsible person the
 * report names never sees it, and a responsible person may step aside. Texts are sealed before they are stored.
 *
 * The `$actor`, `$member` and `$reporter` arguments name a person the application has authenticated: the service
 * decides what that person may do, not who they are. It keeps no state between two calls, and is safe to share across
 * the requests of a long-running worker.
 */
final readonly class Reports
{
    public const int MAX_TEXT = 20_000;

    public const int MAX_NOTE = 10_000;

    public const int MAX_NOTES = 500;

    public const int MAX_PAGE = 100;

    public const int MAX_RESPONSIBLE = 100;

    private const string CONTEXT = "phil/code-of-conduct\x1Fv1";

    public function __construct(
        private ReportStore $reports,
        private CodeVersionStore $versions,
        private ResponsiblePeople $responsible,
        private TextSealer $sealer,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Files a report under the code of a scope.
     *
     * @param string|null  $reporter the member who files it, `null` for an anonymous report
     * @param list<string> $named    the members it names, at most 20
     *
     * @throws InvalidCodeOfConduct when an identifier or the text is out of bounds
     * @throws ActionRefused        when no responsible person outside the people named could receive it
     */
    public function file(string $scope, ?string $reporter, array $named, #[SensitiveParameter] string $text): Report
    {
        Identifier::check($scope, 'A scope');
        $text = Text::check($text, self::MAX_TEXT, \sprintf('The text of a report holds 1 to %d characters.', self::MAX_TEXT));
        $id = Uuid::v4()->toRfc4122();
        $current = $this->versions->current($scope);
        $report = new Report($id, $scope, $reporter, $named, '', $current?->number, $this->clock->now());

        if ([] === array_diff($this->responsibleOf($scope), $report->named)) {
            throw ActionRefused::because('No responsible person outside the people named can receive this report; use the contact outside the site.');
        }

        $report = new Report($id, $scope, $reporter, $report->named, $this->sealer->seal($text, $this->reportContext($id)), $report->codeVersion, $report->filedAt);
        $this->reports->save($report);
        $this->events?->dispatch(new ReportFiled($id, $scope));

        return $report;
    }

    /**
     * What the person who filed the report sees of it.
     *
     * @throws ReportUnavailable when there is no such report, or the person did not file it
     */
    public function receipt(string $id, string $reporter): ReportReceipt
    {
        $report = $this->reports->get(Identifier::check($id, 'A report'));

        if (!$report instanceof Report || $report->reporter !== Identifier::check($reporter, 'A reporter')) {
            throw ReportUnavailable::create();
        }

        return new ReportReceipt($report->id, $report->status, $report->filedAt, $report->updatedAt);
    }

    /**
     * The reports of a scope the responsible person may handle, oldest first; only those of `$status` when given.
     *
     * @throws ReportUnavailable    when the person is not responsible for the scope
     * @throws InvalidCodeOfConduct when the page is out of bounds
     */
    public function queue(string $scope, string $actor, ?ReportStatus $status = null, ?string $after = null, int $limit = 50): ReportPage
    {
        if ($limit < 1 || $limit > self::MAX_PAGE) {
            throw InvalidCodeOfConduct::because(\sprintf('A page of reports reads 1 to %d reports.', self::MAX_PAGE));
        }

        $responsible = $this->responsibleOf(Identifier::check($scope, 'A scope'));

        if (!\in_array(Identifier::check($actor, 'A responsible person'), $responsible, true)) {
            throw ReportUnavailable::create();
        }

        $found = $this->reports->inScope($scope, $status, null === $after ? null : Identifier::check($after, 'A report'), $limit);
        $handled = array_values(array_filter($found, static fn (Report $report): bool => self::mayHandle($report, $actor, $responsible)));

        return new ReportPage($handled, \count($found) < $limit ? null : array_last($found)->id);
    }

    /**
     * Opens a report and its notes for a responsible person who may handle it. Every opening is announced by the
     * `ReportViewed` event, before the texts are opened.
     *
     * @throws ReportUnavailable when there is no such report, or the person may not handle it
     * @throws UnreadableText    when a sealed text does not open
     */
    public function read(string $id, string $actor): ReportView
    {
        $report = $this->handled($id, $actor);
        $this->events?->dispatch(new ReportViewed($report->id, $actor));

        $notes = array_map(fn (ReportNote $note): NoteView => new NoteView(
            $note->id,
            $note->author,
            $this->sealer->open($note->sealedText, $this->noteContext($report->id, $note->id)),
            $note->writtenAt,
        ), $this->reports->notes($report->id, self::MAX_NOTES));

        return new ReportView($report, $this->sealer->open($report->sealedText, $this->reportContext($report->id)), $notes);
    }

    /**
     * Adds a note to a report, sealed.
     *
     * @throws ReportUnavailable    when there is no such report, or the person may not handle it
     * @throws InvalidCodeOfConduct when the text is out of bounds
     * @throws ActionRefused        when the report holds 500 notes
     */
    public function addNote(string $id, string $actor, #[SensitiveParameter] string $text): ReportNote
    {
        $report = $this->handled($id, $actor);
        $text = Text::check($text, self::MAX_NOTE, \sprintf('A note holds 1 to %d characters.', self::MAX_NOTE));

        if (\count($this->reports->notes($report->id, self::MAX_NOTES)) >= self::MAX_NOTES) {
            throw ActionRefused::because(\sprintf('A report holds %d notes at most.', self::MAX_NOTES));
        }

        $noteId = Uuid::v4()->toRfc4122();
        $note = new ReportNote($noteId, $report->id, $actor, $this->sealer->seal($text, $this->noteContext($report->id, $noteId)), $this->clock->now());
        $this->reports->addNote($note);
        $this->events?->dispatch(new ReportNoteAdded($report->id, $noteId, $actor));

        return $note;
    }

    /**
     * Takes a report received, or closed, into review.
     *
     * @throws ReportUnavailable when there is no such report, or the person may not handle it
     * @throws ActionRefused     when the report is already in review
     */
    public function review(string $id, string $actor): Report
    {
        return $this->move($id, $actor, ReportStatus::InReview, null);
    }

    /**
     * Resolves a report in review with an outcome.
     *
     * @throws ReportUnavailable when there is no such report, or the person may not handle it
     * @throws ActionRefused     when the report is not in review
     */
    public function resolve(string $id, string $actor, Outcome $outcome): Report
    {
        return $this->move($id, $actor, ReportStatus::Resolved, $outcome);
    }

    /**
     * Dismisses a report received or in review.
     *
     * @throws ReportUnavailable when there is no such report, or the person may not handle it
     * @throws ActionRefused     when the report is closed
     */
    public function dismiss(string $id, string $actor): Report
    {
        return $this->move($id, $actor, ReportStatus::Dismissed, null);
    }

    /**
     * The responsible person steps aside from the report: from then on, they no longer see it.
     *
     * @throws ReportUnavailable when there is no such report, or the person may not handle it
     */
    public function recuse(string $id, string $actor): Report
    {
        $report = $this->handled($id, $actor)->withRecusal($actor, $this->clock->now());
        $this->reports->save($report);
        $this->events?->dispatch(new ReportRecused($report->id, $actor));

        return $report;
    }

    private function move(string $id, string $actor, ReportStatus $status, ?Outcome $outcome): Report
    {
        $report = $this->handled($id, $actor);

        if (!$report->status->canBecome($status)) {
            throw ActionRefused::because(\sprintf('A report %s cannot become %s.', $report->status->value, $status->value));
        }

        $report = $report->withStatus($status, $outcome, $this->clock->now());
        $this->reports->save($report);
        $this->events?->dispatch(new ReportStatusChanged($report->id, $actor, $status, $outcome));

        return $report;
    }

    /**
     * The report, when the actor may handle it.
     */
    private function handled(string $id, string $actor): Report
    {
        $report = $this->reports->get(Identifier::check($id, 'A report'));
        Identifier::check($actor, 'A responsible person');

        if (!$report instanceof Report || !self::mayHandle($report, $actor, $this->responsibleOf($report->scope))) {
            throw ReportUnavailable::create();
        }

        return $report;
    }

    /**
     * @return list<string>
     */
    private function responsibleOf(string $scope): array
    {
        $responsible = $this->responsible->of($scope);

        if (\count($responsible) > self::MAX_RESPONSIBLE) {
            throw InvalidCodeOfConduct::because(\sprintf('A scope has %d responsible people at most.', self::MAX_RESPONSIBLE));
        }

        return $responsible;
    }

    /**
     * A responsible person of the scope, not named by the report, who did not step aside.
     *
     * @param list<string> $responsible
     */
    private static function mayHandle(Report $report, string $actor, array $responsible): bool
    {
        return \in_array($actor, $responsible, true) && !$report->names($actor) && !\in_array($actor, $report->recused, true);
    }

    private function reportContext(string $report): string
    {
        return self::CONTEXT."\x1Freport\x1F".$report;
    }

    private function noteContext(string $report, string $note): string
    {
        return self::CONTEXT."\x1Fnote\x1F".$report."\x1F".$note;
    }
}
