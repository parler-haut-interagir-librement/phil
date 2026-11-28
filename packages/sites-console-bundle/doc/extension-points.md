# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

## On a site

| To | Do |
|---|---|
| Write pushed settings | Implement `Application\Contract\ConfigurationApplier` and name the service in `agent.configuration_applier`. |
| Run an update | Implement `Application\Contract\UpdateRunner` and name the service in `agent.update_runner`. |
| Report the health from elsewhere | Alias `Application\Contract\SiteHealth` to a service of your own, with `agent.health: none`. |
| Report the packages from elsewhere | Alias `Application\Contract\InstalledPackages` to a service of your own. |
| Keep the nonces elsewhere | Alias `Application\Contract\NonceStore` to a service of your own, or name a cache pool in `agent.cache_pool`. Remembering a nonce must be one atomic step, and a nonce not kept must throw `StateNotKept`. |
| Keep the console keys elsewhere | Alias `Application\Contract\ConsoleKeyStore` to a service of your own, or name a cache pool in `agent.key_pool`; it must be shared by the servers and survive deploys, and throw `StateNotKept` when it does not keep a state. |

An applier applies all the settings or throws; it is called once per push. A runner returns an `Outcome` — succeeded
or failed, with a short message for the report — or throws. Neither keeps state between two requests.

## On the console

| To | Do |
|---|---|
| Be told of an alert | Listen to `Application\Event\SiteAlertRaised`, dispatched once per new alert of a pulled site. |
| Pull, push or update from the application | Call `Application\Console\Puller::pull()`, `ConfigurationPushes::create()` and `deliver()`, `UpdatePlans::plan()` and `run()` (public services), or dispatch `PullSite`, `DeliverConfigurationPush` and `RunUpdatePlan`. |
| Register sites from the application | Call `Application\Console\SiteRegistry::register()` and `remove()` (public). |
| Rotate the console key from the application | Call `Application\Console\KeyRotations::run()` (public), which stops at the first failure and resumes when called again. |
| Read the fleet | Call `Application\Console\Fleet::overview()`, `site()` and `matrix()` (public). |
| Keep the console's rows elsewhere | Alias the repositories of `Domain\Contract` to services of your own. |
| Reach agents another way | Alias `Application\Contract\AgentClient` to a service of your own, or name an HTTP client in `console.http_client`. |
| Draw the screens differently | Override the templates `@PhilSitesConsole/*.html.twig` in `templates/bundles/PhilSitesConsoleBundle/`. |
| Put the screens in the site's layout | Set `console.layout` to a template with the blocks `title` and `content`. |
| Add a language | Add `translations/phil_sites_console.<locale>.php` with the keys of the English file. |

An `AgentClient` verifies what it returns or throws `AgentFailure`: the console stores what it gets as the truth of
the site.
