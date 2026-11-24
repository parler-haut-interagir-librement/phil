<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Contract\FollowerVisibilityRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Infrastructure\Doctrine\FollowTables;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The follows' part of an access or erasure request, the data subject being the member: the export lists what they
 * follow and at which level, the requests they sent and those waiting for them, their mutual relationships and whether
 * they hide their followers — not who follows them, which belongs to each follower. The erasure deletes all of it, the
 * follows of their own member subject, the claims and marks of their deliveries and the activities they did, and takes
 * them out of the mentions of the others; run twice, it reports the same.
 */
final readonly class FollowPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'follows';

    private const int PAGE = 200;

    public function __construct(
        private FollowRepository $follows,
        private FollowRequestRepository $requests,
        private MutualRepository $mutuals,
        private ActivityRepository $activities,
        private DeliveryRepository $deliveries,
        private FollowerVisibilityRepository $visibility,
        private string $memberType = 'member',
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $member = $subject->value;
        $data = array_filter([
            'follows' => $this->follows($member),
            'requests_sent' => array_map($this->request(...), $this->requests(false, $member)),
            'requests_received' => array_map($this->request(...), $this->requests(true, $member)),
            'mutual_relationships' => array_map(static fn (array $mutual): array => ['member' => $mutual['member'], 'since' => $mutual['since']->format(DateTimeInterface::ATOM)], $this->mutuals->exportOf($member)),
        ], static fn (array $entries): bool => [] !== $entries);
        $hidden = $this->visibility->hidden($member);

        return null === $hidden ? $data : [...$data, 'followers_hidden' => $hidden];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $member = $subject->value;
        $this->follows->eraseMember($member, new SubjectReference($this->memberType, $member));
        $this->requests->eraseMember($member);
        $this->mutuals->eraseMember($member);
        $this->activities->eraseMember($member);
        $this->deliveries->eraseMember($member);
        $this->visibility->eraseMember($member);

        return ErasureReport::of(self::MODULE, [
            FollowTables::FOLLOWS => ErasureEffect::Deleted,
            FollowTables::REQUESTS => ErasureEffect::Deleted,
            FollowTables::MUTUALS => ErasureEffect::Deleted,
            FollowTables::ACTIVITIES => ErasureEffect::Deleted,
            FollowTables::DELIVERIES => ErasureEffect::Deleted,
            FollowTables::UNSEEN => ErasureEffect::Deleted,
            FollowTables::VISIBILITY => ErasureEffect::Deleted,
        ]);
    }

    /**
     * @return list<array{subject: string, level: string, events: array<string, string>, followed_at: string}>
     */
    private function follows(string $member): array
    {
        $exported = [];
        $after = null;

        do {
            $page = $this->follows->ofMember($member, $after, self::PAGE);

            foreach ($page as $follow) {
                $exported[] = [
                    'subject' => $follow->subject->key(),
                    'level' => $follow->level()->value,
                    'events' => $follow->events()->toArray(),
                    'followed_at' => $follow->followedAt->format(DateTimeInterface::ATOM),
                ];
                $after = $follow->subject->key();
            }
        } while (self::PAGE === \count($page));

        return $exported;
    }

    /**
     * @return list<FollowRequest>
     */
    private function requests(bool $received, string $member): array
    {
        $requests = [];
        $after = null;

        do {
            $page = $received ? $this->requests->pendingFor($member, $after, self::PAGE) : $this->requests->sentBy($member, $after, self::PAGE);
            $requests = [...$requests, ...$page];
            $after = [] === $page ? null : $page[\count($page) - 1]->id;
        } while (self::PAGE === \count($page));

        return $requests;
    }

    /**
     * @return array{kind: string, requester: string, subject: string, approver: string, requested_at: string}
     */
    private function request(FollowRequest $request): array
    {
        return [
            'kind' => $request->kind->value,
            'requester' => $request->requester,
            'subject' => $request->subject->key(),
            'approver' => $request->approver,
            'requested_at' => $request->requestedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
