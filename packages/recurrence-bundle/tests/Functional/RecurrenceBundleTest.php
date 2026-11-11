<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use Override;
use Phil\Recurrence\RecurrenceRule;
use Phil\RecurrenceBundle\Infrastructure\Form\RecurrenceRuleType;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use Phil\RecurrenceBundle\Tests\Functional\App\Entity\Event;
use Phil\RecurrenceBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

#[CoversClass(PhilRecurrenceBundle::class)]
final class RecurrenceBundleTest extends KernelTestCase
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

    public function testARuleIsStoredInItsColumn(): void
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Event(RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU;UNTIL=20261231')));
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame('FREQ=MONTHLY;UNTIL=20261231;BYDAY=2TU', (string) $entityManager->getRepository(Event::class)->findOneBy([])?->rule);
    }

    public function testTheFormAndTwigSpeakTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $forms->createNamed('rule', RecurrenceRuleType::class)->createView()]);

        self::assertStringContainsString('data-controller="phil--recurrence-bundle--recurrence"', $html);
        self::assertStringContainsString('>Se répète</label>', $html);
        self::assertStringContainsString('>Chaque semaine</option>', $html);
        self::assertStringContainsString('>dernier</option>', $html);
        self::assertStringContainsString('data-show="DAILY WEEKLY"', $html);

        $text = $twig->createTemplate('{{ rule|rrule_text }} : {% for day in rrule_occurrences(start, rule, 3) %}{{ day|date("Y-m-d") }} {% endfor %}')->render(['rule' => RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=TU;COUNT=5'), 'start' => new DateTimeImmutable('2026-11-03 09:00')]);
        self::assertSame('Toutes les semaines le mardi, 5 fois : 2026-11-03 2026-11-10 2026-11-17 ', $text);

        $form = $forms->createNamed('rule', RecurrenceRuleType::class);
        $form->submit(['frequency' => 'DAILY', 'interval' => '0', 'end' => 'never']);
        self::assertSame('Cette récurrence n’est pas valide.', $form->getErrors()->current()->getMessage());
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
