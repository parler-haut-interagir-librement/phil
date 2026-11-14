# Extension points

| Point | How |
|---|---|
| A recipe's markup | Once installed, the component is the application's code: change its utilities in `templates/components/` |
| A component's root | Any attribute given to the component goes on its root; a `class` is added to its utilities |
| The look | The Shadcn kit's tokens, set in the application's stylesheet |
| The headline's timing | The `pause`, `duration` and `loop` props; the button's text, `pauseLabel` and `playLabel` |
| The choice of the illustration | The `seed` prop |
| The content of the coming soon page | The `content` block |
