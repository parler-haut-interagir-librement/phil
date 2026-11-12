<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\AlmanacBundle\Application\DateDraft;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;
use Phil\AlmanacBundle\Infrastructure\Doctrine\DoctrineStoredDateRepository;
use Phil\AlmanacBundle\Infrastructure\Form\StoredDateType;
use Phil\AlmanacBundle\PhilAlmanacBundle;
use Phil\AlmanacBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ServicesResetter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Twig\Environment;

#[CoversClass(PhilAlmanacBundle::class)]
final class AlmanacBundleTest extends KernelTestCase
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
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * A date stored replaces the configured one of its key, and comes back from the database by key.
     */
    public function testAStoredDateIsInTheAlmanac(): void
    {
        $dates = $this->service(StoredDateRepository::class);
        self::assertInstanceOf(DoctrineStoredDateRepository::class, $dates);
        $draft = new DateDraft();
        $draft->key = 'thanksgiving';
        $draft->title = 'Thanksgiving';
        $draft->rule = '4th thursday of november';

        $dates->save(new StoredDate($draft->definition()));
        $draft->key = 'reveillon';
        $draft->title = 'Réveillon';
        $draft->rule = '12-24';

        $dates->save(new StoredDate($draft->definition()));

        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        self::assertSame(['reveillon', 'thanksgiving'], array_map(static fn (StoredDate $date): string => $date->key(), $dates->all()));
        self::assertSame('Réveillon', $dates->ofKey('reveillon')?->definition()->title);
        self::assertNull($dates->ofKey('noel'));

        $almanac = $this->service(Almanac::class);
        self::assertInstanceOf(Almanac::class, $almanac);
        self::assertSame(
            ['thanksgiving 2026-11-26', 'reveillon 2026-12-24', 'noel 2026-12-25'],
            array_map(static fn (ComputedDate $date): string => $date->definition->key.' '.$date->span->start->format('Y-m-d'), $almanac->between(new DateTimeImmutable('2026-10-01'), new DateTimeImmutable('2027-01-01'))),
        );

        $reveillon = $dates->ofKey('reveillon');
        self::assertInstanceOf(StoredDate::class, $reveillon);
        $dates->remove($reveillon);
        self::assertSame(['thanksgiving'], array_map(static fn (StoredDate $date): string => $date->key(), $dates->all()));
    }

    /**
     * The table is read once until a date is saved or removed, or the kernel resets its services.
     */
    public function testTheStoredDatesAreReadOncePerRequest(): void
    {
        $dates = $this->service(StoredDateRepository::class);
        self::assertInstanceOf(DoctrineStoredDateRepository::class, $dates);
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertSame([], $this->keys($dates));

        $draft = new DateDraft();
        $draft->key = 'noel';
        $draft->title = 'Noël';
        $draft->rule = '12-25';

        $entityManager->persist(new StoredDate($draft->definition()));
        $entityManager->flush();
        self::assertSame([], $this->keys($dates), 'what was read is kept: the row was written without the repository');

        $resetter = $this->service('services_resetter');
        self::assertInstanceOf(ServicesResetter::class, $resetter);
        $resetter->reset();
        self::assertSame(['noel'], $this->keys($dates), 'read again after a reset');

        $draft->key = 'reveillon';
        $draft->rule = '12-24';

        $dates->save(new StoredDate($draft->definition()));
        self::assertSame(['noel', 'reveillon'], $this->keys($dates), 'read again after a save');
    }

    /**
     * @return list<string>
     */
    private function keys(StoredDateRepository $dates): array
    {
        return array_map(static fn (StoredDate $date): string => $date->key(), $dates->all());
    }

    public function testTheDatesInTwig(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame(
            'Action de grâce 2026-10-12|Noël 2026-12-25|',
            $twig->createTemplate("{% for date in almanac_dates('2026-01-01', '2027-01-01', ['fete']) %}{{ date.definition.title }} {{ date.span.start|date('Y-m-d') }}|{% endfor %}")->render(),
        );
    }

    /**
     * The declared eclipses are dates of the almanac, at their instant; the places give their sunrise in Twig.
     */
    public function testTheSkyInTwig(): void
    {
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);

        self::assertSame(
            'Éclipse solaire 2026-02-17 07:12|Éclipse lunaire 2026-03-03 06:34|Éclipse solaire 2026-08-12 13:46|Éclipse lunaire 2026-08-28 00:13|',
            $twig->createTemplate("{% for date in almanac_dates('2026-01-01', '2027-01-01', ['eclipse']) %}{{ date.definition.title }} {{ date.span.start|date('Y-m-d H:i', 'America/Toronto') }}|{% endfor %}")->render(),
        );
        self::assertSame(
            '05:05 20:46 04:27|05:46 21:57',
            $twig->createTemplate("{% set day = sun_times('montreal', '2026-06-20') %}{{ day.sunrise|date('H:i', false) }} {{ day.sunset|date('H:i', false) }} {{ day.dawn|date('H:i', false) }}|{% set paris = sun_times('paris', '2026-06-21') %}{{ paris.sunrise|date('H:i', false) }} {{ paris.sunset|date('H:i', false) }}")->render(),
        );
        self::assertSame(
            'total 2026-08-12|partial 2026-08-28|',
            $twig->createTemplate("{% for eclipse in almanac_eclipses('2026-08-01', '2026-09-01') %}{{ eclipse.kind.value }} {{ eclipse.at|date('Y-m-d') }}|{% endfor %}")->render(),
        );
    }

    /**
     * The form fills a draft; a rule it cannot read is refused in French, on its field.
     */
    public function testTheForm(): void
    {
        $factory = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory->create(StoredDateType::class, new DateDraft());
        $form->submit(['key' => 'oktoberfest', 'title' => 'Oktoberfest', 'rule' => 'saturday on or after 09-15', 'until' => 'sunday on or after 10-03', 'days' => '1', 'allDay' => '1', 'tags' => 'fete, bière']);
        self::assertTrue($form->isValid());
        $draft = $form->getData();
        self::assertInstanceOf(DateDraft::class, $draft);
        self::assertSame(['fete', 'bière'], $draft->definition()->tags);

        $refused = $factory->create(StoredDateType::class, new DateDraft());
        $refused->submit(['key' => 'noel', 'title' => 'Noël', 'rule' => 'every blue moon', 'days' => '1']);
        self::assertFalse($refused->isValid());
        self::assertSame('Cette règle ne se lit pas : "every blue moon" is not a rule the almanac reads.', $refused->get('rule')->getErrors()->current()->getMessage());

        $locked = $factory->create(StoredDateType::class, DateDraft::of($draft->definition()), ['key_locked' => true]);
        self::assertTrue($locked->get('key')->isDisabled());
        self::assertFalse($factory->create(StoredDateType::class)->get('key')->isDisabled());

        $view = $this->service('twig');
        self::assertInstanceOf(Environment::class, $view);
        $html = $view->createTemplate('{{ form(form) }}')->render(['form' => $factory->create(StoredDateType::class)->createView()]);
        self::assertStringContainsString('Règle', $html);
        self::assertStringContainsString('Journée entière', $html);
        self::assertStringContainsString('En anglais : 12-25', $html);

        foreach (['Clé', 'Titre', 'Jusqu’à', 'Jours', 'Étiquettes'] as $label) {
            self::assertStringContainsString('>'.$label.'</label>', $html);
        }

        self::assertMatchesRegularExpression('/<input type="number"[^>]* min="1" max="366"/', $html);

        $fields = $factory->create(StoredDateType::class)->createView();
        $required = [];

        foreach (['key', 'title', 'rule', 'until', 'days', 'allDay', 'tags'] as $field) {
            $required[$field] = $fields[$field]->vars['required'];
        }

        self::assertSame(['key' => true, 'title' => true, 'rule' => true, 'until' => false, 'days' => true, 'allDay' => false, 'tags' => false], $required);
    }

    /**
     * A form made without a draft makes one; `key_locked` takes a boolean only.
     */
    public function testTheFormMakesItsDraft(): void
    {
        $factory = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);

        $form = $factory->create(StoredDateType::class);
        $form->submit(['key' => 'noel', 'title' => 'Noël', 'rule' => '12-25', 'days' => '1']);
        self::assertInstanceOf(DateDraft::class, $form->getData());

        $this->expectException(InvalidOptionsException::class);
        $factory->create(StoredDateType::class, null, ['key_locked' => 'yes']);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }
}
