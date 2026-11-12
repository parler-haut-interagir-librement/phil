# Reference

| File | What it is |
|---|---|
| `manifest.json` | The kit: name, description, licence, homepage, colour, icon, dependencies |
| `icon.svg` | The kit's icon |
| `.gitattributes` | Leaves the package's own files out of the archive `ux:install` downloads, but `src/` |
| `src/PhilUxKitBundle.php` | `Phil\UxKit\PhilUxKitBundle`: the bundle, `phil_ux_kit.image.hosts`, `allow_localhost` and `check`, the tag `phil_ux_kit.picture_props`, and the Twig functions of the image recipes |
| `src/Application/ImageOrigin.php` | `Phil\UxKit\Application\ImageOrigin`: where the image at an address, or every candidate of a `srcset`, comes from, read by `phil/url`: `local`, `external` or `refused` |
| `src/Application/ImageSources.php` | `Phil\UxKit\Application\ImageSources`: the props of a `Picture` from the files of one image among the assets of AssetMapper |
| `src/Application/ImageTextCheck.php` | `Phil\UxKit\Application\ImageTextCheck`: what a developer may have left wrong in an image, a text alternative that is the name of its file, a generic word or one that starts by saying it is an image, a missing width or height |
| `src/Application/ImageMigration.php`, `src/Application/ImageMigrationFinding.php` | `Phil\UxKit\Application\ImageMigration`: the `Image` and `Picture` recipes proposed in place of the `img` and `picture` elements of a Twig template, or what stands in the way of each, and the proposals applied |
| `src/Application/PictureProps.php` | `Phil\UxKit\Application\PictureProps`: the props of a `Picture` for an object, from the first provider that supports it, kept to those that describe the image |
| `src/Application/Contract/PicturePropsProvider.php` | `Phil\UxKit\Application\Contract\PicturePropsProvider`: what a service implements, tagged `phil_ux_kit.picture_props` by autoconfiguration, to give the props of a picture for the objects it supports |
| `src/Infrastructure/Console/MigrateImagesCommand.php` | `phil:ux-kit:migrate-images`: the proposals of `ImageMigration` for the templates of a project, written with `--write`; registered with the Console component |
| `src/Infrastructure/LiipImagine/ImageFilters.php` | `Phil\UxKit\Infrastructure\LiipImagine\ImageFilters`: the props of a `Picture` from the filter sets of LiipImagine applied to one image |
| `src/Infrastructure/DependencyInjection/ImageFiltersPass.php` | Leaves `ImageFilters` out when the services of LiipImagine are not registered |
| `src/Infrastructure/StructuredData/ImageJsonLd.php` | `Phil\UxKit\Infrastructure\StructuredData\ImageJsonLd`: the `ImageObject` of an image, added to the graph of the page or written in its own JSON-LD script |
| `src/Infrastructure/Twig/ImageExtension.php` | `image_origin()`, `image_sources()`, `image_filters()`, `picture_props()`, `image_json_ld()` and `image_check()`, which logs the problems of `ImageTextCheck` as warnings when `phil_ux_kit.image.check` is on |
| `src/Test/BrowserTestCase.php` | `Phil\UxKit\Test\BrowserTestCase`: one headless Chrome on the page of the recipes of a kit, its modes plain and under the strict policy, which fails a test on anything the browser refused, and the helpers of a browser test |
| `src/Test/Screenshots.php` | `Phil\UxKit\Test\Screenshots`: the screenshot of a part of a page compared with its reference, both at a scale of its CSS width, within a tolerance, on GD |
| `src/Test/RecipesPage.php` | `Phil\UxKit\Test\RecipesPage`: the web root of every recipe of a kit, in each mode plain and under a strict Content Security Policy, with its stylesheet, that of its page, its controllers, Stimulus and the files of its `media` directory |
| `logo/manifest.json`, `logo/README.md` | The `Logo` recipe |
| `logo/templates/components/Logo.html.twig` | The `Logo` component |
| `animated-headline/manifest.json`, `animated-headline/README.md` | The `AnimatedHeadline` recipe |
| `animated-headline/templates/components/AnimatedHeadline.html.twig` | The `AnimatedHeadline` component |
| `animated-headline/assets/controllers/animated_headline_controller.js` | The Stimulus controller `animated-headline`, `next()`, `timing()` and `BUDGET` |
| `illustration/manifest.json`, `illustration/README.md` | The `Illustration` recipe |
| `illustration/templates/components/Illustration.html.twig` | The `Illustration` component |
| `image/manifest.json`, `image/README.md` | The `Image` recipe |
| `image/templates/components/` | The `Image` and `Picture` components, and `Image:Figure`, the figure of their description, caption, credit, license and creation |
| `image/assets/controllers/image_preview_controller.js` | The Stimulus controller `image-preview`, which paints the blurred preview of an image through the CSSOM until it has loaded |
| `gallery/manifest.json`, `gallery/README.md` | The `Gallery` recipe |
| `gallery/templates/components/Gallery.html.twig` | The `Gallery` component |
| `lightbox/manifest.json`, `lightbox/README.md` | The `Lightbox` recipe |
| `lightbox/templates/components/Lightbox.html.twig` | The `Lightbox` component |
| `lightbox/assets/controllers/lightbox_controller.js` | The Stimulus controller `lightbox`, which opens and closes the dialog where the browser runs neither the invokers nor `closedby` |
| `hotspots/manifest.json`, `hotspots/README.md` | The `Hotspots` recipe |
| `hotspots/templates/components/Hotspots.html.twig` | The `Hotspots` component |
| `hero/manifest.json`, `hero/README.md` | The `Hero` recipe |
| `hero/templates/components/Hero.html.twig` | The `Hero` component |
| `video/manifest.json`, `video/README.md` | The `Video` recipe |
| `video/templates/components/Video.html.twig` | The `Video` component |
| `figure/manifest.json`, `figure/README.md` | The `Figure` recipe |
| `figure/templates/components/Figure.html.twig` | The `Figure` component |
| `autosave-status/manifest.json`, `autosave-status/README.md` | The `AutosaveStatus` recipe |
| `autosave-status/templates/components/AutosaveStatus.html.twig` | The `AutosaveStatus` component |
| `reaction-button/manifest.json`, `reaction-button/README.md` | The `ReactionButton` recipe |
| `reaction-button/templates/components/ReactionButton.html.twig` | The `ReactionButton` component |
| `reaction-picker/manifest.json`, `reaction-picker/README.md` | The `ReactionPicker` recipe |
| `reaction-picker/templates/components/ReactionPicker.html.twig` | The `ReactionPicker` component |
| `coming-soon/manifest.json`, `coming-soon/README.md` | The `ComingSoon` block |
| `coming-soon/templates/components/ComingSoon.html.twig` | The `ComingSoon` component |
| `badge/manifest.json`, `badge/README.md` | The `Badge` recipe |
| `badge/templates/components/` | The `Badge` component and its parts |
| `button/manifest.json`, `button/README.md` | The `Button` recipe |
| `button/templates/components/` | The `Button` component and its parts |
| `button-group/manifest.json`, `button-group/README.md` | The `ButtonGroup` recipe |
| `button-group/templates/components/` | The `ButtonGroup` component and its parts |
| `card/manifest.json`, `card/README.md` | The `Card` recipe |
| `card/templates/components/` | The `Card` component and its parts |
| `empty/manifest.json`, `empty/README.md` | The `Empty` recipe |
| `empty/templates/components/` | The `Empty` component and its parts |
| `popover/manifest.json`, `popover/README.md` | The `Popover` recipe |
| `popover/templates/components/` | The `Popover` component and its parts |
| `popover/assets/controllers/` | The Stimulus controller of the recipe |
| `qr-code/manifest.json`, `qr-code/README.md` | The `QrCode` recipe |
| `qr-code/templates/components/` | The `QrCode` component and its parts |
| `tabs/manifest.json`, `tabs/README.md` | The `Tabs` recipe |
| `tabs/templates/components/` | The `Tabs` component and its parts |
| `tabs/assets/controllers/` | The Stimulus controller of the recipe |
| `accordion/manifest.json`, `accordion/README.md` | The `Accordion` recipe |
| `accordion/templates/components/` | The `Accordion` component and its parts |
| `accordion/assets/controllers/` | The Stimulus controller of the recipe |
| `dialog/manifest.json`, `dialog/README.md` | The `Dialog` recipe |
| `dialog/templates/components/` | The `Dialog` component and its parts |
| `dialog/assets/controllers/` | The Stimulus controller of the recipe |
| `sheet/manifest.json`, `sheet/README.md` | The `Sheet` recipe |
| `sheet/templates/components/` | The `Sheet` component and its parts |
| `switch/manifest.json`, `switch/README.md` | The `Switch` recipe |
| `switch/templates/components/` | The `Switch` component and its parts |
| `tooltip/manifest.json`, `tooltip/README.md` | The `Tooltip` recipe |
| `tooltip/templates/components/` | The `Tooltip` component and its parts |
| `tooltip/assets/controllers/` | The Stimulus controller of the recipe |
| `pagination/manifest.json`, `pagination/README.md` | The `Pagination` recipe |
| `pagination/templates/components/` | The `Pagination` component and its parts |
| `carousel/manifest.json`, `carousel/README.md` | The `Carousel` recipe |
| `carousel/templates/components/` | The `Carousel` component and its parts |
| `carousel/assets/controllers/` | The Stimulus controller of the recipe |
| `spinner/manifest.json`, `spinner/README.md` | The `Spinner` recipe |
| `spinner/templates/components/` | The `Spinner` component and its parts |
| `skeleton/manifest.json`, `skeleton/README.md` | The `Skeleton` recipe |
| `skeleton/templates/components/` | The `Skeleton` component and its parts |
| `alert/manifest.json`, `alert/README.md` | The `Alert` recipe |
| `alert/templates/components/` | The `Alert` component and its parts |
| `avatar/manifest.json`, `avatar/README.md` | The `Avatar` recipe |
| `avatar/templates/components/` | The `Avatar` component and its parts |
| `input/manifest.json`, `input/README.md` | The `Input` recipe |
| `input/templates/components/` | The `Input` component and its parts |
| `label/manifest.json`, `label/README.md` | The `Label` recipe |
| `label/templates/components/` | The `Label` component and its parts |
| `select/manifest.json`, `select/README.md` | The `Select` recipe |
| `select/templates/components/` | The `Select` component and its parts |
| `select/assets/controllers/` | The Stimulus controller of the recipe |
| `separator/manifest.json`, `separator/README.md` | The `Separator` recipe |
| `separator/templates/components/` | The `Separator` component and its parts |
| `textarea/manifest.json`, `textarea/README.md` | The `Textarea` recipe |
| `textarea/templates/components/` | The `Textarea` component and its parts |
| `checkbox/manifest.json`, `checkbox/README.md` | The `Checkbox` recipe |
| `checkbox/templates/components/` | The `Checkbox` component and its parts |
| `breadcrumb/manifest.json`, `breadcrumb/README.md` | The `Breadcrumb` recipe |
| `breadcrumb/templates/components/` | The `Breadcrumb` component and its parts |
| `dropdown-menu/manifest.json`, `dropdown-menu/README.md` | The `DropdownMenu` recipe |
| `dropdown-menu/templates/components/` | The `DropdownMenu` component and its parts |
| `dropdown-menu/assets/controllers/` | The Stimulus controller of the recipe |
| `table/manifest.json`, `table/README.md` | The `Table` recipe |
| `table/templates/components/` | The `Table` component and its parts |
| `composer.json` | `phil/ux-kit`, for the tests and for bundles that render the recipes in theirs |
| `color-picker/manifest.json`, `color-picker/README.md` | The `ColorPicker` recipe |
| `color-picker/templates/components/` | The `ColorPicker` component |
| `color-picker/assets/controllers/` | The Stimulus controller of the recipe |
| `date-picker/manifest.json`, `date-picker/README.md` | The `DatePicker` recipe |
| `date-picker/templates/components/` | The `DatePicker` component |
| `date-picker/assets/controllers/` | The Stimulus controller of the recipe |
| `copy-button/manifest.json`, `copy-button/README.md` | The `CopyButton` recipe |
| `copy-button/templates/components/` | The `CopyButton` component |
| `copy-button/assets/controllers/` | The Stimulus controller of the recipe |
| `celebration/manifest.json`, `celebration/README.md` | The `Celebration` recipe |
| `celebration/templates/components/` | The `Celebration` component |
| `celebration/assets/controllers/` | The Stimulus controller of the recipe, which draws on the canvas |
| `animated-button/manifest.json`, `animated-button/README.md` | The `AnimatedButton` recipe |
| `animated-button/templates/components/` | The `AnimatedButton` component |
| `stepper/manifest.json`, `stepper/README.md` | The `Stepper` recipe |
| `stepper/templates/components/` | The `Stepper` component and its parts |
| `activity-calendar/manifest.json`, `activity-calendar/README.md` | The `ActivityCalendar` recipe |
| `activity-calendar/templates/components/` | The `ActivityCalendar` component |
| `tree/manifest.json`, `tree/README.md` | The `Tree` recipe |
| `tree/templates/components/` | The `Tree` component and its parts |
| `tree/assets/controllers/` | The Stimulus controller of the recipe |
| `rating/manifest.json`, `rating/README.md` | The `Rating` recipe |
| `rating/templates/components/` | The `Rating` component |
| `number-input/manifest.json`, `number-input/README.md` | The `NumberInput` recipe |
| `number-input/templates/components/` | The `NumberInput` component |
| `number-input/assets/controllers/` | The Stimulus controller of the recipe |
| `file-input/manifest.json`, `file-input/README.md` | The `FileInput` recipe |
| `file-input/templates/components/` | The `FileInput` component |
| `file-input/assets/controllers/` | The Stimulus controller of the recipe |
| `tag/manifest.json`, `tag/README.md` | The `Tag` recipe |
| `tag/templates/components/` | The `Tag` component |
| `tag/assets/controllers/` | The Stimulus controller of the recipe |
| `relative-time/manifest.json`, `relative-time/README.md` | The `RelativeTime` recipe |
| `relative-time/templates/components/` | The `RelativeTime` component |
| `relative-time/assets/controllers/` | The Stimulus controller of the recipe |
| `progress-ring/manifest.json`, `progress-ring/README.md` | The `ProgressRing` recipe |
| `progress-ring/templates/components/` | The `ProgressRing` component |
| `sparkline/manifest.json`, `sparkline/README.md` | The `Sparkline` recipe |
| `sparkline/templates/components/` | The `Sparkline` component |
| `before-after/manifest.json`, `before-after/README.md` | The `BeforeAfter` recipe |
| `before-after/templates/components/` | The `BeforeAfter` component |
| `before-after/assets/controllers/` | The Stimulus controller of the recipe |
| `alert-dialog/manifest.json`, `alert-dialog/README.md` | The `AlertDialog` recipe |
| `alert-dialog/templates/components/` | The `AlertDialog` component and its parts |
| `aspect-ratio/manifest.json`, `aspect-ratio/README.md` | The `AspectRatio` recipe |
| `aspect-ratio/templates/components/` | The `AspectRatio` component |
| `collapsible/manifest.json`, `collapsible/README.md` | The `Collapsible` recipe |
| `collapsible/templates/components/` | The `Collapsible` component and its parts |
| `field/manifest.json`, `field/README.md` | The `Field` recipe |
| `field/templates/components/` | The `Field` component and its parts |
| `item/manifest.json`, `item/README.md` | The `Item` recipe |
| `item/templates/components/` | The `Item` component and its parts |
| `kbd/manifest.json`, `kbd/README.md` | The `Kbd` recipe |
| `kbd/templates/components/` | The `Kbd` component and its parts |
| `native-select/manifest.json`, `native-select/README.md` | The `NativeSelect` recipe |
| `native-select/templates/components/` | The `NativeSelect` component and its parts |
| `progress/manifest.json`, `progress/README.md` | The `Progress` recipe |
| `progress/templates/components/` | The `Progress` component |
| `radio-group/manifest.json`, `radio-group/README.md` | The `RadioGroup` recipe |
| `radio-group/templates/components/` | The `RadioGroup` component and its parts |
| `scroll-area/manifest.json`, `scroll-area/README.md` | The `ScrollArea` recipe |
| `scroll-area/templates/components/` | The `ScrollArea` component |
| `toggle/manifest.json`, `toggle/README.md` | The `Toggle` recipe |
| `toggle/templates/components/` | The `Toggle` component |
| `toggle-group/manifest.json`, `toggle-group/README.md` | The `ToggleGroup` recipe |
| `toggle-group/templates/components/` | The `ToggleGroup` component and its parts |
| `login-form/manifest.json`, `login-form/README.md` | The `LoginForm` block |
| `login-form/templates/components/` | The `LoginForm` component |
| `signup-form/manifest.json`, `signup-form/README.md` | The `SignupForm` block |
| `signup-form/templates/components/` | The `SignupForm` component |
| `chart/manifest.json`, `chart/README.md` | The `Chart` recipe |
| `chart/templates/components/` | The `Chart` component |
| `chart-js/manifest.json`, `chart-js/README.md` | The `ChartJs` recipe |
| `chart-js/templates/components/` | The `ChartJs` component |
