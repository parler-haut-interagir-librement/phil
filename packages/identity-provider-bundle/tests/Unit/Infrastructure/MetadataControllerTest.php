<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
use Phil\IdentityProviderBundle\Infrastructure\Http\Oidc\MetadataController;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The discovery document and the key set the provider publishes, as a client reads them.
 */
#[CoversClass(MetadataController::class)]
final class MetadataControllerTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    public function testTheDiscoveryDocumentOfTheDefaults(): void
    {
        $response = $this->controller(new ProviderSettings(self::ISSUER))->discovery();

        self::assertSame([
            'issuer' => self::ISSUER,
            'authorization_endpoint' => 'https://id.example/oidc/authorize',
            'token_endpoint' => 'https://id.example/oidc/token',
            'userinfo_endpoint' => 'https://id.example/oidc/userinfo',
            'jwks_uri' => 'https://id.example/oidc/jwks',
            'end_session_endpoint' => 'https://id.example/oidc/end_session',
            'revocation_endpoint' => 'https://id.example/oidc/revoke',
            'introspection_endpoint' => 'https://id.example/oidc/introspect',
            'scopes_supported' => ['openid', 'profile', 'email', 'phone', 'offline_access'],
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'],
            'token_endpoint_auth_signing_alg_values_supported' => ['RS256', 'ES256', 'EdDSA'],
            'revocation_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'],
            'introspection_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'private_key_jwt'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'sid', 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'],
            'prompt_values_supported' => ['none', 'login', 'consent'],
            'authorization_response_iss_parameter_supported' => true,
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
            'claims_parameter_supported' => false,
        ], $this->document($response));
        self::assertStringContainsString('"issuer":"https://id.example","authorization_endpoint":"https://id.example/oidc/authorize"', (string) $response->getContent());
        $this->assertPublished($response, 300);
    }

    public function testTheDiscoveryDocumentOfAProviderWithItsOwnChoices(): void
    {
        $settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, scopes: [...ProviderSettings::SCOPES, 'events:read'], authMethods: [ClientAuthMethod::None], acr: 'urn:example:acr', idp: 'corporate', sessionStart: true, subjectType: SubjectType::Pairwise, subjectSalt: str_repeat('s', 32));
        $response = $this->controller($settings, 120, true)->discovery();
        $document = $this->document($response);

        self::assertSame(['openid', 'profile', 'email', 'phone', 'offline_access', 'events:read'], $document['scopes_supported']);
        self::assertSame(['pairwise'], $document['subject_types_supported']);
        self::assertSame(['ES256'], $document['id_token_signing_alg_values_supported']);
        self::assertSame(['none'], $document['token_endpoint_auth_methods_supported']);
        self::assertSame(['none'], $document['revocation_endpoint_auth_methods_supported']);
        self::assertArrayNotHasKey('introspection_endpoint_auth_methods_supported', $document, 'A provider of public clients only has no client to introspect.');
        self::assertArrayNotHasKey('token_endpoint_auth_signing_alg_values_supported', $document);
        self::assertSame(['urn:example:acr'], $document['acr_values_supported']);
        self::assertSame(['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'sid', 'acr', 'idp', 'session_start', 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'], $document['claims_supported']);
        self::assertTrue($document['backchannel_logout_supported']);
        self::assertTrue($document['backchannel_logout_session_supported']);
        $this->assertPublished($response, 120);
    }

    public function testTheIntrospectionMethodsAreAListWithoutNone(): void
    {
        $settings = new ProviderSettings(self::ISSUER, authMethods: [ClientAuthMethod::None, ClientAuthMethod::ClientSecretPost]);

        self::assertSame(['client_secret_post'], $this->document($this->controller($settings)->discovery())['introspection_endpoint_auth_methods_supported']);
    }

    public function testOnlyTheOptionalClaimsTheProviderSetsAreAnnounced(): void
    {
        $providers = [
            'acr' => new ProviderSettings(self::ISSUER, acr: 'a'),
            'idp' => new ProviderSettings(self::ISSUER, idp: 'i'),
            'session_start' => new ProviderSettings(self::ISSUER, sessionStart: true),
        ];

        foreach ($providers as $claim => $settings) {
            self::assertSame(
                ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'amr', 'sid', $claim, 'email', 'email_verified', 'name', 'phone_number', 'phone_number_verified'],
                $this->document($this->controller($settings)->discovery())['claims_supported'],
            );
        }
    }

    public function testTheKeySetIsPublishedWithoutTheEscapesOfASlash(): void
    {
        $settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
        $keys = $this->keys($settings);
        $keys->active();

        $response = new MetadataController($settings, $keys, $this->urls())->jwks();

        $published = json_decode((string) $response->getContent(), true);
        self::assertIsArray($published);
        self::assertIsArray($published['keys']);
        self::assertCount(1, $published['keys']);
        self::assertSame($keys->jwks(), $published);
        $this->assertPublished($response, 300);
    }

    private function assertPublished(JsonResponse $response, int $maxAge): void
    {
        self::assertSame(\JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR, $response->getEncodingOptions());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame((string) $maxAge, $response->headers->getCacheControlDirective('max-age'));
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @return array<string, mixed>
     */
    private function document(JsonResponse $response): array
    {
        $document = json_decode((string) $response->getContent(), true);
        self::assertIsArray($document);

        /** @var array<string, mixed> $document */
        return $document;
    }

    private function controller(ProviderSettings $settings, int $maxAge = 300, bool $backchannelLogout = false): MetadataController
    {
        return new MetadataController($settings, $this->keys($settings), $this->urls(), $maxAge, $backchannelLogout);
    }

    private function keys(ProviderSettings $settings): SigningKeys
    {
        $services = new Services();

        return new SigningKeys(new InMemorySigningKeyRepository(), new PlainSecretCipher(), $settings, $services->clock, new LockFactory(new InMemoryStore()), $services->journal);
    }

    private function urls(): UrlGenerator
    {
        $routes = new RouteCollection();

        foreach (['authorize', 'token', 'userinfo', 'jwks', 'end_session', 'revoke', 'introspect'] as $name) {
            $routes->add('phil_identity_provider_oidc_'.$name, new Route('/oidc/'.$name));
        }

        return new UrlGenerator($routes, new RequestContext('', 'GET', 'id.example', 'https'));
    }
}
