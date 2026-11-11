# Dialog

A modal window, on the native `dialog` element and its invokers: the browser makes the page inert, keeps the focus inside, closes it on Escape and gives the focus back.

```twig {"preview":true}
<twig:Dialog id="profile">
    <twig:Dialog:Trigger>
        <twig:Button variant="outline" {{ ...dialog_trigger_attrs }}>Edit</twig:Button>
    </twig:Dialog:Trigger>
    <twig:Dialog:Content closeLabel="Close">
        <twig:Dialog:Header>
            <twig:Dialog:Title>Edit the profile</twig:Dialog:Title>
            <twig:Dialog:Description>The changes show at once.</twig:Dialog:Description>
        </twig:Dialog:Header>
        <twig:Dialog:Footer>
            <twig:Dialog:Close><twig:Button variant="outline" {{ ...dialog_close_attrs }}>Cancel</twig:Button></twig:Dialog:Close>
        </twig:Dialog:Footer>
    </twig:Dialog:Content>
</twig:Dialog>
```

## Installation

::: installation

## Usage

```twig
<twig:Dialog id="profile">
    <twig:Dialog:Trigger>
        <twig:Button variant="outline" {{ ...dialog_trigger_attrs }}>Edit</twig:Button>
    </twig:Dialog:Trigger>
    <twig:Dialog:Content closeLabel="Close">
        <twig:Dialog:Header>
            <twig:Dialog:Title>Edit the profile</twig:Dialog:Title>
            <twig:Dialog:Description>The changes show at once.</twig:Dialog:Description>
        </twig:Dialog:Header>
        <twig:Dialog:Footer>
            <twig:Dialog:Close><twig:Button variant="outline" {{ ...dialog_close_attrs }}>Cancel</twig:Button></twig:Dialog:Close>
        </twig:Dialog:Footer>
    </twig:Dialog:Content>
</twig:Dialog>
```

## Accessibility

Named by its title and described by its description. HTML first: the trigger and the close buttons carry `command` and `commandfor`, and the dialog `closedby="any"`. Where the browser runs them, the buttons work without JavaScript and a click on the backdrop closes the dialog; the Stimulus controller detects what the browser does and stands in only for the rest, and keeps `aria-expanded` on the trigger either way.
