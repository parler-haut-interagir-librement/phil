<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Imports the results a previous platform exported for an imported course, row by row: the learner's stable
 * identifier, the identifier the item or unit had in its package, its SCORM status, a score out of a hundred and the
 * moment, in ISO 8601 or as a UTC date. The learner is enrolled when they are not; a row that cannot be read is
 * reported by its number and left out, the others imported.
 */
final readonly class ResultImporter
{
    public const int MAX_ROWS = 100_000;

    private const array FORMATS = [\DATE_ATOM, 'Y-m-d\TH:i:s.vP', 'Y-m-d\TH:i:sP', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', '!Y-m-d'];

    public function __construct(
        private Courses $courses,
        private Enrollments $enrollments,
    ) {
    }

    /**
     * @param iterable<int, array{learner?: ?string, item?: ?string, status?: ?string, score?: ?string, date?: ?string}> $rows
     *
     * @return array{imported: int, refused: array<int, string>} the rows refused, by number, with the reason
     */
    public function import(CourseId $course, iterable $rows): array
    {
        $activities = [];

        foreach ($this->courses->outline($course)->activities() as $activity) {
            if (null !== $activity->source) {
                $activities[$activity->source] = $activity;
            }
        }

        $imported = 0;
        $refused = [];

        foreach ($rows as $number => $row) {
            if ($imported + \count($refused) >= self::MAX_ROWS) {
                $refused[$number] = \sprintf('Past %d rows, the rest is left out.', self::MAX_ROWS);

                break;
            }

            try {
                $this->row($course, $activities, $row);
                ++$imported;
            } catch (CoursesException $coursesException) {
                $refused[$number] = $coursesException->getMessage();
            }
        }

        return ['imported' => $imported, 'refused' => $refused];
    }

    /**
     * @param array<string, Activity>                                                                     $activities
     * @param array{learner?: ?string, item?: ?string, status?: ?string, score?: ?string, date?: ?string} $row
     */
    private function row(CourseId $course, array $activities, array $row): void
    {
        $item = $row['item'] ?? '';
        $activity = $activities[$item] ?? throw InvalidCourse::because(\sprintf('No activity of the course comes from the item "%s".', mb_substr($item, 0, 120)));
        $outcome = ScormStatus::outcome($row['status'] ?? '', $row['score'] ?? null);
        $at = $this->moment($row['date'] ?? '');
        $enrollment = $this->enrollments->enroll($course, LearnerId::of(trim($row['learner'] ?? '')));

        if ($outcome->isEmpty()) {
            return;
        }

        $this->enrollments->record($enrollment, $activity, $outcome, $at);
    }

    private function moment(string $date): ?DateTimeImmutable
    {
        $date = trim($date);

        if ('' === $date) {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $moment = DateTimeImmutable::createFromFormat($format, $date, new DateTimeZone('UTC'));

            // The last errors are false when the reading raised neither error nor warning, such as a day past the month.
            if (false !== $moment && false === DateTimeImmutable::getLastErrors()) {
                return $moment;
            }
        }

        throw InvalidCourse::because(\sprintf('"%s" is not a moment in ISO 8601.', mb_substr($date, 0, 40)));
    }
}
