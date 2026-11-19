<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceWholesaleBundle\Application\CustomerGroups;
use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Model\CustomerGroup;
use Phil\CommerceWholesaleBundle\Domain\Repository\CustomerGroupRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Doctrine\DoctrineWholesaleRepository;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\AllowedQuantity;
use Phil\CommerceWholesaleBundle\Infrastructure\Validator\RequestedQuantity;
use Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle;
use Phil\CommerceWholesaleBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Groups and memberships stored and read back through the container.
 */
#[CoversClass(PhilCommerceWholesaleBundle::class)]
#[CoversClass(DoctrineWholesaleRepository::class)]
final class WholesaleFlowTest extends KernelTestCase
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

    public function testABuyersGroupIsStored(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->service(CustomerGroupRepository::class, CustomerGroupRepository::class)->addGroup(CustomerGroup::of('wholesale', 'Grossistes', Currency::of('CAD'), 250_000, Money::of(50_000, Currency::of('CAD'))));
        $entityManager->flush();

        $groups = $this->service(CustomerGroups::class, CustomerGroups::class);
        $groups->join('tenant-42', 'wholesale');

        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('wholesale', $groups->groupOf('tenant-42'));
        $group = $this->service(CustomerGroupRepository::class, CustomerGroupRepository::class)->findGroup('wholesale');
        self::assertSame([250_000, 50_000, 'Grossistes'], [$group?->discount(), $group?->minimumOrder()->minorUnits, $group?->name()]);

        $groups->leave('tenant-42');
        $entityManager->flush();
        $entityManager->clear();
        self::assertNull($groups->groupOf('tenant-42'));
    }

    /**
     * A rule stored, read back by the constraint, and its message translated in the buyer's language.
     */
    public function testAQuantityRuleIsStoredAndItsMessageTranslated(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $rules = $this->service(QuantityRules::class, QuantityRules::class);
        $rules->set('DICE', 'wholesale', 6, 60, 6);
        $rules->set('DICE', null, 1, 10);

        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(6, $rules->ruleFor('DICE', 'wholesale')?->increment());
        self::assertNull($rules->ruleFor('DICE', 'retail')?->customerGroup);

        $validator = $this->service('validator', ValidatorInterface::class);
        $translator = $this->service('translator', Translator::class);
        $translator->setLocale('fr');
        self::assertSame('DICE se vend à partir de 6 unités, par tranches de 6 : essayez 12.', (string) $validator->validate(new RequestedQuantity('DICE', 7, 'wholesale'), new AllowedQuantity())->get(0)->getMessage());
        $translator->setLocale('en');
        self::assertSame('DICE is sold by 10 units at most.', (string) $validator->validate(new RequestedQuantity('DICE', 11), new AllowedQuantity())->get(0)->getMessage());
        self::assertCount(0, $validator->validate(new RequestedQuantity('DICE', 10), new AllowedQuantity()));

        $rules->remove('DICE', 'wholesale');
        $entityManager->flush();
        self::assertSame(1, $rules->ruleFor('DICE', 'wholesale')?->minimum());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
