<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use DateTimeInterface;
use Phil\CommunityNotes\Contract\NoteRepository;
use Phil\CommunityNotes\Contract\Qualification;
use Phil\CommunityNotes\Contract\RatingRepository;
use Phil\CommunityNotes\Event\NoteErased;
use Phil\CommunityNotes\Event\NoteRated;
use Phil\CommunityNotes\Event\NoteStatusChanged;
use Phil\CommunityNotes\Event\NoteWithdrawn;
use Phil\CommunityNotes\Event\NoteWritten;
use Phil\CommunityNotes\Exception\InvalidNote;
use Phil\CommunityNotes\Exception\NoteNotFound;
use Phil\CommunityNotes\Exception\NoteRefused;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the application does with community notes: its qualified members write them and rate the notes of others;
 * after every rating, the cross-agreement rule decides the note again, and the decision is kept with it to explain
 * its status. Only a helpful note is shown beside its subject. A member's notes and ratings are exported and erased
 * on request.
 *
 * The service keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class CommunityNotes
{
    public function __construct(
        private NoteRepository $notes,
        private RatingRepository $ratings,
        private Qualification $qualification,
        private ClockInterface $clock,
        private CrossAgreementRule $rule = new CrossAgreementRule(),
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Writes a note beside a resource.
     *
     * @throws NoteRefused when the member may not write a note there
     * @throws InvalidNote when the text is out of bounds
     */
    public function write(ResourceReference $subject, ResourceReference $author, string $text): Note
    {
        if (!$this->qualification->mayWrite($author, $subject)) {
            throw NoteRefused::because('You may not write a note here.');
        }

        $note = Note::write(Uuid::v4()->toRfc4122(), $subject, $author, $text, $this->clock->now());
        $this->notes->save($note);
        $this->events?->dispatch(new NoteWritten($note->id));

        return $note;
    }

    /**
     * Rates a note, or rates it again, and decides it anew.
     *
     * @throws NoteNotFound
     * @throws NoteRefused  when the note is withdrawn, the rater wrote it, or may not rate it
     */
    public function rate(string $noteId, ResourceReference $rater, Verdict $verdict): Note
    {
        $note = $this->get($noteId);

        if (NoteStatus::Withdrawn === $note->status) {
            throw NoteRefused::because('This note is withdrawn.');
        }

        if ($note->author->equals($rater)) {
            throw NoteRefused::because('The author of a note does not rate it.');
        }

        if (!$this->qualification->mayRate($rater, $note)) {
            throw NoteRefused::because('You may not rate this note.');
        }

        $this->ratings->save(new Rating($note->id, $rater, $verdict, $this->clock->now()));
        $this->events?->dispatch(new NoteRated($note->id));

        return $this->decide($note);
    }

    /**
     * Withdraws a note; only its author may.
     *
     * @throws NoteNotFound
     * @throws NoteRefused  when the actor is not its author
     */
    public function withdraw(string $noteId, ResourceReference $author): Note
    {
        $note = $this->get($noteId);

        if (!$note->author->equals($author)) {
            throw NoteRefused::because('Only the author of a note withdraws it.');
        }

        $withdrawn = $note->withdrawn();
        $this->notes->save($withdrawn);
        $this->events?->dispatch(new NoteWithdrawn($note->id));

        return $withdrawn;
    }

    /**
     * Decides a note again from its ratings, after a change of the rule or of the raters' history; a withdrawn note
     * stays withdrawn.
     *
     * @throws NoteNotFound
     */
    public function reconsider(string $noteId): Note
    {
        $note = $this->get($noteId);

        return NoteStatus::Withdrawn === $note->status ? $note : $this->decide($note);
    }

    /**
     * @throws NoteNotFound
     */
    public function get(string $id): Note
    {
        return $this->notes->get($id) ?? throw NoteNotFound::withId($id);
    }

    /**
     * The note, `null` when the store does not have it.
     */
    public function find(string $id): ?Note
    {
        return $this->notes->get($id);
    }

    /**
     * The notes of a resource, the oldest first, whatever their status: for the raters.
     *
     * @return list<Note>
     */
    public function notesOf(ResourceReference $subject): array
    {
        return $this->notes->ofSubject($subject);
    }

    /**
     * The helpful notes of a resource, the oldest first: those shown to its readers.
     *
     * @return list<Note>
     */
    public function shownOf(ResourceReference $subject): array
    {
        return array_values(array_filter($this->notes->ofSubject($subject), static fn (Note $note): bool => $note->isShown()));
    }

    /**
     * What the library keeps of a member, for an access request: the notes they wrote and the ratings they gave, as
     * plain values; nothing when they wrote and rated nothing.
     *
     * @return array{}|array{notes: list<array{id: string, subject: string, text: string, status: string, written_at: string}>, ratings: list<array{note: string, verdict: string, rated_at: string}>}
     */
    public function exportMember(ResourceReference $member): array
    {
        $notes = $this->notes->byAuthor($member);
        $ratings = $this->ratings->byRater($member);

        if ([] === $notes && [] === $ratings) {
            return [];
        }

        return [
            'notes' => array_map(static fn (Note $note): array => [
                'id' => $note->id,
                'subject' => $note->subject->toString(),
                'text' => $note->text,
                'status' => $note->status->value,
                'written_at' => $note->writtenAt->format(DateTimeInterface::ATOM),
            ], $notes),
            'ratings' => array_map(static fn (Rating $rating): array => [
                'note' => $rating->note,
                'verdict' => $rating->verdict->value,
                'rated_at' => $rating->ratedAt->format(DateTimeInterface::ATOM),
            ], $ratings),
        ];
    }

    /**
     * Erases a member's data, for an erasure request: the notes they wrote, with the ratings of those notes, and the
     * ratings they gave. The notes they rated are decided again, a withdrawn one keeping its status, so that no kept
     * decision names them any more. The history of the other raters may change with the notes erased: `reconsider()`
     * decides their notes again.
     */
    public function eraseMember(ResourceReference $member): void
    {
        foreach ($this->notes->byAuthor($member) as $note) {
            $this->ratings->removeNote($note->id);
            $this->notes->remove($note->id);
            $this->events?->dispatch(new NoteErased($note->id));
        }

        $rated = array_map(static fn (Rating $rating): string => $rating->note, $this->ratings->byRater($member));
        $this->ratings->removeRater($member);

        foreach ($rated as $noteId) {
            $note = $this->notes->get($noteId);

            if (!$note instanceof Note) {
                continue;
            }

            if (NoteStatus::Withdrawn === $note->status) {
                $this->notes->save($note->explained($this->rule->decide($note->id, $this->ratings->ofNote($note->id), $this->ratings)));
            } else {
                $this->decide($note);
            }
        }
    }

    private function decide(Note $note): Note
    {
        $decided = $note->decided($this->rule->decide($note->id, $this->ratings->ofNote($note->id), $this->ratings));
        $this->notes->save($decided);

        if ($decided->status !== $note->status) {
            $this->events?->dispatch(new NoteStatusChanged($note->id));
        }

        return $decided;
    }
}
