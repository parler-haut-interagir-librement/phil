<?php

declare(strict_types=1);

/*
 * The reader of authorization requests, on a query string nobody wrote: it returns a request whose redirect address
 * is the registered one and whose scopes the client may ask for, or throws AuthorizationError, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$client = Client::register('app', 'App', null, ['https://app.example/cb'], [], ['email'], false, new DateTimeImmutable('2026-11-14'));
$parser = new AuthorizationRequestParser(['openid', 'email', 'profile']);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($client, $parser): void {
    parse_str($input, $query);

    try {
        $request = $parser->parse($query, static fn (string $id): ?Client => 'app' === $id ? $client : null);
    } catch (AuthorizationError $authorizationError) {
        if ($authorizationError->isRedirectable() && 'https://app.example/cb' !== $authorizationError->redirectUri) {
            throw new LogicException('An error went back to an address the client did not register.', $authorizationError->getCode(), $authorizationError);
        }

        return;
    }

    if ('https://app.example/cb' !== $request->redirectUri || [] !== array_diff($request->scopes, ['openid', 'email'])) {
        throw new LogicException('A request was read with an address or a scope it may not have.');
    }
});
$config->setMaxLen(1024);
