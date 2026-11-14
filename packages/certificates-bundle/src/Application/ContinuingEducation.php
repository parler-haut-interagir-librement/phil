<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Contract\LearningActivityRepository;
use Phil\CertificatesBundle\Domain\Event\LearningActivityRecorded;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The credits of continuing education each member gathers: activities recorded — once for each source the application
 * names, so that a message handled twice records one activity —, removed, listed and summed over a period, and the
 * progress of a certificate's holder toward the credits its recertification asks for.
 *
 * Once an activity is stored, `LearningActivityRecorded` is dispatched when there is a dispatcher.
 */
final readonly class ContinuingEducation
{
    /**
     * The most activities listed at once.
     */
    public const int MAX_LISTED = 500;

    public function __construct(
        private LearningActivityRepository $activities,
        private Courses $courses,
        private CertificateRepository $certificates,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @param string|null $course the slug of the course it counts toward; every course when null
     * @param string|null $source where it comes from, unique for the member: recording the same source again returns
     *                            the activity already recorded
     */
    public function record(string $member, string $title, Credits $credits, ?DateTimeImmutable $completedAt = null, ?string $course = null, ?string $source = null): LearningActivity
    {
        $member = MemberId::fromString($member);

        if (null !== $source) {
            $recorded = $this->activities->findBySource($member, $source);

            if ($recorded instanceof LearningActivity) {
                return $recorded;
            }
        }

        $activity = LearningActivity::record($member, $title, $credits, $completedAt ?? $this->clock->now(), null === $course ? null : $this->courses->get($course), $source);
        $this->activities->save($activity);
        $this->events?->dispatch(new LearningActivityRecorded($activity->id, $member->value));

        return $activity;
    }

    public function remove(string $id): LearningActivity
    {
        $activity = $this->activities->find($id) ?? throw CertificateRefused::because(\sprintf('There is no activity %s.', $id));
        $this->activities->remove($activity);

        return $activity;
    }

    /**
     * @return list<LearningActivity> the latest first, `MAX_LISTED` at most
     */
    public function activities(string $member, Period $period): array
    {
        return $this->activities->of(MemberId::fromString($member), $period, self::MAX_LISTED);
    }

    /**
     * The member's credits over the period; with a course, only those that count toward its recertification.
     */
    public function total(string $member, Period $period, ?string $course = null): Credits
    {
        return $this->activities->total(MemberId::fromString($member), $period, null === $course ? null : $this->courses->get($course));
    }

    /**
     * Where the holder of a certificate stands toward its recertification: the credits gathered since it was awarded,
     * those the course asks for — null when it asks for none —, and those still missing.
     *
     * @return array{gathered: Credits, required: ?Credits, missing: ?Credits}
     */
    public function progress(string $code): array
    {
        $certificate = $this->certificates->findByCode(VerificationCode::fromString($code)->value) ?? throw CertificateRefused::because(\sprintf('There is no certificate %s.', $code));

        if (null === $certificate->holder) {
            throw CertificateRefused::because(\sprintf('The certificate %s names no holder whose credits are counted.', $certificate->code->formatted()));
        }

        $required = $certificate->course->recertificationCredits;
        $gathered = $this->gathered($certificate, $certificate->holder, $certificate->course);

        return ['gathered' => $gathered, 'required' => $required, 'missing' => $required instanceof Credits ? $gathered->missingTo($required) : null];
    }

    private function gathered(Certificate $certificate, string $holder, Course $course): Credits
    {
        return $this->activities->total(MemberId::fromString($holder), new Period($certificate->awardedAt, $this->clock->now()->modify('+1 second')), $course);
    }
}
