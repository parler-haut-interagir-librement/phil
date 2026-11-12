<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Infrastructure;

use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Infrastructure\Form\RateLimitTypeExtension;
use Phil\RateLimitBundle\Tests\Unit\Fixture\Enforcers;
use Phil\RateLimitBundle\Tests\Unit\Fixture\RecordingEvents;
use Phil\RateLimitBundle\Tests\Unit\Fixture\ScriptedLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(RateLimitTypeExtension::class)]
final class RateLimitTypeExtensionTest extends TestCase
{
    public function testTheOptionLimitsTheSubmissionsOfTheForm(): void
    {
        $limiter = new ScriptedLimiter(2);
        $events = new RecordingEvents();
        $factory = $this->factory(['forms' => $limiter], events: $events);

        foreach ([true, true] as $valid) {
            self::assertSame($valid, $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => 'forms']))->isValid());
        }

        $form = $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => 'forms']));
        $error = $form->getErrors()[0];

        self::assertFalse($form->isValid());
        self::assertInstanceOf(FormError::class, $error);
        self::assertSame('Too many submissions of this form. Try again in 60 seconds.', $error->getMessage());
        self::assertSame(RateLimitTypeExtension::MESSAGE, $error->getMessageTemplate());
        self::assertSame(['%seconds%' => '60'], $error->getMessageParameters());
        self::assertInstanceOf(TooManyRequests::class, $error->getCause());
        self::assertSame([['alice|form:contact', 1], ['alice|form:contact', 1], ['alice|form:contact', 1]], $limiter->calls);
        self::assertInstanceOf(RateLimitExceeded::class, $events->events[0]);
        self::assertSame('contact', $events->events[0]->form);
    }

    public function testTheConfigurationLimitsAFormByName(): void
    {
        $limiter = new ScriptedLimiter(5);
        $factory = $this->factory(['forms' => $limiter], ['contact' => [['limiter' => 'forms', 'amount' => 2, 'key' => null]]]);

        $this->submit($factory->createNamed('contact', FormType::class));
        $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => true]));
        $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => new RateLimit('forms', 1)]));
        $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => false]));
        $this->submit($factory->createNamed('search', FormType::class));

        self::assertSame([['alice|form:contact', 2], ['alice|form:contact', 2], ['alice|form:contact', 1]], $limiter->calls);
    }

    public function testOnlyTheRootFormCountsAndOnlyOnARequest(): void
    {
        $limiter = new ScriptedLimiter(5);
        $factory = $this->factory(['forms' => $limiter], ['email' => [['limiter' => 'forms', 'amount' => null, 'key' => null]]]);

        $form = $factory->createNamedBuilder('contact')->add('email', TextType::class)->getForm();
        $form->submit(['email' => 'a@example.org']);

        self::assertSame([], $limiter->calls);

        $quiet = $this->factory(['forms' => $limiter], requests: new RequestStack());
        $quiet->createNamed('contact', FormType::class, null, ['rate_limit' => 'forms'])->submit([]);

        self::assertSame([], $limiter->calls);
    }

    public function testAFormWithoutRuleGetsNoListener(): void
    {
        $factory = $this->factory(['forms' => new ScriptedLimiter()], ['contact' => [['limiter' => 'forms', 'amount' => null, 'key' => null]]]);

        $none = \count($factory->createNamedBuilder('search')->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));

        self::assertCount($none, $factory->createNamedBuilder('contact', FormType::class, null, ['rate_limit' => false])->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));
        self::assertCount($none + 1, $factory->createNamedBuilder('contact')->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));
    }

    public function testTheErrorIsTranslated(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', require \dirname(__DIR__, 3).'/translations/phil_rate_limit.fr.php', 'fr', 'phil_rate_limit');

        $factory = $this->factory(['forms' => new ScriptedLimiter(1)], translator: $translator);

        $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => 'forms']));
        $form = $this->submit($factory->createNamed('contact', FormType::class, null, ['rate_limit' => 'forms']));

        self::assertSame('Ce formulaire a été envoyé trop souvent. Réessayez dans 60 secondes.', $form->getErrors()[0]->getMessage());
    }

    public function testTheOptionIsTyped(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory(['forms' => new ScriptedLimiter()])->createNamed('contact', FormType::class, null, ['rate_limit' => 3]);
    }

    public function testItExtendsEveryForm(): void
    {
        self::assertSame([FormType::class], [...RateLimitTypeExtension::getExtendedTypes()]);
    }

    /**
     * @param array<string, ScriptedLimiter> $limiters
     * @param array<string, mixed>           $forms
     */
    private function factory(array $limiters, array $forms = [], ?RecordingEvents $events = null, ?RequestStack $requests = null, ?Translator $translator = null): FormFactoryInterface
    {
        if (!$requests instanceof RequestStack) {
            $request = Request::create('/', 'POST');
            $request->headers->set('X-Account', 'alice');
            $requests = new RequestStack([$request]);
        }

        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new RateLimitTypeExtension(Enforcers::of($limiters, $events), $requests, $forms, $translator))
            ->getFormFactory();
    }

    /**
     * @template T of FormInterface
     *
     * @param T $form
     *
     * @return T
     */
    private function submit(FormInterface $form): FormInterface
    {
        $form->submit([]);

        return $form;
    }
}
