<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHoursBundle\Infrastructure\Form\OpeningHoursType;
use Phil\OpeningHoursBundle\PhilOpeningHoursBundle;
use Phil\OpeningHoursBundle\Tests\Functional\App\Entity\Shop;
use Phil\OpeningHoursBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

#[CoversClass(PhilOpeningHoursBundle::class)]
final class OpeningHoursBundleTest extends KernelTestCase
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

    public function testHoursAreStoredInTheirColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Shop($this->hours()));
        $entityManager->flush();
        $entityManager->clear();

        $hours = $entityManager->getRepository(Shop::class)->findOneBy([])?->hours;
        self::assertSame(['America/Toronto', '09:00-17:00'], [$hours?->timezone, $hours?->groups()[0]['hours']->toText()]);
    }

    public function testTheFormSpeaksTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('hours', OpeningHoursType::class);
        $form->submit(['monday' => 'toute la journée']);

        self::assertSame('Ces heures ne se lisent pas : tapez des plages comme 09:00-12:00, 13:00-17:00.', $form->get('monday')->getErrors()->current()->getMessage());

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $forms->createNamed('hours', OpeningHoursType::class)->createView()]);
        self::assertStringContainsString('>Lundi</label>', $html);
        self::assertStringContainsString('Heures particulières', $html);
        self::assertStringContainsString('<option value="America/Toronto" selected="selected">', $html);
    }

    public function testTheTableIsWrittenInTheLocale(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate("{% include '@PhilOpeningHours/table.html.twig' %}")->render(['hours' => $this->hours()]);

        self::assertStringContainsString('<th scope="row">Lundi – vendredi</th>', $html);
        self::assertStringContainsString('<td>09 h 00 – 17 h 00</td>', $html);
        self::assertStringContainsString('<td>Fermé</td>', $html);
        self::assertStringContainsString('<span>Noël</span>', $html);
    }

    public function testTheTableKeepsTheCapitalsOfALanguageThatWritesThem(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        Locale::setDefault('en_US');

        $html = $twig->createTemplate("{% include '@PhilOpeningHours/table.html.twig' %}")->render(['hours' => $this->hours()]);

        self::assertStringContainsString('<th scope="row">Monday – Friday</th>', $html);
        self::assertStringContainsString('<td>9:00', $html);
    }

    private function hours(): OpeningHours
    {
        $weekday = HoursText::day('09:00-17:00');

        return OpeningHours::of([1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday], HoursText::specials('2099-12-25 # Noël'), 'America/Toronto');
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
