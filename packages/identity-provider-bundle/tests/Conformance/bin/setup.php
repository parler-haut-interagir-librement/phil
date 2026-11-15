<?php

declare(strict_types=1);

/*
 * Prepares the application the OpenID conformance suite plays against, from nothing: the tables, the first signing
 * key, a test account and the two confidential clients the suite asks for, then writes the configuration of the test
 * plans to $PHIL_CONFORMANCE_ROOT/plan.json. Every secret is generated here, for this run only. Run by run.sh inside
 * the `op` service.
 */

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Tests\Conformance\App\ConformanceKernel;

require dirname(__DIR__, 5).'/vendor/autoload.php';

$issuer = getenv('PHIL_CONFORMANCE_ISSUER');
$root = getenv('PHIL_CONFORMANCE_ROOT');
$suite = getenv('PHIL_CONFORMANCE_SUITE');

if (!is_string($issuer) || !is_string($root) || !is_string($suite)) {
    fwrite(\STDERR, "PHIL_CONFORMANCE_ISSUER, PHIL_CONFORMANCE_ROOT and PHIL_CONFORMANCE_SUITE are not set.\n");

    exit(1);
}

$kernel = new ConformanceKernel($issuer, $root);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get(ConformanceKernel::ENTITY_MANAGER);
$accounts = $container->get(ConformanceKernel::ACCOUNTS);
$store = $container->get(ConformanceKernel::ACCOUNT_STORE);
$clients = $container->get(ConformanceKernel::CLIENTS);
$keys = $container->get(ConformanceKernel::KEYS);
assert($entityManager instanceof EntityManagerInterface && $accounts instanceof Accounts && $store instanceof AccountRepository && $clients instanceof ClientRegistry && $keys instanceof SigningKeys);

$tool = new SchemaTool($entityManager);
$metadata = $entityManager->getMetadataFactory()->getAllMetadata();
$tool->dropSchema($metadata);
$tool->createSchema($metadata);

$keys->rotate();

$email = 'conformance@example.com';
$password = Base64Url::encode(random_bytes(24));
$account = $accounts->register($email, $password, 'Conformance Tester');
assert($account instanceof Account);
$accounts->verifyEmail($account);
$account->verifyPhone('+15145550199', new DateTimeImmutable());
$store->save($account);

$alias = 'phil';
$redirect = rtrim($suite, '/').'/test/a/'.$alias.'/callback';
$postLogout = rtrim($suite, '/').'/test/a/'.$alias.'/post_logout_redirect';
$scopes = ['email', 'profile', 'phone', 'offline_access'];
[$first, $firstSecret] = $clients->register('conformance-1', 'Conformance 1', [$redirect], [$postLogout], $scopes, requirePkce: false);
[$second, $secondSecret] = $clients->register('conformance-2', 'Conformance 2', [$redirect], [$postLogout], $scopes, requirePkce: false);

/*
 * What the suite's browser does on the provider's pages: prove it again when asked, sign in, consent, and wait for the
 * suite's callback. With $screenshot, the page that asks to prove it again stands for the screenshot the suite wants of
 * the second authorization of `prompt=login` and `max_age=1`.
 */
$authorization = static fn (bool $screenshot = false): array => [
    'match' => $issuer.'/oidc/authorize*',
    'tasks' => [
        [
            'task' => 'Prove it again',
            'optional' => true,
            'match' => $issuer.'/reauthenticate*',
            'commands' => [
                ...$screenshot ? [['wait', 'xpath', '//*', 10, 'Confirm it is you', 'update-image-placeholder-optional']] : [],
                ['click', 'css', 'a[href="/login"]'],
            ],
        ],
        [
            'task' => 'Sign in',
            'optional' => true,
            'match' => $issuer.'/login*',
            'commands' => [
                ['text', 'name', '_username', $email],
                ['text', 'name', '_password', $password],
                ['click', 'id', 'sign-in'],
            ],
        ],
        [
            'task' => 'Consent',
            'optional' => true,
            'match' => $issuer.'/oidc/authorize*',
            'commands' => [['click', 'css', 'button[value="allow"]']],
        ],
        [
            'task' => 'Verify complete',
            'match' => '*/test/*/callback*',
            'commands' => [['wait', 'id', 'submission_complete', 10]],
        ],
    ],
];

