<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use LogicException;
use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\PhilApiKeysBundle;
use Phil\ApiKeysBundle\Tests\Functional\App\TestKernel;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\GitHubKeys;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * A token followed from the members' page to the API and back: created, used with its scopes, counted, refused once
 * revoked; a token revoked once GitHub reports it leaked; the commands; and the mapping, valid and in step with the schema.
 */
#[CoversNothing]
final class ApiKeysBundleTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $schema = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testTheMappingIsValidAndTheSchemaInStepWithIt(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $validator = new SchemaValidator($entityManager);

        self::assertSame([], $validator->validateMapping());
        self::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testATokenFromTheMembersPageOpensTheApiWithinItsScopes(): void
    {
        $this->client->loginUser(new InMemoryUser('alice@example.com', 'secret', ['ROLE_MEMBER']));
        $crawler = $this->client->request('GET', '/account/tokens/');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorTextContains('h1', 'Jetons d’API');
        self::assertSelectorTextContains('label', 'Nom');

        $form = $crawler->selectButton('Créer le jeton')->form();
        $form['create_token[name]'] = 'Script de déploiement';
        $scope = $form->get('create_token[scopes][0]');
        self::assertInstanceOf(ChoiceFormField::class, $scope);
        $scope->tick();
        $form['create_token[lifetime]'] = '30';
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();
        $text = $crawler->filter('code')->text();
        self::assertMatchesRegularExpression('/^acme_[0-9A-Za-z]{12}_[0-9A-Za-z]{38}$/', $text);
        self::assertSelectorTextContains('td', 'Lire les projets');
        $this->client->getCookieJar()->clear();

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text, 'REMOTE_ADDR' => '203.0.113.45']);
        self::assertResponseIsSuccessful();
        self::assertSame(['user' => 'alice@example.com', 'roles' => ['ROLE_MEMBER', 'ROLE_API_SCOPE_PROJECTS_READ']], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertResponseHeaderSame('RateLimit-Limit', '3');
        self::assertResponseHeaderSame('RateLimit-Remaining', '2');

        $repository = self::getContainer()->get(ApiTokenRepository::class);
        self::assertInstanceOf(ApiTokenRepository::class, $repository);
        [$token] = $repository->find(TokenOwner::user('alice@example.com'), 10);
        self::assertSame('203.0.113.0', $token->lastUsedAddress());

        $this->client->request('PUT', '/api/projects/1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text]);
        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="api",error="insufficient_scope",scope="projects:write"');
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text]);
        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text]);
        self::assertResponseStatusCodeSame(429);

        $this->client->loginUser(new InMemoryUser('alice@example.com', 'secret', ['ROLE_MEMBER']));
        $crawler = $this->client->request('GET', '/account/tokens/');
        $this->client->submit($crawler->selectButton('Révoquer')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Le jeton est révoqué.');
        self::assertSelectorTextContains('tbody', 'Révoqué');
        $this->client->getCookieJar()->clear();

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text]);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="api",error="invalid_token"');
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testARequestWithoutATokenIsChallenged(): void
    {
        $this->client->request('GET', '/api/projects');
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="api"');

        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer acme_nope']);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="api",error="invalid_token"');
        self::assertSame(['type' => 'about:blank', 'status' => 401, 'title' => 'Unauthorized', 'detail' => 'The API token was refused.'], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Basic YWxpY2U6c2VjcmV0']);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer realm="api"');
    }

    public function testATokenGitHubFindsIsRevoked(): void
    {
        $keys = new GitHubKeys();
        self::getContainer()->set('test.github_http_client', new MockHttpClient(new MockResponse($keys->document())));
        $tokens = self::getContainer()->get(ApiTokens::class);
        self::assertInstanceOf(ApiTokens::class, $tokens);
        $issued = $tokens->create('alice@example.com', 'Published by mistake', ['projects:read']);

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
        self::assertResponseIsSuccessful();

        $body = json_encode([['token' => $issued->plainText, 'type' => 'acme_api_token', 'url' => 'https://github.com/o/r/blob/x/f', 'source' => 'content']], \JSON_THROW_ON_ERROR);
        $this->client->request('POST', '/hooks/github/secret-scanning/', server: [
            'HTTP_GITHUB_PUBLIC_KEY_IDENTIFIER' => $keys->identifier,
            'HTTP_GITHUB_PUBLIC_KEY_SIGNATURE' => $keys->sign($body),
            'CONTENT_TYPE' => 'application/json',
        ], content: $body);
        self::assertResponseIsSuccessful();
        self::assertSame([['token_hash' => hash('sha256', $issued->plainText), 'token_type' => 'acme_api_token', 'label' => 'true_positive']], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('POST', '/hooks/github/secret-scanning/', server: ['HTTP_GITHUB_PUBLIC_KEY_IDENTIFIER' => $keys->identifier, 'HTTP_GITHUB_PUBLIC_KEY_SIGNATURE' => base64_encode('nope')], content: $body);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAnOrganizationsTokenOpensTheApiAsTheOrganization(): void
    {
        $tokens = self::getContainer()->get(ApiTokens::class);
        self::assertInstanceOf(ApiTokens::class, $tokens);
        $issued = $tokens->create(new TokenOwner('organization', 'acme'), 'Dashboard', ['projects:read']);

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
        self::assertResponseIsSuccessful();
        self::assertSame(['user' => 'acme', 'roles' => ['ROLE_ORGANIZATION', 'ROLE_API_SCOPE_PROJECTS_READ']], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->loginUser(new InMemoryUser('alice@example.com', 'secret', ['ROLE_MEMBER']));
        $this->client->request('GET', '/account/tokens/owners/organization/acme');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('th[scope=row]', 'Dashboard');
        self::assertSelectorNotExists('input[value="projects:write"]');
        $this->client->submit($this->client->getCrawler()->selectButton('Révoquer')->form());
        self::assertResponseRedirects('/account/tokens/owners/organization/acme?revoked=1', 303);

        $this->client->request('GET', '/api/projects', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$issued->plainText]);
        self::assertResponseStatusCodeSame(401);

        $this->client->loginUser(new InMemoryUser('alice@example.com', 'secret', ['ROLE_MEMBER']));
        $this->client->request('GET', '/account/tokens/owners/organization/other');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/account/tokens/owners/team/acme');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheMembersPageNeedsASignedInMember(): void
    {
        $this->client->request('GET', '/account/tokens/');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTheCommands(): void
    {
        $application = new Application(self::$kernel ?? throw new LogicException('No kernel.'));
        $create = new CommandTester($application->find('phil:api-keys:create'));
        $create->execute(['owner' => 'alice@example.com', 'name' => 'CI', '--scope' => ['projects:write']]);
        $create->assertCommandIsSuccessful();

        $text = trim(explode("\n", trim($create->getDisplay()))[1]);

        $this->client->request('PUT', '/api/projects/1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$text]);
        self::assertResponseStatusCodeSame(403, 'projects:read is required by the class too');

        $list = new CommandTester($application->find('phil:api-keys:list'));
        $list->execute([]);
        self::assertStringContainsString('| alice@example.com | CI   | projects:write |', $list->getDisplay());

        $tokens = self::getContainer()->get(ApiTokens::class);
        self::assertInstanceOf(ApiTokens::class, $tokens);
        [$token] = $tokens->find('alice@example.com');
        $revoke = new CommandTester($application->find('phil:api-keys:revoke'));
        $revoke->execute(['id' => [$token->id()]]);
        $revoke->assertCommandIsSuccessful();

        $purge = new CommandTester($application->find('phil:api-keys:purge-expired'));
        $purge->execute(['--retention' => '0']);
        self::assertSame("0 tokens removed.\n", $purge->getDisplay(), 'revoked this very second');
        self::assertSame(PhilApiKeysBundle::TEMPLATE, '@PhilApiKeys/tokens.html.twig');
    }
}
