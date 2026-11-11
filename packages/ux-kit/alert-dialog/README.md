# AlertDialog

A question that interrupts, asked in a modal window on the native `dialog` element: Escape dismisses it, a click outside does not, and the focus starts on the answer that does no harm.

```twig {"preview":true}
<twig:AlertDialog id="delete">
    <twig:AlertDialog:Trigger>
        <twig:Button variant="destructive" {{ ...alert_dialog_trigger_attrs }}>Delete</twig:Button>
    </twig:AlertDialog:Trigger>
    <twig:AlertDialog:Content>
        <twig:AlertDialog:Header>
            <twig:AlertDialog:Title>Delete the project?</twig:AlertDialog:Title>
            <twig:AlertDialog:Description>Its pages and files go with it.</twig:AlertDialog:Description>
        </twig:AlertDialog:Header>
        <twig:AlertDialog:Footer>
            <twig:AlertDialog:Cancel>Keep it</twig:AlertDialog:Cancel>
            <twig:AlertDialog:Action variant="destructive" type="submit" form="delete-project">Delete</twig:AlertDialog:Action>
        </twig:AlertDialog:Footer>
    </twig:AlertDialog:Content>
</twig:AlertDialog>
```

## Installation

::: installation

## Usage

```twig
<twig:AlertDialog id="delete">
    <twig:AlertDialog:Trigger>
        <twig:Button variant="destructive" {{ ...alert_dialog_trigger_attrs }}>Delete</twig:Button>
    </twig:AlertDialog:Trigger>
    <twig:AlertDialog:Content>
        <twig:AlertDialog:Header>
            <twig:AlertDialog:Title>Delete the project?</twig:AlertDialog:Title>
            <twig:AlertDialog:Description>Its pages and files go with it.</twig:AlertDialog:Description>
        </twig:AlertDialog:Header>
        <twig:AlertDialog:Footer>
            <twig:AlertDialog:Cancel>Keep it</twig:AlertDialog:Cancel>
            <twig:AlertDialog:Action variant="destructive" type="submit" form="delete-project">Delete</twig:AlertDialog:Action>
        </twig:AlertDialog:Footer>
    </twig:AlertDialog:Content>
</twig:AlertDialog>
```

## Accessibility

The window has the role `alertdialog`, is named by its title and described by its description. It carries `closedby="closerequest"`: Escape closes it, a click on the backdrop does not, so that the question is answered. The cancel button has the focus when it opens. The buttons carry `command` and `commandfor`, which the browser runs itself where it can; the `Dialog` controller, which the recipe installs, stands in for the rest.
