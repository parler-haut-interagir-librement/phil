<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Form\EntryType;
use Phil\FormsBundle\Infrastructure\Http\FormController;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\FormsBundle\Tests\Unit\Fixtures\SpamProtectionOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms as FormFactories;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader as TranslationLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(FormController::class)]
#[CoversClass(EntryType::class)]
final class FormControllerTest extends TestCase
{
    private DoctrineFormRepository $repository;

    private Form $form;

    private string $defaultLocale;

    #[Override]
    protected function setUp(): void
    {
        // `Request::setLocale()` sets PHP's default locale too: put it back, for the tests that run after.
        $this->defaultLocale = Locale::getDefault();
        $this->repository = new DoctrineFormRepository(Database::entityManager());
        $this->form = Forms::registration();
        $this->repository->save($this->form);
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->defaultLocale);
    }

    public function testThePageShowsTheForm(): void
    {
        $response = $this->controller()(Request::create('/forms/inscription'), 'inscription');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Inscription au tournoi|name,email,site,born,team,message,consent|sent:no|errors:', $response->getContent());
        self::assertSame('Inscription au tournoi|name,email,site,born,team,message,consent|sent:yes|errors:', $this->controller()(Request::create('/forms/inscription?sent=1'), 'inscription')->getContent());
    }

    public function testAnEntryIsTakenThenThePageRedirected(): void
    {
        $request = Request::create('/index.php/forms/inscription', 'POST', ['phil_form' => ['name' => 'Ada', 'email' => 'ada@example.com', 'site' => 'https://example.com', 'born' => '1815-12-10', 'team' => 'Bleue', 'message' => 'Bonjour', 'consent' => '1']], server: ['SCRIPT_FILENAME' => '/index.php', 'SCRIPT_NAME' => '/index.php', 'PHP_SELF' => '/index.php/forms/inscription']);
        $request->setLocale('fr');

        $response = $this->controller()($request, 'inscription');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/index.php/forms/inscription?sent=1', $response->headers->get('Location'));
        $entry = $this->repository->entries($this->form)[0] ?? null;
        self::assertNotNull($entry);
        self::assertSame([['name' => 'Ada', 'email' => 'ada@example.com', 'site' => 'https://example.com', 'born' => '1815-12-10', 'team' => 'Bleue', 'message' => 'Bonjour', 'consent' => true], 'fr', null], [$entry->values, $entry->locale, $entry->subject]);

        $this->controller(subject: 'member-7')(Request::create('/forms/inscription', 'POST', ['phil_form' => ['name' => 'Grace', 'email' => 'grace@example.com', 'consent' => '1']]), 'inscription');
        self::assertSame(['member-7'], array_map(static fn (Entry $entry): ?string => $entry->subject, $this->repository->about('member-7', '')), 'the person signed in');

        $spaced = $this->controller(subject: 'Jean Dupont')(Request::create('/forms/inscription', 'POST', ['phil_form' => ['name' => 'Jean', 'email' => 'jean@example.com', 'consent' => '1']]), 'inscription');
        self::assertSame(303, $spaced->getStatusCode(), 'a signed-in user whose identifier holds a space sends the form');
        self::assertCount(1, $this->repository->about(Entry::subjectKey('Jean Dupont'), ''));
    }

    public function testRefusedValuesComeBackOnTheirFields(): void
    {
        $response = $this->controller()(Request::create('/forms/inscription', 'POST', ['phil_form' => ['name' => '', 'email' => 'ada', 'team' => 'Bleue']]), 'inscription');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Inscription au tournoi|name,email,site,born,team,message,consent|sent:no|errors:name=Obligatoire;email=Pas un courriel;consent=Obligatoire;', $response->getContent());
        self::assertSame([], $this->repository->entries($this->form));

        $untranslated = $this->controller(translated: false)(Request::create('/forms/inscription', 'POST', ['phil_form' => ['name' => 'Ada', 'email' => 'ada@example.com']]), 'inscription');
        self::assertSame('Inscription au tournoi|name,email,site,born,team,message,consent|sent:no|errors:consent=phil_forms.error.required;', $untranslated->getContent());
    }

    public function testAnAgeRefusedSaysTheAge(): void
    {
        $form = Form::create('camp', 'Camp', [Field::of('born', 'Né le', FieldType::BirthDate, minAge: 18, maxAge: 25, referenceDate: '2026-07-01')]);
        $this->repository->save($form);

        $young = $this->controller()(Request::create('/forms/camp', 'POST', ['phil_form' => ['born' => '2010-01-01']]), 'camp');
        $old = $this->controller()(Request::create('/forms/camp', 'POST', ['phil_form' => ['born' => '1990-01-01']]), 'camp');

        self::assertSame([422, 'Camp|born|sent:no|errors:born=Au moins 18 ans;'], [$young->getStatusCode(), $young->getContent()]);
        self::assertSame('Camp|born|sent:no|errors:born=Au plus 25 ans;', $old->getContent());
        self::assertSame(303, $this->controller()(Request::create('/forms/camp', 'POST', ['phil_form' => ['born' => '2008-07-01']]), 'camp')->getStatusCode(), '18 on the reference date');
    }

    public function testAFormThatIsUnknownOrClosedIsNotFound(): void
    {
        $this->form->close();
        $this->repository->save($this->form);

        foreach (['inscription' => 'There is no form "inscription" taking entries.', 'none' => 'There is no form "none" taking entries.'] as $key => $message) {
            try {
                $this->controller()(Request::create('/forms/'.$key), $key);
                self::fail($key);
            } catch (NotFoundHttpException $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    public function testTheFormIsBuiltFromItsDefinition(): void
    {
        $form = $this->formFactory()->createNamed('phil_form', EntryType::class, null, ['phil_form' => $this->form]);
        $types = [];
        $options = [];

        foreach ($form as $name => $child) {
            $types[$name] = $child->getConfig()->getType()->getInnerType()::class;
            $options[$name] = [$child->getConfig()->getOption('label'), $child->getConfig()->getOption('required'), $child->getConfig()->getOption('attr')];
        }

        self::assertSame(['name' => TextType::class, 'email' => EmailType::class, 'site' => UrlType::class, 'born' => DateType::class, 'team' => ChoiceType::class, 'message' => TextareaType::class, 'consent' => CheckboxType::class], $types);
        self::assertSame([
            'name' => ['Nom', true, ['maxlength' => 255]],
            'email' => ['Courriel', true, ['maxlength' => 255]],
            'site' => ['Site', false, ['maxlength' => 255]],
            'born' => ['Date de naissance', false, []],
            'team' => ['Équipe', false, []],
            'message' => ['Message', false, ['maxlength' => 5000]],
            'consent' => ['J’accepte le règlement', true, []],
        ], $options);
        self::assertSame(['scope' => 'form-inscription', 'content_fields' => ['name', 'message']], $form->getConfig()->getOption('spam_protection'));
        self::assertSame(['single_text', 'string'], [$form->get('born')->getConfig()->getOption('widget'), $form->get('born')->getConfig()->getOption('input')]);
        self::assertSame(['Bleue' => 'Bleue', 'Verte' => 'Verte'], $form->get('team')->getConfig()->getOption('choices'));
        self::assertSame('', $form->get('team')->getConfig()->getOption('placeholder'));
        self::assertSame('phil_form_entry', $form->getConfig()->getType()->getBlockPrefix());

        try {
            $this->formFactory()->create(EntryType::class, null, ['phil_form' => 'inscription']);
            self::fail('A form was built from a key.');
        } catch (InvalidOptionsException $invalidOptionsException) {
            self::assertStringContainsString('"phil_form"', $invalidOptionsException->getMessage());
        }

        $required = Form::create('a', 'A', [Field::of('pick', 'Choix', FieldType::Choice, true, ['x'])]);
        self::assertNull($this->formFactory()->create(EntryType::class, null, ['phil_form' => $required])->get('pick')->getConfig()->getOption('placeholder'));
    }

    public function testADisqualifyingChoiceShowsTheFormsMessageAndAFullChoiceIsDisabled(): void
    {
        $form = Form::create('workshop', 'Workshop', [
            Field::of('age', 'Âge', FieldType::Choice, choices: ['Moins de 18', '18 et plus'], disqualifying: ['Moins de 18' => 'Réservé aux adultes.']),
            Field::of('slot', 'Plage', FieldType::Choice, choices: ['Matin', 'Soir'], capacities: ['Matin' => 1]),
            Field::of('born', 'Naissance', FieldType::BirthDate),
        ]);
        $this->repository->save($form);
        $controller = $this->controller(places: true);

        $refused = $controller(Request::create('/forms/workshop', 'POST', ['phil_form' => ['age' => 'Moins de 18', 'slot' => 'Matin', 'born' => '2999-01-01']]), 'workshop');
        self::assertSame('Workshop|age,slot,born|sent:no|errors:age=Réservé aux adultes.;born=Pas une date de naissance;', $refused->getContent());
        self::assertSame(303, $controller(Request::create('/forms/workshop', 'POST', ['phil_form' => ['age' => '18 et plus', 'slot' => 'Matin']]), 'workshop')->getStatusCode());

        $full = $controller(Request::create('/forms/workshop', 'POST', ['phil_form' => ['age' => '18 et plus', 'slot' => 'Matin']]), 'workshop');
        self::assertSame(422, $full->getStatusCode());
        self::assertStringContainsString('errors:slot=', (string) $full->getContent(), 'a choice no longer offered is refused');
        self::assertSame(['slot' => ['Matin']], $this->submissions(true)->fullChoices($form));

        $built = $this->formFactory()->createNamed('phil_form', EntryType::class, null, ['phil_form' => $form, 'full_choices' => ['slot' => ['Matin']]]);
        $attr = $built->get('slot')->getConfig()->getOption('choice_attr');
        self::assertIsCallable($attr);
        self::assertSame([['disabled' => 'disabled'], []], [$attr('Matin'), $attr('Soir')]);
        self::assertSame([DateType::class, 'single_text', 'string'], [$built->get('born')->getConfig()->getType()->getInnerType()::class, $built->get('born')->getConfig()->getOption('widget'), $built->get('born')->getConfig()->getOption('input')]);
        $none = $this->formFactory()->createNamed('phil_form', EntryType::class, null, ['phil_form' => $form]);
        $noneAttr = $none->get('slot')->getConfig()->getOption('choice_attr');
        self::assertIsCallable($noneAttr);
        self::assertSame([], $noneAttr('Matin'));
    }

    public function testTheFullChoicesAreAnArray(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->formFactory()->create(EntryType::class, null, ['phil_form' => $this->form, 'full_choices' => 'slot']);
    }

    public function testTheFormKeepsTheFieldsOfItsTiers(): void
    {
        $form = Form::create('t', 'T', [Field::of('all', 'Tous', FieldType::Text), Field::of('vip', 'VIP', FieldType::Text, tiers: ['vip'])]);

        self::assertSame(['all'], array_keys($this->formFactory()->create(EntryType::class, null, ['phil_form' => $form])->all()));
        self::assertSame(['all', 'vip'], array_keys($this->formFactory()->create(EntryType::class, null, ['phil_form' => $form, 'tiers' => ['vip']])->all()));
        $this->expectException(InvalidOptionsException::class);
        $this->formFactory()->create(EntryType::class, null, ['phil_form' => $form, 'tiers' => [1]]);
    }

    public function testWithBrowserConditionsTheConditionalRowsAreNamedAndTheViewHoldsTheSteps(): void
    {
        $blue = ['==' => [['var' => 'team'], 'Blue']];
        $form = Form::create('t', 'T', [
            Field::of('team', 'Équipe', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('captain', 'Capitaine', FieldType::Checkbox, shownWhen: $blue),
            Field::of('meal', 'Repas', FieldType::Text, tiers: ['vip']),
            Field::of('diet', 'Régime', FieldType::Text, shownWhen: ['!=' => [['var' => 'meal'], 'Aucun']]),
        ]);
        $browser = $this->formFactory(new EntryType(browserConditions: true));

        $built = $browser->create(EntryType::class, null, ['phil_form' => $form]);
        self::assertSame(
            [[], ['data-form-logic-field' => 'captain'], ['data-form-logic-field' => 'diet']],
            [$built->get('team')->getConfig()->getOption('row_attr'), $built->get('captain')->getConfig()->getOption('row_attr'), $built->get('diet')->getConfig()->getOption('row_attr')],
        );
        self::assertSame([['field' => 'captain', 'when' => $blue], ['field' => 'diet', 'when' => false]], $built->createView()->vars['phil_form_logic_steps'], 'the meal, for a tier, is not on the page');
        self::assertSame(
            [['field' => 'captain', 'when' => $blue], ['field' => 'diet', 'when' => ['!=' => [['var' => 'meal'], 'Aucun']]]],
            $browser->create(EntryType::class, null, ['phil_form' => $form, 'tiers' => ['vip']])->createView()->vars['phil_form_logic_steps'],
        );
        self::assertNull($browser->create(EntryType::class, null, ['phil_form' => $this->form])->createView()->vars['phil_form_logic_steps'], 'no condition, nothing for the browser');

        $plain = $this->formFactory()->create(EntryType::class, null, ['phil_form' => $form]);
        self::assertSame([], $plain->get('captain')->getConfig()->getOption('row_attr'));
        self::assertNull($plain->createView()->vars['phil_form_logic_steps'], 'without phil/form-logic, the page is rendered as it always was');
    }

    private function submissions(bool $places): Submissions
    {
        return new Submissions($this->repository, new NativeClock(), new HmacEmailDigester('key'), places: $places ? $this->repository : null);
    }

    private function controller(bool $translated = true, ?string $subject = null, bool $places = false): FormController
    {
        $current = null === $subject ? null : new readonly class($subject) implements CurrentSubject {
            public function __construct(
                private string $subject,
            ) {
            }

            public function current(): string
            {
                return $this->subject;
            }
        };

        return new FormController($this->repository, $this->submissions($places), $this->formFactory(), $this->twig(), 'page', $translated ? $this->translator() : null, $current);
    }

    private function formFactory(EntryType $type = new EntryType()): FormFactoryInterface
    {
        return FormFactories::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addTypeExtension(new SpamProtectionOption())
            ->addType($type)
            ->getFormFactory();
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader(['page' => '{{ definition.title }}|{{ form.children|keys|join(",") }}|sent:{{ sent ? "yes" : "no" }}|errors:{% for error in form.vars.errors.form.getErrors(true) %}{{ error.origin.name }}={{ error.message }};{% endfor %}']));
    }

    private function translator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new TranslationLoader());
        $translator->addResource('array', ['phil_forms.error.required' => 'Obligatoire', 'phil_forms.error.email' => 'Pas un courriel', 'phil_forms.error.closed' => 'Fermé', 'phil_forms.error.birth_date' => 'Pas une date de naissance', 'phil_forms.error.disqualified' => 'Disqualifié', 'phil_forms.error.too_young' => 'Au moins %age% ans', 'phil_forms.error.too_old' => 'Au plus %age% ans'], 'fr', 'phil_forms');

        return $translator;
    }
}
