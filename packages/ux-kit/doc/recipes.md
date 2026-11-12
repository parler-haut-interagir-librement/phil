# Recipes

Each recipe has its own README, with examples, in its directory; this page gathers their props. Every component takes
other attributes too, written on its root element, a `class` added to its own.

## Logo

`<twig:Logo>`: a `span`, or an `a` with `href`, of class `isLogo`, holding a `Picture` of the `image` recipe, on which
it depends.

| Prop | Default | |
|---|---|---|
| `src` | required | The image where neither AVIF nor WebP is read, PNG or SVG |
| `alt` | required | The name the logo writes; empty only when the name is written next to it |
| `webp` | `null` | A WebP source, a URL or a map of densities, offered after the AVIF one |
| `avif` | `null` | An AVIF source, a URL or a map of densities, offered first |
| `srcset` | `null` | The densities of the fallback, as HTML writes them or a map |
| `dark` | `null` | The logo of a dark system, a URL or a map of densities, offered where `prefers-color-scheme` is dark |
| `still` | `null` | The still logo of an animated one, a URL or a map of densities, offered where motion is reduced |
| `forced` | `null` | The logo drawn for forced colours, a URL or a map of densities, offered first where `forced-colors` is active |
| `priority` | `false` | The main image of the page, preloaded through WebLink and fetched first, as for `Picture` |
| `layout`, `aspect`, `fit`, `position` | `null` | The image's layout, ratio, fit and crop, as for `Image` |
| `theme` | `null` | Replaces `{theme}` in every URL |
| `locale` | `null` | Replaces `{locale}` in every URL |
| `href` | `null` | A link around the logo |
| `width`, `height` | `null` | The image's size, to reserve its space |
| `loading` | `eager` | `eager` above the fold, `lazy` below |

The `img` carries `isImage`, under the height of `isLogo`; a refused URL is left out as in `Picture`.

## AnimatedHeadline

`<twig:AnimatedHeadline>`: a heading of class `isHeadline`, controller `animated-headline`.

| Prop | Default | |
|---|---|---|
| `words` | required | The word, or the list of words to play through |
| `text` | `null` | Static text before the words |
| `suffix` | `null` | Static text after the words |
| `level` | `1` | The heading's level, kept between 1 and 6 |
| `label` | `null` | What screen readers read instead of the text, the words and the suffix |
| `separator` | `', '` | What joins the words in what screen readers read |
| `pause` | `2500` | Milliseconds a word rests |
| `duration` | `600` | Milliseconds the clip takes to close, as many to open |
| `loop` | `false` | Whether several words keep cycling, with a pause/play button, instead of playing through once |
| `pauseLabel` | `Pause animation` | The button's text while the words cycle |
| `playLabel` | `Play animation` | The button's text once paused |

With `loop` and more than one word, the heading holds, after the words, a `<button type="button">` of class
`isRevealToggle`, `hidden` until the controller shows it, and is named through `aria-labelledby` by its visually
hidden copy alone. Without `loop`, or with one word, there is no button and `loop-value` is `false`.

The controller `animated-headline`:

| Kind | Name | |
|---|---|---|
| Value | `pause`, `duration`, `loop`, `pauseLabel`, `playLabel` | From the props |
| Target | `reveal` | The clip around the stacked words |
| Target | `word` | Each word, in order |
| Target | `toggle` | The pause/play button |
| Action | `toggle` | Pauses the cycle, or plays it again |

It adds `isRevealing` to the clip, sets `--phil-reveal-size` to close it to its caret then open it on the next word,
moves `isCurrent`, and adds `isPulsing` while a word rests. One word is opened once, then the controller lets go: the
clip is back to its natural width. Several words are played through once, back to the word marked `isCurrent` by the
server; `timing()` shortens the rest and the moves so that the run, entrance included, ends within `BUDGET`
(4800 ms), under the five seconds of WCAG 2.2.2. With `loop`, the timings are kept, the words cycle, and the button
is shown, its text switched between the two labels.

The cycle holds, the current word fully open, while the headline is hovered (`mouseenter`, `mouseleave`), holds the
focus (`focusin`, `focusout` leaving it), is paused by the button, or the document is hidden (`visibilitychange`);
it goes on once nothing holds it, and pressing play lets go of the pointer and the focus at once. Each move ends on
the clip's `transitionend`, or after `duration` when the event never fires. It does nothing under
`prefers-reduced-motion`, stops when the reader turns the preference on, starts when it is turned off, measures the
word again on resize, and removes its listeners and its timer on disconnect.

## Illustration

`<twig:Illustration>`: a `figure` holding a `picture` when the chosen candidate has a WebP source, an `img` otherwise.

| Prop | Default | |
|---|---|---|
| `candidates` | required | A URL, or a list of URLs and maps of `src`, `webp`, `alt`, `width`, `height` |
| `alt` | required | The text alternative of a candidate that has none; empty for a decorative picture |
| `seed` | `null` | The candidate is the seed modulo their number; `null` draws one at random |
| `caption` | `null` | A `figcaption` |
| `loading` | `lazy` | `lazy` below the fold, `eager` above |
| `sizes` | `null` | The `sizes` of a responsive source |

No candidate renders nothing.

## Image

The `image` recipe holds two components, under the prop names of Symfony UX Image, and the Stimulus controller
`image-preview`, which paints a blurred preview only when one is given; no inline style. It calls `image_origin()` and
`image_check()` of the bundle of `phil/ux-kit` and `preload()` of Symfony WebLink, and needs both packages and the
StimulusBundle.

`<twig:Image>`: an `img` of class `isImage`, in a `figure` with its `caption`, its credit, its license, its creation and
its description.

| Prop | Default | |
|---|---|---|
| `src` | required | The URL of the image |
| `alt` | required | What the image shows; empty for a decorative image |
| `width`, `height` | required | The image's size: its space is reserved at its ratio before it loads |
| `srcset` | `null` | The candidates as HTML writes them, or a map of densities (`2x`), or of widths with `sizes` (`480w`), to URLs |
| `sizes` | `null` | Written with a `srcset`, after `auto,` while the image loads lazily; computed from the layout when null |
| `layout` | `null` | `fixed`, at its width (maps read as densities); `constrained`, never wider than its width, `sizes` `(min-width: <width>px) <width>px, 100vw`; `full-width`, across its container, `sizes` `100vw`; none: the image shrinks with its container, `sizes` as given |
| `aspect` | `null` | The ratio of its box: `16/9`, `4/3`, `3/2`, `1/1`, `21/9`, `3/4`, `2/3`, `9/16`, or `custom`, read from `--phil-image-ratio`, which a class of the site sets |
| `fit` | `null` | `cover`, the default with a ratio, or `contain` |
| `position` | `null` | Where it is cropped: `center`, `top`, `bottom`, `left` or `right` |
| `priority` | `false` | The main image above the fold: `loading="eager"`, `fetchpriority="high"`, no `decoding`, preloaded |
| `preload` | `null` | Whether the image is preloaded through Symfony WebLink, in a `Link` header; with `priority` when null |
| `loading` | `null` | `lazy` or `eager`, overriding `priority`; `lazy` when null without `priority` |
| `decoding` | `null` | `async`, `sync` or `auto`, overriding `priority`; `async` when null without `priority` |
| `caption` | `null` | A `figcaption`, escaped: the image is then in a `figure`, decorative or not |
| `credit` | `null` | Who made the image, as the site writes it, in a `small` of class `isCredit` under the caption |
| `license`, `licenseHref` | `null` | The name of the license, in a `small` of class `isLicense`, linked to its address with `rel="license"` |
| `creation` | `null` | A code of the IPTC Digital Source Type vocabulary, or an enum whose value is one, said in a `small` of class `isCreation` |
| `creationLabel` | `null` | The words that say the creation; the vocabulary's name of the code when null |
| `deferred` | `false` | An image far below the fold, fetched after the others: `fetchpriority="low"`; `priority` wins over it |
| `placeholder` | `null` | The colour of its box while it loads, a quiet tone of the tokens: `neutral`, `brand`, `red`, `green`, `amber`, `blue`, `violet` (`hasPlaceholderBlue`…), or `custom`, read from `--phil-image-placeholder` (`hasPlaceholder`) |
| `preview` | `null` | A tiny version of the image, inline in base 64 (PNG, JPEG, GIF, WebP or AVIF), painted blurred behind it while it loads by the `image-preview` controller (`hasPreview`); any other value left out |
| `description` | `null` | A long description, escaped, in a `details` of class `isDescription` after the image, its text the image is described by through `aria-describedby` |
| `descriptionLabel` | `'Description'` | The words of the description's `summary` |

