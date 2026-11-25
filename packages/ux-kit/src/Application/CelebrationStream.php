<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

/**
 * The `<turbo-stream action="celebrate">` that plays the celebrations of a page: in the response to a form Turbo
 * submits, published on a Mercure topic a `<turbo-stream-source>` of the page listens to, or written in the page itself,
 * so that a bundle plays a celebration without a line of JavaScript. The `celebration` recipe renders its action, with
 * Turbo or without it; the page holds a `Celebration` for anything to play.
 */
final readonly class CelebrationStream
{
    /** The effects the `celebration` controller draws, the first by default; the list of its `EFFECTS`. */
    public const array EFFECTS = ['confetti', 'fireworks', 'lasers', 'balloons', 'shooting-stars', 'streamers', 'coins', 'bubbles', 'aurora', 'spotlight', 'trophy'];

    /** The action of the stream, which the `celebration` controller renders. */
    public const string ACTION = 'celebrate';

    /**
     * The stream: `message` said in the live region of the celebration, or its own words when null or empty; `effect`
     * one of `EFFECTS`, the first for any other name; `focus` the selector of what the spotlight lights and where the
     * trophy lands. Every value is escaped as an attribute.
     */
    public function render(?string $message = null, string $effect = self::EFFECTS[0], ?string $focus = null): string
    {
        $attributes = ['action' => self::ACTION, 'effect' => self::effect($effect)];

        if (null !== $message && '' !== $message) {
            $attributes['message'] = $message;
        }

        if (null !== $focus && '' !== $focus) {
            $attributes['focus'] = $focus;
        }

        $html = '<turbo-stream';

        foreach ($attributes as $name => $value) {
            $html .= \sprintf(' %s="%s"', $name, htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8'));
        }

        return $html.'><template></template></turbo-stream>';
    }

    /** An effect of `EFFECTS`, the first for any other name. */
    public static function effect(string $name): string
    {
        return \in_array($name, self::EFFECTS, true) ? $name : self::EFFECTS[0];
    }
}
