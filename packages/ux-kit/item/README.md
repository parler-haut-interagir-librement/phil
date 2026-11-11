# Item

One entry of a list: its media, its title and description, its actions.

```twig {"preview":true}
<twig:Item:Group>
    <twig:Item as="li" variant="outline">
        <twig:Item:Content>
            <twig:Item:Title>Two-factor authentication</twig:Item:Title>
            <twig:Item:Description>A code from an application at each sign-in.</twig:Item:Description>
        </twig:Item:Content>
        <twig:Item:Actions><twig:Button size="sm" variant="outline">Turn on</twig:Button></twig:Item:Actions>
    </twig:Item>
</twig:Item:Group>
```

## Installation

::: installation

## Usage

```twig
<twig:Item:Group>
    <twig:Item as="li" variant="outline">
        <twig:Item:Content>
            <twig:Item:Title>Two-factor authentication</twig:Item:Title>
            <twig:Item:Description>A code from an application at each sign-in.</twig:Item:Description>
        </twig:Item:Content>
        <twig:Item:Actions><twig:Button size="sm" variant="outline">Turn on</twig:Button></twig:Item:Actions>
    </twig:Item>
</twig:Item:Group>
```

## Accessibility

An `Item:Group` is a list, its items `li` elements, so a screen reader says how many there are. The media is decorative and hidden from screen readers: write in the title what a picture would say. With `as="a"`, the whole item is one link; then it holds no other link or button.
