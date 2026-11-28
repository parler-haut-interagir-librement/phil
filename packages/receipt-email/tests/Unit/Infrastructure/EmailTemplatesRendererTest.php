<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\Contract\EmbedLoader;
use Phil\EmailTemplatesBundle\Application\Rendering\EmbeddedImage;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\ReceiptEmail\Application\InlineImage;
use Phil\ReceiptEmail\Application\ReceiptMessage;
use Phil\ReceiptEmail\Application\Recipient;
use Phil\ReceiptEmail\Application\RenderedReceipt;
use Phil\ReceiptEmail\Application\TransactionalCheck;
use Phil\ReceiptEmail\Infrastructure\Commerce\CommercePaymentReceipts;
use Phil\ReceiptEmail\Infrastructure\EmailTemplates\EmailTemplatesRenderer;
use Phil\ReceiptEmail\Tests\Support\Commerce;
use Phil\ReceiptEmail\Tests\Support\Donations;
use Phil\ReceiptEmail\Tests\Support\Templates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Address;

#[CoversClass(EmailTemplatesRenderer::class)]
final class EmailTemplatesRendererTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function defaults(): iterable
    {
        foreach ([ReceiptMessage::PAYMENT, ReceiptMessage::DONATION] as $key) {
            foreach (['en', 'fr'] as $locale) {
                yield $key.'.'.$locale => [$key, $locale];
            }
        }
    }

    /**
     * Each default template passes the checks a site's save and publication run, and renders its samples as a
     * transactional message.
     */
    #[DataProvider('defaults')]
    public function testEachDefaultTemplateIsValid(string $key, string $locale): void
    {
        $definition = Templates::defaults()->find(TemplateKey::fromString($key), Locale::fromString($locale));
        self::assertNotNull($definition);
        self::assertNotNull($definition->text, 'The template writes its own plain-text part.');

        Templates::validator()->validate(TemplateKey::fromString($key), Site::fromString('main'), Locale::fromString($locale), $definition);
        self::assertSame([], $definition->embeds);
    }

    public function testAPaymentReceiptIsRenderedInEnglish(): void
    {
        $rendered = $this->render(ReceiptMessage::payment(CommercePaymentReceipts::read(Commerce::receipt()), $this->recipient('en')));

        self::assertSame('Receipt R-000042 from The Shop', $rendered->subject);
        self::assertSame('Receipt R-000042 for order A-1001: 56.74 CAD.', $rendered->preheader);
        self::assertSame([], $rendered->images);

        foreach (['Tea: 3 × 5.00 = 15.00', '  Taxes: GST 0.75, QST 1.50', 'Teapot: 1 × 30.00 = 30.00', '  Colour: Blue', '  Glaze: drawn after payment', 'Subtotal: 45.00', 'Delivery: 10.00', 'Welcome: -5.00', 'GST: 2.25', 'QST: 4.49', 'Total: 56.74 CAD', 'GST 123456789 RT0001'] as $line) {
            self::assertStringContainsString("\n".$line."\n", $rendered->text);
        }

        self::assertStringContainsString('Hello Ada, here is the receipt of order A-1001, issued on November 20, 2026.', $rendered->text);
        self::assertStringContainsString('<td align="right" style="padding:8px 0;font-weight:700;border-top:1px solid #d0d0d0;">56.74 CAD</td>', $rendered->html);
        self::assertStringContainsString('Colour: Blue · Glaze: drawn after payment<br>Taxes: GST 1.50, QST 2.99', $rendered->html);
        self::assertStringContainsString('eeeeee;">Taxes: GST 0.75, QST 1.50</td>', $rendered->html, 'A line without options has no break before its taxes.');
    }

    public function testAPaymentReceiptIsRenderedInFrench(): void
    {
        $rendered = $this->render(ReceiptMessage::payment(CommercePaymentReceipts::read(Commerce::receipt(taxIncluded: true)), $this->recipient('fr')));

        self::assertSame('Reçu R-000042 de The Shop', $rendered->subject);
        self::assertSame('Reçu R-000042 de la commande A-1001 : 50,00 CAD.', $rendered->preheader);

        foreach (['Tea : 3 × 5,00 = 15,00', '  Taxes : GST 0,75 ; QST 1,50', '  Glaze : tiré après le paiement', 'Sous-total : 45,00', 'Total : 50,00 CAD', 'GST incluse : 2,25', 'QST incluse : 4,49', 'Taxes incluses : 6,74'] as $line) {
            self::assertStringContainsString("\n".$line."\n", $rendered->text);
        }

        self::assertStringContainsString('délivré le 2026-11-20', $rendered->text);
        self::assertStringNotContainsString("\nGST : ", $rendered->text, 'A tax the prices included is not added.');
    }

    public function testACreditNoteSaysSo(): void
    {
        $rendered = $this->render(ReceiptMessage::payment(CommercePaymentReceipts::read(Commerce::creditNote()), $this->recipient('en')));

        self::assertSame('Credit note C-000007 from The Shop', $rendered->subject);
        self::assertStringContainsString('the credit note of order A-1001, crediting receipt R-000042', $rendered->text);
        self::assertStringContainsString("\nTotal: -56.74 CAD\n", $rendered->text);
    }

    public function testADonationReceiptCarriesTheStatementsOfAnOfficialReceipt(): void
    {
        $rendered = $this->render(ReceiptMessage::donation(Donations::receipt(), $this->recipient('en')));

        self::assertSame('Official donation receipt D-2026-000017 from The Reading Circle', $rendered->subject);

        foreach ([
            'Official receipt for income tax purposes',
            'The Reading Circle',
            '8 Library Lane',
            'Registration number: 123456789 RR 0001',
            'Serial number: D-2026-000017',
            'Date issued: 2026-11-20',
            'Location issued: Montréal',
            'Date gift received: 2026-11-18',
            'Donor: Ada B. Lovelace',
            '1 Analytical Road',
            'Amount of the gift: 150.00 CAD',
            'Amount of the advantage: 40.00 CAD',
            'Advantage: A ticket to the gala dinner',
            'Eligible amount of the gift: 110.00 CAD',
            'Authorized signature: Grace Hopper, treasurer',
            'Canada Revenue Agency: canada.ca/charities-giving',
        ] as $statement) {
            self::assertStringContainsString($statement, $rendered->text);
            self::assertStringContainsString(htmlspecialchars(explode(': ', $statement)[0], \ENT_QUOTES), $rendered->html);
        }
    }

    public function testAGiftOfPropertyIsDescribedInFrench(): void
    {
        $rendered = $this->render(ReceiptMessage::donation(Donations::receipt(Donations::property()), $this->recipient('fr')));

        foreach ([
            'Reçu officiel aux fins de l’impôt sur le revenu',
            'Année de réception du don : 2026',
            'Description du don : A first edition of a book on the analytical engine',
            'Évaluateur : Charles Babbage, appraiser, 2 Difference Street, Laval QC  H7N 1A1',
            'Montant de l’avantage : 0,00 CAD',
            'Montant admissible du don : 1200,00 CAD',
            'Agence du revenu du Canada : canada.ca/charities-giving',
        ] as $statement) {
            self::assertStringContainsString($statement, $rendered->text);
        }

        self::assertStringNotContainsString('Avantage :', $rendered->text);
    }

    /**
     * The renderer's scope is the site `main`; a renderer given the site `other` renders the template it published.
     */
    public function testTheSiteGivenIsRendered(): void
    {
        $key = TemplateKey::fromString(ReceiptMessage::DONATION);
        $locale = Locale::fromString('en');
        $other = Site::fromString('other');
        $definition = Templates::defaults()->find($key, $locale);
        self::assertNotNull($definition);
        $store = new InMemoryTemplateStore();
        $store->saveDraft($key, $other, $locale, TemplateDefinition::fromArray(['subject' => 'Your receipt from the other site'] + $definition->toArray()), new DateTimeImmutable('2026-11-20'), null, 0);
        $store->publish($key, $other, $locale);

        $message = ReceiptMessage::donation(Donations::receipt(), $this->recipient('en'));

        self::assertSame('Your receipt from the other site', new EmailTemplatesRenderer(Templates::renderer(store: $store), $other)->render($message)->subject);
        self::assertSame('Official donation receipt D-2026-000017 from The Reading Circle', new EmailTemplatesRenderer(Templates::renderer(store: $store))->render($message)->subject);
        self::assertSame(realpath(__DIR__.'/../../../email-templates'), realpath(EmailTemplatesRenderer::DEFAULTS));
    }

    public function testTheImagesATemplateEmbedsAreInlineImagesOfTheReceipt(): void
    {
        $key = TemplateKey::fromString(ReceiptMessage::DONATION);
        $locale = Locale::fromString('en');
        $definition = Templates::defaults()->find($key, $locale);
        self::assertNotNull($definition);
        $store = new InMemoryTemplateStore();
        $store->saveDraft($key, Site::fromString('main'), $locale, TemplateDefinition::fromArray(['embeds' => ['logo' => 'logo.png']] + $definition->toArray()), new DateTimeImmutable('2026-11-20'), null, 0);
        $store->publish($key, Site::fromString('main'), $locale);

        $loader = new class implements EmbedLoader {
            public function load(string $name, string $path): EmbeddedImage
            {
                return new EmbeddedImage($name, 'PNG of '.$path, 'image/png');
            }
        };

        $rendered = new EmailTemplatesRenderer(Templates::renderer(store: $store, embeds: $loader))->render(ReceiptMessage::donation(Donations::receipt(), $this->recipient('en')));

        self::assertEquals([new InlineImage('logo', 'PNG of logo.png', 'image/png')], $rendered->images);
    }

    private function render(ReceiptMessage $message): RenderedReceipt
    {
        $rendered = new EmailTemplatesRenderer(Templates::renderer())->render($message);
        new TransactionalCheck()->check($rendered);

        return $rendered;
    }

    private function recipient(string $locale): Recipient
    {
        return new Recipient(new Address('ada@example.com', 'Ada'), $locale);
    }
}
