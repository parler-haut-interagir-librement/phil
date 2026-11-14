<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Http\CheckResult;
use Phil\OpenBadgesBundle\Infrastructure\Http\CredentialCheck;
use Phil\OpenBadgesBundle\Infrastructure\Http\IssuerController;
use Phil\OpenBadgesBundle\Infrastructure\Http\StatusListController;
use Phil\OpenBadgesBundle\Infrastructure\Http\VerificationController;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\Kit;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\Lists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(IssuerController::class)]
#[CoversClass(StatusListController::class)]
#[CoversClass(VerificationController::class)]
#[CoversClass(CredentialCheck::class)]
#[CoversClass(CheckResult::class)]
final class HttpTest extends TestCase
{
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIW2NgUPr/HwADaAIhG61j/AAAAABJRU5ErkJggg==';

    public function testTheIssuerControllerServesJsonForCaches(): void
    {
        $controller = new IssuerController(['id' => 'https://example.com/i', 'name' => 'É'], ['keys' => [['kty' => 'OKP']]], 60);
        $profile = $controller->profile(Request::create('/'));

        self::assertSame('{"id":"https://example.com/i","name":"É"}', $profile->getContent());
        self::assertSame('application/ld+json', $profile->headers->get('Content-Type'));
        self::assertSame('*', $profile->headers->get('Access-Control-Allow-Origin'));
        self::assertSame(60, $profile->getMaxAge());
        self::assertTrue($profile->headers->hasCacheControlDirective('public'));
        self::assertSame('"'.hash('xxh128', '{"id":"https://example.com/i","name":"É"}').'"', $profile->getEtag());

        $keys = $controller->keys(Request::create('/'));
        self::assertSame('{"keys":[{"kty":"OKP"}]}', $keys->getContent());
        self::assertSame('application/jwk-set+json', $keys->headers->get('Content-Type'));

        $again = $controller->profile(Request::create('/', server: ['HTTP_IF_NONE_MATCH' => $profile->getEtag()]));
        self::assertSame(304, $again->getStatusCode());
    }

    public function testTheStatusListController(): void
    {
        $controller = $this->statusLists(Securing::DATA_INTEGRITY);
        $response = $controller->show('main');

        self::assertSame('application/vc', $response->headers->get('Content-Type'));
        self::assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame(300, $response->getMaxAge());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertStringContainsString('"id":"https://example.com/open-badges/status/main"', (string) $response->getContent());
        $verified = Kit::verifier()->verify((string) $response->getContent(), new FrozenClock()->now());
        self::assertSame('2026-11-08T10:00:00Z', $verified->credential['validFrom'] ?? null);
        self::assertSame('2026-11-08T10:05:00Z', $verified->credential['validUntil'] ?? null);
        self::assertTrue(new StatusListIssuer(Kit::issuer())->applies(Kit::urls()->entry('main', 7), $verified->credential));

        $token = $this->statusLists(Securing::JWT)->show('paused');
        self::assertSame('application/vc+jwt', $token->headers->get('Content-Type'));
        $subject = Kit::verifier()->verify((string) $token->getContent(), new FrozenClock()->now())->credential['credentialSubject'] ?? null;
        self::assertIsArray($subject);
        self::assertSame('suspension', $subject['statusPurpose'] ?? null);
    }

    public function testAnUnknownListIsNotFound(): void
    {
        $this->expectExceptionObject(new NotFoundHttpException('The status list "missing" does not exist.'));
        $this->statusLists(Securing::JWT)->show('missing');
    }

    public function testWithoutListsNoneIsFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        new StatusListController(new StatusListIssuer(Kit::issuer()), new Securing(Kit::signer(), Kit::METHOD, Securing::JWT, new FrozenClock()), Kit::urls(), new FrozenClock(), 131_072, 300)->show('main');
    }

    public function testTheCheckOfACredential(): void
    {
        $check = $this->check();

        $active = $check->check(Kit::token(Kit::urls()->entry('main', 8)), ' ada@example.org ');
        self::assertTrue($active->holds());
        self::assertFalse($active->statusApplies);
        self::assertTrue($active->recipientMatches);

        $revoked = $check->check(Kit::token(Kit::urls()->entry('main', 7)));
        self::assertFalse($revoked->holds());
        self::assertTrue($revoked->statusApplies);
        self::assertNull($revoked->recipientMatches);

        $other = $check->check(Kit::token(), 'bob@example.org');
        self::assertFalse($other->holds());
        self::assertNull($other->statusApplies);
        self::assertFalse($other->recipientMatches);

        self::assertNull($check->check(Kit::token(Kit::urls()->entry('paused', 3)))->statusApplies, 'A list of suspensions does not answer for a revocation.');
        self::assertTrue($check->check(Kit::token(Kit::urls()->entry('paused', 3, 'suspension')))->statusApplies);
        self::assertNull($check->check(Kit::token(Kit::urls()->entry('missing', 3)))->statusApplies);
        self::assertNull($check->check(Kit::token(Kit::urls('partner.example')->entry('main', 7)))->statusApplies);
        self::assertNull(new CredentialCheck(Kit::verifier(), new FrozenClock(), Kit::urls())->check(Kit::token(Kit::urls()->entry('main', 7)))->statusApplies);
        self::assertTrue($check->check(Kit::token(), '   ')->holds());

        $refused = $check->check('hello');
        self::assertNull($refused->credential);
        self::assertSame('A credential is a compact JSON Web Token or the JSON of a document with its proof.', $refused->reason);
        self::assertFalse($refused->holds());
        self::assertSame('The status of the credential is not a BitstringStatusListEntry.', $check->check(new JwtSigner(Kit::signer(), Kit::METHOD)->sign(['issuer' => Kit::ISSUER, 'credentialStatus' => 'x']))->reason);
    }

    public function testACheckResultReadsTheCredential(): void
    {
        $result = $this->check()->check(Kit::token());

        self::assertSame('Gold', $result->achievement('name'));
        self::assertSame('Fifty comments.', $result->achievement('description'));
        self::assertNull($result->achievement('missing'));
        self::assertNull($result->achievement('type'));
        self::assertSame('Example', $result->issuerName());

        $bare = $this->check()->check(new JwtSigner(Kit::signer(), Kit::METHOD)->sign(['name' => 'Record', 'issuer' => Kit::ISSUER, 'credentialSubject' => ['achievement' => 'x']]));
        self::assertSame('Record', $bare->achievement('name'));
        self::assertSame(Kit::ISSUER, $bare->issuerName());
        self::assertSame('Record', $this->check()->check(new JwtSigner(Kit::signer(), Kit::METHOD)->sign(['name' => 'Record', 'issuer' => ['id' => Kit::ISSUER, 'name' => 3]]))->achievement('name'));
        self::assertSame(Kit::ISSUER, $this->check()->check(new JwtSigner(Kit::signer(), Kit::METHOD)->sign(['issuer' => ['id' => Kit::ISSUER, 'name' => 3]]))->issuerName());

        $refused = CheckResult::refused('no');
        self::assertNull($refused->achievement('name'));
        self::assertNull($refused->issuerName());
    }

    public function testTheVerificationPage(): void
    {
        $page = $this->page();

        $form = $page->page(Request::create('/verify'));
        self::assertSame(200, $form->getStatusCode());
        self::assertSame('form', $form->getContent());
        self::assertSame('no-store, private', $form->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $form->headers->get('Referrer-Policy'));

        $valid = $page->page(Request::create('/verify', 'POST', ['credential' => ' '.Kit::token()."\n", 'identity' => 'ada@example.org']));
        self::assertSame(200, $valid->getStatusCode());
        self::assertSame('holds layout', $valid->getContent());

        $other = $page->page(Request::create('/verify', 'POST', ['credential' => Kit::token(), 'identity' => 'bob@example.org']));
        self::assertSame(422, $other->getStatusCode());
        self::assertSame('fails', $other->getContent());

        $empty = $page->page(Request::create('/verify', 'POST', ['credential' => '  ']));
        self::assertSame(422, $empty->getStatusCode());
        self::assertSame('Paste a credential or give a baked image.', $empty->getContent());

        self::assertSame('A credential is at most 2048 bytes.', $page->page(Request::create('/verify', 'POST', ['credential' => str_repeat('a', 2049)]))->getContent());
        self::assertSame('A credential is a compact JSON Web Token or the JSON of a document with its proof.', $page->page(Request::create('/verify', 'POST', ['credential' => str_repeat('a', 2048)]))->getContent());
    }

    public function testTheVerificationPageReadsImages(): void
    {
        $page = $this->page(65_536);
        $png = PngBaker::bake(base64_decode(self::PNG, true), Kit::token());

        self::assertSame('holds layout', $page->page($this->upload($png))->getContent());
        self::assertSame('holds layout', $page->page($this->upload(SvgBaker::bake('<svg xmlns="http://www.w3.org/2000/svg"/>', Kit::token()), ['credential' => 'ignored']))->getContent());
        self::assertSame('The image holds no baked credential.', $page->page($this->upload(base64_decode(self::PNG, true)))->getContent());
        self::assertSame('The image is not well-formed XML.', $page->page($this->upload('GIF89a'))->getContent());
        self::assertSame('An image is at most 65536 bytes.', $page->page($this->upload(str_repeat('a', 65_537)))->getContent());
        self::assertSame('The image is not well-formed XML.', $page->page($this->upload(str_repeat('a', 65_536)))->getContent());
        self::assertSame('Paste a credential or give a baked image.', $page->page($this->upload('', [], \UPLOAD_ERR_NO_FILE))->getContent());
        self::assertSame('An image is at most 65536 bytes.', $page->page($this->upload('x', [], \UPLOAD_ERR_PARTIAL))->getContent());
    }

    private function statusLists(string $format): StatusListController
    {
        return new StatusListController(new StatusListIssuer(Kit::issuer()), new Securing(Kit::signer(), Kit::METHOD, $format, new FrozenClock()), Kit::urls(), new FrozenClock(), 131_072, 300, new Lists());
    }

    private function check(): CredentialCheck
    {
        return new CredentialCheck(Kit::verifier(), new FrozenClock(), Kit::urls(), new Lists());
    }

    private function page(int $maxUpload = 2048): VerificationController
    {
        $twig = new Environment(new ArrayLoader(['@PhilOpenBadges/verify.html.twig' => '{% if result is null %}form{% elseif result.credential is null %}{{ result.reason }}{% elseif result.holds %}holds {{ layout }}{% else %}fails{% endif %}']));

        return new VerificationController($this->check(), $twig, 'layout', $maxUpload);
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(string $content, array $fields = [], int $error = \UPLOAD_ERR_OK): Request
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'badge');
        file_put_contents($path, $content);

        return Request::create('/verify', 'POST', $fields, files: ['image' => new UploadedFile($path, 'badge', null, $error, true)]);
    }
}
