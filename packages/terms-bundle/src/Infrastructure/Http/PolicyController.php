<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Http;

use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public pages of a policy: the version in force at its own address, each version at an address that never
 * changes, with the list of the versions, and the changes from one version to another.
 */
final readonly class PolicyController
{
    public const string CURRENT_ROUTE = 'phil_terms_policy';

    public const string VERSION_ROUTE = 'phil_terms_version';

    public const string CHANGES_ROUTE = 'phil_terms_changes';

    public const string CURRENT_CACHE = 'public, max-age=300';

    public const string VERSION_CACHE = 'public, max-age=86400';

    public function __construct(
        private Pages $pages,
        private Policies $policies,
        private VersionCatalog $catalog,
        private PolicyTexts $texts,
        private ClockInterface $clock,
        private string $fallbackLocale,
    ) {
    }

    public function current(Request $request, string $policy): Response
    {
        $now = $this->clock->now();
        $version = $this->catalog->inForce($this->policy($policy), $now) ?? $this->catalog->latest($policy) ?? throw new NotFoundHttpException('The policy has no version yet.');

        return $this->show($request, $version, self::CURRENT_CACHE);
    }

    public function version(Request $request, string $policy, int $number): Response
    {
        return $this->show($request, $this->find($policy, $number), self::VERSION_CACHE);
    }

    public function changes(Request $request, string $policy, int $number, int $from): Response
    {
        $new = $this->find($policy, $number);
        $old = $this->find($policy, $from);

        if ($old->number >= $new->number) {
            throw new NotFoundHttpException('The changes go from an older version to a newer one.');
        }

        $changes = $this->texts->changes($old, $new, $request->getLocale(), $this->fallbackLocale) ?? throw new NotFoundHttpException('The changes between two versions cannot be shown.');

        return $this->pages->render('changes', ['old' => $old, 'new' => $new, 'changes' => $changes], cacheControl: self::VERSION_CACHE);
    }

    private function show(Request $request, PolicyVersion $version, string $cacheControl): Response
    {
        $now = $this->clock->now();

        return $this->pages->render('policy', [
            'version' => $version,
            'html' => $this->texts->toHtml($version, $request->getLocale(), $this->fallbackLocale),
            'versions' => $this->catalog->versions($version->policy),
            'in_force' => $this->catalog->inForce($version->policy, $now),
            'now' => $now,
        ], cacheControl: $cacheControl);
    }

    private function policy(string $policy): string
    {
        if (!$this->policies->has($policy)) {
            throw new NotFoundHttpException('No such policy.');
        }

        return $policy;
    }

    private function find(string $policy, int $number): PolicyVersion
    {
        return $this->catalog->find($this->policy($policy), $number) ?? throw new NotFoundHttpException('No such version.');
    }
}
