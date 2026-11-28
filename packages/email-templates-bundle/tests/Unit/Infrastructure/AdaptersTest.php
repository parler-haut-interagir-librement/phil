<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure;

use GdImage;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Delivery;
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedParts;
use Phil\EmailTemplatesBundle\Application\Rendering\Theme;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Audit\AuditTemplateChanges;
use Phil\EmailTemplatesBundle\Infrastructure\Filesystem\DirectoryEmbedLoader;
use Phil\EmailTemplatesBundle\Infrastructure\Markup\MarkupMarkdown;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmail;
use Phil\EmailTemplatesBundle\Infrastructure\Messenger\SendTemplatedEmailHandler;
use Phil\EmailTemplatesBundle\Infrastructure\Tenancy\TenancySiteScope;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\AdminExtension;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\CssInlinerStyles;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\KitComponents;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\NoStyleInliner;
use Phil\EmailTemplatesBundle\Infrastructure\Twig\TwigLayout;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\Tests\Fixtures\AuditRows;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(DirectoryEmbedLoader::class)]
#[CoversClass(YamlDefaultTemplates::class)]
#[CoversClass(CssInlinerStyles::class)]
#[CoversClass(NoStyleInliner::class)]
#[CoversClass(TwigLayout::class)]
#[CoversClass(MarkupMarkdown::class)]
#[CoversClass(TenancySiteScope::class)]
#[CoversClass(AuditTemplateChanges::class)]
#[CoversClass(SendTemplatedEmail::class)]
#[CoversClass(SendTemplatedEmailHandler::class)]
#[CoversClass(Delivery::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(AdminExtension::class)]
final class AdaptersTest extends TestCase
{
    public function testImagesAreReadFromTheirDirectoryByTheirFirstBytes(): void
    {
        $loader = new DirectoryEmbedLoader(Stage::IMAGES);

        self::assertSame('image/png', $loader->load('logo', 'logo.png')->contentType);
        self::assertSame('image/gif', $loader->load('dot', 'dot.gif')->contentType);
        self::assertSame("\xFF\xD8\xFF", substr($this->jpeg($loader), 0, 3));
    }

    public function testAnImageOutsideTheDirectoryOrNotAnImage(): void
    {
        $loader = new DirectoryEmbedLoader(Stage::IMAGES);
        $failures = [];

        foreach (['../defaults/plain.en.yaml', 'missing.png', '.', 'fake.png'] as $path) {
            try {
                $loader->load('x', $path);
            } catch (RenderingFailed $renderingFailed) {
                $failures[] = $renderingFailed->getMessage();
            }
        }

        self::assertSame([
            'The image "x" is not a file of the images directory.',
            'The image "x" is not a file of the images directory.',
            'The image "x" is not a file of the images directory.',
            'The image "x" is not a PNG, a JPEG or a GIF.',
        ], $failures);

        try {
            new DirectoryEmbedLoader(Stage::IMAGES.'/nowhere')->load('x', 'logo.png');
            self::fail('A directory that does not exist has no image.');
        } catch (RenderingFailed $renderingFailed) {
            self::assertSame('The image "x" is not a file of the images directory.', $renderingFailed->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The image "x" is larger than 0 KiB.');
        new DirectoryEmbedLoader(Stage::IMAGES, 10)->load('x', 'logo.png');
    }

    public function testTheDefaultFilesOfTheApplicationComeFirst(): void
    {
        $directory = sys_get_temp_dir().'/phil-email-defaults-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/plain.en.yaml', "subject: 'Mine'\nbody: '<p>x</p>'\n");
        file_put_contents($directory.'/ignored.txt', 'x');
        file_put_contents($directory.'/Bad.en.yaml', 'x');
        mkdir($directory.'/sub.en.yaml');
        $defaults = new YamlDefaultTemplates([$directory, '/nowhere', Stage::DEFAULTS]);

        self::assertSame('Mine', $defaults->find(TemplateKey::fromString('plain'), Locale::fromString('en'))?->subject);
        self::assertSame(['plain' => ['en'], 'welcome' => ['en', 'fr']], $defaults->catalog());
        self::assertNull($defaults->find(TemplateKey::fromString('plain'), Locale::fromString('fr')));
        self::assertNull($defaults->find(TemplateKey::fromString('nothing'), Locale::fromString('en')));
        self::assertSame($defaults->find(TemplateKey::fromString('welcome'), Locale::fromString('en')), $defaults->find(TemplateKey::fromString('welcome'), Locale::fromString('en')), 'A file is read once.');
    }

    public function testADefaultFileThatIsNotATemplate(): void
    {
        $directory = sys_get_temp_dir().'/phil-email-defaults-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/yaml.en.yaml', "subject: [\n");
        file_put_contents($directory.'/scalar.en.yaml', 'just text');
        file_put_contents($directory.'/shape.en.yaml', "subject: 'a'\nbody: 'b'\nformat: mjml\n");
        file_put_contents($directory.'/large.en.yml', str_repeat('a', 1_048_577));
        $defaults = new YamlDefaultTemplates([$directory]);
        $errors = [];

        foreach (['yaml', 'scalar', 'shape', 'large'] as $key) {
            try {
                $defaults->find(TemplateKey::fromString($key), Locale::fromString('en'));
            } catch (InvalidTemplate $invalidTemplate) {
                $errors[$key] = $invalidTemplate->errors[0];
            }
        }

        self::assertStringStartsWith('The default template "yaml.en.yaml" is not valid YAML: ', $errors['yaml'] ?? '');
        self::assertSame('The default template "scalar.en.yaml" is a map of its fields.', $errors['scalar'] ?? '');
        self::assertSame('shape.en.yaml: The format is "html" or "markdown".', $errors['shape'] ?? '');
        self::assertSame('The default template "large.en.yml" is larger than 1024 KiB.', $errors['large'] ?? '');
    }

