<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Infrastructure\Form\SignupAcceptances;
use Phil\TermsBundle\Infrastructure\Form\TermsAcceptanceTypeExtension;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(TermsAcceptanceTypeExtension::class)]
#[CoversClass(SignupAcceptances::class)]
final class FormTest extends TestCase
{
    public function testAFormWithoutTheOptionIsLeftAlone(): void
    {
        $kit = $this->kit();

        self::assertSame(['name'], array_keys($this->form($kit, null)->all()));
        self::assertSame(['name'], array_keys($this->form($kit, false)->all()));
        self::assertSame(['name'], array_keys($this->form($kit, [])->all()));
        $listeners = static fn (FormInterface $form): int => \count($form->getConfig()->getEventDispatcher()->getListeners(FormEvents::POST_SUBMIT));
        self::assertSame($listeners($this->form($kit, false)), $listeners($this->form($kit, [])));
        self::assertSame($listeners($this->form($kit, false)) + 1, $listeners($this->form($kit, true)));
    }

    public function testEachRequiredPolicyInForceAddsABoxNotTicked(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, true);

        self::assertSame(['phil_terms_accept_terms', 'phil_terms_version_terms', 'name'], array_keys($form->all()));
        $checkbox = $form->get('phil_terms_accept_terms')->getConfig();
        self::assertInstanceOf(CheckboxType::class, $checkbox->getType()->getInnerType());
        self::assertFalse($checkbox->getMapped());
        self::assertTrue($checkbox->getRequired());
        self::assertFalse($checkbox->getData());
        self::assertSame('phil_terms', $checkbox->getOption('translation_domain'));
        self::assertSame('signup.accept', $checkbox->getOption('label'));
        self::assertSame(['%policy%' => 'terms of use', '%version%' => '2'], $checkbox->getOption('label_translation_parameters'));
        $version = $form->get('phil_terms_version_terms')->getConfig();
        self::assertInstanceOf(HiddenType::class, $version->getType()->getInnerType());
        self::assertFalse($version->getMapped());
        self::assertSame('2', $version->getData());
    }

    public function testTheListedPoliciesOnly(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('conduct', ['en' => 'x']);

        self::assertSame(['phil_terms_accept_conduct', 'phil_terms_version_conduct', 'name'], array_keys($this->form($kit, ['privacy', 'conduct'])->all()));
    }

    public function testAnUndeclaredPolicyIsRefused(): void
    {
        $this->expectException(InvalidTerms::class);

        $this->form($this->kit(), ['cookies']);
    }

    public function testTheOptionIsABooleanOrAList(): void
    {
        $this->expectException(InvalidOptionsException::class);

        $this->form($this->kit(), 'terms');
    }

    public function testATitleWithoutTranslationIsTheKey(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('conduct', ['en' => 'x']);

        $form = $this->form($kit, ['conduct'], $kit->translator);
        self::assertSame(['%policy%' => 'conduct', '%version%' => '1'], $form->get('phil_terms_accept_conduct')->getConfig()->getOption('label_translation_parameters'));

        $form = $this->form($kit, ['conduct']);
        self::assertSame(['%policy%' => 'conduct', '%version%' => '1'], $form->get('phil_terms_accept_conduct')->getConfig()->getOption('label_translation_parameters'));
    }

    public function testTheBoxMustBeTicked(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, true);
        $form->submit(['name' => 'Ada', 'phil_terms_version_terms' => '2']);

        self::assertFalse($form->isValid());
        self::assertSame('Tick the box to accept.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());
        self::assertSame('signup.required', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessageTemplate());
    }

    public function testTheVersionShownMustStillBeInForce(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, true);
        $kit->terms->publish('terms', ['en' => 'three'], material: false);
        $kit->catalog->reset();

        $form->submit(['name' => 'Ada', 'phil_terms_accept_terms' => '1', 'phil_terms_version_terms' => '2']);

        self::assertFalse($form->isValid());
        self::assertSame('This version was replaced: read the new one before accepting it.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());
        self::assertSame('signup.changed', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessageTemplate());
    }

    public function testTheErrorsAreTranslated(): void
    {
        $kit = $this->kit();
        $kit->translator->setLocale('fr');

        $form = $this->form($kit, true, $kit->translator);
        $form->submit(['name' => 'Ada']);

        self::assertSame('Cochez la case pour accepter.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());
        self::assertSame(['%policy%' => 'conditions d’utilisation', '%version%' => '2'], $form->get('phil_terms_accept_terms')->getConfig()->getOption('label_translation_parameters'));
    }

    public function testWithoutTranslatorTheMessagesAreInEnglishAndTheTitleIsTheKey(): void
    {
        $kit = $this->kit();
        $factory = Forms::createFormFactoryBuilder()->addTypeExtension(new TermsAcceptanceTypeExtension($kit->policies, $kit->catalog, $kit->clock))->getFormFactory();

        $form = $factory->createBuilder(FormType::class, null, [TermsAcceptanceTypeExtension::OPTION => true])->getForm();
        self::assertSame(['%policy%' => 'terms', '%version%' => '2'], $form->get('phil_terms_accept_terms')->getConfig()->getOption('label_translation_parameters'));
        $form->submit([]);
        self::assertSame('Tick the box to accept.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());

        $form = $factory->createBuilder(FormType::class, null, [TermsAcceptanceTypeExtension::OPTION => true])->getForm();
        $kit->terms->publish('terms', ['en' => 'three'], material: false);
        $kit->catalog->reset();

        $form->submit(['phil_terms_accept_terms' => '1', 'phil_terms_version_terms' => '2']);
        self::assertSame('This version was replaced: read the new one before accepting it.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());
    }

    public function testAPolicyWithoutVersionIsSkippedAndTheNextOneChecked(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, ['privacy', 'terms']);
        $form->submit(['name' => 'Ada', 'phil_terms_version_terms' => '2']);

        self::assertSame('Tick the box to accept.', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessage());
    }

    public function testAVersionGoneSinceTheFormWasShownIsAChange(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, ['terms']);
        $kit->versions->clear();
        $kit->catalog->reset();

        $form->submit(['phil_terms_accept_terms' => '1', 'phil_terms_version_terms' => '2']);

        self::assertSame('signup.changed', $form->get('phil_terms_accept_terms')->getErrors()->current()->getMessageTemplate());
    }

    public function testAPolicyWithoutVersionAddsNothing(): void
    {
        $kit = new Kit();
        $form = $this->form($kit, true);
        $form->submit(['name' => 'Ada']);

        self::assertSame(['name'], array_keys($form->all()));
        self::assertTrue($form->isValid());
    }

    public function testTheAcceptancesOfAValidFormAreRecorded(): void
    {
        $kit = $this->kit();
        $kit->terms->publish('privacy', ['en' => 'x'], effectiveAt: new DateTimeImmutable('2026-06-01'));

        $kit->terms->publish('conduct', ['en' => 'x']);

        $form = $this->form($kit, ['terms', 'conduct']);
        $form->add('newsletter', CheckboxType::class);
        $form->submit(['name' => 'Ada', 'newsletter' => '1', 'phil_terms_accept_terms' => '1', 'phil_terms_version_terms' => '2', 'phil_terms_accept_conduct' => '1', 'phil_terms_version_conduct' => '1']);

        $acceptances = new SignupAcceptances($kit->terms)->record($form, '42', '192.0.2.7');

        self::assertCount(2, $acceptances);
        self::assertSame(['42', 'terms', 2, AcceptanceChannel::Signup], [$acceptances[0]->member, $acceptances[0]->policy, $acceptances[0]->version, $acceptances[0]->channel]);
        self::assertSame(['conduct', 1], [$acceptances[1]->policy, $acceptances[1]->version]);
        self::assertSame($acceptances, $kit->acceptances->acceptances);
    }

    public function testOnlyAValidFormCarriesAcceptances(): void
    {
        $kit = $this->kit();
        $recorder = new SignupAcceptances($kit->terms);

        foreach ([[], ['name' => 'Ada']] as $submitted) {
            $form = $this->form($kit, true);

            if ([] !== $submitted) {
                $form->submit($submitted);
            }

            try {
                $recorder->record($form, '42');
                self::fail('Recorded.');
            } catch (InvalidTerms $invalidTerms) {
                self::assertSame('form_not_valid', $invalidTerms->reason);
            }
        }

        self::assertSame([], $kit->acceptances->acceptances);
    }

    public function testAFormWithoutTermsRecordsNothing(): void
    {
        $kit = $this->kit();
        $form = $this->form($kit, false);
        $form->submit(['name' => 'Ada']);

        self::assertSame([], new SignupAcceptances($kit->terms)->record($form, '42'));
    }

    private function kit(): Kit
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->publish('terms', ['en' => 'two'], material: false);

        return $kit;
    }

    /**
     * @return FormInterface<mixed>
     */
    private function form(Kit $kit, mixed $option, ?TranslatorInterface $translator = null): FormInterface
    {
        $factory = $this->factory($kit, $translator);
        $options = null === $option ? [] : [TermsAcceptanceTypeExtension::OPTION => $option];

        return $factory->createBuilder(FormType::class, null, $options)->add('name', TextType::class)->getForm();
    }

    private function factory(Kit $kit, ?TranslatorInterface $translator): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new TermsAcceptanceTypeExtension($kit->policies, $kit->catalog, $kit->clock, $translator ?? $kit->translator))
            ->getFormFactory();
    }
}
