<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Exception;
use Phil\IncidentMode\Duration;
use Phil\IncidentMode\Presets;
use Phil\IncidentMode\Restriction;
use Phil\IncidentModeBundle\Application\Exception\SuspendedByIncident;
use Phil\IncidentModeBundle\Application\Suspension;
use Phil\IncidentModeBundle\Infrastructure\Form\SuspendedDuringTypeExtension;
use Phil\IncidentModeBundle\Infrastructure\Http\CheckpointListener;
use Phil\IncidentModeBundle\Infrastructure\Http\SuspensionPage;
use Phil\IncidentModeBundle\Infrastructure\Translation\IncidentWording;
use Phil\IncidentModeBundle\Infrastructure\Twig\IncidentModeExtension;
use Phil\IncidentModeBundle\Infrastructure\Twig\KitComponents;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Pages;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Stage;
use Phil\IncidentModeBundle\Tests\Unit\Fixture\Words;
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
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(CheckpointListener::class)]
#[CoversClass(SuspensionPage::class)]
#[CoversClass(SuspendedDuringTypeExtension::class)]
#[CoversClass(IncidentModeExtension::class)]
final class CheckpointsTest extends TestCase
{
    public function testAControllerWithoutRestrictionsPasses(): void
    {
        $stage = new Stage();
        $stage->start(Presets::READ_ONLY, '30m');

        $event = $this->event(static fn (): string => 'free', new Request());
        new CheckpointListener($stage->checkpoint)->onKernelController($event);
        self::assertSame('free', ($event->getController())());
    }

    public function testTheRestrictionsOfTheAttributesAndOfTheRouteAreChecked(): void
    {
        $stage = new Stage();
        $listener = new CheckpointListener($stage->checkpoint);
        $stage->start(Presets::SUSPEND_INVITATIONS, '1h');

        $listener->onKernelController($this->event(new Pages()->signUp(...), Request::create('/', 'POST')));
        $listener->onKernelController($this->event(new Pages()->signUp(...), $this->routed(['', 7, 'signups'])));

        foreach ([$this->routed('invitations'), $this->routed(['signups', 'invitations']), Request::create('/', 'PUT')] as $request) {
            try {
                $listener->onKernelController($this->event(new Pages()->invite(...), $request));
                self::fail('Invitations are refused.');
            } catch (SuspendedByIncident $suspended) {
                self::assertSame(Restriction::INVITATIONS, $suspended->suspension->restriction);
            }
        }

        $listener->onKernelController($this->event(new Pages()->invite(...), Request::create('/', 'GET')));
    }

    public function testTheFirstRestrictionNamedThatAppliesIsTheOneRefused(): void
    {
        $stage = new Stage();
        $stage->start(Presets::SUSPEND_CONTRIBUTIONS, '1h');

        $request = $this->routed(['new-comments', 'signups']);
        $request->setMethod('POST');

        try {
            new CheckpointListener($stage->checkpoint)->onKernelController($this->event(new Pages()->signUp(...), $request));
            self::fail('The sign-up is refused.');
        } catch (SuspendedByIncident $suspendedByIncident) {
            self::assertSame(Restriction::SIGNUPS, $suspendedByIncident->suspension->restriction);
        }
    }

    public function testEveryRestrictionOfEveryAttributeIsChecked(): void
    {
        $stage = new Stage();
        $listener = new CheckpointListener($stage->checkpoint);

        $stage->start(Presets::SUSPEND_INVITATIONS, '1h');

        try {
            $listener->onKernelController($this->event(new Pages()->both(...), new Request()));
            self::fail('The second restriction of the first attribute is refused.');
        } catch (SuspendedByIncident $suspendedByIncident) {
            self::assertSame(Restriction::INVITATIONS, $suspendedByIncident->suspension->restriction);
        }

        $stage = new Stage();
        $stage->start(Presets::SUSPEND_SIGNUPS, '1h');

        try {
            new CheckpointListener($stage->checkpoint)->onKernelController($this->event(new Pages()->both(...), new Request()));
            self::fail('The first attribute is refused.');
        } catch (SuspendedByIncident $suspendedByIncident) {
            self::assertSame(Restriction::SIGNUPS, $suspendedByIncident->suspension->restriction);
        }
    }

