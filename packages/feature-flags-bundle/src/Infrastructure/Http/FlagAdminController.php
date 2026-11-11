<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Http;

use JsonException;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Phil\FeatureFlagsBundle\Application\Edit\FlagEditor;
use Phil\FeatureFlagsBundle\Application\Edit\WritableFlagDefinitions;
use Phil\FeatureFlagsBundle\Application\Flag\CatalogEntry;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Flag\UnreadableEntry;
use Phil\FeatureFlagsBundle\Application\Toggle\FlagToggler;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\EditConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Exception\UnknownFlag;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleReason;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The admin screens: the list of flags with their current configuration, one flag in detail with its toggle,
 * a flag whose stored definition no longer reads shown with its reason (neither toggled nor edited), and — when the database store is enabled — the creation and edit forms of the flags it holds.
 *
 * The application puts the routes behind its firewall and says who the operator is ({@see ActorProvider}); every
 * change then goes through the toggle and the editor, authorized and audited there. A form posted from another
 * site is refused ({@see SameOriginGuard}). After a change, the browser is sent back to the flag with a `303`.
 */
final readonly class FlagAdminController
{
    private const string EDIT_KEYS = 'rules,experiment';

    /**
     * The outcomes a redirect after a change reports in `done`: the page shows one of these, and nothing a crafted
     * link puts in the query string.
     */
    private const array OUTCOMES = ['toggled', 'created', 'edited'];

    public function __construct(
        private FlagCatalog $catalog,
        private FlagToggler $toggler,
        private ActorProvider $actors,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $layout,
        private ?FlagEditor $editor = null,
        private ?WritableFlagDefinitions $definitions = null,
        private ?string $editableSource = null,
    ) {
    }

    public function list(): Response
    {
        $this->actor();

        return $this->render('list', [
            'entries' => $this->catalog->entries(),
            'unreadable' => $this->catalog->unreadable(),
            'can_create' => $this->editor instanceof FlagEditor,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        $this->actor();

        $done = $request->query->getString('done');
        $entry = $this->catalog->lookup($this->flagName($name)) ?? throw new NotFoundHttpException();

        if ($entry instanceof UnreadableEntry) {
            return $this->render('unreadable', ['entry' => $entry]);
        }

        return $this->renderShow($entry, \in_array($done, self::OUTCOMES, true) ? $done : '');
    }

    public function toggle(Request $request, string $name): Response
    {
        SameOriginGuard::assert($request);
        $actor = $this->actor();
        $entry = $this->entry($name);

        try {
            $this->toggler->toggle(new ToggleRequest(
                $entry->declared->name,
                '1' === $request->request->getString('active'),
                $actor,
                ToggleReason::of($request->request->getString('reason')),
            ));
        } catch (InvalidValue $invalidValue) {
            return $this->renderShow($entry, error: $invalidValue->getMessage(), status: Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ToggleRefused $toggleRefused) {
            return $this->renderShow($entry, error: $toggleRefused->getMessage(), status: Response::HTTP_FORBIDDEN);
        } catch (ToggleConflict $toggleConflict) {
            return $this->renderShow($this->entry($name), error: $toggleConflict->getMessage(), status: Response::HTTP_CONFLICT);
        }

        return $this->backTo($entry->declared->name, 'toggled');
    }

    public function create(Request $request): Response
    {
        $actor = $this->actor();
        $editor = $this->editor ?? throw new NotFoundHttpException();
        $form = ['name' => '', 'definition' => "{\n    \"rules\": []\n}", 'description' => '', 'owner' => '', 'kind' => '', 'expires_on' => ''];

        if (!$request->isMethod('POST')) {
            return $this->render('form', ['form' => $form, 'revision' => null]);
        }

        SameOriginGuard::assert($request);
        $form = $this->submitted($request, $form);

        try {
            $name = FlagName::of($form['name']);
            $editor->create(new EditRequest($this->definition($name, $form), $actor, ToggleReason::of($request->request->getString('reason'))));
        } catch (InvalidValue|JsonException $invalid) {
            return $this->render('form', ['form' => $form, 'revision' => null, 'error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ToggleRefused $toggleRefused) {
            return $this->render('form', ['form' => $form, 'revision' => null, 'error' => $toggleRefused->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (EditConflict $editConflict) {
            return $this->render('form', ['form' => $form, 'revision' => null, 'error' => $editConflict->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->backTo($name, 'created');
    }

    public function edit(Request $request, string $name): Response
    {
        $actor = $this->actor();
        $editor = $this->editor ?? throw new NotFoundHttpException();
        $flag = $this->flagName($name);

        try {
            $current = $this->definitions?->findRevised($flag) ?? throw new NotFoundHttpException();
        } catch (InvalidValue $invalidValue) {
            throw new NotFoundHttpException($invalidValue->getMessage(), $invalidValue);
        }

        $written = FlagDefinitionFormat::write($current->definition);
        $form = [
            'name' => $flag->value,
            'definition' => json_encode(array_intersect_key($written, array_flip(explode(',', self::EDIT_KEYS))) + ['rules' => []], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
            'description' => $current->definition->metadata->description ?? '',
            'owner' => $current->definition->metadata->owner ?? '',
            'kind' => $current->definition->metadata->kind->value ?? '',
            'expires_on' => $current->definition->metadata->expiresOn?->format('Y-m-d') ?? '',
        ];

        if (!$request->isMethod('POST')) {
            return $this->render('form', ['form' => $form, 'revision' => $current->revision]);
        }

        SameOriginGuard::assert($request);
        $form = $this->submitted($request, $form, $flag->value);
        $revision = $request->request->getInt('revision');

        try {
            $editor->edit(new EditRequest($this->definition($flag, $form), $actor, ToggleReason::of($request->request->getString('reason')), $revision));
        } catch (InvalidValue|JsonException $invalid) {
            return $this->render('form', ['form' => $form, 'revision' => $revision, 'error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ToggleRefused $toggleRefused) {
            return $this->render('form', ['form' => $form, 'revision' => $revision, 'error' => $toggleRefused->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (UnknownFlag $unknownFlag) {
            throw new NotFoundHttpException($unknownFlag->getMessage(), $unknownFlag);
        } catch (EditConflict $editConflict) {
            return $this->render('form', ['form' => $form, 'revision' => $revision, 'error' => $editConflict->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->backTo($flag, 'edited');
    }

    private function actor(): string
    {
        return $this->actors->current() ?? throw new AccessDeniedHttpException('The flag admin needs an authenticated operator.');
    }

    /**
     * The flag to toggle: one that no provider knows, or whose definition its provider cannot read, is not found.
     */
    private function entry(string $name): CatalogEntry
    {
        try {
            return $this->catalog->find($this->flagName($name)) ?? throw new NotFoundHttpException();
        } catch (InvalidValue $invalidValue) {
            throw new NotFoundHttpException($invalidValue->getMessage(), $invalidValue);
        }
    }

    private function flagName(string $name): FlagName
    {
        try {
            return FlagName::of($name);
        } catch (InvalidValue $invalidValue) {
            throw new NotFoundHttpException($invalidValue->getMessage(), $invalidValue);
        }
    }

    /**
     * @param array<string, string> $form
     *
     * @return array<string, string>
     */
    private function submitted(Request $request, array $form, ?string $name = null): array
    {
        foreach (array_keys($form) as $field) {
            $form[$field] = $request->request->getString($field);
        }

        if (null !== $name) {
            $form['name'] = $name;
        }

        return $form;
    }

    /**
     * The rules and the experiment from the JSON field, the metadata from their own fields.
     *
     * @param array<string, string> $form
     *
     * @throws InvalidValue
     * @throws JsonException
     */
    private function definition(FlagName $name, array $form): FlagDefinition
    {
        $decoded = json_decode($form['definition'], true, 16, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded) || [] !== array_diff(array_keys($decoded), explode(',', self::EDIT_KEYS))) {
            throw InvalidValue::because('The definition is a JSON object with "rules" and, for an A/B test, "experiment".');
        }

        $metadata = array_filter([
            'description' => $form['description'],
            'owner' => $form['owner'],
            'kind' => $form['kind'],
            'expires_on' => $form['expires_on'],
        ], static fn (string $value): bool => '' !== trim($value));

        return FlagDefinitionFormat::read($name, $decoded + $metadata);
    }

    private function backTo(FlagName $flag, string $done): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->generate('phil_feature_flags_admin_show', ['name' => $flag->value, 'done' => $done]),
            Response::HTTP_SEE_OTHER,
        );
    }

    private function renderShow(CatalogEntry $entry, string $done = '', ?string $error = null, int $status = Response::HTTP_OK): Response
    {
        return $this->render('show', [
            'entry' => $entry,
            'effective' => $entry->effective(),
            'expired' => $entry->declared->metadata->isExpiredAt($this->clock->now()),
            'editable' => $this->editor instanceof FlagEditor && $entry->source === $this->editableSource,
            'done' => $done,
            'error' => $error,
        ], $status);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render('@PhilFeatureFlags/admin/'.$template.'.html.twig', $parameters + [
            'layout' => $this->layout,
            'kinds' => FlagKind::values(),
            'now' => $this->clock->now(),
            'error' => null,
        ]), $status);
    }
}
