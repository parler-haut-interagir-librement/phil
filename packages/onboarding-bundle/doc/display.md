# The checklist and the tour

## In a template

With Symfony UX Twig Component:

```twig
<twig:PhilOnboarding:Checklist flow="author" class="sidebar" />
<twig:PhilOnboarding:Tour flow="author" />
<twig:PhilOnboarding:Tour flow="author" :autostart="false" />
```

Without it, the same with the Twig functions:

```twig
{{ onboarding_checklist('author') }}
{{ onboarding_tour('author') }}
{{ onboarding_tour('author', false) }}

{% set progress = onboarding_progress('author') %}
{% if progress and progress.active %}{{ progress.percent }} %{% endif %}
```

Neither shows anything for a visitor. The checklist shows nothing once the flow is dismissed; the tour shows nothing
once the flow is finished or dismissed, nor for a user who turned the tours off. A component's other attributes go on
its main element.

## The checklist

A `<section>` named by the flow's title: its description, the share of the steps done in words and as a `<progress>`,
then the steps in an ordered list. Each step says its state in words for screen readers; the current step carries
`aria-current="step"`; a pending step with a route links to it. A step ticked by hand has a form to mark it done, a
step that may be skipped a form to skip it, a step with a question a form of its choices — radio buttons, or
checkboxes when several may be picked, grouped in a `<fieldset>` whose legend is the step's title — and a button that
answers it; each button names its step for screen readers. A form puts the list away;
once every step is done, another starts the flow again.

The forms post to the endpoints with the CSRF token and come back to the page: the checklist needs no script.

## The tour

The tour is a non-modal dialog that shows one step at a time — its progress, its title, its description, a link to its
route, the form of its question — while the step's target is highlighted and scrolled into view. It starts at the first
pending step when the page loads, unless `autostart` is false, and whenever the window receives the event
`phil-onboarding:start`:

```js
// in a Stimulus controller of the application, behind a "Take the tour" button
this.dispatch('start', { prefix: 'phil-onboarding', target: window });
```

| Control | Does |
|---|---|
| Next, or the right arrow | Marks a pending step done when it is ticked by hand, then shows the next step; ends on the last one. A question stays to answer, by its form. |
| Previous, or the left arrow | Shows the step before. |
| Skip this step | Marks the step skipped, when it may be skipped, then moves on. |
| End the tour (×) | Dismisses the flow for good. |
| Escape | Closes the tour for this page only. |

The arrows act only when the focus is not in a field. The panel takes the focus when a step shows, and the focus
returns where it was when the tour closes; a status announces each step to screen readers ("Step 2 of 4: …"). A step
whose target is not on the page shows without a highlight; a selector the browser refuses is passed over.

The target scrolls into view at once when the reader asks for reduced motion, through the system's
`prefers-reduced-motion` or the `data-motion="reduce"` that `phil/settings-bundle` writes on the root element, and
smoothly otherwise; the stylesheet drops the highlight's transition the same way. Each change is sent to the endpoints
with the token in the header `X-Phil-Onboarding-Token`; one that fails dispatches `phil--onboarding-bundle--tour:failed`
and the tour goes on. The controller also dispatches `shown`, with the flow and the step, `closed` and `finished`.

Without JavaScript, the tour does not show: the checklist is the way through the flow.

## The look

The buttons are the recipe `Button` of the kit, installed in the application by the Symfony UX Toolkit — see
[Installation](installation.md#the-recipe-of-the-kit) —, which dresses them: `secondary` to tick a step, `ghost` to skip
it, put the list away or start it again, `default` to answer and to go on in the tour, all of size `sm`, and an
`icon-sm` button to end the tour. A template calls it with `{% component (phil_onboarding_kit('Button')) %}`, the
function giving the name it has under the Toolkit's components subdirectory. The bundle's stylesheet,
`phil-onboarding/onboarding.css`, lays out the checklist, the questions and the panel, hides the texts meant for screen
readers (`phil-onboarding-visually-hidden`) and draws the highlight (`phil-onboarding-highlight`), with `Highlight` in
forced colours. Its colours are custom properties an application overrides:

```css
:root {
    --phil-onboarding-accent: #2557a7;
    --phil-onboarding-surface: #fff;
    --phil-onboarding-text: #1b1b1b;
    --phil-onboarding-muted: #595959;
    --phil-onboarding-radius: 0.5rem;
}
```

The templates are `@PhilOnboarding/_checklist.html.twig` and `@PhilOnboarding/_tour.html.twig`; an application
overrides them as any bundle's, under `templates/bundles/PhilOnboardingBundle/`.

## Turning the tours off

With `phil/settings-bundle`, the bundle declares the boolean setting `onboarding.tours`, on by default, in the
`general` section of the preferences: a user who turns it off sees no tour, and keeps the checklist. Another rule —
a role, a plan — is a `TourPreference` of the application: see [Extension points](extension-points.md).
