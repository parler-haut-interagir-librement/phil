# ActivityCalendar

Activity over time: a cell per day, each read through its label, the colour never standing alone. Wider than the screen, it scrolls by itself and takes the focus to do so.

```twig {"preview":true}
<twig:ActivityCalendar label="Contributions" :offset="2" :days="[{level: 0, label: 'No contribution on 1 March'}, {level: 3, label: '4 contributions on 2 March'}]" lessLabel="Less" moreLabel="More" />
```

## Installation

::: installation

## Usage

```twig
<twig:ActivityCalendar label="Contributions" :offset="2" :days="[{level: 0, label: 'No contribution on 1 March'}, {level: 3, label: '4 contributions on 2 March'}]" lessLabel="Less" moreLabel="More" />
```

## Accessibility

The list is named by `label` and each day read through its own. Levels run from 0 to 4 and two levels apart reach 3:1, under forced colours each cell keeps a border.
