# Operating sites

## Registering a site

```sh
php bin/console phil:sites-console:site:add shop https://shop.example.com/_sites-console <public key> \
    --name="Shop" --group=eu --group=web --actor=ada
```

The identifier is 1 to 64 lowercase letters, digits and inner dashes, starting with a letter; it must be the
`site_id` of the site's agent. The address is read by `phil/url`: `https`, a public host name, no query, no fragment.
The public key is the one the site's agent signs with. Running the command again with the same identifier changes the
site. `phil:sites-console:site:remove shop` removes a site and its snapshots; its lines of the journal stay.

Groups name sets of sites — a region, a customer, a kind of site — that pulls and plans can target with `--group`.

## Pulling

```sh
php bin/console phil:sites-console:pull                 # every site
php bin/console phil:sites-console:pull --site=shop      # one site, repeatable
php bin/console phil:sites-console:pull --group=eu
```

Each pull asks the site's agent for its `status` and keeps a **snapshot**: the versions of PHP and Symfony, the
packages and their versions, the health and each check. A site that cannot be read, that answers with an error or
whose answer is not signed by its key gets a snapshot that says so, with the reason. The command exits with a failure
when one site could not be read.

To pull on a schedule, dispatch `Application\Message\PullSite` for each site from a Scheduler of the application, or
run the command from cron. From the screens, "Read every site now" and "Read this site now" hand the work to
Messenger when a bus is there, and run it at once otherwise.

`phil:sites-console:purge` removes the snapshots older than `snapshot_retention_days`, keeping the latest of each site.

## Alerts

A site needs attention when:

| Alert | When |
|---|---|
| `unreachable` | Its latest snapshot is an error. |
| `health_down`, `health_degraded` | Its health is down or degraded. |
| `version_behind` | A watched package is older than the newest version another site runs, or than its expected version. |

The dashboard shows the alerts of the latest snapshots. A pull that finds an alert its site's previous snapshot did
not have dispatches `Application\Event\SiteAlertRaised` once: listen to it to send a notification. A version behind
because another site was upgraded since is shown on the dashboard, and raised at the next pull that changes the site.
Versions are compared in the order of Composer's releases; a branch such as `dev-main` has no order and raises
nothing.

## Versions side by side

The versions screen lists the watched packages against the sites, the newest version of each, and marks a version
older than the newest one.

## Pushing settings

```sh
php bin/console phil:sites-console:push '{"mail.footer": "Hello", "banner.text": null}' --site=shop --site=blog
php bin/console phil:sites-console:push --retry=<push id>
```

A push is kept and journaled before anything is sent. Each site then gets a signed request carrying the push's
identifier and the settings; its agent applies them through its `ConfigurationApplier`, once: a push delivered again
answers that it was already applied. A site that failed — unreachable, a key it does not allow, an applier that threw —
is tried again by the next delivery, and only it. The push screen shows each delivery; "Deliver to the sites not
reached" delivers again.

Settings are 1 to 50 keys of lowercase letters, digits, dots, dashes and underscores, each a text of at most 1,000
characters, a number, a boolean or null.

## Updating in batches

```sh
php bin/console phil:sites-console:update:plan vendor/package 1.4.2 --site=a --site=b --site=c --batch-size=2
php bin/console phil:sites-console:update:run <plan id>               # every batch left
php bin/console phil:sites-console:update:run <plan id> --batches=1   # the next batch only
```

A plan names a package, a version and the sites in their order, grouped in batches. Running it asks each site's
agent in turn, one after the other; each outcome is kept as it comes. **The first failure stops the plan**: the site
that failed is marked failed, every site not reached is skipped, and the plan is stopped. A plan that ran every site
is completed. The plan screen is the report, one line per site with its batch, outcome and the agent's message; "Run
the next batch" runs one batch, so an operator can check the first sites before the rest.

Each step carries the plan and the site as its idempotency key: an agent that already ran it answers the first
outcome again.

## Rotating the console key

```sh
php bin/console phil:sites-console:keys:rotate --actor=ada
```

Without `console.next_private_key`, the command prints a new key pair and stops: put the private key in the console's
secrets as `console.next_private_key`, deploy the console, and run the command again. It then stages the next public
key on every site, switches the console to the next key once every site holds it, retires the previous key from every
site, and prints the report: one line per site, with whether the next key is staged, whether the previous key is
retired, and the agent's message.

**The first failure stops the rotation**: fix the site — or remove it from the console — and run the command again;
it resumes where it stopped, each step with the same idempotency key as before. While the next key is being staged
the console keeps signing with the current key, so the sites not reached yet go on answering. Once the rotation is
finished, move `console.next_private_key` to `console.private_key`, and set `agent.console_public_key` to the new
public key on each site at its next deploy. The procedure and what to do when a key leaks are in
[the security model](security-model.md#key-rotation).

## The journal

Every site registered or removed, every push created and each of its deliveries, every plan and each of its steps, every
key rotation and each of its steps is a line of the journal: when, who — the user identifier of the operator, or the
`--actor` of a command —, which site, which push, plan or rotation, whether it worked and the message. The journal
screen shows the latest 200 lines; a site's screen its latest 20.
