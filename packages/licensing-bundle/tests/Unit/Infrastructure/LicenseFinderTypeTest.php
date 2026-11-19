<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Finder\LicenseChoices;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Domain\Finder\Modifications;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderData;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderStepType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderType;
use Phil\LicensingBundle\Tests\Unit\Support\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Flow\DataStorage\InMemoryDataStorage;
use Symfony\Component\Form\Flow\DataStorage\SessionDataStorage;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[CoversClass(LicenseFinderType::class)]
#[CoversClass(LicenseFinderStepType::class)]
#[CoversClass(LicenseFinderData::class)]
final class LicenseFinderTypeTest extends TestCase
{
    private InMemoryDataStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryDataStorage('finder');
    }

    public function testTheQuestionsAreAskedOneAPage(): void
    {
        $flow = $this->flow();
        self::assertSame('adapted_material', $flow->getCursor()->getCurrentStep());
        self::assertSame(['adapted_material', 'attribution', 'commercial_use', 'modifications'], $flow->getCursor()->getSteps());

        $flow = $this->next(['adapted_material' => ['adaptedLicense' => LicenseFinderData::NO_ADAPTED_MATERIAL]]);
        self::assertSame('attribution', $flow->getCursor()->getCurrentStep());
        $flow = $this->next(['attribution' => ['attribution' => '1']]);
        self::assertSame('commercial_use', $flow->getCursor()->getCurrentStep());
        $flow = $this->next(['commercial_use' => ['commercialUse' => '0']]);
        self::assertSame('modifications', $flow->getCursor()->getCurrentStep());

        $flow = $this->flow();
        $flow->submit(['modifications' => ['modifications' => 'share_alike'], 'navigator' => ['finish' => '']]);

        self::assertTrue($flow->isValid(), (string) $flow->getErrors(true));
        self::assertTrue($flow->isFinished());
        $data = $flow->getData();
        self::assertInstanceOf(LicenseFinderData::class, $data);
        self::assertSame([LicenseFinderData::NO_ADAPTED_MATERIAL, true, false, Modifications::ShareAlike], [$data->adaptedLicense, $data->attribution, $data->commercialUse, $data->modifications]);
        self::assertSame(LicenseCatalog::CC_BY_NC_SA, new LicenseFinder(new LicenseCatalog())->recommend($data->choices())->license);
    }

    public function testShareAlikeMaterialEndsTheFlow(): void
    {
        $flow = $this->flow();
        $flow->submit(['adapted_material' => ['adaptedLicense' => LicenseCatalog::ORC], 'navigator' => ['next' => '']]);
        $flow->getStepForm();

        self::assertTrue($flow->isFinished());
        $data = $flow->getData();
        self::assertInstanceOf(LicenseFinderData::class, $data);
        self::assertTrue($data->adaptsShareAlikeMaterial());
        self::assertSame(LicenseCatalog::ORC, new LicenseFinder(new LicenseCatalog())->recommend($data->choices())->license);
    }

    public function testEachAnswerIsRequired(): void
    {
        $flow = $this->flow();
        $flow->submit(['adapted_material' => [], 'navigator' => ['next' => '']]);

        self::assertFalse($flow->isValid());
        self::assertSame('adapted_material', $flow->getStepForm()->getCursor()->getCurrentStep());
        self::assertCount(1, $flow->getErrors(true));
    }

    public function testTheQuestionsAreShownWithTheirAnswers(): void
    {
        $view = $this->flow()->createView();

        $prefixes = $view->vars['block_prefixes'];
        self::assertIsArray($prefixes);
        self::assertSame('phil_licensing_finder', $prefixes[2]);
        self::assertSame(['adapted_material', 'navigator'], array_keys($view->children));
        self::assertSame(['none', 'orc', 'cc-by-sa-4.0', 'cc-by-nc-sa-4.0', 'ogl-1.0a'], $this->values($view->children['adapted_material']->children['adaptedLicense']));
        self::assertSame(['No, the work adapts no such material', 'Yes, material under the ORC License'], \array_slice($this->labels($view->children['adapted_material']->children['adaptedLicense']), 0, 2));
        self::assertSame('finder.adapted_material', $view->children['adapted_material']->children['adaptedLicense']->vars['label']);
        self::assertSame('finder.adapted_material.help', $view->children['adapted_material']->children['adaptedLicense']->vars['help']);
        self::assertSame(['next'], array_keys($view->children['navigator']->children));
        self::assertSame('phil_licensing', $view->children['navigator']->vars['translation_domain']);
        self::assertFalse($view->children['adapted_material']->vars['label']);
        self::assertSame('phil_licensing', $view->children['adapted_material']->vars['translation_domain']);
        self::assertFalse($view->children['adapted_material']->children['adaptedLicense']->vars['choice_translation_domain']);
        self::assertTrue($view->children['adapted_material']->children['adaptedLicense']->vars['expanded']);
        self::assertTrue($view->children['adapted_material']->children['adaptedLicense']->vars['required']);

        $this->next(['adapted_material' => ['adaptedLicense' => LicenseFinderData::NO_ADAPTED_MATERIAL]]);
        $this->next(['attribution' => ['attribution' => '0']]);
        $this->next(['commercial_use' => ['commercialUse' => '1']]);
        $view = $this->flow()->createView();
        self::assertSame(['allowed', 'share_alike', 'forbidden'], $this->values($view->children['modifications']->children['modifications']));
        self::assertSame(['finder.modifications.allowed', 'finder.modifications.share_alike', 'finder.modifications.forbidden'], $this->labels($view->children['modifications']->children['modifications']));
        self::assertSame(['previous', 'finish'], array_keys($view->children['navigator']->children));

        $this->storage->clear();
        $this->next(['adapted_material' => ['adaptedLicense' => LicenseFinderData::NO_ADAPTED_MATERIAL]]);
        $view = $this->flow()->createView();
        self::assertSame(['finder.attribution.yes', 'finder.attribution.no'], $this->labels($view->children['attribution']->children['attribution']));
        self::assertSame(['1', '0'], $this->values($view->children['attribution']->children['attribution']));
        self::assertTrue($view->children['attribution']->children['attribution']->vars['expanded']);
        $this->next(['attribution' => ['attribution' => '1']]);
        $view = $this->flow()->createView();
        self::assertSame(['finder.commercial_use.yes', 'finder.commercial_use.no'], $this->labels($view->children['commercial_use']->children['commercialUse']));
        self::assertSame(['1', '0'], $this->values($view->children['commercial_use']->children['commercialUse']));
    }

    public function testTheAnswersAreKeptInTheSessionByDefault(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $requests = new RequestStack([$request]);

        $resolver = new OptionsResolver();
        $resolver->setDefined(['data_class', 'step_property_path', 'translation_domain', 'data_storage']);
        new LicenseFinderType($requests)->configureOptions($resolver);
        $options = $resolver->resolve();

        self::assertInstanceOf(SessionDataStorage::class, $options['data_storage']);
        self::assertSame([LicenseFinderData::class, 'step', 'phil_licensing'], [$options['data_class'], $options['step_property_path'], $options['translation_domain']]);

        $resolver = new OptionsResolver();
        $resolver->setDefined(['data_storage']);
        new LicenseFinderType()->configureOptions($resolver);
        self::assertNull($resolver->resolve()['data_storage']);
    }

    public function testTheDataGivesTheChoices(): void
    {
        $data = new LicenseFinderData();
        self::assertEquals(LicenseChoices::none(), $data->choices());
        self::assertFalse($data->adaptsShareAlikeMaterial());

        $data->adaptedLicense = LicenseFinderData::NO_ADAPTED_MATERIAL;
        $data->attribution = false;
        $data->commercialUse = true;
        $data->modifications = Modifications::Allowed;
        self::assertEquals(LicenseChoices::none()->withAdaptedLicense(null)->withAttribution(false)->withCommercialUse(true)->withModifications(Modifications::Allowed), $data->choices());
        self::assertFalse($data->adaptsShareAlikeMaterial());

        $data->adaptedLicense = LicenseCatalog::OGL;
        self::assertSame(LicenseCatalog::OGL, $data->choices()->adaptedLicense);
        self::assertTrue($data->adaptsShareAlikeMaterial());
        self::assertSame(['adapted_material', 'attribution', 'commercial_use', 'modifications'], LicenseFinderData::steps());
    }

    private function flow(): FormFlowInterface
    {
        $flow = Forms::factory()->create(LicenseFinderType::class, null, ['data_storage' => $this->storage]);
        self::assertInstanceOf(FormFlowInterface::class, $flow);

        return $flow;
    }

    /**
     * @param array<string, mixed> $answer
     */
    private function next(array $answer): FormFlowInterface
    {
        $flow = $this->flow();
        $flow->submit($answer + ['navigator' => ['next' => '']]);
        self::assertTrue($flow->isValid(), (string) $flow->getErrors(true));
        $next = $flow->getStepForm();
        self::assertInstanceOf(FormFlowInterface::class, $next);

        return $next;
    }

    /**
     * @return list<string>
     */
    private function values(FormView $field): array
    {
        return array_values(array_map(static fn (FormView $choice): string => \is_string($choice->vars['value']) ? $choice->vars['value'] : '', $field->children));
    }

    /**
     * @return list<string>
     */
    private function labels(FormView $field): array
    {
        return array_values(array_map(static fn (FormView $choice): string => \is_string($choice->vars['label']) ? $choice->vars['label'] : '', $field->children));
    }
}