    public function testTheRefusalPageSaysWhatIsSuspendedAndWhenToComeBack(): void
    {
        $stage = new Stage();
        $twig = new Environment(new ArrayLoader(['@PhilIncidentMode/suspended.html.twig' => '{{ layout }}|{{ suspension.restriction }}|{{ retry_after }}']));
        $refusal = new SuspendedByIncident(new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 12:00 UTC')), 423, 90);

        $response = new SuspensionPage($stage->checkpoint, $twig, 'base.html.twig')->refusal($refusal);

        self::assertSame('base.html.twig|signups|90', $response->getContent());
        self::assertSame(423, $response->getStatusCode());
        self::assertSame('90', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testTheRefusalBecomesThePageWhenThereIsOne(): void
    {
        $stage = new Stage();
        $page = new SuspensionPage($stage->checkpoint, new Environment(new ArrayLoader(['@PhilIncidentMode/suspended.html.twig' => 'Suspended'])), 'layout.html.twig');
        $listener = new CheckpointListener($stage->checkpoint, $page);

        $event = $this->failure(new Exception('Else'));
        $listener->onKernelException($event);
        self::assertNull($event->getResponse());

        $event = $this->failure(new SuspendedByIncident(new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 12:00 UTC')), 429, 60));
        $listener->onKernelException($event);
        self::assertSame(429, $event->getResponse()?->getStatusCode());
        self::assertSame('Suspended', $event->getResponse()->getContent());
    }

    public function testOnlyARefusalBecomesThePage(): void
    {
        $stage = new Stage();
        $listener = new CheckpointListener($stage->checkpoint);
        $refusal = new SuspendedByIncident(new Suspension(Restriction::SIGNUPS, new DateTimeImmutable('2026-11-17 12:00 UTC')), 503, 60);

        $event = $this->failure($refusal);
        $listener->onKernelException($event);
        self::assertNull($event->getResponse());

        $event = $this->failure(new Exception('Else'));
        $listener->onKernelException($event);
        self::assertNull($event->getResponse());
    }

    public function testAFormIsInvalidWhileARestrictionItNamesApplies(): void
    {
        $stage = new Stage();
        $forms = $this->forms($stage);

        $form = $this->submit($forms, ['suspended_during' => 'signups']);
        self::assertTrue($form->isValid());

        $stage->start(Presets::SUSPEND_SIGNUPS, '2h');
        $form = $this->submit($forms, ['suspended_during' => 'signups']);
        self::assertFalse($form->isValid());
        $error = $form->getErrors()[0] ?? null;
        self::assertInstanceOf(FormError::class, $error);
        self::assertSame('Sign-ups are suspended until November 17, 2026 at 12:00 PM (Coordinated Universal Time).', str_replace("\u{202F}", ' ', $error->getMessage()));
        self::assertInstanceOf(Suspension::class, $error->getCause());

        self::assertTrue($this->submit($forms, ['suspended_during' => ['invitations', '']])->isValid());
        self::assertFalse($this->submit($forms, ['suspended_during' => ['', 'invitations', 'signups']])->isValid());
        self::assertTrue($this->submit($forms, ['suspended_during' => []])->isValid());
        self::assertTrue($this->submit($forms, ['suspended_during' => ''])->isValid());
        self::assertTrue($this->submit($forms, [])->isValid());
    }

    public function testTheOptionIsAListOfTheNamesKept(): void
    {
        $builder = $this->forms(new Stage())->createNamedBuilder('signup', FormType::class, null, ['suspended_during' => ['', 'invitations', 'signups']]);
        self::assertSame(['invitations', 'signups'], $builder->getOption('suspended_during'));
    }

    public function testAFormWithoutRestrictionsListensToNothing(): void
    {
        $forms = $this->forms(new Stage());

        $without = \count($forms->createNamedBuilder('signup', FormType::class)->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));
        $with = \count($forms->createNamedBuilder('signup', FormType::class, null, ['suspended_during' => 'signups'])->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));

        self::assertSame($without + 1, $with);
    }

    public function testTheOptionTakesNamesOnly(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->forms(new Stage())->create(FormType::class, null, ['suspended_during' => 3]);
    }

    public function testTheTwigFunctionsAskTheCheckpointAndTheWords(): void
    {
        $stage = new Stage();
        $extension = new IncidentModeExtension($stage->checkpoint, new IncidentWording(Words::translator(), 'UTC'), new KitComponents('ui'));
        $activation = $stage->start(Presets::SUSPEND_SIGNUPS, '2h');

        self::assertTrue($extension->suspended(Restriction::SIGNUPS));
        self::assertFalse($extension->suspended(Restriction::INVITATIONS));
        [$suspension] = $extension->suspensions();
        self::assertSame(Restriction::SIGNUPS, $suspension->restriction);
        self::assertStringStartsWith('Sign-ups are suspended until November 17, 2026', $extension->sentence($suspension));
        self::assertStringStartsWith('November 17, 2026 at 12:00', $extension->moment($activation->endsAt));
        self::assertSame('Suspend sign-ups', $extension->preset(Presets::SUSPEND_SIGNUPS));
        self::assertSame('Sign-ups', $extension->restriction(Restriction::SIGNUPS));
        self::assertSame('2 hours', $extension->duration(Duration::ofHours(2)));
        self::assertSame('ui:Button', $extension->kit('Button'));
        self::assertSame('Button', new IncidentModeExtension($stage->checkpoint, new IncidentWording(Words::translator(), 'UTC'))->kit('Button'));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return FormInterface<mixed>
     */
    private function submit(FormFactoryInterface $forms, array $options): FormInterface
    {
        $form = $forms->createNamedBuilder('signup', FormType::class, null, $options)->add('name', TextType::class)->getForm();
        $form->submit(['name' => 'Ada']);

        return $form;
    }

    private function forms(Stage $stage): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new SuspendedDuringTypeExtension($stage->checkpoint, new IncidentWording(Words::translator(), 'UTC')))
            ->getFormFactory();
    }

    /**
     * @param string|list<mixed> $restrictions
     */
    private function routed(string|array $restrictions): Request
    {
        $request = Request::create('/');
        $request->attributes->set(CheckpointListener::ROUTE_DEFAULT, $restrictions);

        return $request;
    }

    private function event(callable $controller, Request $request): ControllerEvent
    {
        return new ControllerEvent(self::createStub(HttpKernelInterface::class), $controller, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function failure(Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
