<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\ContactFormBundle\Application\ConfiguredRecipients;
use Phil\ContactFormBundle\Application\ContactMessage;
use Phil\ContactFormBundle\Infrastructure\Form\ContactFormType;
use Phil\ContactFormBundle\Infrastructure\Http\ContactFormController;
use Phil\ContactFormBundle\Infrastructure\Mailer\ContactMailer;
use Phil\ContactFormBundle\Tests\Unit\Fixtures\RecordingLogger;
use Phil\ContactFormBundle\Tests\Unit\Fixtures\RecordingMailer;
use Phil\ContactFormBundle\Tests\Unit\Fixtures\SpamProtectionOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ContactFormController::class)]
#[CoversClass(ContactFormType::class)]
#[CoversClass(ContactMessage::class)]
final class ContactFormControllerTest extends TestCase
{
    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testThePageShowsTheForm(): void
    {
        $response = $this->controller(new RecordingMailer())(Request::create('/contact'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('page:name,email,subject,message|sent:no|failed:no', $response->getContent());
    }

    public function testASentMessageIsSaidSo(): void
    {
        self::assertSame('page:name,email,subject,message|sent:yes|failed:no', $this->controller(new RecordingMailer())(Request::create('/contact?sent=1'))->getContent());
    }

    public function testAMessageIsSentThenThePageRedirected(): void
    {
        $mailer = new RecordingMailer();
        $request = Request::create('/index.php/contact', 'POST', ['contact_form' => ['name' => 'Ada', 'email' => 'ada@example.com', 'subject' => '', 'message' => 'A quote, please.']], server: ['SCRIPT_FILENAME' => '/index.php', 'SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '/index.php/contact']);
        $request->setLocale('fr');

        $response = $this->controller($mailer)($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/index.php/contact?sent=1', $response->headers->get('Location'));
        self::assertCount(1, $mailer->sent);
        self::assertSame([], $this->logger->logged);
    }

    public function testAnIncompleteMessageIsShownAgain(): void
    {
        $mailer = new RecordingMailer();
        $response = $this->controller($mailer)(Request::create('/contact', 'POST', ['contact_form' => ['name' => '', 'email' => 'ada', 'message' => 'Short']]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $mailer->sent);
    }

    #[DataProvider('addressesMimeRefuses')]
    public function testAnAddressTheMailerCannotWriteIsAWrongField(string $email): void
    {
        $mailer = new RecordingMailer();
        $response = $this->controller($mailer)(Request::create('/contact', 'POST', ['contact_form' => ['name' => 'Ada', 'email' => $email, 'message' => 'A quote, please.']]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([], $mailer->sent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressesMimeRefuses(): iterable
    {
        yield 'two dots in a row' => ['a..b@example.com'];
        yield 'a leading dot' => ['.a@example.com'];
        yield 'a trailing dot' => ['a.@example.com'];
    }

    public function testAnAddressOutsideAsciiIsSent(): void
    {
        $mailer = new RecordingMailer();
        $response = $this->controller($mailer)(Request::create('/contact', 'POST', ['contact_form' => ['name' => 'Zoé', 'email' => 'zoé@exämple.ca', 'message' => 'A quote, please.']]));

        self::assertSame(303, $response->getStatusCode());
        self::assertCount(1, $mailer->sent);
    }

    public function testATransportThatIsDownLeavesTheFormFilledAndIsLogged(): void
    {
        $response = $this->controller(new RecordingMailer(down: true))(Request::create('/contact', 'POST', ['contact_form' => ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'A quote, please.']]));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('page:name,email,subject,message|sent:no|failed:yes', $response->getContent());
        self::assertSame(['error A contact message could not be sent: {reason} Connection refused.'], $this->logger->logged);
    }

    public function testTheFormIsProtectedAndLabelled(): void
    {
        $form = $this->forms()->create(ContactFormType::class);

        self::assertSame(['scope' => 'contact_form', 'content_fields' => ['subject', 'message']], $form->getConfig()->getOption('spam_protection'));
        self::assertSame(['phil_contact_form', ContactMessage::class], [$form->getConfig()->getOption('translation_domain'), $form->getConfig()->getOption('data_class')]);
        self::assertSame(['contact_form.name', ['autocomplete' => 'name', 'maxlength' => 100]], [$form->get('name')->getConfig()->getOption('label'), $form->get('name')->getConfig()->getOption('attr')]);
        self::assertSame(['contact_form.email', ['autocomplete' => 'email', 'maxlength' => 254]], [$form->get('email')->getConfig()->getOption('label'), $form->get('email')->getConfig()->getOption('attr')]);
        self::assertSame(['contact_form.subject', false, ['maxlength' => 150]], [$form->get('subject')->getConfig()->getOption('label'), $form->get('subject')->getConfig()->getOption('required'), $form->get('subject')->getConfig()->getOption('attr')]);
        self::assertSame(['contact_form.message', ['rows' => 8, 'maxlength' => 5000]], [$form->get('message')->getConfig()->getOption('label'), $form->get('message')->getConfig()->getOption('attr')]);
    }

    public function testTheMessageIsChecked(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $message = new ContactMessage();
        $message->name = str_repeat('a', 101);
        $message->email = 'ada@example';
        $message->subject = str_repeat('a', 151);
        $message->message = str_repeat('a', 5001);

        self::assertSame(['name', 'email', 'subject', 'message'], array_values(array_unique(array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate($message))))));

        $message->name = str_repeat('a', 100);
        $message->email = str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 59).'.com';
        $message->subject = str_repeat('a', 150);
        $message->message = str_repeat('a', 10);
        self::assertCount(0, $validator->validate($message));

        $message->message = str_repeat('a', 9);
        $message->email = str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 60).'.com';
        self::assertSame(['email', 'message'], array_values(array_unique(array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate($message))))));
        self::assertSame(['name', 'email', 'message'], array_map(static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath(), iterator_to_array($validator->validate(new ContactMessage()))));
    }

    private function controller(RecordingMailer $mailer): ContactFormController
    {
        $twig = new Environment(new ArrayLoader(['page.html.twig' => "page:{{ form.children|keys|join(',') }}|sent:{{ sent ? 'yes' : 'no' }}|failed:{{ failed ? 'yes' : 'no' }}"]));

        return new ContactFormController($this->forms(), new ContactMailer($mailer, new ConfiguredRecipients(['info@example.com']), 'contact@example.com'), $twig, $this->logger, 'page.html.twig');
    }

    private function forms(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()))
            ->addTypeExtension(new SpamProtectionOption())
            ->getFormFactory();
    }
}
