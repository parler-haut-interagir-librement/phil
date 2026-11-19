<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ReservationBundle\Application\ReservationDraft;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Infrastructure\Form\ReservationType;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use Phil\ReservationBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryReservations;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryResources;
use Phil\ReservationBundle\Tests\Unit\Fixture\Translations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

#[CoversClass(ReservationType::class)]
#[CoversClass(ReservationDraft::class)]
final class ReservationTypeTest extends TestCase
{
    private InMemoryResources $resources;

    private Reservations $service;

    protected function setUp(): void
    {
        $this->resources = new InMemoryResources();
        $this->service = new Reservations($this->resources, new InMemoryReservations(), new FrozenClock(new DateTimeImmutable('2026-11-01 12:00 UTC')));
    }

    private function factory(): FormFactoryInterface
    {
        $translator = Translations::translator('fr');

        return Forms::createFormFactoryBuilder()->addType(new ReservationType($this->service, new ViolationMessages($translator), $translator))->getFormFactory();
    }

    private function resource(int $capacity = 1): BookableResource
    {
        $resource = BookableResource::create('Salle', $capacity, 'America/Toronto');
        $this->resources->save($resource);

        return $resource;
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    /**
     * @param iterable<mixed> $formErrors
     *
     * @return list<string>
     */
    private function errors(iterable $formErrors): array
    {
        $errors = [];

        foreach ($formErrors as $error) {
            self::assertInstanceOf(FormError::class, $error);
            $errors[] = $error->getMessage();
        }

        return $errors;
    }

    public function testAFreeSlotIsAValidDraftOnTheResourcesClock(): void
    {
        $form = $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource()]);
        $form->submit(['start' => '2026-11-10T09:00', 'end' => '2026-11-10T10:00']);

        self::assertTrue($form->isSynchronized());
        self::assertSame([], $this->errors($form->getErrors()));
        $draft = $form->getData();
        self::assertInstanceOf(ReservationDraft::class, $draft);
        self::assertSame('2026-11-10T09:00:00-05:00', $draft->start?->format(\DATE_ATOM));
        self::assertSame('2026-11-10T10:00:00-05:00', $draft->end?->format(\DATE_ATOM));
        self::assertSame(1, $draft->quantity);
        self::assertFalse($form->has('quantity'));
        self::assertSame('form.start', $form->get('start')->getConfig()->getOption('label'));
        self::assertSame('form.end', $form->get('end')->getConfig()->getOption('label'));
        self::assertSame('phil_reservation', $form->getConfig()->getOption('translation_domain'));
        self::assertFalse($form->get('start')->getConfig()->getOption('with_seconds'));
        self::assertSame('single_text', $form->get('end')->getConfig()->getOption('widget'));
        self::assertInstanceOf(DateTimeImmutable::class, $form->get('start')->getData());
        self::assertInstanceOf(DateTimeImmutable::class, $form->get('end')->getData());
    }

    public function testTheQuantityIsAskedWhenTheResourceHoldsMoreThanOne(): void
    {
        $form = $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource(8)]);
        $form->submit(['start' => '2026-11-10T09:00', 'end' => '2026-11-10T10:00', 'quantity' => '9']);

        self::assertSame(['De 1 à 8 unités peuvent être réservées.'], $this->errors($form->getErrors()));
        self::assertSame(['min' => 1, 'max' => 8], $form->get('quantity')->getConfig()->getOption('attr'));
        self::assertSame('form.quantity', $form->get('quantity')->getConfig()->getOption('label'));
        self::assertTrue($this->factory()->create(ReservationType::class, null, ['resource' => $this->resource(2)])->has('quantity'));
    }

    public function testEachReasonIsAnErrorOfTheForm(): void
    {
        $resource = $this->resource();
        $this->service->book($resource->id, $this->at('2026-11-10 09:30'), $this->at('2026-11-10 10:30'));
        $form = $this->factory()->create(ReservationType::class, null, ['resource' => $resource]);
        $form->submit(['start' => '2026-11-10T09:00', 'end' => '2026-11-10T10:00']);

        self::assertSame(['Ce moment est déjà réservé.'], $this->errors($form->getErrors()));
    }

    public function testTheReservationMovedIsLeftOut(): void
    {
        $resource = $this->resource();
        $booked = $this->service->book($resource->id, $this->at('2026-11-10 09:30'), $this->at('2026-11-10 10:30'));
        $form = $this->factory()->create(ReservationType::class, new ReservationDraft(), ['resource' => $resource, 'replacing' => $booked->id]);
        $form->submit(['start' => '2026-11-10T09:00', 'end' => '2026-11-10T10:00']);

        self::assertSame([], $this->errors($form->getErrors()));
    }

    public function testAnEndThatDoesNotFollowTheStartIsOneError(): void
    {
        $form = $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource()]);
        $form->submit(['start' => '2026-11-10T10:00', 'end' => '2026-11-10T10:00']);

        self::assertSame(['La fin vient après le début.'], $this->errors($form->getErrors()));
    }

    public function testAnIncompleteDraftIsLeftToTheFieldsOwnChecks(): void
    {
        $form = $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource()]);
        $form->submit(['start' => '2026-11-10T10:00', 'end' => '']);
        self::assertSame([], $this->errors($form->getErrors()));

        $missingStart = $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource()]);
        $missingStart->submit(['start' => '', 'end' => '2026-11-10T10:00']);
        self::assertSame([], $this->errors($missingStart->getErrors()));
    }

    public function testTheResourceIsRequired(): void
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory()->create(ReservationType::class);
    }

    public function testTheOptionsAreTyped(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->factory()->create(ReservationType::class, null, ['resource' => $this->resource(), 'replacing' => 12]);
    }
}
