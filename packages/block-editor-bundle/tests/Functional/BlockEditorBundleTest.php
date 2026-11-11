<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Functional;

use Override;
use Phil\BlockEditorBundle\Infrastructure\Form\BlockEditorType;
use Phil\BlockEditorBundle\PhilBlockEditorBundle;
use Phil\BlockEditorBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;

#[CoversClass(PhilBlockEditorBundle::class)]
final class BlockEditorBundleTest extends WebTestCase
{
    private const string TOKEN = 'a-token-of-24-characters';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testTheFieldIsRenderedByTheFormTheme(): void
    {
        self::bootKernel();
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $form = $forms->createNamed('body', BlockEditorType::class, '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>x</b> \' \""}}]}', ['label' => 'Body']);
        $html = $twig->render('form.html.twig', ['form' => $form->createView()]);

        $editor = htmlspecialchars('{"tools":["paragraph","header","list","quote"],"inlineTools":["bold","italic","link","lineBreak"],"readOnly":false,"placeholder":null,"locale":"en","messages":[],"colors":["accent","muted","success","warning","danger"],"uploadUrl":"\/_block-editor\/image","mentionsUrl":"\/_block-editor\/mentions","linksUrl":null,"csrfCookie":"csrf-token"}', \ENT_QUOTES);
        $value = htmlspecialchars('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>x</b> \' \""}}]}', \ENT_QUOTES);

        self::assertSame(<<<HTML
            <div class="phil-block-editor"
                     data-controller="phil--block-editor-bundle--editor"
                     data-phil--block-editor-bundle--editor-config-value="{$editor}">
                    <input type="hidden" id="body" name="body" required="required" value="{$value}" data-phil--block-editor-bundle--editor-target="input">
                    <div class="phil-block-editor__canvas" data-phil--block-editor-bundle--editor-target="canvas" aria-labelledby="body_label"></div>
                    <div class="phil-block-editor__status" data-phil--block-editor-bundle--editor-target="status" role="status" aria-live="polite"></div>
                </div>
            HTML, trim($html));

        self::assertSame('<label id="body_label" for="body" class="required">Body</label>', trim($twig->createTemplate('{{ form_label(form) }}')->render(['form' => $form->createView()])));
        self::assertSame('<label id="mine" for="body" class="required">Body</label>', trim($twig->createTemplate('{{ form_label(form) }}')->render(['form' => $forms->createNamed('body', BlockEditorType::class, null, ['label' => 'Body', 'label_attr' => ['id' => 'mine']])->createView()])));

        $readOnly = $twig->render('form.html.twig', ['form' => $forms->createNamed('body', BlockEditorType::class, null, ['read_only' => true, 'label' => false])->createView()]);
        self::assertStringContainsString('class="phil-block-editor phil-block-editor--read-only"', $readOnly);
        self::assertStringContainsString('target="canvas"></div>', $readOnly, 'no label to name it');
    }

    public function testTheSearchAndUploadEndpointsAnswerTheEditor(): void
    {
        $client = $this->signedIn();

        $this->request($client, 'GET', '/_block-editor/mentions?q=a');
        self::assertResponseIsSuccessful();
        self::assertSame('{"items":[{"reference":"user:ada","label":"Ada Lovelace","url":"\/people\/ada","hint":null},{"reference":"user:alan","label":"Alan Turing","url":"\/people\/alan","hint":null}]}', $client->getResponse()->getContent());

        $this->request($client, 'GET', '/_block-editor/links?q=a');
        self::assertResponseStatusCodeSame(404);

        $this->request($client, 'GET', '/_block-editor/mentions?q=a', ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403);

        $file = (string) tempnam(sys_get_temp_dir(), 'pbe');
        file_put_contents($file, 'PNG!');
        $this->request($client, 'POST', '/_block-editor/image', files: ['image' => new UploadedFile($file, 'cat.png', 'image/png', null, true)]);
        self::assertResponseIsSuccessful();
        self::assertSame('{"success":1,"file":{"url":"\/media\/cat.png","width":2,"height":1}}', $client->getResponse()->getContent());
        self::assertArrayNotHasKey('csrf-token_'.self::TOKEN, $client->getCookieJar()->allValues('http://localhost/'), 'the token cookie is cleared once checked');

        file_put_contents($file, 'PNG!');
        $this->request($client, 'POST', '/_block-editor/image', files: ['image' => new UploadedFile($file, 'refused.png', 'image/png', null, true)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('{"success":0,"message":"Not this one."}', $client->getResponse()->getContent());
        @unlink($file);

        $client->request('GET', '/_block-editor/image');
        self::assertResponseStatusCodeSame(405);
    }

    public function testAnUploadWithoutItsCsrfTokenIsRefusedAndASearchNeedsNone(): void
    {
        $client = $this->signedIn();

        $client->request('GET', '/_block-editor/mentions?q=a', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1']);
        self::assertResponseIsSuccessful('a search carries no token');

        $client->getHistory()->clear();
        $client->request('POST', '/_block-editor/image', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1']);
        self::assertResponseStatusCodeSame(403, 'no token');

        $client->getHistory()->clear();
        $client->request('POST', '/_block-editor/image', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN]);
        self::assertResponseStatusCodeSame(403, 'a token without its cookie');

        $client->getHistory()->clear();
        $client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $client->request('POST', '/_block-editor/image', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1', 'HTTP_CSRF_TOKEN' => 'another-token-of-24-chars']);
        self::assertResponseStatusCodeSame(403, 'the cookie of another token');

        $client->getHistory()->clear();
        $client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $client->request('POST', '/_block-editor/image', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, 'HTTP_ORIGIN' => 'https://elsewhere.example']);
        self::assertResponseStatusCodeSame(403, 'another origin');

        $client->getHistory()->clear();
        $client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $client->request('POST', '/_block-editor/image', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN]);
        self::assertResponseStatusCodeSame(400, 'a token and its cookie reach the endpoint, which finds no image');
    }

    public function testAnAnonymousVisitorReachesNoEndpoint(): void
    {
        $client = self::createClient();
        self::assertInstanceOf(HttpKernelBrowser::class, $client);

        $this->request($client, 'GET', '/_block-editor/mentions?q=a');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"items":[]}', $client->getResponse()->getContent());

        $file = (string) tempnam(sys_get_temp_dir(), 'pbe');
        file_put_contents($file, 'PNG!');
        $this->request($client, 'POST', '/_block-editor/image', files: ['image' => new UploadedFile($file, 'cat.png', 'image/png', null, true)]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('{"success":0,"message":"You may not use the editor."}', $client->getResponse()->getContent());
        @unlink($file);
    }

    /**
     * A browser signed in as a member, as the editor's endpoints require by default.
     */
    private function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        self::assertInstanceOf(KernelBrowser::class, $client);
        $client->loginUser(new InMemoryUser('ada', 'not-used', ['ROLE_USER']));

        return $client;
    }

    /**
     * A request as the editor sends it: its header, and a new token in the header and the cookie of Symfony's stateless
     * CSRF check.
     *
     * @param array<string, string> $server
     * @param array<string, mixed>  $files
     */
    private function request(HttpKernelBrowser $client, string $method, string $uri, array $server = [], array $files = []): void
    {
        $client->getHistory()->clear();
        $client->getCookieJar()->set(new Cookie('csrf-token_'.self::TOKEN, 'csrf-token', null, '/', 'localhost'));
        $client->request($method, $uri, files: $files, server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1', 'HTTP_CSRF_TOKEN' => self::TOKEN, ...$server]);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
