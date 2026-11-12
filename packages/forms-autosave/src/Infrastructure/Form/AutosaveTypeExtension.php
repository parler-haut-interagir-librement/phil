<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Form;

use Closure;
use Override;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Phil\FormsAutosave\Infrastructure\Http\DoneCookie;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\Http\Routes;
use Phil\FormsAutosave\Infrastructure\Http\SubmittedDraft;
use Stringable;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The option `autosave`. On a root form, `true` or an array turns the controller on: the form's tag gets the
 * controller, the draft's key — derived from the form, its subject and its user —, the version of its structure, the
 * settings and the labels, and, with the server store, the URL of the draft and the name of its CSRF cookie. A
 * submitted form is kept in the request; if it is valid when the response leaves, its key is told to the browser,
 * which forgets the draft, and the server's copy is deleted. On a field, `false` keeps it out of drafts — something sensitive — and `true` puts it in, even hidden; the
 * field of `phil/block-editor-bundle` is put in, its editor mounted again when a draft is restored.
 */
final class AutosaveTypeExtension extends AbstractTypeExtension
{
    public const string CONTROLLER = 'phil--forms-autosave--autosave';

    /** The attribute that keeps a field out of drafts (`off`) or puts it in (`on`), on the field or around it. */
    public const string FIELD = 'data-phil-autosave';

    /** The attribute naming the controller to mount again around a field once its value is restored. */
    public const string REMOUNT = 'data-phil-autosave-remount';

    /** The controller of the block editor of `phil/block-editor-bundle`, and the block prefix of its field. */
    public const array BLOCK_EDITOR = ['phil_block_editor', 'phil--block-editor-bundle--editor'];

