<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Functional;

use Locale;
use Override;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessengerBundle\Infrastructure\Form\HandleType;
use Phil\InstantMessengerBundle\Infrastructure\Validator\HandleOfType;
use Phil\InstantMessengerBundle\PhilInstantMessengerBundle;
use Phil\InstantMessengerBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

#[CoversClass(PhilInstantMessengerBundle::class)]
final class InstantMessengerBundleTest extends KernelTestCase
{
    private string $locale;

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

        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Locale::setDefault($this->locale);
    }

    public function testTheFieldTheConstraintAndTwigAreReady(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('messenger', HandleType::class, null, ['types' => [KnownMessenger::Signal, KnownMessenger::WhatsApp]]);
        $form->submit(['type' => 'Phil.InstantMessenger.KnownMessenger.WhatsApp', 'handle' => '+1 514 555-0123']);
        self::assertTrue($form->isValid());
        self::assertEquals(Handle::of(KnownMessenger::WhatsApp, '+15145550123'), $form->getData());

        $validator = $this->service('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);
        $violations = $validator->validate(Handle::of(KnownMessenger::Telegram, 'adalove'), new HandleOfType(KnownMessenger::Signal));
        self::assertSame('Cet identifiant n’est sur aucun des services acceptés : Signal.', $violations->get(0)->getMessage());

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertSame('<a href="https://wa.me/15145550123">WhatsApp +1 514-555-0123</a>', $twig->createTemplate('<a href="{{ handle|messenger_link }}">{{ handle|messenger_label }} {{ handle|messenger_display }}</a>')->render(['handle' => $form->getData()]));
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
