<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Http;

use Closure;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\SettingPreview;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Form\SettingsForm;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * The two pages of settings, one section at a time, each a form: the preferences of the current user — or, for a
 * visitor, the settings kept for visitors —, and the site's settings, each shown only to the users of its role.
 *
 * Without a section, a page redirects to its first; a section it does not show is a `404`, a page with nothing to
 * show an access denied, which sends a visitor to sign in. A valid form saves and redirects to its section with
 * `?saved=1`, so that reloading saves nothing twice; a refused value shows the form again with its error, in a `422`.
 * The settings kept for visitors are also written to the preference cookie, so that the site keeps its look once the
 * user signs out.
 *
 * A valid form submitted by its preview button, named `phil_settings_preview`, saves nothing: the page shows the form
 * as submitted and, for each setting of the section, its value now and the value saving would give it.
 */
final readonly class SettingsController
{
    /** The name of the button that previews a form instead of saving it. */
    public const string PREVIEW = 'phil_settings_preview';

    public function __construct(
        private SettingCatalog $catalog,
        private Settings $settings,
        private SettingsForm $forms,
        private PreferenceCookie $cookie,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private CurrentUser $currentUser,
        private string $template,
        private string $siteRole = 'ROLE_ADMIN',
        private ?AuthorizationCheckerInterface $authorization = null,
    ) {
    }

    public function preferences(Request $request, ?string $section = null): Response
    {
        $user = $this->currentUser->id();

        return $this->page(
            $request,
            Scope::User,
            $section,
            static fn (SettingDefinition $definition): bool => null !== $user || $definition->anonymous,
            null === $user ? null : Subject::user($user),
        );
    }

    public function site(Request $request, ?string $section = null): Response
    {
        return $this->page(
            $request,
            Scope::Site,
            $section,
            fn (SettingDefinition $definition): bool => true === $this->authorization?->isGranted($definition->role ?? $this->siteRole),
            Subject::site(),
        );
    }

    /**
     * @param Closure(SettingDefinition): bool $shown
     */
    private function page(Request $request, Scope $scope, ?string $section, Closure $shown, ?Subject $subject): Response
    {
        $route = Scope::Site === $scope ? Routes::SITE : Routes::PREFERENCES;
        $sections = [];

        foreach ($this->catalog->sections($scope) as $declared) {
            $definitions = array_filter($this->catalog->settings($scope, $declared->key), $shown);

            if ([] !== $definitions) {
                $sections[$declared->key] = $definitions;
            }
        }

        if ([] === $sections) {
            throw new AccessDeniedException('No setting of this page is shown to the current user.');
        }

        if (null === $section) {
            return new RedirectResponse($this->urls->generate($route, ['section' => array_key_first($sections)]));
        }

        $definitions = $sections[$section] ?? throw new NotFoundHttpException(\sprintf('The section "%s" is not shown.', $section));
        $form = $this->forms->create($definitions, $this->settings->values($subject, $definitions));
        $form->handleRequest($request);

        $preview = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $values = SettingsForm::values($form, $definitions);

            if (!$request->request->has(self::PREVIEW)) {
                return $this->saved($request, $route, $section, $subject, $definitions, $values);
            }

            $preview = array_map($this->previewed(...), $this->settings->preview($subject, $definitions, $values));
        }

        $links = [];

        foreach (array_keys($sections) as $key) {
            $declared = $this->catalog->section($key);
            $links[] = [
                'key' => $key,
                'label' => $declared->label,
                'translation_domain' => $declared->translationDomain,
                'url' => $this->urls->generate($route, ['section' => $key]),
                'current' => $key === $section,
            ];
        }

        return new Response(
            $this->twig->render($this->template, [
                'form' => $form->createView(),
                'sections' => $links,
                'section' => $this->catalog->section($section),
                'scope' => $scope->value,
                'saved' => $request->query->getBoolean('saved'),
                'preview' => $preview,
            ]),
            $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
            ['Cache-Control' => 'no-store, private'],
        );
    }

    /**
     * @param array<SettingDefinition> $definitions
     * @param array<string, mixed>     $values      by key
     */
    private function saved(Request $request, string $route, string $section, ?Subject $subject, array $definitions, array $values): RedirectResponse
    {
        if ($subject instanceof Subject) {
            $this->settings->save($subject, $values);
        }

        $response = new RedirectResponse($this->urls->generate($route, ['section' => $section, 'saved' => 1]), Response::HTTP_SEE_OTHER);

        if ([] !== array_filter($definitions, static fn (SettingDefinition $definition): bool => $definition->anonymous)) {
            $response->headers->setCookie($this->cookie->cookie(array_merge($this->cookie->values(), $values), $request, $this->clock->now()->getTimestamp()));
        }

        return $response;
    }

    /**
     * A setting of the preview as the template shows it: its label, and each value as the parts it is read in, a
     * translation key with its domain or a text as it is.
     *
     * @return array{key: string, label: string, translation_domain: string, current: list<array{message: string, domain: ?string}>, proposed: list<array{message: string, domain: ?string}>, changed: bool}
     */
    private function previewed(SettingPreview $preview): array
    {
        $definition = $preview->definition;

        return [
            'key' => $definition->key,
            'label' => $definition->label,
            'translation_domain' => $definition->translationDomain,
            'current' => $this->parts($definition, $preview->current),
            'proposed' => $this->parts($definition, $preview->proposed),
            'changed' => $preview->changed,
        ];
    }

    /**
     * @param bool|int|string|list<string> $value
     *
     * @return list<array{message: string, domain: ?string}>
     */
    private function parts(SettingDefinition $definition, bool|int|string|array $value): array
    {
        $own = static fn (string $message): array => [['message' => $message, 'domain' => 'phil_settings']];
        $choice = static fn (string $choice): array => ['message' => $definition->key.'.choices.'.$choice, 'domain' => $definition->translationDomain];

        return match (true) {
            \is_bool($value) => $own($value ? 'phil_settings.yes' : 'phil_settings.no'),
            \is_int($value) => [['message' => (string) $value, 'domain' => null]],
            \is_array($value) => [] === $value ? $own('phil_settings.none') : array_map($choice, $value),
            SettingType::Choice === $definition->type => [$choice($value)],
            '' === $value => $own('phil_settings.empty'),
            default => [['message' => $value, 'domain' => null]],
        };
    }
}