    /**
     * @param int  $seconds  how long a draft is kept in the browser
     * @param int  $debounce how long the controller waits after the last change before it saves, in milliseconds
     * @param int  $maxBytes how large a draft the controller keeps in the browser
     * @param bool $server   whether drafts are also kept on the server
     */
    public function __construct(
        private readonly DraftKeys $keys,
        private readonly AutosaveLabels $labels,
        private readonly RequestStack $requests,
        private readonly int $seconds,
        private readonly int $debounce,
        private readonly int $maxBytes,
        private readonly bool $server = false,
        private readonly ?DraftOwner $owner = null,
        private readonly ?Drafts $drafts = null,
        private readonly ?UrlGeneratorInterface $router = null,
        private readonly ?DraftRequest $guard = null,
    ) {
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('autosave', null);
        $resolver->setAllowedTypes('autosave', ['null', 'bool', 'array']);
        $resolver->setInfo('autosave', 'On a root form, true or {subject, server} keeps its input as a draft; on a field, false keeps it out, true puts it in.');
        $resolver->setNormalizer('autosave', static function (Options $options, bool|array|null $value): bool|array|null {
            if (true === $value) {
                $value = [];
            }

            if (!\is_array($value)) {
                return $value;
            }

            $settings = new OptionsResolver();
            $settings->setDefaults(['subject' => null, 'server' => true]);
            $settings->setAllowedTypes('subject', ['null', 'bool', 'int', 'string', Stringable::class, Closure::class]);
            $settings->setAllowedValues('subject', static fn (mixed $subject): bool => true !== $subject);
            $settings->setAllowedTypes('server', 'bool');

            return $settings->resolve($value);
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $autosave = $this->settings($options);

        if (null === $autosave) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($autosave): void {
            $form = $event->getForm();

            if ($form->isRoot()) {
                $this->submitted($form, $autosave);
            }
        });
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if ($form->isRoot()) {
            return;
        }

        $autosave = $options['autosave'];
        /** @var list<string> $prefixes */
        $prefixes = $view->vars['block_prefixes'];
        /** @var array<string, string> $attr */
        $attr = $view->vars['attr'];

        if (false === $autosave) {
            $attr[self::FIELD] = 'off';
        } elseif (null !== $autosave) {
            $attr[self::FIELD] = 'on';
        }

        if (false !== $autosave && \in_array(self::BLOCK_EDITOR[0], $prefixes, true)) {
            $attr[self::FIELD] = 'on';
            $attr[self::REMOUNT] = self::BLOCK_EDITOR[1];
        }

        $view->vars['attr'] = $attr;
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $autosave = $this->settings($options);

        if (!$form->isRoot() || null === $autosave) {
            return;
        }

        $user = $this->owner?->current();
        $key = $this->key($form, $autosave, $user);
        $prefix = 'data-'.self::CONTROLLER.'-';
        /** @var array<string, string> $attr */
        $attr = $view->vars['attr'];
        $attr['data-controller'] = trim(($attr['data-controller'] ?? '').' '.self::CONTROLLER);
        $attr[$prefix.'key-value'] = $key->value;
        $attr[$prefix.'version-value'] = FormStructure::version($form);
        $attr[$prefix.'ttl-value'] = (string) $this->seconds;
        $attr[$prefix.'debounce-value'] = (string) $this->debounce;
        $attr[$prefix.'max-bytes-value'] = (string) $this->maxBytes;
        $attr[$prefix.'labels-value'] = json_encode($this->labels->all(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        if ($form->isSubmitted() && !$form->isValid()) {
            $attr[$prefix.'invalid-value'] = 'true';
        }

        $endpoint = $this->endpoint($autosave, $user, $key);

        if (null !== $endpoint && $this->guard instanceof DraftRequest) {
            $attr[$prefix.'endpoint-value'] = $endpoint;
            $attr[$prefix.'csrf-value'] = $this->guard->cookieName();
        }

        $view->vars['attr'] = $attr;
    }

    /**
     * The settings of the option on a form that keeps drafts, null on one that does not.
     *
     * @param array<string, mixed> $options
     *
     * @return ?array{subject: mixed, server: bool}
     */
    private function settings(array $options): ?array
    {
        $autosave = $options['autosave'] ?? null;

        return \is_array($autosave) ? ['subject' => $autosave['subject'], 'server' => true === $autosave['server']] : null;
    }

    /**
     * Keeps the submitted form in the request until the response, which forgets its draft if it is still valid; outside
     * a request, deletes the server's copy at once when it is valid.
     *
     * @param FormInterface<mixed>                $form
     * @param array{subject: mixed, server: bool} $autosave
     */
    private function submitted(FormInterface $form, array $autosave): void
    {
        $user = $this->owner?->current();
        $key = $this->key($form, $autosave, $user);
        $owner = $this->server && $autosave['server'] ? $user : null;
        $request = $this->requests->getCurrentRequest();

        if ($request instanceof Request) {
            /** @var list<SubmittedDraft> $submitted */
            $submitted = $request->attributes->get(DoneCookie::ATTRIBUTE, []);
            $request->attributes->set(DoneCookie::ATTRIBUTE, [...$submitted, new SubmittedDraft($form, $key, $owner)]);
        } elseif (null !== $owner && $form->isValid()) {
            $this->drafts?->discard($owner, $key);
        }
    }

    /**
     * @param FormInterface<mixed>                $form
     * @param array{subject: mixed, server: bool} $autosave
     */
    private function key(FormInterface $form, array $autosave, ?string $user): DraftKey
    {
        $config = $form->getConfig();

        return $this->keys->key($config->getType()->getInnerType()::class.'#'.$form->getName(), $this->subject($config->getData(), $autosave['subject']), $user);
    }

    /**
     * The subject as the option gives it, false for none; by default, what the form's data answers to `getId()`.
     *
     * @throws InvalidDraft when a closure gives something else than a text, a number or null
     */
    private function subject(mixed $data, mixed $subject): ?string
    {
        if ($subject instanceof Closure) {
            $subject = $subject($data);
        } elseif (null === $subject && \is_object($data) && \is_callable([$data, 'getId'])) {
            $subject = \call_user_func([$data, 'getId']);
        }

        if (null === $subject || false === $subject) {
            return null;
        }

        if (\is_int($subject) || \is_string($subject) || $subject instanceof Stringable) {
            return (string) $subject;
        }

        throw InvalidDraft::because('The subject of a draft is a text, a number or null.');
    }

    /**
     * @param array{subject: mixed, server: bool} $autosave
     */
    private function endpoint(array $autosave, ?string $user, DraftKey $key): ?string
    {
        if (null === $user || !$this->server || !$autosave['server'] || !$this->router instanceof UrlGeneratorInterface) {
            return null;
        }

        try {
            return $this->router->generate(Routes::DRAFT, ['key' => $key->value]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
