<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Event\CertificateExpiring;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Reminds the holders of certificates that are about to end: for each certificate, neither revoked nor renewed, whose
 * end comes within one of the thresholds — 60, 30 and 7 days by default —, `CertificateExpiring` is dispatched once
 * per threshold reached, and the certificate notes when. The application sends the reminder itself, by mail or
 * otherwise; the bundle sends nothing.
 *
 * It is run on a schedule, by `phil:certificates:remind` or the application's own scheduler: a run reads the
 * certificates by pages of `PAGE` and reminds `$limit` of them at most, so that a long backlog is spread over several
 * runs.
 */
final readonly class ExpiryReminders
{
    public const int PAGE = 200;

    public const int MAX_DAYS = 3650;

    /** @var list<int> */
    private array $thresholds;

    /**
     * @param list<int> $thresholds the days before the end at which the holder is reminded, from 1 to 3650
     */
    public function __construct(
        private CertificateRepository $certificates,
        private ClockInterface $clock,
        private EventDispatcherInterface $events,
        array $thresholds = [60, 30, 7],
    ) {
        if ([] === $thresholds) {
            throw InvalidValue::because('The reminders have one threshold at least.');
        }

        foreach ($thresholds as $days) {
            if ($days < 1 || $days > self::MAX_DAYS) {
                throw InvalidValue::because(\sprintf('A reminder comes from 1 to %d days before the end; %d is not.', self::MAX_DAYS, $days));
            }
        }

        rsort($thresholds);
        $this->thresholds = $thresholds;
    }

    /**
     * Reminds the holders whose certificates reached a threshold since they were last reminded.
     *
     * @return int how many reminders were dispatched
     */
    public function remind(int $limit = 1000): int
    {
        $now = $this->clock->now();
        $until = $now->modify(\sprintf('+%d days', $this->thresholds[0]));
        $sent = 0;

        for ($offset = 0; $sent < $limit; $offset += self::PAGE) {
            $page = $this->certificates->expiring($now, $until, self::PAGE, $offset);

            foreach ($page as $certificate) {
                if ($sent < $limit && $this->remindOne($certificate, $now)) {
                    ++$sent;
                }
            }

            if (\count($page) < self::PAGE) {
                break;
            }
        }

        return $sent;
    }

    /**
     * The moment the certificate reached the nearest threshold to its end, or null when it reached none, or has ended.
     */
    public function reachedAt(Certificate $certificate, DateTimeImmutable $at): ?DateTimeImmutable
    {
        $end = $certificate->validUntil;

        if (!$end instanceof DateTimeImmutable || $end <= $at) {
            return null;
        }

        $reached = null;

        foreach ($this->thresholds as $days) {
            $moment = $end->modify(\sprintf('-%d days', $days));

            if ($moment <= $at) {
                $reached = $moment;
            }
        }

        return $reached;
    }

    private function remindOne(Certificate $certificate, DateTimeImmutable $now): bool
    {
        $reachedAt = $this->reachedAt($certificate, $now);

        if (!$reachedAt instanceof DateTimeImmutable || $certificate->remindedAt instanceof DateTimeImmutable && $certificate->remindedAt >= $reachedAt) {
            return false;
        }

        $certificate->remind($now);
        $this->certificates->save($certificate);
        $this->events->dispatch(new CertificateExpiring($certificate->id, $certificate->code->value, $certificate->course->slug, $certificate->daysLeft($now) ?? 0));

        return true;
    }
}
