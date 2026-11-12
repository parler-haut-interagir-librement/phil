<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure;

use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveLabels;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveTypeExtension;
use Phil\FormsAutosave\Infrastructure\Form\FormStructure;
use Phil\FormsAutosave\Infrastructure\Http\DoneCookie;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\Http\SubmittedDraft;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Phil\FormsAutosave\Tests\Unit\Fixture\FixedOwner;
use Phil\FormsAutosave\Tests\Unit\Fixture\Post;
use Phil\FormsAutosave\Tests\Unit\Fixture\Router;
use Phil\FormsAutosave\Tests\Unit\Fixture\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Csrf\SameOriginCsrfTokenManager;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AutosaveTypeExtension::class)]
#[CoversClass(FormStructure::class)]
#[CoversClass(AutosaveLabels::class)]
final class FormTest extends TestCase
{
    private const string C = 'data-phil--forms-autosave--autosave-';

    private RequestStack $requests;

    private DraftKeys $keys;

    private FixedOwner $owner;

    private Drafts $drafts;

    protected function setUp(): void
    {
        $this->requests = new RequestStack();
        $this->requests->push(new Request());

        $this->keys = new DraftKeys('secret');
        $this->owner = new FixedOwner();
        $this->drafts = new Drafts(new InMemoryDraftStore(), new PlainSealer(), $this->keys, new MockClock('2026-11-08 15:00:00 UTC'), 60);
    }

    public function testTheExtensionExtendsEveryForm(): void
    {
        self::assertSame([FormType::class], [...AutosaveTypeExtension::getExtendedTypes()]);
        self::assertSame('phil--forms-autosave--autosave', AutosaveTypeExtension::CONTROLLER);

        $resolver = new OptionsResolver();
        $this->extension()->configureOptions($resolver);
        self::assertSame('On a root form, true or {subject, server} keeps its input as a draft; on a field, false keeps it out, true puts it in.', $resolver->getInfo('autosave'));
    }

