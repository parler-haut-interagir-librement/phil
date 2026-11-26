<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Functional\App\Http;

use Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver;
use Phil\DddBundle\Tests\Functional\App\Membership\MemberId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Two actions that echo the identifier they receive: one as an application writes it, one that opts out of the
 * bundle's resolver and falls back to its default.
 */
final readonly class MemberController
{
    public function show(MemberId $member): Response
    {
        return new Response($member::class.' '.$member->toString());
    }

    public function optOut(#[ValueResolver(IdentifierValueResolver::class, disabled: true)] ?MemberId $member = null): Response
    {
        return new Response($member?->toString() ?? 'none');
    }
}
