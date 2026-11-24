<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Http;

use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Contract\MemberResolver;
use Phil\TermsBundle\Application\Contract\PolicyTexts;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The page the gate sends a member to: the text of each version to accept, each with its own checkbox, never ticked
 * in advance. The form is taken only when every box is ticked for the version shown — a version published meanwhile
 * is shown again rather than taken as accepted —, then the member goes back where they were going. Each acceptance
 * keeps the locale of the request, whose text the page showed.
 */
final readonly class AcceptanceController
{
    public const string ROUTE = 'phil_terms_accept';

    public function __construct(
        private Pages $pages,
        private AcceptanceChecker $checker,
        private Terms $terms,
        private MemberResolver $members,
        private PolicyTexts $texts,
        private ClockInterface $clock,
        private string $fallbackLocale,
    ) {
    }

    public function accept(Request $request): Response
    {
        $member = $this->members->current() ?? throw new AccessDeniedException('The acceptance page is for a signed-in member.');
        $target = Pages::target($request->query->getString('target', '/'));
        $pending = $this->checker->pending($member, $this->clock->now());

        if ([] === $pending) {
            return new RedirectResponse($target, Response::HTTP_SEE_OTHER);
        }

        if (!$request->isMethod('POST')) {
            return $this->form($request, $pending, $target, false);
        }

        $this->pages->guardForm($request);
        $ticked = $request->request->all('accept');

        foreach ($pending as $version) {
            if (($ticked[$version->policy] ?? null) !== (string) $version->number) {
                return $this->form($request, $pending, $target, true);
            }
        }

        foreach ($pending as $version) {
            $this->terms->accept($member, $version->policy, $version->number, AcceptanceChannel::Form, $request->getClientIp(), $request->getLocale());
        }

        return new RedirectResponse($target, Response::HTTP_SEE_OTHER);
    }

    /**
     * @param non-empty-list<PolicyVersion> $pending
     */
    private function form(Request $request, array $pending, string $target, bool $missing): Response
    {
        return $this->pages->form('accept', [
            'versions' => array_map(fn (PolicyVersion $version): array => [
                'version' => $version,
                'html' => $this->texts->toHtml($version, $request->getLocale(), $this->fallbackLocale),
            ], $pending),
            'target' => $target,
            'missing' => $missing,
        ], $missing ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
