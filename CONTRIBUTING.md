# Contributing

This is the monorepo of the open packages of [Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement)
(PHIL). Every `phil/*` package is developed here, and its own repository is a read-only copy split from
`packages/<name>/`: **open issues and pull requests here**, not on a split repository, which each split overwrites.

## Principles

The twelve principles of the [README](README.md#principles) hold for every package. Each one is a rule a change
follows:

1. **Secure by default.** Defaults are the safe choice; a less safe behaviour needs an explicit, documented option.
   Everything that comes from people, bots or third parties is validated and bounded in size, depth and time.
   Queries are parameterised, output is escaped by its context, secrets and personal data never reach logs or
   messages. Security-sensitive packages carry fuzzing and taint analysis in their CI.
2. **Open, all of it.** Each package carries its own licence file. The code of a package is its own: third-party code
   enters only as a declared Composer dependency, and other projects are studied for their ideas. Reference data from
   open sources may be imported by a script, with its licence and attribution recorded next to it.
3. **Symfony-native.** A package installs with `composer require` and, when it needs wiring, a Flex recipe. A bundle
   has the standard shape: its configuration tree, services tagged the Symfony way, no hidden bootstrap. Its
   configuration lives in `config/packages/`. The library uses an existing Symfony component rather than reinventing
   it. Templates use what Twig and twig/extra already provide; front-end code goes through AssetMapper and Stimulus,
   with no Node build step required.
4. **Bilingual, every language.** English is the source of `README.md` and `doc/`; the French translation in
   `README.fr.md` and `doc/fr/` is updated in the same change, and the tooling checks it. Every user-facing string is
   translatable; formats, plurals, addresses and typography follow the locale, from CLDR and other open references.
5. **Private by design.** No tracking and no third-party call without the site's explicit configuration. Analytics in
   aggregates only, IP addresses anonymised. Personal fields are declared so that they can be exported and erased.
   Queued messages carry identifiers, never personal data.
6. **Fast, measured, worker-safe.** Each hot path has a benchmark, and a change that slows it needs a reason.
   Complexity is bounded. No state survives a request, or it is reset through `kernel.reset`. Plain PHP is the
   reference and works everywhere (PHP-FPM, CLI, FrankenPHP); an optional accelerator lives in its own package, only
   after a benchmark proves the gain, with differential tests against the PHP version.
7. **Accessible by default.** Markup uses the native element that carries the meaning; ARIA only fills what HTML
   lacks. WCAG 2.2 AA is the floor: keyboard use, visible focus, focus moved and restored on dialogs, reduced motion,
   contrast checked when the tokens are built. Motion informs; it never delays the person.
8. **A real need.** A new package starts from a need that a site has today, described in its issue. It leaves
   incubation when it solves that need completely, not when its roadmap is long.
9. **Little to configure.** A bundle boots with an empty configuration and safe defaults. Each option is documented
   with its default and its reason; an option nobody would change is removed. `debug:config` shows everything.
10. **Works alone, better together.** Every package is installed and tested on its own, with no artificial
    dependency. Packages follow the shared conventions (configuration keys, Twig names, UX recipes, extension points,
    privacy declarations), so that each added package gains from the others. Groups are offered as `profile-*`
    packages, never as a required core.
11. **Predictable and extensible.** Extension points are tagged services and public interfaces, listed in
    `doc/extension-points.md` and stable from 1.0. A package never names a particular site or service of
    ours, not even in an example. Semantic versioning, a Keep a Changelog file, breaking changes only in a new version.
12. **Proven quality.** Floor: PHP 8.5+ and Symfony 8.2+. Every requirement is constrained to its latest published
    minor (`^X.Y`), in every `composer.json`; PHP stays at 8.5 until PHP 8.6.1 is out and the packages run on 8.6
    without a problem. PHPStan at its maximum level with strict rules on every PHP of the matrix; PHP-CS-Fixer,
    Rector, Deptrac and Arkitect; PHPUnit failing on any deprecation; Infection at 100 %, and a package does not leave
    incubation below it. The next PHP and Symfony are tested from their first release candidate. The floor moves up
    only in a new version of our packages.

## Reporting a bug

- Check first that the bug is not already reported.
- Name the package and give its version, of PHP and of Symfony, the configuration involved, and the smallest code that
  shows the bug.
- A security vulnerability is not a bug report: follow the [security policy](SECURITY.md) instead.

## Proposing a change

Open an issue before a large change, so that its design can be agreed first. Then:

1. **Write your own code.** No code is copied from another project, even under a compatible licence:
   another project may serve as a reference to read, never as a source to copy, translate or port. A third-party
   licence enters a package only as a declared Composer dependency.
2. **Keep the package independent of any application.** Its code, comments and documentation never name an
   application that uses it; what is specific to one application is configuration.
3. **Follow the layers.** A bundle keeps `Domain` (plain PHP, value objects and rules, no container and no I/O),
   `Application` (the services and the ports the application implements) and `Infrastructure` (the adapters to
   Symfony, Doctrine, Twig…) apart; the bundle class only wires them.
4. **Test it.** Every change comes with its tests. The domain layer is held to 100 % mutation coverage: a mutant
   that survives is a missing test, or code that nothing can reach.
5. **Document it, in English.** Update the package's `doc/` and, when a topic is added, the documentation table of
   its README. The French mirrors (`README.fr.md`, `doc/fr/`) follow principle 4; when a contributor does not write
   French, a maintainer completes them before the release.
6. **Keep it secure by default.** Three rules hold in every package:
   - a value object of personal data — an address, a phone number, a name — never copies the input into the message
     of an exception, which may reach a log: the message names the reason, and the input, when a caller needs it,
     stays in a property;
   - a bundle that calls a third-party service accepts the identifier of its HTTP client as an option, and sets
     default timeouts on every call (`timeout` and `max_duration`), which its configuration can change;
   - a key that signs or hashes is derived for its usage from the secret of the application, by
     `hash_hkdf('sha256', $secret, 32, 'phil_<bundle>.<usage>')`, never the secret itself: two usages never share a
     key, so that a signature or a pseudonym made for one never matches in another.
7. **Pass the checks.** From the root of the monorepo, after `composer install` and `composer hooks`, the quality
   checks run on every package — PHP-CS-Fixer, Rector, PHPStan at its maximum level, deptrac, Arkitect, PHPUnit and
   Infection — and `composer packages` installs each package on its own, checks its declared dependencies and runs
   its tests. A pull request is merged when every check passes.

The checks are Composer scripts of the root: `composer gates` runs them in order, and each one runs alone, for
instance `composer phpstan` or `composer test`. To check a package on its own, from its directory:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

## Fuzzing

A package whose code reads input nobody wrote — a parser, a decoder, a reader of files or tokens — declares fuzz
targets in `tests/Fuzz/<target>.php`, run by `composer fuzz` from the root of the monorepo, each for its time budget.
A target starts from the seeds of `tests/Fuzz/corpus/<target>/`.

**Only named seeds are committed.** A seed is a small input written on purpose and named for what it holds, such as
`seed-1.json`, `seed-empty.json` or `two-records-seed.txt`; an input that once failed a target is kept as a
regression seed, `regression-<hash>.txt`. Every target keeps at least one seed, so that it starts from a valid
input. The fuzzer adds the inputs it finds to the corpus under their MD5, such as
`0f3a…9c.txt`: those files are local to the run, ignored by the `.gitignore` of the monorepo, and never committed. To
keep one, rename it after what it shows. The fuzzer also shortens the inputs of the corpus as it goes, a named seed
among them, and saves the shorter input under its MD5: a seed a run shows as deleted is restored with
`git restore packages/<name>/tests/Fuzz/corpus`, and the generated files are removed with
`git clean -X packages/<name>/tests/Fuzz/corpus`.

## Licence

By contributing, you agree to license your contribution under the MIT licence of the package, whose holder its
`LICENSE` names.

## Code of conduct

Everyone taking part follows the [code of conduct](CODE_OF_CONDUCT.md).