`<twig:Picture>`: a `picture` whose `source` elements, in the order given, precede an `Image` holding the fallback;
the `Image` alone when no source is left; in a `figure` with its `caption`.

| Prop | Default | |
|---|---|---|
| The props of `Image` | | Carried by the fallback `img`; `sizes` also serves the sources that have none |
| `sources` | `[]` | Each `{type, srcset, sizes, media, width, height}`: `type` an `image/*` type or left out, `srcset` as for `Image`, `media` and the size of its crop for art direction |
| `dark` | `null` | Offered where `prefers-color-scheme` is dark: candidates as for `srcset`, or sources `{type, srcset}` |
| `still` | `null` | Offered where `prefers-reduced-motion` is reduce, first: candidates as for `srcset`, or sources `{type, srcset}` |
| `forced` | `null` | Offered where `forced-colors` is active, before every other source: candidates as for `srcset`, or sources `{type, srcset}` |

The sources are written in this order: the variant for forced colours, the still image, the dark variant, then those
with a `media` query, in the order of their first query, then those without; within each group, AVIF, then WebP, then
the other types, then the sources without a type. A `media` holding `<`, `>`, `{`, `}` or `;` leaves its source out; a
`width` or `height` that is not a positive integer is left out. The layout, the ratio, the fit, the crop, the
placeholder and the preview are classes of phil-ux on the `img` (`hasLayoutFixed`, `hasLayoutFullWidth`,
`hasRatio16x9`…, `hasFitCover`, `hasPositionTop`…, `hasPlaceholderBlue`…, `hasPreview`), never an inline style.

The other attributes go to the `img`, not to a root, and only `id`, `class` (added to `isImage`), `title`,
`crossorigin`, `referrerpolicy`, `itemprop`, `draggable`, `data-*` and `aria-*`, written with `html_attr()` of
`twig/html-extra`; a decorative image also leaves out `title` and `aria-*`, which would name it. A value of `loading`
or `decoding` outside the listed ones falls back to the default. A `javascript:`, `vbscript:` or `data:` URL other
than `data:image/` is refused, in whatever case, after spaces or control characters, or cut by tabs and line breaks:
a refused `src` renders nothing, a refused `srcset` is left out whole, as is a source holding a refused URL, without
candidates, or of a type other than `image/*`.

An address of another host is refused unless `phil_ux_kit.image.hosts` lists its host, read by `phil/url`: a relative
path, a `data:image/` one and the page's own origin are the page's. An image of a listed host is fetched with
`referrerpolicy="no-referrer"` unless the page gives another policy. With `priority`, `Image` preloads its `src` with its
`srcset` and `sizes`; `Picture` preloads its first source with its `type`, and nothing when a source has a `media`
query, the variants included. While the kernel is in debug, or with `phil_ux_kit.image.check`, `image_check()` logs a
warning for a text alternative that is the name of the file, a word that only says it is an image or starts by saying
so, and for a missing width or height.

`image_sources('images/logo')` reads the files of an image among the assets of AssetMapper, `logo.avif`,
`logo@2x.avif`, `logo.webp`, `logo.png`…, and gives the `src`, `srcset`, `sources`, `width` and `height` of a
`Picture`; its options are `densities`, `widths`, `formats` and `fallbacks`.

The props keep the names and the meaning of Symfony UX Image, lazy loading by default included, so that a page can
move from one to the other by changing the tag; the recipe transforms no image.

## Gallery

`<twig:Gallery>`: a `div` of class `isGallery` holding a `ul` named by `label`, each image a `Picture` in its `li`, or
a `Lightbox`; nothing without an image.

| Prop | Default | |
|---|---|---|
| `label` | required | The name of the list for screen readers |
| `images` | `[]` | Each with the props of a `Picture`, `src`, `alt`, `width`, `height` required, and `full` for its lightbox |
| `aspect`, `fit` | `null` | The ratio and the fit of every image |
| `sizes` | `null` | The width of a column, for the candidates of widths |
| `lightbox` | `false` | Whether each image opens in a `Lightbox` |
| `id` | `null` | The identifier of the gallery, from which those of its lightboxes are made; `gallery` when null |
| `openLabel`, `closeLabel` | `null`, `'Close'` | The words of each lightbox's buttons |
| `deferred` | `false` | A long gallery: its figures off screen skipped with `content-visibility: auto` (`isDeferred`), each `li` sized by its `data-width` and `data-height`, its images fetched last |
| `placeholder` | `null` | The placeholder of every image, as for `Image` |
| `descriptionLabel` | `'Description'` | The words of the summary of each image's `description` |

## Lightbox

`<twig:Lightbox>`: a `div` of class `isLightbox`, controller `lightbox`, holding the thumbnail in a button of class
`isLightboxTrigger` that opens, by `command="show-modal"` and `commandfor`, a `dialog` of class `isLightboxDialog` with
`closedby="any"`, its full image and its close button `isLightboxClose`.

| Prop | Default | |
|---|---|---|
| `id` | required | Unique in the page; the dialog's is `lightbox-<id>` |
| `src`, `alt`, `width`, `height` | required | The thumbnail; `alt` also names the dialog, the caption when it is empty |
| `srcset`, `sizes`, `sources`, `aspect`, `fit`, `position` | | The thumbnail's, as for `Picture` |
| `full` | `null` | The image of the dialog, `{src, width, height, srcset, sizes, sources}`, shown whole, sized `100vw` unless it has `sizes`; the thumbnail when null |
| `caption`, `credit`, `license`, `licenseHref`, `creation`, `creationLabel` | `null` | Under the thumbnail and under the full image |
| `openLabel` | `null` | Words read after the text alternative in the button |
| `closeLabel` | `'Close'` | What screen readers read for the close button |
| `deferred`, `placeholder`, `preview` | `false`, `null`, `null` | The thumbnail's, as for `Image` |
| `description`, `descriptionLabel` | `null`, `'Description'` | A long description after the thumbnail, outside its button, which it describes |

## ReactionButton

`<twig:ReactionButton>`: a `<button type="submit">` of class `isReaction`, with `aria-pressed`; or a `<span>` of the
same class when `interactive` is false. The emoji and the visible counter are hidden from screen readers, which read
the label and `countLabel`; the class makes a target 2.75rem wide and tall. No counter is written at zero.

| Prop | Default | |
|---|---|---|
| `label` | required | The name of the reaction |
| `emoji` | `null` | The Unicode emoji |
| `image` | `null` | The address of the image, when the reaction has no emoji |
| `imageAlt` | `null` | The replacement text of the image; `label` when null |
| `count` | `null` | The counter; none is shown when null |
| `countLabel` | `null` | What screen readers read instead of the counter |
| `pressed` | `false` | Whether this reaction is the viewer's |
| `interactive` | `true` | A button, or plain text |

## ReactionPicker

`<twig:ReactionPicker>`: a `<details>` of class `isReactionPicker`, controller `phil--reactions-bundle--picker` of
`phil/reactions-bundle`, written inside the form that receives its buttons. The search field stays `hidden` until the
controller shows it; each button works without it. Of the `attributes` of an option, only `name`, `value`, `title`,
`data-*` and `aria-*` are written: an event handler or a `formaction` given there is left out.

