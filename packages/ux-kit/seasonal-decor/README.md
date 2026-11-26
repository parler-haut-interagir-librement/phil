# SeasonalDecor

A decor for the holidays of the year, chosen by the site and off by default: snowflakes, hearts, bats, falling leaves
or lanterns drawn over the page for a few seconds, a poppy fixed in a corner for a day of remembrance, an egg hunt
played by mouse or keyboard, and a secret code that plays the `Celebration` of the page. It is decorative, hidden from
screen readers, never in the way, and a button closes it for the rest of the holiday.

```twig {"preview":true}
<twig:SeasonalDecor decor="snow" :eggs="3" />
<p data-egg-nest>Eggs hide in the elements marked <code>data-egg-nest</code>: look here,</p>
<p data-egg-nest>here,</p>
<p data-egg-nest>and here.</p>
```

## Installation

::: installation

## Usage

The holiday of the day comes from `seasonal_decor()`, which counts the days of the holidays of the configuration with
phil/almanac, in the layout:

```twig
{% set season = seasonal_decor() %}
{% if season %}
    <twig:SeasonalDecor :decor="season.decor" :holiday="season.id" :sober="season.sober" />
{% endif %}
```

`decor` is one of the decors below; nothing is drawn for any other name. `holiday` tells this year's holiday from the
others: the closing of the decor, and the eggs found, are remembered under it, so that a decor closed at Halloween shows
again the next year. `sober` marks a day of remembrance: a still picture, no hunt, no code. `duration` is how long the
decor moves, in milliseconds, kept between 2000 and 15000.

| Decor | What is drawn |
|---|---|
| `snow` | Snowflakes falling and swaying |
| `hearts` | Hearts rising |
| `clovers` | Three-leaf clovers falling |
| `eggs` | Pastel eggs falling slowly |
| `bats` | Bats crossing the screen, beating their wings once a second |
| `leaves` | Autumn leaves falling and turning |
| `petals` | Spring petals drifting down |
| `lanterns` | Lanterns rising slowly |
| `maple-leaves` | Maple leaves falling and turning |
| `fleurs-de-lis` | Fleurs-de-lis falling |
| `poppy` | A poppy fixed in a corner, never animated, for a day of remembrance |

The colours are those of each decor; `data-seasonal-decor-colors-value`, a list of custom properties separated by
spaces, gives those of the theme instead.

## The holidays

The seasonal decor is off until the site turns it on. Each holiday is a rule of phil/almanac, so that a site adds its
own, Hanukkah, Diwali or Eid, without a line of code:

```yaml
# config/packages/phil_ux_kit.yaml
phil_ux_kit:
    seasonal:
        enabled: true
        time_zone: America/Toronto   # that of PHP when left out
        holidays:
            winter-holidays: { title: Winter holidays, rule: '12-20', days: 14, decor: snow }
            fete-nationale: { title: Fête nationale, rule: '06-24', decor: fleurs-de-lis }
            canada-day: { title: Canada Day, rule: '07-01', decor: maple-leaves }
            halloween: { title: Halloween, rule: '10-31 - 6 days', days: 7, decor: bats }
            remembrance-day: { title: Remembrance Day, rule: '11-11', decor: poppy }
```

`rule` is the first day, in the words of phil/almanac: `12-25`, `2nd monday of october`, `easter - 2 days`,
`chinese new year`, `march equinox`; `days` how many days it lasts, or `until` the rule of its last day. A day that falls
in two holidays shows the sober one, then the first of the list. The holidays given replace the default ones: winter
holidays, Lunar New Year, Valentine's Day, St. Patrick's Day, spring, Easter, autumn, Halloween and Remembrance Day.
`sober` is true for the poppy, and may be given to any holiday.

`seasonal_decor('2026-10-31')` gives the holiday of another day, read in the time zone of the configuration, for a
preview of the decor.

## Examples

### A day of remembrance

The poppy is fixed in a corner of the screen, never animated, and no hunt or code comes with it.

```twig {"preview":true}
<twig:SeasonalDecor decor="poppy" closeLabel="Hide the poppy" />
```

A site may also write `data-celebrations="none"` on its root for a day of mourning: no decor is drawn then, but a sober
one, and a `Celebration` says its message alone.

### The egg hunt

With `eggs`, as many eggs hide in the elements marked `data-egg-nest`, in the decor itself without one. Each egg is a
button, found by a click, or by the keyboard with Tab and Enter; the count is said in a polite live region, `counter`
with `%found%` and `%total%`, and remembered for the holiday from page to page. When the last one is found, `hunted` is
said, and the `Celebration` of the page plays it with `codeEffect`.

```twig
<twig:SeasonalDecor decor="eggs" :holiday="season.id" :eggs="5"
    eggLabel="{{ 'decor.egg'|trans }}"
    counter="{{ 'decor.eggs_found'|trans }}"
    hunted="{{ 'decor.every_egg'|trans }}" />
```

### The secret code

Typed anywhere on the page but in a field, the keys of `code` play the `Celebration` of the page, with `codeMessage` and
`codeEffect`: the Konami code by default, the keys named as `KeyboardEvent.key` names them, a letter with or without the
shift key. An empty `code` turns it off.

```twig
<twig:SeasonalDecor :decor="season.decor" code="g n o m e" codeMessage="{{ 'decor.code'|trans }}" codeEffect="balloons" />
<twig:Celebration />
```

## Accessibility

The decor is decorative: its canvas, its poppy and its pieces are hidden from screen readers, let every click through,
and announce nothing; only the eggs, which are a game, have a name and a count said in a polite live region. The pieces
move for a few seconds at most, sway slowly, never flash, hold while the tab is hidden, and fade away; where motion is
reduced, by the system or by `data-motion="reduce"` on an ancestor, they show as a still picture. The button that
closes it has a name, `closeLabel`, and is reached by the keyboard. There is no sound.

## Security

Nothing is written in a `style` attribute and nothing runs inline: the canvas and the eggs wait in templates, and the
controller sets the canvas over the page through the CSSOM, which a strict Content Security Policy allows. What is
remembered, the closing and the eggs found, stays in the local storage of the browser, under the keys
`phil-seasonal-decor:closed:<holiday>` and `phil-seasonal-decor:eggs:<holiday>`; where it cannot be read, the page alone
remembers. On paper, the canvas and the eggs are left out.