    public function testWithoutTheServerArgumentDraftsStayInTheBrowser(): void
    {
        $extension = new AutosaveTypeExtension($this->keys, new AutosaveLabels(), $this->requests, 60, 800, 4096, owner: $this->owner, drafts: $this->drafts, router: new Router(), guard: new DraftRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [DraftRequest::TOKEN_ID])));
        $attr = self::attr(Forms::createFormFactoryBuilder()->addTypeExtension($extension)->getFormFactory()->createNamed('post', FormType::class, null, ['autosave' => true]));

        self::assertArrayNotHasKey(self::C.'endpoint-value', $attr);
    }

    public function testASubmittedFormWithoutTheOptionIsNotKept(): void
    {
        $request = $this->requests->getCurrentRequest();
        self::assertNotNull($request);
        $this->factory(server: true)->createNamed('post', FormType::class)->add('title', TextType::class)->submit(['title' => 'x']);

        self::assertNull($request->attributes->get(DoneCookie::ATTRIBUTE));
    }

    public function testEachSubmittedFormOfARequestIsKept(): void
    {
        $request = $this->requests->getCurrentRequest();
        self::assertNotNull($request);
        $this->factory()->createNamed('first', FormType::class, null, ['autosave' => true])->submit([]);
        $this->factory(owner: false)->createNamed('second', FormType::class, null, ['autosave' => true])->submit([]);

        $submitted = $request->attributes->get(DoneCookie::ATTRIBUTE);
        self::assertIsArray($submitted);
        self::assertCount(2, $submitted);
        self::assertInstanceOf(SubmittedDraft::class, $submitted[1]);
        self::assertSame($this->keys->key(FormType::class.'#second', null, null)->value, $submitted[1]->key->value, 'without an owner, a visitor');
    }

    public function testOutsideARequestWithoutTheServerStoreNothingIsDeleted(): void
    {
        $this->requests->pop();
        $extension = new AutosaveTypeExtension($this->keys, new AutosaveLabels(), $this->requests, 60, 800, 4096, true, $this->owner);
        Forms::createFormFactoryBuilder()->addTypeExtension($extension)->getFormFactory()->createNamed('post', FormType::class, null, ['autosave' => true])->submit([]);

        $this->expectNotToPerformAssertions();
    }

    public function testAFormWithoutTheOptionIsLeftAlone(): void
    {
        $view = $this->factory()->createNamed('post', FormType::class)->add('title', TextType::class)->createView();

        self::assertSame([], $view->vars['attr']);
        self::assertSame([], $view['title']->vars['attr']);
    }

    public function testTheFormTagCarriesTheControllerAndItsValues(): void
    {
        $form = $this->factory()->createNamed('post', FormType::class, new Post(42), ['autosave' => true, 'attr' => ['data-controller' => 'other', 'class' => 'x']])
            ->add('title', TextType::class);
        $attr = self::attr($form);

        self::assertSame([
            'data-controller' => 'other phil--forms-autosave--autosave',
            'class' => 'x',
            self::C.'key-value' => $this->keys->key(FormType::class.'#post', '42', 'ada')->value,
            self::C.'version-value' => FormStructure::version($form),
            self::C.'ttl-value' => '3600',
            self::C.'debounce-value' => '800',
            self::C.'max-bytes-value' => '4096',
            self::C.'labels-value' => json_encode(AutosaveLabels::ENGLISH, \JSON_THROW_ON_ERROR),
        ], $attr);
        self::assertSame('phil--forms-autosave--autosave', self::attr($this->factory()->createNamed('post', FormType::class, null, ['autosave' => []]))['data-controller']);
    }

    public function testTheKeyFollowsTheSubjectAndTheUser(): void
    {
        $key = fn (mixed $data, array $autosave = []): string => self::attr($this->factory()->createNamed('post', FormType::class, $data, ['autosave' => $autosave, 'data_class' => null]))[self::C.'key-value'];

        self::assertSame($this->keys->key(FormType::class.'#post', '7', 'ada')->value, $key(new Post(7)));
        self::assertSame($this->keys->key(FormType::class.'#post', 'abc', 'ada')->value, $key(new Post('abc')));
        self::assertSame($this->keys->key(FormType::class.'#post', null, 'ada')->value, $key(new Post()), 'a new record has no subject');
        self::assertSame($this->keys->key(FormType::class.'#post', null, 'ada')->value, $key(new stdClass()));
        self::assertSame($this->keys->key(FormType::class.'#post', null, 'ada')->value, $key(['id' => 3]));
        self::assertSame($this->keys->key(FormType::class.'#post', null, 'ada')->value, $key(new Post(7), ['subject' => false]));
        self::assertSame($this->keys->key(FormType::class.'#post', 'chapter-2', 'ada')->value, $key(new Post(7), ['subject' => 'chapter-2']));
        self::assertSame($this->keys->key(FormType::class.'#post', '9', 'ada')->value, $key(null, ['subject' => 9]));
        self::assertSame($this->keys->key(FormType::class.'#post', 'id-7', 'ada')->value, $key(new Post(7), ['subject' => static fn (Post $post): string => 'id-'.$post->getId()]));
        self::assertSame($this->keys->key(FormType::class.'#post', null, 'ada')->value, $key(new Post(7), ['subject' => static fn (): null => null]));
        self::assertSame($this->keys->key(FormType::class.'#post', 'stringable', 'ada')->value, $key(null, ['subject' => new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        }]));

        $this->owner->user = null;
        self::assertSame($this->keys->key(FormType::class.'#post', '7', null)->value, $key(new Post(7)));
        self::assertSame($this->keys->key(FormType::class.'#post', '7', null)->value, self::attr($this->factory(owner: false)->createNamed('post', FormType::class, new Post(7), ['autosave' => true]))[self::C.'key-value']);
    }

    public function testASubjectOfAnotherKindIsRefused(): void
    {
        $this->expectException(InvalidDraft::class);
        $this->expectExceptionMessageIsOrContains('The subject of a draft is a text, a number or null.');

        $this->factory()->createNamed('post', FormType::class, null, ['autosave' => ['subject' => static fn (): array => []]])->createView();
    }

    public function testABadOptionIsRefused(): void
    {
        $refused = 0;

        foreach ([['autosave' => 'yes'], ['autosave' => ['subject' => true]], ['autosave' => ['server' => 'no']], ['autosave' => ['other' => 1]], ['autosave' => ['subject' => 1.5]]] as $options) {
            try {
                $this->factory()->createNamed('post', FormType::class, null, $options);
                self::fail((string) json_encode($options));
            } catch (ExceptionInterface) {
                ++$refused;
            }
        }

        self::assertSame(5, $refused);

        $this->expectException(InvalidOptionsException::class);
        $this->factory()->createNamed('post', FormType::class, null, ['autosave' => ['subject' => true]]);
    }

    public function testTheFieldsAreMarkedForTheController(): void
    {
        $view = $this->factory()->createNamed('post', FormType::class, null, ['autosave' => true])
            ->add('title', TextType::class)
            ->add('secret', TextType::class, ['autosave' => false])
            ->add('id', HiddenType::class, ['autosave' => true])
            ->add('ref', HiddenType::class, ['autosave' => ['server' => false]])
            ->add('body', BlockEditorLike::class)
            ->add('hiddenBody', BlockEditorLike::class, ['autosave' => false])
            ->createView();

        self::assertSame([], $view['title']->vars['attr']);
        self::assertSame(['data-phil-autosave' => 'off'], $view['secret']->vars['attr']);
        self::assertSame(['data-phil-autosave' => 'on'], $view['id']->vars['attr']);
        self::assertSame(['data-phil-autosave' => 'on'], $view['ref']->vars['attr']);
        self::assertSame(['data-phil-autosave' => 'on', 'data-phil-autosave-remount' => 'phil--block-editor-bundle--editor'], $view['body']->vars['attr']);
        self::assertSame(['data-phil-autosave' => 'off'], $view['hiddenBody']->vars['attr']);
    }

    public function testAnInvalidSubmissionIsToldSoTheSubmittedValuesStay(): void
    {
        $form = $this->factory()->createNamed('post', FormType::class, null, ['autosave' => true])->add('title', TextType::class);
        $form->submit(['title' => 'x']);
        $form->addError(new FormError('Wrong.'));

        self::assertSame('true', self::attr($form)[self::C.'invalid-value']);

        $valid = $this->factory()->createNamed('post', FormType::class, null, ['autosave' => true])->add('title', TextType::class);
        $valid->submit(['title' => 'x']);
        self::assertArrayNotHasKey(self::C.'invalid-value', self::attr($valid));
        self::assertArrayNotHasKey(self::C.'invalid-value', self::attr($this->factory()->createNamed('post', FormType::class, null, ['autosave' => true])));
    }

    public function testTheServerStoreGivesTheUrlOfTheDraftAndTheNameOfItsCookie(): void
    {
        $attr = self::attr($this->factory(server: true)->createNamed('post', FormType::class, new Post(1), ['autosave' => true]));
        $key = $this->keys->key(FormType::class.'#post', '1', 'ada')->value;

        self::assertSame('/_autosave/drafts/'.$key, $attr[self::C.'endpoint-value']);
        self::assertSame('csrf-token', $attr[self::C.'csrf-value']);

        $has = static fn (FormFactoryInterface $factory, array $autosave = []): bool => \array_key_exists(self::C.'endpoint-value', self::attr($factory->createNamed('post', FormType::class, null, ['autosave' => $autosave])))
            || \array_key_exists(self::C.'csrf-value', self::attr($factory->createNamed('post', FormType::class, null, ['autosave' => $autosave])));

        self::assertTrue($has($this->factory(server: true)));
        self::assertFalse($has($this->factory(server: true), ['server' => false]), 'a form can keep its drafts in the browser alone');
        self::assertFalse($has($this->factory()), 'without the server store');
        self::assertFalse($has($this->factory(server: true, router: false)), 'without a router');
        self::assertFalse($has($this->factory(server: true, router: new Router(false))), 'without the routes');
        self::assertFalse($has($this->factory(server: true, guard: false)), 'without the guard');
        $this->owner->user = null;
        self::assertFalse($has($this->factory(server: true)), 'for a visitor');
    }

    public function testASubmittedFormIsKeptInTheRequestUntilTheResponse(): void
    {
        $key = DraftKey::of($this->keys->key(FormType::class.'#post', '5', 'ada')->value);
        $this->drafts->save('ada', $key, null, DraftContent::of('v', []));
        $request = $this->requests->getCurrentRequest();
        self::assertNotNull($request);

        $form = $this->factory(server: true)->createNamed('post', FormType::class, new Post(5), ['autosave' => true, 'data_class' => Post::class])
            ->add('title', TextType::class, ['autosave' => true]);
        $form->submit(['title' => 'Done']);

        $submitted = $request->attributes->get(DoneCookie::ATTRIBUTE);
        self::assertIsArray($submitted);
        self::assertCount(1, $submitted, 'the root form alone');
        self::assertInstanceOf(SubmittedDraft::class, $submitted[0]);
        self::assertSame($form, $submitted[0]->form);
        self::assertSame($key->value, $submitted[0]->key->value);
        self::assertSame('ada', $submitted[0]->user);
        self::assertNotNull($this->drafts->read('ada', $key), 'nothing is deleted before the response');

        $form->get('title')->addError(new FormError('Added by the application.'));
        self::assertNull($this->respond($request), 'an error added after the submission keeps the draft');
        self::assertNotNull($this->drafts->read('ada', $key));

        $valid = $this->factory(server: true)->createNamed('post', FormType::class, new Post(5), ['autosave' => true, 'data_class' => Post::class])->add('title', TextType::class);
        $request->attributes->remove(DoneCookie::ATTRIBUTE);
        $valid->submit(['title' => 'Done']);
        self::assertSame($key->value, $this->respond($request));
        self::assertNull($this->drafts->read('ada', $key));
    }

    public function testTheServerCopyIsNamedOnlyWhenTheFormKeepsOne(): void
    {
        $request = $this->requests->getCurrentRequest();
        self::assertNotNull($request);

        foreach ([[true, ['server' => false], 'ada'], [false, true, 'ada'], [true, true, null]] as [$server, $autosave, $user]) {
            $this->owner->user = $user;
            $request->attributes->remove(DoneCookie::ATTRIBUTE);
            $this->factory(server: $server)->createNamed('post', FormType::class, null, ['autosave' => $autosave])->submit([]);
            $submitted = $request->attributes->get(DoneCookie::ATTRIBUTE);
            self::assertIsArray($submitted);
            self::assertInstanceOf(SubmittedDraft::class, $submitted[0]);
            self::assertNull($submitted[0]->user);
        }
    }

    public function testOutsideARequestAValidFormLosesItsServerCopyAtOnce(): void
    {
        $this->requests->pop();
        $key = DraftKey::of($this->keys->key(FormType::class.'#post', null, 'ada')->value);
        $this->drafts->save('ada', $key, null, DraftContent::of('v', []));

        $this->factory(server: true)->createNamed('post', FormType::class, null, ['autosave' => true])->add('flag', Erring::class, ['mapped' => false])->submit(['flag' => '1']);
        self::assertNotNull($this->drafts->read('ada', $key), 'an invalid form keeps it');

        $this->factory(server: true)->createNamed('post', FormType::class, null, ['autosave' => ['server' => false]])->submit([]);
        self::assertNotNull($this->drafts->read('ada', $key), 'a form without a server copy keeps it');

        $this->owner->user = null;
        $this->factory(server: true)->createNamed('post', FormType::class, null, ['autosave' => true])->submit([]);
        $this->owner->user = 'ada';
        self::assertNotNull($this->drafts->read('ada', $key), 'a visitor has none');

        $this->factory(server: true)->createNamed('post', FormType::class, null, ['autosave' => true])->submit([]);
        self::assertNull($this->drafts->read('ada', $key));
    }

    public function testTheVersionFollowsTheStructureNotTheData(): void
    {
        $factory = $this->factory();
        $version = static fn (FormInterface $form): string => FormStructure::version($form);
        $base = $factory->createNamed('post', FormType::class)->add('title', TextType::class)->add('body', TextareaType::class);

        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $version($base));
        self::assertSame(substr(hash('xxh128', ' '.FormType::class."\n/title ".TextType::class."\n/body ".TextareaType::class), 0, 16), $version($base));
        self::assertSame($version($base), $version($factory->createNamed('post', FormType::class, ['title' => 'x'])->add('title', TextType::class)->add('body', TextareaType::class)));
        self::assertNotSame($version($base), $version($factory->createNamed('post', FormType::class)->add('title', TextType::class)));
        self::assertNotSame($version($base), $version($factory->createNamed('post', FormType::class)->add('title', TextType::class)->add('body', TextType::class)));
        self::assertNotSame($version($base), $version($factory->createNamed('post', FormType::class)->add('heading', TextType::class)->add('body', TextareaType::class)));

        $collection = static fn (array $data, string $entry = TextType::class): FormInterface => $factory->createNamed('post', FormType::class, ['tags' => $data])->add('tags', CollectionType::class, ['entry_type' => $entry, 'allow_add' => true]);
        self::assertSame($version($collection(['a'])), $version($collection(['a', 'b', 'c'])));
        self::assertSame(substr(hash('xxh128', ' '.FormType::class."\n/tags ".CollectionType::class."\n/tags[] ".TextType::class), 0, 16), $version($collection([])));
        self::assertNotSame($version($collection(['a'])), $version($collection([1], IntegerType::class)));
        self::assertSame(FormStructure::LENGTH, 16);
    }

    public function testNestedFieldsAreDescribedByTheirPath(): void
    {
        $form = $this->factory()->createNamed('post', FormType::class)->add('address', FormType::class);
        $form->get('address')->add('street', TextType::class);

        self::assertSame(substr(hash('xxh128', ' '.FormType::class."\n/address ".FormType::class."\n/address/street ".TextType::class), 0, 16), FormStructure::version($form));
    }

    public function testACollectionWhoseEntryIsNotAClassNameIsDescribedByItsType(): void
    {
        $form = $this->factory()->createNamed('post', FormType::class)->add('tags', OddEntryType::class);

        self::assertSame(substr(hash('xxh128', ' '.FormType::class."\n/tags ".OddEntryType::class."\n/tags[] int"), 0, 16), FormStructure::version($form));
    }

    public function testTheLabelsAreTranslatedWhenTheApplicationHasATranslator(): void
    {
        self::assertSame(AutosaveLabels::ENGLISH, new AutosaveLabels()->all());
        $translated = new AutosaveLabels(new Translator())->all();

        self::assertSame(array_keys(AutosaveLabels::ENGLISH), array_keys($translated));
        self::assertSame('phil_forms_autosave/phil_forms_autosave.saved', $translated['saved']);
        self::assertSame('phil_forms_autosave/phil_forms_autosave.useServer', $translated['useServer']);
        self::assertStringContainsString('"saved":"phil_forms_autosave\/phil_forms_autosave.saved"', self::attr($this->factory(translator: true)->createNamed('post', FormType::class, null, ['autosave' => true]))[self::C.'labels-value']);
        self::assertStringContainsString('"saved":"Brouillon"', self::attr($this->factory(labels: new AutosaveLabels(new FrenchTranslator()))->createNamed('post', FormType::class, null, ['autosave' => true]))[self::C.'labels-value']);
        self::assertStringContainsString('"restored":"Rétabli"', self::attr($this->factory(labels: new AutosaveLabels(new FrenchTranslator()))->createNamed('post', FormType::class, null, ['autosave' => true]))[self::C.'labels-value']);
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return array<string, string>
     */
    private static function attr(FormInterface $form): array
    {
        $attr = $form->createView()->vars['attr'];
        self::assertIsArray($attr);

        /** @var array<string, string> $attr */
        return $attr;
    }

    private function respond(Request $request): ?string
    {
        $event = new ResponseEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
        new DoneCookie(new MockClock(), $this->drafts)($event);

        return ($event->getResponse()->headers->getCookies()[0] ?? null)?->getValue();
    }

    private function extension(): AutosaveTypeExtension
    {
        return new AutosaveTypeExtension($this->keys, new AutosaveLabels(), $this->requests, 60, 800, 4096);
    }

    private function factory(bool $server = false, bool|Router $router = true, bool $guard = true, bool $owner = true, bool $translator = false, ?AutosaveLabels $labels = null): FormFactoryInterface
    {
        $extension = new AutosaveTypeExtension(
            $this->keys,
            $labels ?? new AutosaveLabels($translator ? new Translator() : null),
            $this->requests,
            3600,
            800,
            4096,
            $server,
            $owner ? $this->owner : null,
            $this->drafts,
            $router instanceof Router ? $router : ($router ? new Router() : null),
            $guard ? new DraftRequest(new SameOriginCsrfTokenManager($this->requests, null, null, [DraftRequest::TOKEN_ID])) : null,
        );

        return Forms::createFormFactoryBuilder()->addTypeExtension($extension)->getFormFactory();
    }
}

/**
 * A field whose block prefix is that of the block editor.
 *
 * @extends AbstractType<mixed>
 */
final class BlockEditorLike extends AbstractType
{
    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'phil_block_editor';
    }
}

/**
 * A field that makes its form invalid once submitted.
 *
 * @extends AbstractType<mixed>
 */
final class Erring extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $event->getForm()->getParent()?->addError(new FormError('Wrong.'));
        });
    }

    public function getParent(): string
    {
        return CheckboxType::class;
    }
}

/**
 * A field with an `entry_type` option that is no class name.
 *
 * @extends AbstractType<mixed>
 */
final class OddEntryType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('entry_type', 3);
    }
}

/**
 * Translates two labels with accents, to see them kept as they are.
 */
final class FrenchTranslator implements TranslatorInterface
{
    /**
     * @param array<mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return ['phil_forms_autosave.saved' => 'Brouillon', 'phil_forms_autosave.restored' => 'Rétabli'][$id] ?? $id;
    }

    public function getLocale(): string
    {
        return 'fr';
    }
}