| Prop | Default | |
|---|---|---|
| `label` | required | The name of the disclosure |
| `groups` | required | The headings, `{label, options}`, each option `{label, emoji, image, imageAlt, attributes}`, the attributes written on its button |
| `searchLabel` | `Search reactions by name` | The name of the search field |
| `emptyLabel` | `No reaction has this name.` | What is shown when the search finds nothing |
| `resultsLabel` | `Reactions shown: {count}` | What screen readers hear after a search |

## AutosaveStatus

`<twig:AutosaveStatus>`: a block of class `isStack`, inside a form whose tag carries the controller
`phil--forms-autosave--autosave` of `phil/forms-autosave`. It holds a paragraph of class `isMuted`, `role="status"`
and `aria-live="polite"`, the target `status`; and the notice, of class `isCluster`, `role="group"` and `hidden`, the
target `notice`, with its paragraph `message` and three buttons, targets and actions `useServer` (hidden), `discard`
and `dismiss`. The controller writes the messages and shows the buttons each notice needs.

| Prop | Default | |
|---|---|---|
| `discardLabel` | `Discard the draft` | The button that puts the shown values back and forgets the draft |
| `dismissLabel` | `Keep it` | The button that hides the notice and keeps the values in the form |
| `useServerLabel` | `Use the newer draft` | The button that puts the newer draft of another device or tab in the form |

## ComingSoon

`<twig:ComingSoon>`: a block of class `isSplit isFullHeight`.

| Prop | Default | |
|---|---|---|
| `logoSrc`, `logoAlt` | required | The logo's image and name |
| `eyebrow` | required | The label above the headline |
| `words` | required | The headline's word, or words |
| `logoWebp`, `logoHref` | `null` | The logo's WebP source and link |
| `theme`, `locale` | `null` | Replace `{theme}` and `{locale}` in the logo's sources |
| `headlineText` | `null` | Static text before the headline's words |
| `paragraphs` | `[]` | Paragraphs under the headline, escaped |
| `illustrations` | `[]` | The illustration's candidates; none leaves the column out |
| `illustrationAlt` | `''` | The illustration's text alternative |
| `seed` | `null` | The illustration's seed |
| `landmarks` | `true` | `header` and `main`, or `div` when the layout has them |
| `logoWidth`, `logoHeight` | `null` | The logo's size, to reserve its space |
| `logoPriority` | `false` | The logo preloaded through WebLink and fetched first |
| `logoLayout`, `logoAspect`, `logoFit`, `logoPosition` | `null` | The logo's layout, ratio, fit and crop, as for `Image` |
| `illustrationLoading` | `lazy` | `eager` loads the illustration at once |

| Block | |
|---|---|
| `content` | More content under the paragraphs |

## LoginForm

`<twig:LoginForm>`: a `section` of class `isCard isLoginForm`, its form of plain elements posting the field names the
form login of Symfony Security reads. The focus starts on the user name, or on the password when the user name is
written back; the error is a live alert, which both fields name.

| Prop | Default | |
|---|---|---|
| `action` | required | The `check_path` of the firewall |
| `title`, `usernameLabel`, `passwordLabel`, `submitLabel` | required | The texts of the card |
| `description` | `null` | A sentence under the title |
| `id` | `'login'` | The prefix of the identifiers of the fields |
| `usernameField`, `passwordField` | `'_username'`, `'_password'` | The names of the fields |
| `usernameType` | `'username'` | `email` for an email field and its autocompletion |
| `lastUsername` | `''` | The user name written back |
| `error` | `null` | The message of the last failed attempt, translated; empty is none |
| `csrfToken`, `csrfField` | `null`, `'_csrf_token'` | The CSRF token and the name of its hidden field |
| `rememberLabel`, `rememberField` | `null`, `'_remember_me'` | A "remember me" checkbox, left out without its label |
| `forgotHref`, `forgotLabel` | `null` | The link to a forgotten password, written when both are given |
| `signupHref`, `signupLabel` | `null` | The link to the sign-up page, written when both are given |

| Block | |
|---|---|
| `content` | More fields before the button: a hidden target path, a captcha |

## SignupForm

`<twig:SignupForm>`: a `section` of class `isCard isSignupForm`: an optional name, the email address, the new password
and its optional repetition, each in an `isField` with its errors, which the field names by `aria-describedby`. The
values are written back after a refused submission, never a password.

| Prop | Default | |
|---|---|---|
| `action` | required | The address the form posts to |
| `title`, `emailLabel`, `passwordLabel`, `submitLabel` | required | The texts of the card |
| `description` | `null` | A sentence under the title |
| `id` | `'signup'` | The prefix of the identifiers of the fields |
| `nameLabel`, `confirmLabel` | `null` | The name field and the repeated password, left out without their labels |
| `passwordHint` | `null` | The rule of the password, under its field |
| `passwordMinLength` | `12` | The `minlength` of the password fields |
| `nameField`, `emailField`, `passwordField`, `confirmField` | `'name'`, `'email'`, `'password'`, `'password_confirmation'` | The names of the fields |
| `values` | `{}` | The values written back, by field name |
| `errors` | `{}` | The error messages, by field name |
| `csrfToken`, `csrfField` | `null`, `'_token'` | The CSRF token and the name of its hidden field |
| `loginHref`, `loginLabel` | `null` | The link to the sign-in page, written when both are given |

| Block | |
|---|---|
| `content` | More fields before the button: the acceptance of the terms, a consent |

## The recipes of the Shadcn kit

These recipes take the names, props, blocks and Stimulus controllers of the Shadcn kit of the Toolkit; a bundle renders
them with either kit. Their HTML is built on elements and on the classes of phil-ux. The classes of the
Shadcn sizes and variants: `default` is `isPrimary`, `secondary` `isSecondary`, `destructive` `isDanger`, `outline`
`isOutline`, `ghost` `isGhost` and `link` `isLink`; `xs` and `sm` are `isSmall`, `lg` `isLarge`, the `icon` sizes
`hasIcon isIconOnly`.
The texts of a recipe are props or blocks, in English by default, to give in the language of the page.

## Badge

A short label: a tag, a status, a count.

`<twig:Badge>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | The visual style variant. |
| `as` | `'span'` | The HTML tag to render; use `a` when the badge navigates. |
| `color` | `null` | A CSS colour replacing the colour of the variant; anything else than a colour is left out. |

## Button

A button, or a link that looks like one with `as="a"`.

`<twig:Button>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | The visual style variant. |
| `size` | `'default'` | The button size. |
| `as` | `'button'` | The HTML tag to render; use `a` when the button navigates. |
| `loading` | `false` | Whether an action is running: the button is marked busy. |

## ButtonGroup

Buttons that belong together, joined: a view switcher, the previous and next periods.

Components: `<twig:ButtonGroup>`, `<twig:ButtonGroup:Separator>`, `<twig:ButtonGroup:Text>`.

`<twig:ButtonGroup>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'horizontal'` | The layout direction of the button group. |

`<twig:ButtonGroup:Separator>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'vertical'` | The separator orientation. |

`<twig:ButtonGroup:Text>`

| Prop | Default | |
|---|---|---|
| `as` | `'div'` | The HTML tag to render. |

## Card

A card: its picture, a header, its content and a footer.

Components: `<twig:Card>`, `<twig:Card:Action>`, `<twig:Card:Content>`, `<twig:Card:Description>`, `<twig:Card:Footer>`,
`<twig:Card:Header>`, `<twig:Card:Title>`.

`<twig:Card>`

| Prop | Default | |
|---|---|---|
| `size` | `'default'` | Size variant of the card. |
| `as` | `'article'` | The HTML tag to render. |

`<twig:Card:Title>`

| Prop | Default | |
|---|---|---|
| `level` | `3` | The level of the heading, kept between 1 and 6. |

## Empty

What a list shows when it has nothing: a picture, a title, a sentence, and what to do.

