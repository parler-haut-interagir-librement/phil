<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Form\CreateTokenType;
use Phil\ApiKeysBundle\Infrastructure\Http\TokenPageController;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\FixedCsrf;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\QueryUrls;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(TokenPageController::class)]
#[CoversClass(CreateTokenType::class)]
final class TokenPageTest extends TestCase
{
    private const string TEMPLATE = <<<'TWIG'
        {{ tokens|map(t => t.name)|join(',') }}|{{ issued ? issued.plainText : '-' }}|{{ revoked ? 'revoked' : '-' }}|{{ now|date('c') }}|{{ scope_labels|json_encode|raw }}|{{ scope_domain }}|{{ form.vars.name }}|{{ owner.type }}:{{ owner.id }}:{{ typed_owner ? 'typed' : 'own' }}
        TWIG;

    private ApiTokens $tokens;

    private MockClock $clock;

    private OwnerTypes $owners;

    protected function setUp(): void
    {
        $this->clock = new MockClock(Tokens::NOW);
        $this->owners = new OwnerTypes(['organization' => ['billing', 'projects:read'], 'application' => null]);
        $this->tokens = Tokens::service(new InMemoryApiTokenRepository(), $this->clock, owners: $this->owners);
    }

    public function testThePageListsTheMembersTokens(): void
    {
        $this->tokens->create('alice', 'Mine', []);
        $this->tokens->create('bob', 'Theirs', []);

        $response = $this->controller()->page(Request::create('/?revoked=1'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('Mine|-|revoked|2026-11-08T10:00:00+00:00|{"projects:read":"scope.projects_read","projects:write":"scope.projects:write","billing":"scope.billing"}|scopes|create_token|user:alice:own', $response->getContent());
    }

    public function testAMemberCreatesATokenAndSeesItOnce(): void
    {
        $response = $this->controller()->page($this->submit(['name' => 'Deploy', 'scopes' => ['billing', 'projects:read'], 'lifetime' => '7']));
        [$token] = $this->tokens->find('alice');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(['Deploy', ['projects:read', 'billing']], [$token->name(), $token->scopes()]);
        self::assertEquals(new DateTimeImmutable('2026-11-15 10:00:00 UTC'), $token->expiresAt());
        self::assertMatchesRegularExpression('/^Deploy\|test_'.$token->id().'_[0-9A-Za-z]{38}\|-\|/', (string) $response->getContent());
    }

    public function testAnInvalidFormCreatesNothing(): void
    {
        foreach ([
            ['name' => '', 'lifetime' => '7'],
            ['name' => str_repeat('n', 101), 'lifetime' => '7'],
            ['name' => 'n', 'lifetime' => '8'],
            ['name' => 'n', 'lifetime' => ''],
            ['name' => 'n', 'lifetime' => '7', 'scopes' => ['admin']],
        ] as $data) {
            $response = $this->controller()->page($this->submit($data));
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        }

        $response = $this->controller()->page($this->submit(['name' => 'n', 'lifetime' => '7'], 'forged'));
        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $this->tokens->find());
    }

    public function testTheFormOffersTheScopesAndLifetimes(): void
    {
        $form = $this->forms()->create(CreateTokenType::class);
        $view = $form->createView();

        self::assertSame(CreateTokenType::CSRF_TOKEN_ID, $form->getConfig()->getOption('csrf_token_id'));
        self::assertSame('phil_api_keys', $form->getConfig()->getOption('translation_domain'));
        self::assertSame(30, $form->get('lifetime')->getData());
        $scopes = $this->choices($view['scopes']);
        self::assertSame(['projects:read', 'projects:write', 'billing'], array_map(static fn (ChoiceView $choice): string => $choice->value, $scopes));
        self::assertSame(['scope.projects_read', 'scope.projects:write', 'scope.billing'], array_map(static fn (ChoiceView $choice): mixed => $choice->label, $scopes));
        self::assertSame(['title' => 'See the invoices'], $scopes[2]->attr);
        self::assertSame('scopes', $view['scopes']->vars['choice_translation_domain']);
        self::assertTrue($view['scopes']->vars['multiple']);
        self::assertTrue($view['scopes']->vars['expanded']);
        self::assertFalse($view['scopes']->vars['required']);
        $lifetimes = $this->choices($view['lifetime']);
        self::assertSame(['7', '30', '90'], array_map(static fn (ChoiceView $choice): string => $choice->value, $lifetimes));
        self::assertEquals(new TranslatableMessage('api_keys.form.days', ['%count%' => 7], 'phil_api_keys'), $lifetimes[0]->label);
        self::assertSame(['maxlength' => 100, 'autocomplete' => 'off'], $view['name']->vars['attr']);
        self::assertSame('api_keys.form.name', $view['name']->vars['label']);
        self::assertSame('api_keys.form.scopes', $view['scopes']->vars['label']);
        self::assertSame('api_keys.form.lifetime', $view['lifetime']->vars['label']);
        self::assertNull($this->forms(7, [30, 90])->create(CreateTokenType::class)->get('lifetime')->getData(), 'no default among the choices');
    }

    public function testAMemberRevokesTheirToken(): void
    {
        $token = $this->tokens->create('alice', 'Mine', [])->token;
        $request = Request::create('/x/revoke', 'POST', ['_token' => 'valid-phil_api_keys_revoke_'.$token->id()]);

        $response = $this->controller()->revoke($request, $token->id());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/phil_api_keys?revoked=1', $response->headers->get('Location'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertTrue($token->isRevoked());
    }

    public function testRevokingNeedsTheCsrfTokenOfTheRow(): void
    {
        $token = $this->tokens->create('alice', 'Mine', [])->token;

        foreach ([[], ['_token' => 'valid-phil_api_keys_revoke_BBBBBBBBBBBB']] as $body) {
            try {
                $this->controller()->revoke(Request::create('/x/revoke', 'POST', $body), $token->id());
                self::fail('The token was revoked.');
            } catch (AccessDeniedHttpException $accessDeniedHttpException) {
                self::assertSame('The form has expired: reload the page and try again.', $accessDeniedHttpException->getMessage());
            }
        }

        self::assertFalse($token->isRevoked());
    }

    public function testAnotherMembersTokenIsNotFound(): void
    {
        $token = $this->tokens->create('bob', 'Theirs', [])->token;

        try {
            $this->controller()->revoke(Request::create('/x/revoke', 'POST', ['_token' => 'valid-phil_api_keys_revoke_'.$token->id()]), $token->id());
            self::fail('The token was revoked.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('No such token.', $notFoundHttpException->getMessage());
        }

        self::assertFalse($token->isRevoked());
    }

    public function testThePageNeedsASignedInMember(): void
    {
        foreach ([fn (): mixed => $this->controller(null)->page(Request::create('/')), fn (): mixed => $this->controller(null)->revoke(Request::create('/', 'POST'), 'AAAAAAAAAAAA')] as $call) {
            try {
                $call();
                self::fail('The page answered.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertSame('Sign in to manage your API tokens.', $accessDeniedException->getMessage());
            }
        }
    }

    public function testAnAllowedMemberManagesTheTokensOfAnOrganization(): void
    {
        $acme = new TokenOwner('organization', 'acme');
        $this->tokens->create($acme, 'Existing', ['billing']);
        $this->tokens->create('acme', 'A member named acme', []);

        $controller = $this->controller(granted: [$acme]);

        $page = $controller->page(Request::create('/'), 'organization', 'acme');
        self::assertSame(200, $page->getStatusCode());
        self::assertSame('no-store, private', $page->headers->get('Cache-Control'));
        self::assertStringStartsWith('Existing|-|-|', (string) $page->getContent());
        self::assertStringEndsWith('|organization:acme:typed', (string) $page->getContent());
        self::assertStringEndsWith('|user:alice:own', (string) $controller->page(Request::create('/'))->getContent());

        $this->clock->sleep(60);
        $created = $controller->page($this->submit(['name' => 'Export', 'scopes' => ['billing'], 'lifetime' => '7']), 'organization', 'acme');
        self::assertSame(200, $created->getStatusCode());
        [$token] = $this->tokens->find($acme);
        self::assertSame(['Export', 'organization', 'acme', ['billing']], [$token->name(), $token->ownerType(), $token->owner(), $token->scopes()]);

        $refused = $controller->page($this->submit(['name' => 'Write', 'scopes' => ['projects:write'], 'lifetime' => '7']), 'organization', 'acme');
        self::assertSame(422, $refused->getStatusCode(), 'the form offers only the scopes of the type');
        self::assertCount(2, $this->tokens->find($acme));

        $revoked = $controller->revoke(Request::create('/x', 'POST', ['_token' => 'valid-phil_api_keys_revoke_'.$token->id()]), $token->id(), 'organization', 'acme');
        self::assertSame(303, $revoked->getStatusCode());
        self::assertSame('/phil_api_keys_owner?ownerType=organization&owner=acme&revoked=1', $revoked->headers->get('Location'));
        self::assertTrue($token->isRevoked());
    }

    public function testTheFormOffersOnlyTheScopesOfTheOwnerType(): void
    {
        $view = $this->forms()->create(CreateTokenType::class, null, ['scopes' => ['billing', 'projects:read']])->createView();

        self::assertSame(['projects:read', 'billing'], array_map(static fn (ChoiceView $choice): string => $choice->value, $this->choices($view['scopes'])));
        self::assertNull($this->forms()->create(CreateTokenType::class)->getConfig()->getOption('scopes'));

        $this->expectException(InvalidOptionsException::class);
        $this->forms()->create(CreateTokenType::class, null, ['scopes' => 'billing']);
    }

    public function testTheTokensOfAnotherOwnerNeedTheVotersConsent(): void
    {
        $token = $this->tokens->create(new TokenOwner('organization', 'acme'), 'Theirs', [])->token;

        foreach ([
            fn (): mixed => $this->controller(granted: [new TokenOwner('organization', 'other')])->page(Request::create('/'), 'organization', 'acme'),
            fn (): mixed => $this->controller(granted: [new TokenOwner('application', 'acme')])->revoke(Request::create('/x', 'POST', ['_token' => 'valid-phil_api_keys_revoke_'.$token->id()]), $token->id(), 'organization', 'acme'),
            fn (): mixed => $this->controller(authorization: false)->page(Request::create('/'), 'organization', 'acme'),
        ] as $call) {
            try {
                $call();
                self::fail('The page answered.');
            } catch (AccessDeniedException $accessDeniedException) {
                self::assertSame('You may not manage these API tokens.', $accessDeniedException->getMessage());
            }
        }

        self::assertFalse($token->isRevoked());
    }

    public function testAnUndeclaredOrMalformedOwnerIsNotFound(): void
    {
        foreach ([['team', 'acme'], ['Organization', 'acme'], ['organization', '']] as [$type, $owner]) {
            try {
                $this->controller(granted: [new TokenOwner('team', 'acme')])->page(Request::create('/'), $type, $owner);
                self::fail('The page answered.');
            } catch (NotFoundHttpException $notFoundHttpException) {
                self::assertSame('No such owner.', $notFoundHttpException->getMessage());
            }
        }
    }

    public function testATokenOfAnotherOwnerTypeIsNotFound(): void
    {
        $token = $this->tokens->create('acme', 'A member named acme', [])->token;

        try {
            $this->controller(granted: [new TokenOwner('organization', 'acme')])->revoke(Request::create('/x', 'POST', ['_token' => 'valid-phil_api_keys_revoke_'.$token->id()]), $token->id(), 'organization', 'acme');
            self::fail('The token was revoked.');
        } catch (NotFoundHttpException $notFoundHttpException) {
            self::assertSame('No such token.', $notFoundHttpException->getMessage());
        }

        self::assertFalse($token->isRevoked());
    }

    /**
     * @return list<ChoiceView>
     */
    private function choices(FormView $view): array
    {
        $choices = $view->vars['choices'];
        self::assertIsArray($choices);

        return array_values(array_filter($choices, static fn (mixed $choice): bool => $choice instanceof ChoiceView));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function submit(array $data, string $csrf = 'valid-'.CreateTokenType::CSRF_TOKEN_ID): Request
    {
        return Request::create('/', 'POST', ['create_token' => [...$data, '_token' => $csrf]]);
    }

    /**
     * @param list<TokenOwner> $granted the owners whose tokens the member may manage
     */
    private function controller(?string $user = 'alice', array $granted = [], bool $authorization = true): TokenPageController
    {
        $storage = new TokenStorage();

        if (null !== $user) {
            $storage->setToken(new UsernamePasswordToken(new InMemoryUser($user, null), 'main'));
        }

        return new TokenPageController(
            $this->tokens,
            Tokens::scopes(),
            $this->forms(),
            new Environment(new ArrayLoader(['page' => self::TEMPLATE]), ['strict_variables' => true]),
            $storage,
            new FixedCsrf(),
            new QueryUrls(),
            $this->clock,
            'page',
            'scopes',
            $authorization ? new readonly class($granted) implements AuthorizationCheckerInterface {
                /**
                 * @param list<TokenOwner> $granted
                 */
                public function __construct(private array $granted)
                {
                }

                #[Override]
                public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
                {
                    return TokenPageController::MANAGE === $attribute
                        && $subject instanceof TokenOwner
                        && [] !== array_filter($this->granted, static fn (TokenOwner $owner): bool => $owner->equals($subject));
                }
            } : null,
            $this->owners,
        );
    }

    /**
     * @param positive-int       $default
     * @param list<positive-int> $lifetimes
     */
    private function forms(int $default = 30, array $lifetimes = [7, 30, 90]): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new CsrfExtension(new FixedCsrf()))
            ->addType(new CreateTokenType(Tokens::scopes(), $lifetimes, $default, 'scopes'))
            ->getFormFactory();
    }
}