// The modules whose request the provider must refuse on a page of its own, never by a redirect, of which the suite
// wants a screenshot.
$errorPage = static fn (string $task): array => ['browser' => [[
    'match' => $issuer.'/oidc/authorize*',
    'tasks' => [[
        'task' => $task,
        'match' => $issuer.'/oidc/authorize*',
        'commands' => [['wait', 'xpath', '//*', 10, 'This request cannot be answered', 'update-image-placeholder']],
    ]],
]]];

/*
 * RP-initiated logout: the page that asks to confirm — no `id_token_hint` of the account signed in —, and the page that
 * says the person is signed out — no redirection the provider can trust. $steps lists what the suite's browser meets on
 * the provider, in order: `confirm` clicks the button, `ask` and `done` wait for the page and take the screenshot.
 */
$logout = static fn (string ...$steps): array => [
    'match' => $issuer.'/oidc/logout*',
    'tasks' => array_map(static fn (string $step): array => [
        'task' => $step,
        'match' => $issuer.'/oidc/logout*',
        'commands' => match ($step) {
            'confirm' => [['click', 'css', 'button[type="submit"]']],
            'ask' => [['wait', 'xpath', '//*', 10, 'Do you want to sign out of this site?', 'update-image-placeholder']],
            default => [['wait', 'xpath', '//*', 10, 'You are signed out', 'update-image-placeholder']],
        },
    ], $steps),
];

$plan = [
    'alias' => $alias,
    'description' => 'phil/identity-provider-bundle',
    'server' => ['discoveryUrl' => $issuer.'/.well-known/openid-configuration'],
    'client' => ['client_id' => $first->id(), 'client_secret' => $firstSecret],
    'client_secret_post' => ['client_id' => $first->id(), 'client_secret' => $firstSecret],
    'client2' => ['client_id' => $second->id(), 'client_secret' => $secondSecret],
    'browser' => [
        $authorization(),
        [
            'match' => $issuer.'/oidc/logout*',
            'tasks' => [
                [
                    'task' => 'Confirm sign-out',
                    'optional' => true,
                    'match' => $issuer.'/oidc/logout*',
                    'commands' => [['click', 'css', 'button[type="submit"]']],
                ],
                [
                    'task' => 'Verify complete',
                    'match' => '*/test/*/post_logout_redirect*',
                ],
            ],
        ],
    ],
    'override' => [
        'oidcc-prompt-login' => ['browser' => [$authorization(true)]],
        'oidcc-max-age-1' => ['browser' => [$authorization(true)]],
        'oidcc-ensure-registered-redirect-uri' => $errorPage('Expect the page of an unregistered redirect address'),
        'oidcc-ensure-request-object-with-redirect-uri' => $errorPage('Expect the page of a request without a registered redirect address'),
        'oidcc-rp-initiated-logout-bad-post-logout-redirect-uri' => ['browser' => [$authorization(), $logout('done')]],
        'oidcc-rp-initiated-logout-query-added-to-post-logout-redirect-uri' => ['browser' => [$authorization(), $logout('done')]],
        'oidcc-rp-initiated-logout-no-post-logout-redirect-uri' => ['browser' => [$authorization(), $logout('done')]],
        'oidcc-rp-initiated-logout-modified-id-token-hint' => ['browser' => [$authorization(), $logout('ask')]],
        'oidcc-rp-initiated-logout-bad-id-token-hint' => ['browser' => [$authorization(), $logout('ask')]],
        'oidcc-rp-initiated-logout-no-id-token-hint' => ['browser' => [$authorization(), $logout('ask')]],
        'oidcc-rp-initiated-logout-no-params' => ['browser' => [$authorization(), $logout('confirm', 'done')]],
        'oidcc-rp-initiated-logout-only-state' => ['browser' => [$authorization(), $logout('confirm', 'done')]],
    ],
];

file_put_contents($root.'/plan.json', json_encode($plan, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n");
echo "The tables, the first key, the account and the clients are ready; the plan configuration is in {$root}/plan.json.\n";