Components: `<twig:Empty>`, `<twig:Empty:Content>`, `<twig:Empty:Description>`, `<twig:Empty:Header>`,
`<twig:Empty:Media>`, `<twig:Empty:Title>`.

`<twig:Empty:Media>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | The media display style. |

## Popover

A panel opened by a button, on the HTML popover API: it opens and closes, Escape and a click outside close it, without
JavaScript; the controller places it next to its button.

Components: `<twig:Popover>`, `<twig:Popover:Content>`, `<twig:Popover:Trigger>`.

`<twig:Popover>`

| Prop | Default | |
|---|---|---|
| `name` | `null` | Group name shared by mutually exclusive popovers, so only one is open at a time. |
| `open` | `false` | Whether the popover is open on initial render. |
| `id` | `null` | The identifier of the panel, generated when left out. |

`<twig:Popover:Content>`

| Prop | Default | |
|---|---|---|
| `side` | `'bottom'` | Which side of the trigger the content appears on. |
| `align` | `'center'` | Alignment of the content along the cross axis. |

The trigger and close elements take `popover_trigger_attrs`: `<twig:Button {{ ...popover_trigger_attrs }}>`.

The controller `popover`:

| Kind | Name | |
|---|---|---|
| Value | `open`, `name` | As the props |
| Target | `trigger`, `content` | The button and the panel |
| Action | `toggle`, `close` | Open and close; on an invoker the browser does it |
| Action | `sync` | Follows the `toggle` event of the panel: state, placement, focus |
| Action | `handleGroupOpen`, `handleOutsideClick`, `handleEscape` | The group of popovers; a click outside and Escape where the popover API is missing |

## QrCode

A QR code in a figure, with what it holds written beside it, a link for a web address, drawn on the server by
`phil/qr-code-bundle`.

`<twig:QrCode>`

| Prop | Default | |
|---|---|---|
| `content` | `required` | A text, or the array of a typed content of the bundle. |
| `alt` | `''` | The name of the image for screen readers. |
| `caption` | `true` | Whether what the code holds is written beside it. |
| `size` | `null` | The width and height of the image in pixels; the size of the bundle when null. |
| `level` | `null` | The error correction level, L, M, Q or H; the level of the bundle when null. |
| `logo` | `''` | The name of a logo of the configuration of the bundle. |

## Tabs

Tabs over panels. Without JavaScript every panel shows; with it, one at a time, the arrows, Home and End moving between
the tabs.

Components: `<twig:Tabs>`, `<twig:Tabs:Content>`, `<twig:Tabs:List>`, `<twig:Tabs:Trigger>`.

`<twig:Tabs>`

| Prop | Default | |
|---|---|---|
| `defaultValue` | `''` | Define the open Tabs at initial rendering; the first one when empty. |
| `orientation` | `'horizontal'` | Define the visual orientation. |

`<twig:Tabs:Content>`

| Prop | Default | |
|---|---|---|
| `value` | `required` | Unique suffix identifier for generating Tabs internal IDs. |

`<twig:Tabs:List>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | The visual style variant. |

`<twig:Tabs:Trigger>`

| Prop | Default | |
|---|---|---|
| `value` | `required` | Unique suffix identifier for generating Tabs internal IDs. |

The controller `tabs`:

| Kind | Name | |
|---|---|---|
| Value | `activeTab` | The tab shown |
| Target | `trigger`, `tab` | The tabs and the panels |
| Action | `open` | Shows the tab clicked |
| Action | `move` | The arrows, Home and End |

## Accordion

Sections that open and close, on `details` and `summary`: they work without JavaScript, the browser keeping one open at
a time; the controller adds the arrows, Home and End.

Components: `<twig:Accordion>`, `<twig:Accordion:Content>`, `<twig:Accordion:Item>`, `<twig:Accordion:Trigger>`.

`<twig:Accordion>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier for the Accordion. |
| `multiple` | `false` | Whether multiple items can be opened at once. |
| `defaultValue` | `null` | Value(s) of the item(s) to open by default. |
| `orientation` | `'vertical'` | The visual orientation, controls whether keyboard navigation uses up/down or left/right arrow keys. |

`<twig:Accordion:Item>`

| Prop | Default | |
|---|---|---|
| `value` | `required` | Unique value for this accordion item. |
| `open` | `false` | Whether the item is open by default. |
| `disabled` | `false` | Whether the item is disabled. |

The controller `accordion`:

| Kind | Name | |
|---|---|---|
| Value | `multiple`, `orientation` | As the props |
| Target | `item`, `trigger`, `content` | The `details`, the `summary` and the content |
| Action | `toggle` | Opens an item from another control than its summary |
| Action | `handleKeydown` | The arrows, Home and End |

## Dialog

A modal window, on the native `dialog` element and its invokers: the browser makes the page inert, keeps the focus
inside, closes it on Escape and gives the focus back.

Components: `<twig:Dialog>`, `<twig:Dialog:Close>`, `<twig:Dialog:Content>`, `<twig:Dialog:Description>`,
`<twig:Dialog:Footer>`, `<twig:Dialog:Header>`, `<twig:Dialog:Title>`, `<twig:Dialog:Trigger>`.

`<twig:Dialog>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier used to generate internal Dialog IDs. |
| `open` | `false` | Whether the dialog is open on initial render. |

`<twig:Dialog:Content>`

| Prop | Default | |
|---|---|---|
| `showCloseButton` | `true` | Whether to display the close button. |
| `closeLabel` | `'Close'` | What screen readers read for the close button. |

The trigger and close elements take `dialog_close_attrs`, `dialog_trigger_attrs`: `<twig:Button {{ ...dialog_close_attrs
}}>`.

The controller `dialog`:

| Kind | Name | |
|---|---|---|
| Value | `open` | As the prop |
| Target | `trigger`, `dialog` | The button and the element |
| Action | `open`, `close` | Show and close it, modal |
| Action | `closeOnClickOutside`, `closed` | A click on the backdrop; the trigger told when the browser closed it |

## Sheet

A panel sliding from an edge of the screen: a `Dialog` that keeps to one side, with the same controller.

Components: `<twig:Sheet>`, `<twig:Sheet:Close>`, `<twig:Sheet:Content>`, `<twig:Sheet:Description>`,
`<twig:Sheet:Footer>`, `<twig:Sheet:Header>`, `<twig:Sheet:Title>`, `<twig:Sheet:Trigger>`.

`<twig:Sheet>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier used to generate internal Sheet IDs. |
| `side` | `'right'` | Which edge the sheet slides in from. |
| `open` | `false` | Whether the sheet is open on initial render. |

`<twig:Sheet:Content>`

| Prop | Default | |
|---|---|---|
| `showCloseButton` | `true` | Whether to display the close button. |
| `closeLabel` | `'Close'` | What screen readers read for the close button. |

The trigger and close elements take `sheet_close_attrs`, `sheet_trigger_attrs`: `<twig:Button {{ ...sheet_close_attrs
}}>`.

## Switch

An on and off control: a checkbox with the role `switch`, which the browser checks and submits without JavaScript.

`<twig:Switch>`

| Prop | Default | |
|---|---|---|
| `size` | `'default'` | The switch size. |
| `checked` | `false` | Whether the switch is initially checked. |

## Tooltip

A short description of a control, shown on hover and focus: it can be hovered, stays until left, and Escape dismisses it
(WCAG 1.4.13).

Components: `<twig:Tooltip>`, `<twig:Tooltip:Content>`, `<twig:Tooltip:Trigger>`.

`<twig:Tooltip>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier for the tooltip. |
| `delayDuration` | `0` | Delay in milliseconds before showing the tooltip. |

`<twig:Tooltip:Content>`

| Prop | Default | |
|---|---|---|
| `side` | `'top'` | The preferred side to display the tooltip. |
| `sideOffset` | `0` | The distance in pixels from the trigger. |

The trigger and close elements take `tooltip_trigger_attrs`: `<twig:Button {{ ...tooltip_trigger_attrs }}>`.

