# Extension points

| Point | How |
|---|---|
| A recipe's markup | Once installed, the component is the application's code: change it in `templates/components/` |
| A component's root | Any attribute given to the component goes on its root; a `class` is added to its own |
| The look | phil-ux's tokens and layers, overridden in the application's stylesheet |
| The headline's timing | The `pause`, `duration` and `loop` props; the button's text, `pauseLabel` and `playLabel` |
| The choice of the illustration | The `seed` prop: a day of the year, an identifier, a fixed number in tests |
| The content of the coming soon page | The `content` block |
| More fields in a sign-in or sign-up form | The `content` block of `LoginForm` and `SignupForm`; their field names and identifiers as props |
| The colours of the series of a chart | The tones of phil-ux, `isPrimary` to `isNeutral`, whose `--phil-tone-*` tokens the application's stylesheet overrides; the dashes on `.isChartLine.isInfo` and the others |
| The names of the copies | The `ux_toolkit` configuration of the application: directories and subdirectory |
| The texts of a recipe | Its props and blocks: `closeLabel`, `label`, `text`, `pauseLabel`, `placeholder`… |
| A recipe of the Common kit | Installed from the Common kit, with `--kit=common` |
| What the browser does natively | `dialog` and `sheet` carry `closedby="any"`, `AlertDialog` `closedby="closerequest"`, all three `command` and `commandfor`; the controller `dialog` runs only what the browser does not, as `hasInvokers()` and `hasLightDismiss()` detect |
| The props of a picture for an object of the application | A service implementing `Phil\UxKit\Application\Contract\PicturePropsProvider`, tagged `phil_ux_kit.picture_props` by autoconfiguration, which `picture_props()` asks |
| The graph of the page | A `NodeCollector` of `phil/structured-data`, which the `ImageObject` of a structured image joins instead of a script of its own |
| The browser suite of another kit | Extend `Phil\UxKit\Test\BrowserTestCase` and give it a `RecipesPage`: see [Testing](testing.md#for-another-kit) |
