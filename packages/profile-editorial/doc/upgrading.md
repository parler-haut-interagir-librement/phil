# Upgrading

## How the constraints move

The profile requires each member at its latest published minor version, `^0.x`. Below 1.0, a caret constraint keeps
the minor version: `^0.1` accepts `0.1.3-alpha`, never `0.2.0-alpha`. So `composer update` brings the fixes of every
member, and a new minor version of a member — which may change its API — arrives only with a release of the profile
that raises its constraint. The CHANGELOG of the profile lists those releases; the CHANGELOG of the member says what
its new minor version changes.

## Updating

```sh
composer update phil/profile-editorial --with-dependencies
```

`--with-dependencies` lets Composer update the members along with the profile.

## Leaving out a member

The profile is a convenience: an application that does not want one member requires the others directly, at the
constraints the profile gives, and removes the profile. Without `phil/content-warnings-bundle`, for example:

```sh
composer remove phil/profile-editorial
composer require phil/markup-bundle:^0.1 phil/citations:^0.1 phil/footnotes-sidenotes:^0.1 phil/structured-data-bundle:^0.1
```

The bundles already registered stay in `config/bundles.php`; remove the line of the member left out, and its
configuration file if the application wrote one.

## Holding back a member

To keep a member at an older version while the profile moves on, require that member directly at the version wanted:
Composer then satisfies both constraints, or says why it cannot.