The controller `tooltip`:

| Kind | Name | |
|---|---|---|
| Value | `delayDuration`, `wrapperSelector`, `contentSelector`, `arrowSelector` | The delay; the elements when they are moved out of the controller |
| Target | `trigger`, `wrapper`, `content`, `arrow` | Its parts |
| Action | `show`, `hide` | On hover and focus, and on the tooltip itself |

## Pagination

The links between the pages of a list.

Components: `<twig:Pagination>`, `<twig:Pagination:Content>`, `<twig:Pagination:Ellipsis>`, `<twig:Pagination:Item>`,
`<twig:Pagination:Link>`, `<twig:Pagination:Next>`, `<twig:Pagination:Previous>`.

`<twig:Pagination>`

| Prop | Default | |
|---|---|---|
| `label` | `'Pagination'` | The name of the navigation for screen readers. |

`<twig:Pagination:Ellipsis>`

| Prop | Default | |
|---|---|---|
| `text` | `'More pages'` | What screen readers read instead of the ellipsis. |

`<twig:Pagination:Link>`

| Prop | Default | |
|---|---|---|
| `active` | `false` | Whether this is the current page. |
| `size` | `'icon'` | The button size. |

`<twig:Pagination:Next>`

| Prop | Default | |
|---|---|---|
| `text` | `'Next'` | The label text. |
| `label` | `'Go to next page'` | What screen readers read for the link. |

`<twig:Pagination:Previous>`

| Prop | Default | |
|---|---|---|
| `text` | `'Previous'` | The label text. |
| `label` | `'Go to previous page'` | What screen readers read for the link. |

## Carousel

Slides in a row, on native scroll snapping: they scroll by touch, trackpad and keyboard without JavaScript; the
controller adds the buttons, the arrows and, on request, the automatic play. `::scroll-button()` being in one engine
alone, the buttons do not rely on it.

Components: `<twig:Carousel>`, `<twig:Carousel:Content>`, `<twig:Carousel:Item>`, `<twig:Carousel:Next>`,
`<twig:Carousel:Previous>`.

`<twig:Carousel>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'horizontal'` | The axis the slides scroll along. |
| `align` | `'center'` | The alignment of the selected slide inside the viewport. |
| `loop` | `false` | Whether the carousel wraps around after the last slide. |
| `options` | `{}` | Extra options read by the controller, merged over `align` and `loop`. |
| `autoplay` | `0` | The delay in milliseconds between two automatic slide changes. Zero disables autoplay. |
| `label` | `'Carousel'` | The accessible name of the carousel region. |
| `roleDescription` | `'carousel'` | What the carousel is announced as. |
| `pauseLabel` | `'Pause'` | The text of the button while the slides play. |
| `playLabel` | `'Play'` | The text of the button once paused. |

`<twig:Carousel:Item>`

| Prop | Default | |
|---|---|---|
| `roleDescription` | `'slide'` | What the slide is announced as. |

`<twig:Carousel:Next>`

| Prop | Default | |
|---|---|---|
| `variant` | `'outline'` | The button style variant. |
| `size` | `'icon-sm'` | The button size. |
| `text` | `'Next slide'` | The accessible label of the button. |

`<twig:Carousel:Previous>`

| Prop | Default | |
|---|---|---|
| `variant` | `'outline'` | The button style variant. |
| `size` | `'icon-sm'` | The button size. |
| `text` | `'Previous slide'` | The accessible label of the button. |

The controller `carousel`:

| Kind | Name | |
|---|---|---|
| Value | `orientation`, `options`, `autoplay`, `pauseLabel`, `playLabel` | As the props, `loop` read from `options` |
| Target | `viewport`, `previous`, `next`, `toggle` | The scroller, the buttons, the pause button |
| Action | `scrollPrev`, `scrollNext`, `handleKeydown`, `update` | One slide at a time, the arrows, the ends disabled |
| Action | `hold`, `release`, `toggleAutoplay` | Automatic play held or paused |
| Event | `carousel:select` | `{index, count}` when the slide changes |

## Spinner

What shows while something loads; phil-ux stills it under `prefers-reduced-motion`.

`<twig:Spinner>`

| Prop | Default | |
|---|---|---|
| `label` | `'Loading'` | The accessible label announced to assistive technologies. |

## Skeleton

A placeholder for content on its way, hidden from screen readers; phil-ux stills it under `prefers-reduced-motion`.

## Alert

A message that stands out: an error, a confirmation.

Components: `<twig:Alert>`, `<twig:Alert:Action>`, `<twig:Alert:Description>`, `<twig:Alert:Title>`.

`<twig:Alert>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | The visual style variant. |

## Avatar

The picture of a person, over the initials that show when it is missing or broken, without script.

Components: `<twig:Avatar>`, `<twig:Avatar:Badge>`, `<twig:Avatar:Fallback>`, `<twig:Avatar:Group>`,
`<twig:Avatar:GroupCount>`, `<twig:Avatar:Image>`.

`<twig:Avatar>`

| Prop | Default | |
|---|---|---|
| `size` | `'default'` | The avatar size. |

## Input

A text field; any attribute reaches the element.

## Label

The name of a form control.

## Select

A list of options to choose one from, submitted as a field of the form when it has a `name`. For a page without
JavaScript, the `select` element is styled by phil-ux on its own.

Components: `<twig:Select>`, `<twig:Select:Content>`, `<twig:Select:Group>`, `<twig:Select:Item>`,
`<twig:Select:Label>`, `<twig:Select:Separator>`, `<twig:Select:Trigger>`, `<twig:Select:Value>`.

`<twig:Select>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier set on the trigger, so a `<label for>` can point at it, and used to generate the internal Select IDs. |
| `name` | `null` | Name of the form field carrying the selected value. |
| `value` | `''` | The value selected on initial render. |
| `disabled` | `false` | Whether the select is disabled. |
| `required` | `false` | Whether a value must be selected before the form can be submitted. |
| `open` | `false` | Whether the select is open on initial render. |

`<twig:Select:Content>`

| Prop | Default | |
|---|---|---|
| `position` | `'item-aligned'` | How the content is placed: over the trigger on the selected item, or anchored to the trigger edge. |
| `side` | `'bottom'` | Which side of the trigger the content appears on. |
| `align` | `'start'` | Alignment of the content along the cross axis. |

`<twig:Select:Group>`

| Prop | Default | |
|---|---|---|
| `id` | `null` | Unique identifier linking the group to its `Select:Label`, so the group is announced with a name. |

`<twig:Select:Item>`

| Prop | Default | |
|---|---|---|
| `value` | `required` | The value submitted when this item is selected. |
| `disabled` | `false` | Whether the item is disabled. |

`<twig:Select:Trigger>`

| Prop | Default | |
|---|---|---|
| `size` | `'default'` | The trigger size. |

`<twig:Select:Value>`

| Prop | Default | |
|---|---|---|
| `placeholder` | `''` | Text shown while no option is selected. |

The controller `select`:

| Kind | Name | |
|---|---|---|
| Value | `open`, `value` | As the props |
| Target | `trigger`, `value`, `content`, `viewport`, `item`, `hiddenSelect`, `scrollUpButton`, `scrollDownButton` | Its parts |
| Action | `toggle`, `sync`, `selectItem`, `highlightItem`, `unhighlightItem`, `onTriggerKeydown`, `onContentKeydown`, `scrollUp`, `scrollDown`, `onViewportScroll` | Opening, choosing, the keyboard and the scrolling |
| Event | `select:change` | `{value, label}`, and a `change` on the hidden field |

## Separator

A line between two parts of a page.

`<twig:Separator>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'horizontal'` | The separator orientation. |
| `decorative` | `true` | Whether the separator is purely decorative (not semantic). |

## Textarea

A field of several lines.

## Checkbox

A checkbox, drawn by phil-ux.

## Breadcrumb

Where the page sits in the site.

