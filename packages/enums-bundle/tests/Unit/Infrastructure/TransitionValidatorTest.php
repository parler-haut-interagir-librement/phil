<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\EnumsBundle\Infrastructure\Validator\Transition;
use Phil\EnumsBundle\Infrastructure\Validator\TransitionValidator;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Entity\Shipment;
use Phil\EnumsBundle\Tests\Unit\Fixture\OneManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionProperty;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<TransitionValidator>
 */
#[CoversClass(Transition::class)]
#[CoversClass(TransitionValidator::class)]
final class TransitionValidatorTest extends ConstraintValidatorTestCase
{
    private ?EntityManager $entityManager = null;

    public function testAMoveTheEnumDeclaresIsValid(): void
    {
        $this->setProperty($this->shipment(Delivery::Ordered), 'status');
        $this->validate(Delivery::Shipped, new Transition(from: 'previous'));

        $this->assertNoViolation();
    }

    public function testStayingIsValid(): void
    {
        $this->setProperty($this->shipment(Delivery::Delivered), 'status');
        $this->validate(Delivery::Delivered, new Transition(from: 'previous'));

        $this->assertNoViolation();
    }

    public function testAMoveTheEnumDoesNotDeclareIsRefused(): void
    {
        $this->setProperty($this->shipment(Delivery::Shipped), 'status');
        $this->validate(Delivery::Cancelled, new Transition(from: 'previous', message: 'From {{ from }} to {{ to }}, no.'));

        $this->buildViolation('From {{ from }} to {{ to }}, no.')
            ->setParameter('{{ from }}', '"Shipped"')
            ->setParameter('{{ to }}', '"Cancelled"')
            ->setCode(Transition::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testAFinalCaseMovesNowhere(): void
    {
        $this->setProperty($this->shipment(Delivery::Delivered), 'status');
        $this->validate(Delivery::Shipped, new Transition(from: 'previous'));

        $this->buildViolation('The state cannot move from {{ from }} to {{ to }}.')
            ->setParameter('{{ from }}', '"Delivered"')
            ->setParameter('{{ to }}', '"Shipped"')
            ->setCode(Transition::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testANewObjectStartsInAnInitialCase(): void
    {
        $this->setProperty($this->shipment(null), 'status');
        $this->validate(Delivery::Ordered, new Transition(from: 'previous'));

        $this->assertNoViolation();
    }

    public function testANewObjectCannotStartFurtherOn(): void
    {
        $this->setProperty($this->shipment(null), 'status');
        $this->validate(Delivery::Shipped, new Transition(from: 'previous', initialMessage: 'Not as {{ to }}.'));

        $this->buildViolation('Not as {{ to }}.')
            ->setParameter('{{ to }}', '"Shipped"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testANewObjectMayStartAnywhereWhenAllowed(): void
    {
        $this->setProperty($this->shipment(null), 'status');
        $this->validate(Delivery::Delivered, new Transition(from: 'previous', fromInitial: false));

        $this->assertNoViolation();
    }

    public function testWithoutAnObjectTheCaseMustBeInitial(): void
    {
        $this->setObject(null);
        $this->validate(Delivery::Cancelled, new Transition());

        $this->buildViolation('The state cannot start as {{ to }}.')
            ->setParameter('{{ to }}', '"Cancelled"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testDoctrineGivesThePreviousCaseOfAManagedEntity(): void
    {
        $shipment = new Shipment();
        $this->entityManager()->persist($shipment);
        $this->entityManager()->flush();
        $shipment->status = Delivery::Delivered;

        $this->setProperty($shipment, 'status');
        $this->validate(Delivery::Delivered, new Transition());

        $this->buildViolation('The state cannot move from {{ from }} to {{ to }}.')
            ->setParameter('{{ from }}', '"Ordered"')
            ->setParameter('{{ to }}', '"Delivered"')
            ->setCode(Transition::NOT_ALLOWED_ERROR)
            ->assertRaised();
    }

    public function testDoctrineAllowsWhatTheEnumDeclares(): void
    {
        $shipment = new Shipment();
        $this->entityManager()->persist($shipment);
        $this->entityManager()->flush();

        $this->setProperty($shipment, 'status');
        $this->validate(Delivery::Cancelled, new Transition());

        $this->assertNoViolation();
    }

    public function testAnEntityNotYetPersistedStartsInAnInitialCase(): void
    {
        $this->entityManager();
        $this->setProperty(new Shipment(), 'status');
        $this->validate(Delivery::Delivered, new Transition());

        $this->buildViolation('The state cannot start as {{ to }}.')
            ->setParameter('{{ to }}', '"Delivered"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testAnObjectDoctrineDoesNotMapStartsInAnInitialCase(): void
    {
        $this->entityManager();
        $this->setProperty(new class {
            public ?Delivery $status = null;
        }, 'status');
        $this->validate(Delivery::Shipped, new Transition());

        $this->buildViolation('The state cannot start as {{ to }}.')
            ->setParameter('{{ to }}', '"Shipped"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testANullColumnHadNoCase(): void
    {
        $shipment = new Shipment();
        $this->entityManager()->persist($shipment);
        $this->entityManager()->flush();

        $this->setProperty($shipment, 'returned');
        $this->validate(Delivery::Shipped, new Transition());

        $this->buildViolation('The state cannot start as {{ to }}.')
            ->setParameter('{{ to }}', '"Shipped"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testWithDoctrineAndNoObjectTheCaseMustBeInitial(): void
    {
        $this->entityManager();
        $this->setObject(null);
        $this->validate(Delivery::Ordered, new Transition());

        $this->assertNoViolation();
    }

    public function testWithoutDoctrineNorFromAnObjectStartsInAnInitialCase(): void
    {
        $this->setProperty(new Shipment(), 'status');
        $this->validate(Delivery::Shipped, new Transition());

        $this->buildViolation('The state cannot start as {{ to }}.')
            ->setParameter('{{ to }}', '"Shipped"')
            ->setCode(Transition::NOT_INITIAL_ERROR)
            ->assertRaised();
    }

    public function testNullIsValid(): void
    {
        $this->validate(null, new Transition());

        $this->assertNoViolation();
    }

    public function testOnlyACaseIsChecked(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validate('shipped', new Transition());
    }

    public function testOnlyTheTransitionConstraintIsChecked(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validate(Delivery::Shipped, new NotNull());
    }

    public function testTheConstraintHasItsDefaults(): void
    {
        $constraint = new Transition(groups: ['edit']);

        self::assertSame([null, true, 'The state cannot move from {{ from }} to {{ to }}.', 'The state cannot start as {{ to }}.', ['edit']], [$constraint->from, $constraint->fromInitial, $constraint->message, $constraint->initialMessage, $constraint->groups]);
        self::assertSame('NOT_ALLOWED_ERROR', Transition::getErrorName(Transition::NOT_ALLOWED_ERROR));
        self::assertSame('NOT_INITIAL_ERROR', Transition::getErrorName(Transition::NOT_INITIAL_ERROR));
    }

    protected function createValidator(): TransitionValidator
    {
        return new TransitionValidator($this->entityManager instanceof EntityManager ? new OneManagerRegistry($this->entityManager) : null);
    }

    private function shipment(?Delivery $previous): Shipment
    {
        $shipment = new Shipment();
        $shipment->previous = $previous;

        return $shipment;
    }

    /**
     * An entity manager on SQLite in memory with the shipment's table; the validator is built again to see it.
     */
    private function entityManager(): EntityManager
    {
        if (!$this->entityManager instanceof EntityManager) {
            $config = new Configuration();
            $config->setMetadataDriverImpl(new AttributeDriver([\dirname(__DIR__).'/Fixture/Entity']));
            $config->enableNativeLazyObjects(true);
            $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
            new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
            $this->validator = $this->createValidator();
            new ReflectionProperty(ConstraintValidator::class, 'context')->setValue($this->validator, $this->context);
        }

        return $this->entityManager;
    }
}
