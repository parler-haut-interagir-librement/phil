<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\MoneyBundle\Infrastructure\Form\MoneyType;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrency;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRange;
use Phil\MoneyBundle\PhilMoneyBundle;
use Phil\MoneyBundle\Tests\Functional\App\Entity\Product;
use Phil\MoneyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * The bundle in a real kernel, in `fr_CA`: a price entered in a form, validated with translated messages,
 * printed by Twig, and stored in two columns.
 */
#[CoversClass(PhilMoneyBundle::class)]
final class MoneyBundleTest extends KernelTestCase
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

    public function testAPriceIsEnteredInTheLocaleAndAnAcceptedCurrency(): void
    {
        $form = $this->forms()->createNamed('price', MoneyType::class);
        $form->submit(['amount' => '1 234,50', 'currency' => 'USD']);

        self::assertTrue($form->isValid());
        self::assertEquals(Money::of(123_450, Currency::of('USD')), $form->getData());
        $choices = $form->get('currency')->getConfig()->getOption('choices');
        self::assertIsArray($choices);
        self::assertSame(['CAD', 'USD'], array_keys($choices));
    }

    public function testAnAmountFinerThanTheCurrencyFailsWithATranslatedMessage(): void
    {
        $form = $this->forms()->createNamed('price', MoneyType::class);
        $form->submit(['amount' => '12,345', 'currency' => 'CAD']);

        self::assertFalse($form->isValid());
        self::assertSame(['Cette valeur n’est pas un montant valide.'], array_map(static fn (FormError|FormErrorIterator $error): string => $error->getMessage(), iterator_to_array($form->getErrors())));
    }

    public function testAnEmptyFieldStartsOnTheDefaultCurrency(): void
    {
        $view = $this->forms()->createNamed('price', MoneyType::class)->createView();

        self::assertSame('CAD', $view['currency']->vars['value']);
        self::assertSame('', $view['amount']->vars['value']);
        self::assertIsArray($view->vars['block_prefixes']);
        self::assertSame('phil_money', $view->vars['block_prefixes'][1]);
    }

    public function testTheConstraintsSpeakTheLocale(): void
    {
        $violations = $this->validator()->validate(Money::of(-100, Currency::of('EUR')), [new AcceptedCurrency(), new MoneyRange(min: '0')]);

        self::assertSame(
            ['La devise "EUR" n’est pas acceptée.', 'Ce montant doit être d’au moins 0.00 EUR.'],
            array_map(static fn (ConstraintViolationInterface $violation): string => (string) $violation->getMessage(), iterator_to_array($violations)),
        );
    }

    public function testTwigFormatsMoney(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame("1\u{A0}234,50\u{A0}\$", $twig->createTemplate('{{ price|format_money }}')->render(['price' => Money::of(123_450, Currency::of('CAD'))]));
        self::assertSame('CA$1,234.50', $twig->createTemplate("{{ price|format_money('en') }}")->render(['price' => Money::of(123_450, Currency::of('CAD'))]));
    }

    public function testAPriceIsStoredInTwoColumnsAndReadBack(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $schema = new SchemaTool($entityManager);
        $schema->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Product(Money::of(-123_450, Currency::of('CAD')), Currency::of('USD')));
        $entityManager->persist(new Product(Money::of(\PHP_INT_MAX, Currency::of('JPY'))));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(['id', 'supplierCurrency', 'price_amount', 'price_currency'], $entityManager->getClassMetadata(Product::class)->getColumnNames());

        $products = $entityManager->getRepository(Product::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $products);
        self::assertEquals(Money::of(-123_450, Currency::of('CAD')), $products[0]->price);
        self::assertEquals(Currency::of('USD'), $products[0]->supplierCurrency);
        self::assertEquals(Money::of(\PHP_INT_MAX, Currency::of('JPY')), $products[1]->price);
        self::assertNull($products[1]->supplierCurrency);

        $total = $entityManager->getRepository(Product::class)->createQueryBuilder('p')
            ->select('SUM(p.price.minorUnits)')
            ->where('p.price.currency = :currency')
            ->setParameter('currency', 'CAD')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(-123_450, (int) $total);
    }

    private function forms(): FormFactoryInterface
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);

        return $forms;
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }

    private function validator(): ValidatorInterface
    {
        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }
}
