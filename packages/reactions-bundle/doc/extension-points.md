# Extension points

| Point | How |
|---|---|
| Who may react | A voter on `phil_reactions.react`, about a `ReactionSubject`; or a `ReactionAccess` named by `access` |
| Who the member is | A `CurrentMember` named by `current_member`, giving a stable identifier without `@`; required with Security |
| How many reactions a member gives a content | `per_member`, from 1 to 20 |
| How members are named | A `MemberNames` named by `member_names` |
| Who sees what, content by content | A `VisibilityPolicy` named by `visibility_policy` |
| More reactions | A service implementing `ReactionSource`, autoconfigured; their place with `order` |
| Where an image is | The service `EmojiImages`, decorated or replaced: `urls()` receives every image declaration at once |
| How fast a member may react | The limiter `phil_reactions` of `framework.rate_limiter`, or `rate_limiter` |
| What happens after a reaction | A listener of `ReactionAdded` and `ReactionRemoved`; `messenger: true` for later work |
| The store | The service `ReactionRepository`, replaced |
| The look of a button | The recipes, the application's code once installed |
| The page after a stopped reaction | `templates/bundles/PhilReactionsBundle/stopped.html.twig` |
| The texts | The translation domain `phil_reactions` |
| The list of names | `format_list` of twig/intl-extra, in the translator's language; TwigExtraBundle's extension when it is registered |
