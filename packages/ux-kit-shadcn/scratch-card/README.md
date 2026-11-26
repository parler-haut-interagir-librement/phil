# ScratchCard

A prize, a draw or a result decided by the server, under a cover scratched away with a mouse, a finger or a pen; a
button reveals it at once, by a click or the keyboard. Once revealed, the prize is said, and the `Celebration` of the
page may play.

```twig {"preview":true}
<twig:ScratchCard label="Reveal my prize">
    <p><strong>A free coffee!</strong> Show this card at the counter.</p>
</twig:ScratchCard>
```

## Installation

::: installation

## Usage

```twig
<twig:ScratchCard label="{{ 'raffle.reveal'|trans }}" effect="coins">
    {{ 'raffle.prize'|trans({prize: ticket.prize}) }}
</twig:ScratchCard>
```

The block `content` holds the prize; the server decides it, the card only shows it. `label` is the words of the button
that reveals it at once. `message` is said once it is revealed, the text of the prize when empty. `threshold` is the
share of the cover, in percent, that reveals the whole prize once scratched away, 50 by default, kept between 10 and 90.
`effect` plays the `Celebration` of the page once the prize is revealed; give the celebration no message of its own, so
that the words are said once.

## Examples

### With a celebration

```twig {"preview":true}
<twig:ScratchCard label="Reveal the winner" message="Ticket 1207 wins the raffle" effect="coins" :threshold="40">
    <p>Ticket <strong>1207</strong></p>
</twig:ScratchCard>
<twig:Celebration />
```

### After the reveal

The event `scratch-card:revealed` tells the page, here to show the button that claims the prize:

```twig
<twig:ScratchCard data-action="scratch-card:revealed->claim#show">…</twig:ScratchCard>
```

## Accessibility

While it is covered, the prize is hidden from screen readers and from the keyboard, `aria-hidden` and `inert`; the
button reveals it in one press, by the keyboard or a switch, and scratching is never needed. Once revealed, the prize is
said in a polite live region, and the focus goes to it when it was on the button, which leaves. The rest of the cover
fades in 300 milliseconds, at once where motion is reduced, by the system or by `data-motion="reduce"` on an ancestor.
A finger scratches without scrolling the page while it is on the cover. Without the controller, the prize shows from
the start. There is no sound.

## Security

Nothing is written in a `style` attribute and nothing runs inline: the canvas waits in a template, and the controller
sets it over the prize through the CSSOM, which a strict Content Security Policy allows. The prize is in the page from
the start: a card for a secret that must stay hidden until a moment comes is loaded at that moment, by a Turbo Frame
or a stream.
