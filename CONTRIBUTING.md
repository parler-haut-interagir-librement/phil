# Contributing

This is the monorepo of the open packages of [Parler Haut Interagir Librement](https://github.com/parler-haut-interagir-librement)
(PHIL). Every `phil/*` package is developed here, and its own repository is a read-only copy split from
`packages/<name>/`: **open issues and pull requests here**, not on a split repository, which each split overwrites.

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
   its README. The French mirrors (`README.fr.md`, `doc/fr/`) may follow in a later pull request.
6. **Pass the checks.** From the root of the monorepo, after `composer install` and `composer hooks`, the quality
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
