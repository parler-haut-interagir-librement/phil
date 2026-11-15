<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Functional\App;

use Override;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Infrastructure\Federation\Contract\ExternalProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * A federated provider for the tests: it sends the person straight back to the callback with the subject, address
 * and verification the test put in the query of the start.
 */
final class FakeProvider implements ExternalProvider
{
    #[Override]
    public function name(): string
    {
        return 'fake';
    }

    #[Override]
    public function label(): string
    {
        return 'Fake';
    }

    #[Override]
    public function start(Request $request, string $callbackUrl): RedirectResponse
    {
        return new RedirectResponse($callbackUrl.'?'.http_build_query($request->query->all()));
    }

    #[Override]
    public function finish(Request $request, string $callbackUrl): ExternalIdentity
    {
        $subject = $request->query->getString('sub');

        if ('' === $subject) {
            throw Refused::because('external.failed');
        }

        $email = $request->query->getString('email');

        return new ExternalIdentity('fake', $subject, '' === $email ? null : $email, $request->query->getBoolean('verified'), 'Fake Person');
    }
}
