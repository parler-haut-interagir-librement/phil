# Timeline

Dated entries down a line, each with its marker, its date and its content.

```twig {"preview":true}
<twig:Timeline label="History">
    <twig:Timeline:Item datetime="2026-03-01">The first version.</twig:Timeline:Item>
    <twig:Timeline:Item datetime="2026-11-12" dateLabel="Autumn 2026">The second.</twig:Timeline:Item>
</twig:Timeline>
```

## Installation

::: installation

## Usage

```twig
<twig:Timeline label="History">
    {% for event in events %}
        <twig:Timeline:Item :datetime="event.date"><h3>{{ event.title }}</h3><p>{{ event.summary }}</p></twig:Timeline:Item>
    {% endfor %}
</twig:Timeline>
```

## Accessibility

An ordered list, so screen readers say how many entries it holds and where each one stands; each date is a `time` element whose `datetime` machines read. The line and the markers are drawn by the style sheet alone, and take the colour of the text where colours are forced.
