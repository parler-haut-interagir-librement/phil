<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional;

use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;
use Phil\EnumsBundle\PhilEnumsBundle;
use Phil\EnumsBundle\Tests\Functional\App\Entity\Parcel;
use Phil\EnumsBundle\Tests\Functional\App\ShippingRequest;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelOption;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * Each integration of the bundle, through the services and commands of a real kernel.
 */
#[CoversClass(PhilEnumsBundle::class)]
final class IntegrationTest extends FunctionalTestCase
{
    public function testTheBadgeTemplateIsFoundUnderItsNamespace(): void
    {
        $html = $this->service('twig', Environment::class)->render('badge.html.twig', ['status' => ParcelStatus::Ordered]);

        self::assertStringContainsString('class="enum-badge enum-badge--grey"', $html);
        self::assertStringContainsString('Ordered', $html);
    }

    public function testASetIsStoredAsABitmaskAndReadBack(): void
    {
        $parcel = new Parcel();
        $parcel->options = EnumSet::of(ParcelOption::class, ParcelOption::Fragile, ParcelOption::Express);

        $manager = $this->entityManager();
        $manager->persist($parcel);
        $manager->flush();
        $manager->clear();

        self::assertSame(5, $manager->getConnection()->fetchOne('SELECT options FROM parcel'));

        $read = $manager->find(Parcel::class, $parcel->id);
        self::assertInstanceOf(Parcel::class, $read);
        self::assertSame([ParcelOption::Fragile, ParcelOption::Express], [...$read->options]);
    }

    public function testCheckSetsListsTheStoredValuesThatNameNoCase(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->insert('parcel', ['status' => 'ordered', 'options' => 3]);

        $tester = $this->command('phil:enums:check-sets');
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('of the 1 EnumSet column(s)', $tester->getDisplay());

        $connection->insert('parcel', ['status' => 'ordered', 'options' => 8]);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString(Parcel::class.'::$options', $tester->getDisplay());
        self::assertMatchesRegularExpression('/\b8\b/', $tester->getDisplay());
        self::assertMatchesRegularExpression('/Field\s+Enum\s+Stored value/', $tester->getDisplay());
        self::assertStringContainsString('[ERROR] 1 stored value(s) name no case of their enum', $tester->getDisplay());
    }

    public function testTheWorkflowIsTheStatusTransitions(): void
    {
        $parcel = new Parcel();
        $workflow = $this->service('workflow.registry', Registry::class)->get($parcel, 'parcel');

        self::assertTrue($workflow->can($parcel, 'ordered_to_shipped'));
        self::assertFalse($workflow->can($parcel, 'shipped_to_delivered'));

        $workflow->apply($parcel, 'ordered_to_shipped');

        self::assertSame(ParcelStatus::Shipped, $parcel->status);
        self::assertSame(['shipped_to_delivered'], array_map(static fn (Transition $transition): string => $transition->getName(), $workflow->getEnabledTransitions($parcel)));
    }

    public function testTheRouteReadsTheStatusLeniently(): void
    {
        self::assertSame('shipped', $this->request('/status/SHIPPED')->getContent());
        self::assertSame('ordered', $this->request('/status/new')->getContent());
        self::assertSame(404, $this->request('/status/lost')->getStatusCode());
    }

    public function testARouteConditionReadsTheGroups(): void
    {
        self::assertSame('open: shipped', $this->request('/open/shipped')->getContent());
        self::assertSame(404, $this->request('/open/delivered')->getStatusCode());
    }

    public function testTheSerializerWritesAndReadsSetsAndLabelledCases(): void
    {
        $serializer = $this->service('serializer', SerializerInterface::class);

        self::assertSame('[1,2]', $serializer->serialize(EnumSet::of(ParcelOption::class, ParcelOption::Insured, ParcelOption::Fragile), 'json'));
        self::assertSame(
            '{"value":"ordered","label":"Commandé","description":"Waiting to be shipped."}',
            $this->withLocale('fr', static fn (): string => $serializer->serialize(ParcelStatus::Ordered, 'json', [EnumsNormalizer::LABELS => true, 'json_encode_options' => \JSON_UNESCAPED_UNICODE])),
        );

        $set = $serializer->deserialize('[4]', EnumSet::class, 'json', [EnumsNormalizer::ENUM => ParcelOption::class]);
        self::assertInstanceOf(EnumSet::class, $set);
        self::assertSame([ParcelOption::Express], [...$set]);
    }

    public function testAPropertyNamesTheEnumOfItsSet(): void
    {
        $request = $this->service('serializer', SerializerInterface::class)->deserialize('{"options":[2,4]}', ShippingRequest::class, 'json');

        self::assertInstanceOf(ShippingRequest::class, $request);
        self::assertNotNull($request->options);
        self::assertSame([ParcelOption::Insured, ParcelOption::Express], [...$request->options]);
    }

    public function testTheSetConstraintValidatesTheEntity(): void
    {
        $parcel = new Parcel();
        $parcel->options = EnumSet::all(ParcelOption::class);

        $violations = $this->service('validator', ValidatorInterface::class)->validate($parcel);

        self::assertCount(1, $violations);
        self::assertSame('options', $violations[0]?->getPropertyPath());
    }

    public function testTheCommandsReadTheEnumsOfTheConfiguredPaths(): void
    {
        $debug = $this->command('debug:enums');
        self::assertSame(Command::SUCCESS, $debug->execute([]));
        self::assertStringContainsString(ParcelStatus::class, $debug->getDisplay());
        self::assertStringContainsString(ParcelOption::class, $debug->getDisplay());

        self::assertSame(Command::FAILURE, $debug->execute(['enum' => 'ParcelStatus', '--locale' => 'fr']));
        self::assertStringContainsString('Waiting to be shipped.', $debug->getDisplay());

        $typescript = $this->command('phil:enums:typescript');
        self::assertSame(Command::SUCCESS, $typescript->execute([]));
        self::assertStringContainsString('export const ParcelStatusDescriptions', $typescript->getDisplay());
        self::assertStringContainsString('export const ParcelOptionGroups', $typescript->getDisplay());

        $icu = $this->command('phil:enums:icu');
        self::assertSame(Command::SUCCESS, $icu->execute(['enum' => ParcelStatus::class, '--locale' => 'fr']));
        self::assertStringContainsString('ordered {Commandé}', $icu->getDisplay());
    }

    public function testTranslationExtractFindsTheKeysOfTheEnums(): void
    {
        $tester = $this->command('translation:extract');

        self::assertSame(Command::SUCCESS, $tester->execute(['locale' => 'fr', '--dump-messages' => true, '--domain' => 'enums']));
        self::assertStringContainsString('Waiting to be shipped.', $tester->getDisplay());
        self::assertStringContainsString('Cancelled', $tester->getDisplay());
    }

    /**
     * @template T
     *
     * @param callable(): T $call
     *
     * @return T
     */
    private function withLocale(string $locale, callable $call): mixed
    {
        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $previous = $translator->getLocale();
        $translator->setLocale($locale);

        try {
            return $call();
        } finally {
            $translator->setLocale($previous);
        }
    }
}