Components: `<twig:Breadcrumb>`, `<twig:Breadcrumb:Ellipsis>`, `<twig:Breadcrumb:Item>`, `<twig:Breadcrumb:Link>`,
`<twig:Breadcrumb:List>`, `<twig:Breadcrumb:Page>`, `<twig:Breadcrumb:Separator>`.

`<twig:Breadcrumb>`

| Prop | Default | |
|---|---|---|
| `label` | `'Breadcrumb'` | The name of the navigation for screen readers. |

`<twig:Breadcrumb:Ellipsis>`

| Prop | Default | |
|---|---|---|
| `text` | `'More'` | What screen readers read instead of the ellipsis. |

## DropdownMenu

A menu of actions opened by a button, on the popover API: Escape and a click outside close it and the focus goes back to
the button.

The keyboard follows WAI-ARIA and differs from the Shadcn kit in two ways: a submenu opens at the arrow towards its end
side (the right arrow, or the left one right to left) and not only at Enter; and Escape on a dialog is left to the
browser, which closes the top one alone when dialogs are nested, instead of closing them all. Escape in a submenu closes
that submenu and gives the focus back to the item that opened it.

Components: `<twig:DropdownMenu>`, `<twig:DropdownMenu:CheckboxItem>`, `<twig:DropdownMenu:Content>`,
`<twig:DropdownMenu:Group>`, `<twig:DropdownMenu:Item>`, `<twig:DropdownMenu:Label>`, `<twig:DropdownMenu:RadioGroup>`,
`<twig:DropdownMenu:RadioItem>`, `<twig:DropdownMenu:Separator>`, `<twig:DropdownMenu:Shortcut>`,
`<twig:DropdownMenu:Sub>`, `<twig:DropdownMenu:SubContent>`, `<twig:DropdownMenu:SubTrigger>`,
`<twig:DropdownMenu:Trigger>`.

`<twig:DropdownMenu>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier used to generate the internal DropdownMenu IDs. |
| `open` | `false` | Whether the menu is open on initial render. |
| `side` | `'bottom'` | Which side of the trigger the menu appears on. |
| `align` | `'start'` | Alignment of the menu along the cross axis. |

`<twig:DropdownMenu:CheckboxItem>`

| Prop | Default | |
|---|---|---|
| `checked` | `false` | Whether the checkbox is initially checked. |
| `disabled` | `false` | Whether the item is disabled. |
| `closeOnSelect` | `false` | Whether selecting the item closes the menu. |

`<twig:DropdownMenu:Item>`

| Prop | Default | |
|---|---|---|
| `as` | `'button'` | The HTML tag to render. |
| `disabled` | `false` | Whether the item is disabled. |
| `inset` | `false` | Whether the item is inset with extra start padding. |
| `variant` | `'default'` | The visual style variant. |
| `closeOnSelect` | `true` | Whether selecting the item closes the menu. |

`<twig:DropdownMenu:Label>`

| Prop | Default | |
|---|---|---|
| `inset` | `false` | Whether the label is inset with extra start padding. |

`<twig:DropdownMenu:RadioGroup>`

| Prop | Default | |
|---|---|---|
| `value` | `null` | The currently selected value. |

`<twig:DropdownMenu:RadioItem>`

| Prop | Default | |
|---|---|---|
| `value` | `required` | The value submitted when this radio is selected. |
| `checked` | `false` | Whether this radio is initially checked. |
| `disabled` | `false` | Whether the item is disabled. |
| `closeOnSelect` | `false` | Whether selecting the item closes the menu. |

`<twig:DropdownMenu:Sub>`

| Prop | Default | |
|---|---|---|
| `openDelay` | `0` | Delay in milliseconds before showing the sub-menu. |
| `closeDelay` | `0` | Delay in milliseconds before hiding the sub-menu. |

`<twig:DropdownMenu:SubTrigger>`

| Prop | Default | |
|---|---|---|
| `inset` | `false` | Whether the trigger is inset with extra start padding. |

The trigger and close elements take `dropdown_menu_trigger_attrs`: `<twig:Button {{ ...dropdown_menu_trigger_attrs }}>`.

The controller `dropdown-menu, dropdown-menu-sub`:

| Kind | Name | |
|---|---|---|
| Value | open; openDelay, closeDelay | As the props |
| Target | `trigger`, `content`, `item` | The menu; `trigger` and `content` for a sub-menu |
| Action | `toggle`, `sync`, `closeFromItem`, `toggleCheckbox`, `selectRadio`, `onTriggerKeydown`, `onContentKeydown` | The menu and the roles of its items |
| Action | `show`, `hide`, `sync` | The sub-menu |

## Table

A table of data, which scrolls sideways inside its own box on a small screen.

Components: `<twig:Table>`, `<twig:Table:Body>`, `<twig:Table:Caption>`, `<twig:Table:Cell>`, `<twig:Table:Footer>`,
`<twig:Table:Head>`, `<twig:Table:Header>`, `<twig:Table:Row>`.

## AlertDialog

A question that interrupts, in a modal window on the native `dialog` element with the role `alertdialog`: Escape
dismisses it, as a request to close (`closedby="closerequest"`), a click on the backdrop does not, and the focus starts
on the cancel button. It runs on the `dialog` controller, which the recipe installs with the `Dialog` recipe.

Components: `<twig:AlertDialog>`, `<twig:AlertDialog:Action>`, `<twig:AlertDialog:Cancel>`,
`<twig:AlertDialog:Content>`, `<twig:AlertDialog:Description>`, `<twig:AlertDialog:Footer>`,
`<twig:AlertDialog:Header>`, `<twig:AlertDialog:Title>`, `<twig:AlertDialog:Trigger>`.

`<twig:AlertDialog>`

| Prop | Default | |
|---|---|---|
| `id` | `required` | Unique identifier used to generate internal AlertDialog IDs. |
| `open` | `false` | Whether the alert dialog is open on initial render. |

`<twig:AlertDialog:Action>` and `<twig:AlertDialog:Cancel>`: buttons that close the alert dialog, of variant `default`
and `outline`; any other attribute reaches the button, such as `type="submit"` and `form`.

The trigger element takes `alert_dialog_trigger_attrs`: `<twig:Button {{ ...alert_dialog_trigger_attrs }}>`.

## AspectRatio

A box kept to a ratio, its picture or video covering it.

`<twig:AspectRatio>`

| Prop | Default | |
|---|---|---|
| `ratio` | `1` | The ratio of the width to the height, a number or a quotient such as `"16 / 9"`; anything else is a square |

## Collapsible

A part of the page that opens and closes, on `details` and `summary`, without JavaScript: the summary is the trigger.

Components: `<twig:Collapsible>`, `<twig:Collapsible:Content>`, `<twig:Collapsible:Trigger>`.

`<twig:Collapsible>`

| Prop | Default | |
|---|---|---|
| `open` | `false` | Whether the collapsible is open on initial render. |
| `disabled` | `false` | Whether the collapsible is disabled. |

## Field

A form field — its label, its control, its help and its errors — and sets of fields under a legend.

Components: `<twig:Field>`, `<twig:Field:Content>`, `<twig:Field:Description>`, `<twig:Field:Error>`,
`<twig:Field:Group>`, `<twig:Field:Label>`, `<twig:Field:Legend>`, `<twig:Field:Separator>`, `<twig:Field:Set>`,
`<twig:Field:Title>`.

`<twig:Field>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'vertical'` | `vertical`, `horizontal`, or `responsive`, horizontal on a container of 40rem or wider |
| `invalid` | `false` | Whether the field holds an invalid value: it takes `isDanger` and `data-invalid` |

`<twig:Field:Error>`: a live alert, written only when there is an error: a paragraph for one, a list for several, its
block in place of `errors`.

| Prop | Default | |
|---|---|---|
| `errors` | `[]` | The error messages, each escaped |

`<twig:Field:Legend>`

| Prop | Default | |
|---|---|---|
| `variant` | `'legend'` | `legend`, or `label` for the size of a label |

## Item

One entry of a list: its media, its title and description, its actions.

Components: `<twig:Item>`, `<twig:Item:Actions>`, `<twig:Item:Content>`, `<twig:Item:Description>`,
`<twig:Item:Footer>`, `<twig:Item:Group>`, `<twig:Item:Header>`, `<twig:Item:Media>`, `<twig:Item:Separator>`,
`<twig:Item:Title>`.

`<twig:Item>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | `default`, `outline` or `muted` |
| `size` | `'default'` | `default` or `sm` |
| `as` | `'div'` | `div`, `a`, or `li` inside an `Item:Group`, which is a list |