    public function testEveryDefaultOfTheBundlePassesTheChecksOfASave(): void
    {
        $stage = new Stage();
        $defaults = new YamlDefaultTemplates([__DIR__.'/../../../defaults']);
        $checked = [];

        foreach ($defaults->catalog() as $key => $locales) {
            foreach ($locales as $locale) {
                $definition = $defaults->find(TemplateKey::fromString($key), Locale::fromString($locale));
                self::assertNotNull($definition);
                $stage->validator->validate(TemplateKey::fromString($key), Site::shared(), Locale::fromString($locale), $definition);
                $checked[] = $key.'.'.$locale;
            }
        }

        self::assertSame(['cancellation.en', 'cancellation.fr', 'confirmation.en', 'confirmation.fr', 'ticket.en', 'ticket.fr'], $checked);
    }

    public function testTheInlinersAndTheLayout(): void
    {
        self::assertSame('<p>x</p>', new NoStyleInliner()->inline('<p>x</p>'));
        $inlined = new CssInlinerStyles()->inline('<!doctype html><html><head><style>p{color:red} @media (prefers-color-scheme: dark){p{color:blue !important}}</style></head><body><p style="margin:0">é &amp; ’</p></body></html>');
        self::assertStringContainsString('<p style="color: red; margin: 0;">é &amp; ’</p>', $inlined);
        self::assertStringContainsString('@media (prefers-color-scheme: dark){p{color:blue !important}}', $inlined);

        $theme = Theme::create(['primary' => '#000'], ['primary' => '#fff'], 'Georgia, serif');
        $html = Stage::layout($theme, brand: ['name' => 'Brand & Co', 'logo' => null])->wrap(new RenderedParts('Subject <1>', 'Pre & header', '<p>Body</p>'), new RenderContext(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('fr_CA')));
        self::assertStringContainsString('<html lang="fr-CA"', $html);
        self::assertStringContainsString('<title>Subject &lt;1&gt;</title>', $html);
        self::assertStringContainsString('>Pre &amp; header&#847;', $html);
        self::assertStringContainsString('Brand &amp; Co', $html);
        self::assertStringContainsString('font-family: Georgia, serif;', $html);
        self::assertStringContainsString('.phil-email-button td { background-color: #fff !important; }', $html);
        self::assertStringContainsString('<p>Body</p>', $html);
        $logo = Stage::layout($theme, brand: ['name' => 'B', 'logo' => 'https://example.com/l.png'])->wrap(new RenderedParts('S', '', '<p>B</p>'), new RenderContext(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en')));
        self::assertStringContainsString('<img src="https://example.com/l.png" alt="B" height="40"', $logo);
        self::assertStringNotContainsString('data-text="skip"', $logo, 'No preheader, no hidden block.');
        self::assertStringNotContainsString('phil-email-brand" style', Stage::layout($theme)->wrap(new RenderedParts('S', '', '<p>B</p>'), new RenderContext(TemplateKey::fromString('t'), Site::shared(), Locale::fromString('en'))));
    }

    public function testTheSiteIsTheActiveTenant(): void
    {
        $context = new TenantContext();
        $scope = new TenancySiteScope($context);

        self::assertTrue($scope->current()->isShared());
        $context->enter(TenantId::fromString('acme'));
        self::assertSame('acme', $scope->current()->value);
    }

    public function testEachChangeIsWrittenToTheAuditTrail(): void
    {
        $rows = new AuditRows();
        $audit = new AuditTemplateChanges(new AuditTrail($rows, $rows, new MockClock('2026-11-18 10:00:00 UTC')));
        $audit->saved(new TemplateSaved('ticket', 'acme', 'fr', 3, 7, 'editor-1'));
        $audit->saved(new TemplateSaved('ticket', '', 'fr', 4, 8, null, 2));
        $audit->published(new TemplatePublished('ticket', 'acme', 'fr', 4, 'editor-2'));
        $audit->discarded(new TemplateDraftDiscarded('ticket', '', 'fr', 5));
        $audit->reset(new TemplateReset('ticket', 'acme', 'fr', 'editor-1'));

        $written = $rows->rows['email_templates'];
        self::assertCount(5, $written);
        self::assertSame(['email_templates.saved', 'editor-1', 'email_template', 'ticket', 'acme'], [$written[0]->facts->action, $written[0]->facts->actor, $written[0]->facts->subject?->type, $written[0]->facts->subject?->id, $written[0]->facts->tenant]);
        self::assertSame('{"site":"acme","locale":"fr","version":3,"revision":7,"reverted_from":null}', $written[0]->payload);
        self::assertSame(['email_templates.reverted', null, null], [$written[1]->facts->action, $written[1]->facts->actor, $written[1]->facts->tenant]);
        self::assertSame('{"site":"","locale":"fr","version":4,"revision":8,"reverted_from":2}', $written[1]->payload);
        self::assertSame(['email_templates.published', 'editor-2', 'email_template', 'ticket', 'acme'], [$written[2]->facts->action, $written[2]->facts->actor, $written[2]->facts->subject?->type, $written[2]->facts->subject?->id, $written[2]->facts->tenant]);
        self::assertSame('{"site":"acme","locale":"fr","version":4}', $written[2]->payload);
        self::assertSame(['email_templates.discarded', null, 'email_template', 'ticket', null], [$written[3]->facts->action, $written[3]->facts->actor, $written[3]->facts->subject?->type, $written[3]->facts->subject?->id, $written[3]->facts->tenant]);
        self::assertSame('{"site":"","locale":"fr","version":5}', $written[3]->payload);
        self::assertSame(['email_templates.reset', 'editor-1', 'email_template', 'ticket', 'acme'], [$written[4]->facts->action, $written[4]->facts->actor, $written[4]->facts->subject?->type, $written[4]->facts->subject?->id, $written[4]->facts->tenant]);
        self::assertSame('{"site":"acme","locale":"fr"}', $written[4]->payload);
        new AuditTemplateChanges(new AuditTrail($rows, $rows, new MockClock()), 'other')->reset(new TemplateReset('ticket', '', 'fr'));
        self::assertNull($rows->rows['other'][0]->facts->tenant);
    }

    public function testAMessageCarriesIdentifiersAndTheHandlerSends(): void
    {
        $stage = new Stage();
        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            /**
             * @return list<RawMessage>
             */
            public function sent(): array
            {
                return $this->sent;
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $resolver = new class implements DeliveryResolver {
            /** @var list<string> */
            public array $asked = [];

            public function resolve(string $template, string $reference): ?Delivery
            {
                $this->asked[] = $template.':'.$reference;

                return 'gone' === $reference ? null : new Delivery([new Address('ada@example.com')], ['name' => 'Ada'], 'fr', [], 'other');
            }
        };
        $handler = new SendTemplatedEmailHandler($resolver, new EmailComposer($stage->renderer), $mailer);
        $message = new SendTemplatedEmail('welcome', 'r-1', 'main');

        self::assertSame(['welcome', 'r-1', 'main'], [$message->template, $message->reference, $message->site]);
        $handler($message);
        $handler(new SendTemplatedEmail('welcome', 'gone'));

        self::assertSame(['welcome:r-1', 'welcome:gone'], $resolver->asked);
        self::assertCount(1, $mailer->sent);
        $email = $mailer->sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Bienvenue, Ada', $email->getSubject());
        self::assertSame('ada@example.com', $email->getTo()[0]->getAddress());

        $resolverWithoutSite = new class implements DeliveryResolver {
            public function resolve(string $template, string $reference): Delivery
            {
                return new Delivery([new Address('ada@example.com')], ['name' => 'Ada']);
            }
        };
        new SendTemplatedEmailHandler($resolverWithoutSite, new EmailComposer($stage->renderer), $mailer)(new SendTemplatedEmail('welcome', 'r-2'));
        self::assertCount(2, $mailer->sent());
        $delivery = $resolverWithoutSite->resolve('welcome', 'r-2');
        self::assertNull($delivery->locale);
        self::assertNull($delivery->site);
        self::assertSame([], $delivery->segments);
    }

    public function testTheRecipesOfTheKitAreNamedAfterTheirSubdirectory(): void
    {
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('ui:Button', new KitComponents('/ui/')->name('Button'));
        self::assertSame('ui:kit:Table:Row', new KitComponents('ui/kit')->name('Table:Row'));
        $function = new AdminExtension(new KitComponents('ui'))->getFunctions()[0];
        self::assertSame('phil_email_templates_kit', $function->getName());
        $callable = $function->getCallable();
        self::assertIsCallable($callable);
        self::assertSame('ui:Alert', $callable('Alert'));
    }

    public function testMarkdownIsPurified(): void
    {
        self::assertSame('<p><strong>a</strong></p>', trim(Stage::markdown()->toHtml('**a**<script>x</script>')));
    }

    private function jpeg(DirectoryEmbedLoader $loader): string
    {
        $file = Stage::IMAGES.'/photo.jpg';

        if (!is_file($file)) {
            $image = imagecreatetruecolor(1, 1);
            self::assertInstanceOf(GdImage::class, $image);
            imagejpeg($image, $file);
        }

        try {
            return $loader->load('photo', 'photo.jpg')->body;
        } finally {
            unlink($file);
        }
    }
}
