<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Infrastructure;

use Phil\BlockEditorBundle\Application\Exception\UploadRefused;
use Phil\BlockEditorBundle\Application\Upload\ImageUpload;
use Phil\BlockEditorBundle\Infrastructure\Http\EditorRequest;
use Phil\BlockEditorBundle\Infrastructure\Http\Endpoints;
use Phil\BlockEditorBundle\Infrastructure\Http\ImageUploadController;
use Phil\BlockEditorBundle\Infrastructure\Http\ReferenceSearchController;
use Phil\BlockEditorBundle\Tests\Unit\Fixture\FixedRouter;
use Phil\BlockEditorBundle\Tests\Unit\Fixture\RecordingAuthorization;
use Phil\BlockEditorBundle\Tests\Unit\Fixture\RecordingSearch;
use Phil\BlockEditorBundle\Tests\Unit\Fixture\RecordingUploader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;

#[CoversClass(EditorRequest::class)]
#[CoversClass(Endpoints::class)]
#[CoversClass(ImageUploadController::class)]
#[CoversClass(ReferenceSearchController::class)]
final class HttpTest extends TestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    private string $file = '';

    private RequestStack $requests;

    private EditorRequest $editor;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'pbe');
        file_put_contents($this->file, 'PNG!');
        $this->requests = new RequestStack();
        $this->editor = new EditorRequest($this->tokens(), $this->authorization(true));
    }

    private function tokens(): SameOriginCsrfTokenManager
    {
        return new SameOriginCsrfTokenManager($this->requests, null, null, [EditorRequest::TOKEN_ID]);
    }

    private function authorization(bool $granted): RecordingAuthorization
    {
        return new RecordingAuthorization($granted);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    /**
     * A `POST` of the editor, or a request of another method, with its header and a double-submitted token unless told
     * otherwise; it is the current request, where the token manager reads it.
     *
     * @param array<string, string> $headers
     * @param array<string, mixed>  $files
     * @param array<string, string> $query
     * @param array<string, string> $cookies
     */
    private function request(array $headers = ['X-Phil-Block-Editor' => '1', 'csrf-token' => self::TOKEN], array $files = [], array $query = [], array $cookies = ['csrf-token_'.self::TOKEN => 'csrf-token'], string $method = 'POST'): Request
    {
        $request = new Request($query, [], [], $cookies, $files, ['HTTP_HOST' => 'localhost', 'REQUEST_METHOD' => $method]);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        $this->requests->push($request);

        return $request;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(JsonResponse $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    public function testOnlyTheEditorsRequestsAreTrusted(): void
    {
        $token = ['X-Phil-Block-Editor' => '1', 'csrf-token' => self::TOKEN];

        self::assertTrue($this->editor->isTrusted($this->request()));
        self::assertTrue($this->editor->isTrusted($this->request([...$token, 'Sec-Fetch-Site' => 'same-origin'])));
        self::assertTrue($this->editor->isTrusted($this->request([...$token, 'Origin' => 'http://localhost'])));
        self::assertFalse($this->editor->isTrusted($this->request([])));
        self::assertFalse($this->editor->isTrusted($this->request(['X-Phil-Block-Editor' => 'yes', 'csrf-token' => self::TOKEN])));
        self::assertFalse($this->editor->isTrusted($this->request(['csrf-token' => self::TOKEN])));
        self::assertFalse($this->editor->isTrusted($this->request([...$token, 'Sec-Fetch-Site' => 'cross-site'])));
        self::assertSame('csrf-token', $this->editor->cookieName());
        self::assertSame(['X-Phil-Block-Editor', 'phil_block_editor'], [EditorRequest::HEADER, EditorRequest::TOKEN_ID]);
    }

    public function testTheEditorsRequestsCarryAStatelessCsrfToken(): void
    {
        $header = ['X-Phil-Block-Editor' => '1'];

        self::assertFalse($this->editor->isTrusted($this->request($header)), 'no token');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'csrf-token' => 'short'])), 'a token too short');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'csrf-token' => self::TOKEN], cookies: [])), 'a token without its cookie nor the origin');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'csrf-token' => self::TOKEN], cookies: ['csrf-token_another-token-of-24-chars' => 'csrf-token'])), 'the cookie of another token');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'csrf-token' => self::TOKEN, 'Sec-Fetch-Site' => 'same-site'])), 'another site of the same domain');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'csrf-token' => self::TOKEN, 'Origin' => 'https://elsewhere.example'])), 'another origin');
        self::assertTrue($this->editor->isTrusted($this->request([...$header, 'csrf-token' => self::TOKEN, 'Sec-Fetch-Site' => 'same-origin'], cookies: [])), 'a token and the browser naming this site');
        self::assertFalse(new EditorRequest(self::createStub(CsrfTokenManagerInterface::class), $this->authorization(true))->isTrusted($this->request()), 'a manager that finds the token wrong');
    }

    public function testAReadOfTheEditorCarriesNoToken(): void
    {
        $header = ['X-Phil-Block-Editor' => '1'];

        self::assertTrue($this->editor->isTrusted($this->request($header, cookies: [], method: 'GET')), 'a GET without a token');
        self::assertTrue($this->editor->isTrusted($this->request($header, cookies: [], method: 'HEAD')), 'a HEAD without a token');
        self::assertFalse($this->editor->isTrusted($this->request([], cookies: [], method: 'GET')), 'a GET without the header');
        self::assertFalse($this->editor->isTrusted($this->request([...$header, 'Sec-Fetch-Site' => 'cross-site'], cookies: [], method: 'GET')), 'a GET from another site');
        self::assertFalse($this->editor->isTrusted($this->request($header, cookies: [], method: 'PUT')), 'a PUT without a token');
    }

    public function testTheEndpointsAreForThoseTheApplicationGrants(): void
    {
        $granted = $this->authorization(true);
        self::assertTrue(new EditorRequest($this->tokens(), $granted)->isGranted());
        self::assertSame('IS_AUTHENTICATED', $granted->asked[0], 'signed in, by default');
        self::assertSame('IS_AUTHENTICATED', EditorRequest::ACCESS);

        $editors = $this->authorization(true);
        self::assertTrue(new EditorRequest($this->tokens(), $editors, 'ROLE_EDITOR')->isGranted());
        self::assertSame('ROLE_EDITOR', $editors->asked[0]);

        self::assertFalse(new EditorRequest($this->tokens(), $this->authorization(false))->isGranted(), 'refused by the application');
        self::assertFalse(new EditorRequest($this->tokens())->isGranted(), 'no authorization checker to ask: refused');
        self::assertTrue(new EditorRequest($this->tokens(), null, null)->isGranted(), 'no attribute: the application guards the routes itself');

        $refused = new EditorRequest($this->tokens(), $this->authorization(false));
        $uploader = $this->uploader();
        $response = new ImageUploadController($refused, $uploader)($this->request(files: ['image' => $this->file()]));
        self::assertSame([403, ['success' => 0, 'message' => 'You may not use the editor.']], [$response->getStatusCode(), $this->json($response)]);
        self::assertSame([], $uploader->received, 'nothing is kept');

        $search = $this->search();
        $controller = new ReferenceSearchController($refused, $search, $search);

        foreach ([$controller->mentions($this->request(['X-Phil-Block-Editor' => '1'], query: ['q' => 'a'], cookies: [], method: 'GET')), $controller->links($this->request(['X-Phil-Block-Editor' => '1'], query: ['q' => 'a'], cookies: [], method: 'GET'))] as $response) {
            self::assertSame([403, ['items' => []]], [$response->getStatusCode(), $this->json($response)]);
        }

        self::assertSame([], $search->queries, 'nothing is searched');
        self::assertSame(404, new ImageUploadController($refused)($this->request())->getStatusCode(), 'an endpoint turned off says so first');
    }

    public function testTheEndpointsAreThoseConfiguredAndRouted(): void
    {
        $router = new FixedRouter([Endpoints::LINKS]);

        $endpoints = new Endpoints($router, [Endpoints::IMAGE, Endpoints::LINKS]);
        self::assertSame('/_be/phil_block_editor_image', $endpoints->url(Endpoints::IMAGE));
        self::assertNull($endpoints->url(Endpoints::MENTIONS), 'not configured');
        self::assertNull($endpoints->url(Endpoints::LINKS), 'not routed');
        self::assertNull(new Endpoints(null, [Endpoints::IMAGE])->url(Endpoints::IMAGE), 'no router');
        self::assertNull(new Endpoints()->url(Endpoints::IMAGE));
        self::assertSame(['phil_block_editor_image', 'phil_block_editor_mentions', 'phil_block_editor_links'], [Endpoints::IMAGE, Endpoints::MENTIONS, Endpoints::LINKS]);
    }

    private function uploader(?UploadRefused $refusal = null): RecordingUploader
    {
        return new RecordingUploader($refusal);
    }

    private function file(?int $error = null): UploadedFile
    {
        return new UploadedFile($this->file, 'cat.png', 'image/png', $error, true);
    }

    public function testAnImageIsUploaded(): void
    {
        $uploader = $this->uploader();
        $response = new ImageUploadController($this->editor, $uploader)($this->request(files: ['image' => $this->file()]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => 1, 'file' => ['id' => 9, 'url' => '/media/cat.png', 'width' => 4, 'height' => 3]], $this->json($response));
        self::assertCount(1, $uploader->received);
        self::assertEquals(new ImageUpload($this->file, 'cat.png', 'image/png', 4), $uploader->received[0]);
        self::assertSame(10_485_760, ImageUploadController::MAX_BYTES);
    }

    public function testAnUploadIsRefusedForEachReason(): void
    {
        $cases = [
            [new ImageUploadController($this->editor), $this->request(files: ['image' => $this->file()]), 404, 'Images cannot be uploaded here.'],
            [new ImageUploadController($this->editor, $this->uploader()), $this->request([], ['image' => $this->file()]), 403, 'The request does not come from the editor.'],
            [new ImageUploadController($this->editor, $this->uploader()), $this->request(), 400, 'No image was received.'],
            [new ImageUploadController($this->editor, $this->uploader()), $this->request(files: ['image' => $this->file(\UPLOAD_ERR_PARTIAL)]), 400, 'No image was received.'],
            [new ImageUploadController($this->editor, $this->uploader(), 3), $this->request(files: ['image' => $this->file()]), 413, 'The image is too large.'],
            [new ImageUploadController($this->editor, $this->uploader(new UploadRefused('Only photos, please.'))), $this->request(files: ['image' => $this->file()]), 422, 'Only photos, please.'],
        ];

        foreach ($cases as [$controller, $request, $status, $message]) {
            $response = $controller($request);
            self::assertSame($status, $response->getStatusCode(), $message);
            self::assertSame(['success' => 0, 'message' => $message], $this->json($response));
        }

        self::assertSame(200, new ImageUploadController($this->editor, $this->uploader(), 4)($this->request(files: ['image' => $this->file()]))->getStatusCode(), 'exactly the largest size');
    }

    public function testAFileOfUnknownSizeIsRefused(): void
    {
        $file = new class($this->file, 'cat.png', 'image/png', null, true) extends UploadedFile {
            public function getSize(): int|false
            {
                return false;
            }
        };

        self::assertSame(413, new ImageUploadController($this->editor, $this->uploader())($this->request(files: ['image' => $file]))->getStatusCode());
    }

    private function search(int $count = 3): RecordingSearch
    {
        return new RecordingSearch($count);
    }

    public function testTheMenusAreAnsweredByTheirSearch(): void
    {
        $mentions = $this->search();
        $links = $this->search(20);
        $controller = new ReferenceSearchController($this->editor, $mentions, $links);

        $response = $controller->mentions($this->request(['X-Phil-Block-Editor' => '1'], query: ['q' => '  ad  '], cookies: [], method: 'GET'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['items' => [
            ['reference' => 'user:1', 'label' => 'ad1', 'url' => null, 'hint' => null],
            ['reference' => 'user:2', 'label' => 'ad2', 'url' => null, 'hint' => null],
            ['reference' => 'user:3', 'label' => 'ad3', 'url' => null, 'hint' => null],
        ]], $this->json($response));

        $answer = $this->json($controller->links($this->request(['X-Phil-Block-Editor' => '1'], query: ['q' => str_repeat('é', 150)], cookies: [], method: 'GET')));
        self::assertIsArray($answer['items']);
        self::assertCount(8, $answer['items'], 'no more than the menu shows, whatever the search gives');
        self::assertSame([[str_repeat('é', 100), 8]], $links->queries);
        self::assertSame([['ad', 8]], $mentions->queries);
        $empty = $this->search(0);
        new ReferenceSearchController($this->editor, null, $empty)->links($this->request());
        self::assertSame([['', 8]], $empty->queries);
    }

    public function testAMenuWithoutSearchOrFromElsewhereIsRefused(): void
    {
        $controller = new ReferenceSearchController($this->editor, null, $this->search());

        self::assertSame(404, $controller->mentions($this->request())->getStatusCode());
        self::assertSame(['items' => []], $this->json($controller->mentions($this->request())));
        self::assertSame(404, new ReferenceSearchController($this->editor, $this->search())->links($this->request())->getStatusCode());
        self::assertSame(403, $controller->links($this->request([]))->getStatusCode());
        self::assertSame(['items' => []], $this->json($controller->links($this->request([]))));
        self::assertSame([8, 100], [ReferenceSearchController::LIMIT, ReferenceSearchController::MAX_QUERY]);
    }
}