`<twig:Item:Media>`, hidden from screen readers

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | `default`, `icon` or `image` |

## Kbd

A key of the keyboard, on the native `kbd` element, and the keys of a shortcut.

Components: `<twig:Kbd>`, `<twig:Kbd:Group>`.

## NativeSelect

The `select` element of the browser, dressed by phil-ux.

Components: `<twig:NativeSelect>`, `<twig:NativeSelect:OptGroup>`, `<twig:NativeSelect:Option>`.

| Prop | Default | |
|---|---|---|
| `size` | `'default'` | `default` or `sm`, on `<twig:NativeSelect>` |
| `label` | required | The name of the group, on `<twig:NativeSelect:OptGroup>` |

## Progress

The progress of a task, on the native `progress` element.

`<twig:Progress>`

| Prop | Default | |
|---|---|---|
| `value` | `null` | The progress, kept between 0 and the maximum; null while it is unknown |
| `max` | `100` | The value of a finished task; 100 when it is not above 0 |

## RadioGroup

Radio buttons that share a name, in an element of role `radiogroup`: the browser checks one at a time, moves between
them with the arrows and submits it.

Components: `<twig:RadioGroup>`, `<twig:RadioGroup:Item>`.

`<twig:RadioGroup>`

| Prop | Default | |
|---|---|---|
| `name` | required | The name of the form field the radio buttons share |
| `value` | `null` | The value checked on initial render |
| `orientation` | `'vertical'` | `vertical` or `horizontal` |
| `disabled`, `required` | `false` | Set on every radio button |

`<twig:RadioGroup:Item>`: an `input` of type `radio`, named by a `Label`; `value` required, `disabled`.

## ScrollArea

A box whose content scrolls inside it, with thin scroll bars.

`<twig:ScrollArea>`

| Prop | Default | |
|---|---|---|
| `orientation` | `'vertical'` | `vertical`, `horizontal` or `both` |
| `label` | `null` | The name of the region; given, the box is a region the keyboard reaches |

## Toggle

A button that stays pressed, on a checkbox inside its label: the browser presses it and submits it with the form,
without JavaScript.

`<twig:Toggle>`

| Prop | Default | |
|---|---|---|
| `variant` | `'default'` | `default` or `outline` |
| `size` | `'default'` | `default`, `sm` or `lg` |
| `pressed`, `disabled` | `false` | The state on initial render |
| `name` | `null` | The name of the form field |
| `value` | `'on'` | The value submitted while pressed |

## ToggleGroup

Toggles that belong together: radio buttons for one choice, checkboxes for several, in a group named by `label`.

Components: `<twig:ToggleGroup>`, `<twig:ToggleGroup:Item>`.

`<twig:ToggleGroup>`

| Prop | Default | |
|---|---|---|
| `name`, `label` | required | The field name the toggles share and the name of the group |
| `type` | `'single'` | `single`, or `multiple`, whose field name ends in `[]` |
| `value` | `null` | The value, or the values, pressed on initial render |
| `variant`, `size` | `'default'` | As on `Toggle` |
| `disabled` | `false` | Set on every toggle |

`<twig:ToggleGroup:Item>`: `value` required, `disabled`.

## Recipes of this kit alone

`Logo`, `AnimatedHeadline`, `Illustration`, `Gallery`, `Lightbox`, `AutosaveStatus`, `ReactionButton`, `ReactionPicker`,
`QrCode`, the blocks `ComingSoon`, `LoginForm` and `SignupForm`, the two charts and the recipes below have no equivalent
in the Shadcn kit. On Tailwind, the kit `phil/ux-kit-shadcn` holds each of them under the same names, props, blocks and
controllers.

## With the Common kit

The recipes of the Common kit of the Toolkit (`clipboard`, `closeable`, `password-visibility`, `post-link`,
`logout-link`, `sortable`, `tooltip`) are used as they are, from that kit; this kit does not copy them.

```sh
php bin/console ux:install clipboard --kit=common
```

## ColorPicker

`<twig:ColorPicker>`: a `div` of class `isColorPicker`: the colour well, its text and its swatches. Controller
`color-picker`: the actions `pick`, `type` and `choose`, the targets `well`, `text` and `swatch`.

| Prop | Default | |
|---|---|---|
| `label` | required | The name the colour well is read by |
| `id` | `null` | The identifier of the field |
| `name` | `null` | The name of the field |
| `value` | `#000000` | The colour, as `#rrggbb`; anything else is black |
| `swatches` | `[]` | The colours to choose from: `#rrggbb`, or maps of `value` and `label` |
| `valueLabel` | `Hexadecimal value` | The name of the text field |
| `swatchesLabel` | `Suggested colours` | The name of the group of swatches |
| `disabled` | `false` | Whether the field cannot be changed for now |

## DatePicker

`<twig:DatePicker>`: a `div` of class `isDatePicker`: the date field, its button and the month in a popover. Controller
`date-picker`: the actions `toggled`, `navigate`, `choose`, `previous` and `next`, the targets `input`, `trigger`,
`panel`, `heading`, `weekdays` and `weeks`, the classes `day` and `weekday`, written by the template on what the script
creates.

| Prop | Default | |
|---|---|---|
| `id` | required | The identifier of the field, from which those of the panel are made |
| `name` | `null` | The name of the field |
| `value` | `null` | The date, as `YYYY-MM-DD` |
| `min` | `null` | The first date that can be chosen |
| `max` | `null` | The last date that can be chosen |
| `label` | `null` | What screen readers call the field, without a `label` beside it |
| `locale` | `null` | The language of the month; that of the page when null |
| `firstDay` | `null` | The first day of the week, 0 for Sunday; that of the language when null |
| `openLabel` | `Choose a date` | The name of the button that opens the month |
| `previousLabel` | `Previous month` | The name of the button that goes back a month |
| `nextLabel` | `Next month` | The name of the button that goes forward a month |
| `required` | `false` | Whether a date has to be given |
| `disabled` | `false` | Whether the field cannot be changed for now |

## CopyButton

`<twig:CopyButton>`: a `span` of class `isCopyButton`: the button and its live region. Controller `copy-button`: the
action `copy`, the event `copy-button:copied`.

| Prop | Default | |
|---|---|---|
| `text` | `null` | The text to copy |
| `source` | `null` | The CSS selector of the element whose value or text is copied, when `text` is null |
| `label` | `Copy` | The name of the button |
| `copiedLabel` | `Copied` | What the button says once the text is copied |
| `failedLabel` | `Copy failed` | What the button says when the browser refuses |
| `duration` | `2000` | How long the outcome shows, in milliseconds |

## AnimatedButton

`<twig:AnimatedButton>`: a `button`, or an `a` with `href`, of class `isAnimatedButton`; no controller.

| Prop | Default | |
|---|---|---|
| `effect` | `shine` | How it moves |
| `href` | `null` | Where the link leads; a button when null |
| `type` | `button` | The type of the button |
| `loading` | `false` | Whether what it started is still running |

## Stepper

`<twig:Stepper>`: an `ol` of class `isStepper`, holding `<twig:Stepper:Item>`, an `li` of class `isStepperItem`.

| Prop | Default | |
|---|---|---|
| `label` | `null` | What screen readers call the list of steps |

`<twig:Stepper:Item>`:

| Prop | Default | |
|---|---|---|
| `state` | `upcoming` | Where the process stands at this step |
| `href` | `null` | Where the step leads, when it can be gone back to |
| `doneLabel` | `null` | What screen readers read before the name of a finished step |

## ActivityCalendar

`<twig:ActivityCalendar>`: a `div` of class `hasActivityCalendar`, holding the `ol` of class `isActivityCalendar` and
the legend.

| Prop | Default | |
|---|---|---|
| `days` | required | The days in order, each a map of `level`, from 0 to 4, and `label` |
| `label` | required | What screen readers call the calendar |
| `offset` | `0` | The empty cells before the first day, from 0 to 6, so that it sits on its weekday |
| `lessLabel` | `null` | The word at the low end of the legend, which needs both ends |
| `moreLabel` | `null` | The word at the high end of the legend, which needs both ends |

## Tree

`<twig:Tree>`: a `ul` of class `isTree`, holding `<twig:Tree:Item>`, an `li` of class `isTreeItem`. Controller `tree`:
the actions `navigate` and `activate`, the targets `item`, `toggle` and `link`, the event `tree:select`, whose
`detail.value` is the value of the item.

| Prop | Default | |
|---|---|---|
| `label` | required | What screen readers call the tree |

`<twig:Tree:Item>`:

| Prop | Default | |
|---|---|---|
| `label` | required | The name of the item |
| `value` | `null` | What the event `tree:select` carries; the name when null |
| `branch` | `false` | Whether the item holds others, written as its content |
| `expanded` | `false` | Whether the branch shows what it holds at first |
| `selected` | `false` | Whether the item is the chosen one |
| `href` | `null` | Where the item leads |

## Rating

`<twig:Rating>`: a `fieldset` of radio buttons with a `name`, a `span` of `role="img"` without one, both of class
`isRating`; no controller.

| Prop | Default | |
|---|---|---|
| `label` | required | What is being rated |
| `name` | `null` | The name of the field; read-only when null |
| `value` | `0` | The value |
| `max` | `5` | How many stars, up to ten |
| `starLabel` | `{value} of {max}` | What screen readers read for a note, `{value}` and `{max}` replaced |
| `clearLabel` | `null` | The choice of no star, which makes the note optional; none when null |
| `required` | `false` | Whether a note has to be given |
| `disabled` | `false` | Whether the note cannot be changed for now |

## NumberInput

`<twig:NumberInput>`: a `div` of class `isNumberInput`. Controller `number-input`: the actions `decrement`, `increment`
and `refresh`.

| Prop | Default | |
|---|---|---|
| `id` | `null` | The identifier of the field, for a `label` beside it |
| `name` | `null` | The name of the field |
| `value` | `null` | The value |
| `min` | `null` | The lowest value |
| `max` | `null` | The highest value |
| `step` | `1` | The step, or `any` |
| `label` | `null` | What screen readers call the field, without a `label` beside it |
| `decrementLabel` | `Decrease` | The name of the button that lowers the value |
| `incrementLabel` | `Increase` | The name of the button that raises the value |
| `required` | `false` | Whether a value has to be given |
| `disabled` | `false` | Whether the field cannot be changed for now |

## FileInput

`<twig:FileInput>`: a `div` of class `isFileInput`. Controller `file-input`: the actions `over`, `leave`, `drop`, `list`
and `clear`, the classes `item` and `dragging`. Its limits serve the reader; the server validates what it receives.

| Prop | Default | |
|---|---|---|
| `name` | required | The name of the field |
| `label` | required | What the zone says |
| `hint` | `null` | A second line in the zone: the types and the size expected |
| `id` | `null` | The identifier of the field |
| `accept` | `null` | The types taken, as the attribute `accept` writes them |
| `multiple` | `false` | Whether several files can be given |
| `maxSize` | `0` | The largest file taken, in bytes; 0 for no limit |
| `rejectedLabel` | `Files left out: {count}` | What is said of the files left out, `{count}` replaced |
| `clearLabel` | `Remove` | The button that empties the field |
| `required` | `false` | Whether a file has to be given |
| `disabled` | `false` | Whether the field cannot be changed for now |

## Tag

`<twig:Tag>`: a `span` of class `isTag`. Controller `tag`, when it is removable: the action `remove`, the event
`tag:remove`, which can be cancelled and whose `detail.name` is the value of the tag.

| Prop | Default | |
|---|---|---|
| `label` | required | What the tag says |
| `removable` | `false` | Whether it can be taken away |
| `name` | `null` | The name of a hidden field carrying the tag in a form |
| `value` | `null` | What the field and the event carry; the label when null |
| `removeLabel` | `Remove {label}` | The name of the button, `{label}` replaced |

## RelativeTime

`<twig:RelativeTime>`: a `time` of class `isRelativeTime`. Controller `relative-time`, on a single timer, set again when
the page comes back.

| Prop | Default | |
|---|---|---|
| `datetime` | required | The moment |
| `format` | `Y-m-d H:i` | The date written by the server, in the format of the filter `date` |
| `locale` | `null` | The language of the text; that of the page when null |
| `length` | `long` | The length of the words |
| `limit` | `0` | How old a moment stays relative, in seconds; 0 for always |

## ProgressRing

`<twig:ProgressRing>`: a `div` of `role="progressbar"` and class `isProgressRing`; no controller.

| Prop | Default | |
|---|---|---|
| `label` | required | What is progressing |
| `value` | `null` | How far it is; not known when null |
| `max` | `100` | The value at the end |
| `text` | `null` | What the centre says instead of the percentage |

## Sparkline

`<twig:Sparkline>`: an `svg` of `role="img"` and class `isSparkline`, drawn by the server; nothing is written without
values. The last 365 values are kept.

| Prop | Default | |
|---|---|---|
| `values` | required | The values, oldest first |
| `label` | required | What the line says, read by screen readers |
| `width` | `96` | The width of the drawing |
| `height` | `24` | The height of the drawing |
| `area` | `false` | Whether the area under the line is filled |

## BeforeAfter

`<twig:BeforeAfter>`: a `div` of class `isBeforeAfter`, with the blocks `before` and `after`. Controller `before-after`:
the action `move`, the targets `range` and `before`.

| Prop | Default | |
|---|---|---|
| `label` | required | What the slider moves, read by screen readers |
| `position` | `50` | Where the cut starts, as a percentage |

## Chart

`<twig:Chart>`: a `figure` of class `isChart`, its caption, the drawing in SVG hidden from screen readers, a legend when
there are several series, and the table of the values. The scale is rounded to steps of 1, 2 or 5 times a power of ten,
four to six of them whatever the values; a missing value breaks the line, a value alone between two missing ones is a
dot. The last 366 labels and the first six series are kept. Each series takes a tone, `isPrimary`, `isInfo`,
`isSuccess`, `isWarning`, `isDanger` then `isNeutral`, and from the second on, its line a dash of its own.

| Prop | Default | |
|---|---|---|
| `label` | required | What the chart shows, its caption |
| `labels` | required | The labels along the horizontal axis, oldest first |
| `series` | required | The series, each a mapping of its `label` and its `values` |
| `type` | `'bar'` | `bar` or `line` |
| `width`, `height` | `640`, `320` | The size of the drawing, which shrinks to its box |
| `tableLabel` | `null` | The summary of a disclosure showing the table; without it, screen readers alone read it |

## ChartJs

`<twig:ChartJs>`: a `figure` of class `isChart hasChartJs`, the canvas that Symfony UX Chart.js draws, hidden from screen
readers, and the table of the same values read from the chart's data. Chart.js, its options and its plugins are the
application's; the recipe depends on `symfony/ux-chartjs`, which `ux:install` adds.

| Prop | Default | |
|---|---|---|
| `chart` | required | The `Symfony\UX\Chartjs\Model\Chart` the application built |
| `label` | required | What the chart shows, its caption |
| `tableLabel` | `null` | The summary of a disclosure showing the table; without it, screen readers alone read it |
