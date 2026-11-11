<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Functional;

use Override;
use Phil\ContactFormBundle\PhilContactFormBundle;
use Phil\ContactFormBundle\Tests\Functional\App\SentMessages;
use Phil\ContactFormBundle\Tests\Functional\App\TestKernel;
use Phil\SpamProtection\Proof\ProofOfWork;
use Phil\SpamProtectionBundle\Infrastructure\Form\SpamProtectionTypeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The contact page in a real kernel: rendered in French with its protection, submitted as a person's browser would
 * — the proof of work solved —, the message sent and the page redirected.
 */
#[CoversClass(PhilContactFormBundle::class)]
final class ContactFormBundleTest extends KernelTestCase
{
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

        self::bootKernel();
    }

    public function testThePageIsInFrenchAndProtected(): void
    {
        $html = (string) $this->handle(Request::create('/contact/'))->getContent();

        self::assertStringContainsString('<title>Écrivez-nous</title>', $html);
        self::assertStringContainsString('Votre adresse courriel', $html);
        self::assertStringContainsString('name="contact_form['.SpamProtectionTypeExtension::TOKEN.']"', $html);
        self::assertStringContainsString('>Envoyer</button>', $html);
    }

    public function testAMessageIsSentAndThePageSaysSo(): void
    {
        $html = (string) $this->handle(Request::create('/contact/'))->getContent();
        $response = $this->handle(Request::create('/contact/', 'POST', ['contact_form' => [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Une soumission',
            'message' => 'Une soumission pour douze exemplaires, s’il vous plaît.',
            SpamProtectionTypeExtension::HONEYPOT => '',
            SpamProtectionTypeExtension::TOKEN => $this->attribute($html, 'name="contact_form['.SpamProtectionTypeExtension::TOKEN.']"', 'value'),
            SpamProtectionTypeExtension::PROOF => $this->solve($html),
        ]], server: ['REMOTE_ADDR' => '198.51.100.4']));

        self::assertSame(303, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('/contact/?sent=1', $response->headers->get('Location'));

        $sent = $this->service(SentMessages::class);
        self::assertInstanceOf(SentMessages::class, $sent);
        $email = $sent->messages[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame('[Contact] Une soumission', $email->getSubject());
        self::assertStringContainsString('Un message de Ada Lovelace <ada@example.com>', (string) $email->getTextBody());
        self::assertStringContainsString('Objet : Une soumission', (string) $email->getHtmlBody());

        self::assertStringContainsString('Merci : votre message a été envoyé.', (string) $this->handle(Request::create('/contact/?sent=1'))->getContent());
    }

    public function testASubmissionWithoutProtectionIsHeld(): void
    {
        $response = $this->handle(Request::create('/contact/', 'POST', ['contact_form' => ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things here, now.']]));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Cet envoi n’a pas pu être accepté.', (string) $response->getContent());
    }

    private function attribute(string $html, string $marker, string $attribute): string
    {
        self::assertSame(1, preg_match('/<[^>]*'.preg_quote($marker, '/').'[^>]*>/', $html, $tag));
        self::assertSame(1, preg_match('/'.preg_quote($attribute, '/').'="(?<value>[^"]*)"/', $tag[0], $value));

        return html_entity_decode($value['value']);
    }

    private function solve(string $html): string
    {
        $marker = 'name="contact_form['.SpamProtectionTypeExtension::PROOF.']"';
        $challenge = $this->attribute($html, $marker, 'data-'.SpamProtectionTypeExtension::CONTROLLER.'-challenge-value');
        $proof = new ProofOfWork((int) $this->attribute($html, $marker, 'data-'.SpamProtectionTypeExtension::CONTROLLER.'-difficulty-value'));

        $solution = 0;

        while (!$proof->verify($challenge, (string) $solution)) {
            ++$solution;
        }

        return (string) $solution;
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }

    private function handle(Request $request): Response
    {
        $kernel = self::$kernel;
        self::assertInstanceOf(HttpKernelInterface::class, $kernel);

        return $kernel->handle($request);
    }
}
