# Changelog

All notable changes to `phil/doctrine-behaviours-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-15

### Added

- `SecurityBlameResolver`: the identifier of the authenticated user, or nobody; a user who acts as another through
  `switch_user` is blamed, not the user impersonated; `blame.user_method`, a method of the user class that names them
  in the blame, such as a UUID getter.
- `PhilDoctrineBehavioursBundle` and the `phil_doctrine_behaviours` configuration: `timestamps`, `blame` (an array
  that can be disabled, `blame: false` and `blame: true` also read), `soft_delete` (with `filter`, the
  `phil_soft_deleted` filter added to the default entity manager, enabled) and `tree`.
- `SecurityOwnerResolver`: the authenticated user as owner, by the method `owner.user_method` names, required; under
  `switch_user`, the account acted as.
- `SecurityVersionRestoreGuard`: going back to a past version takes the right `versions.restore_right`, asked of Symfony
  Security with the record as subject.
- `position`, on by default: `PositionListener` and the public service `Positions`, each group `positionGroup()` names
  ordered on its own.
- `translation`, on by default: `TranslationListener`, which gives each translation table its unique index on the record
  and the locale, and the public service `Translations`.
- `BlamePrivacy` and `OwnerPrivacy`: the blame and the owner in the access and erasure requests of
  `phil/privacy-bundle`, registered when it is installed and the behaviour is on; `privacy` in the configuration, with
  `blame_replacement` and `owner_replacement`, the pseudonyms checked when the container is built.
- `deactivation` (with `filter`, the `phil_deactivated` filter added to the default entity manager, enabled), `owner`,
  `publication` (with `workflow` and `define_workflow`, the state machine declared for `workflow.registry`), `slug`,
  `versions` (the mapping of `RecordVersion` and the public service `Versions`) and `dated_identifier`, in the
  `phil_doctrine_behaviours` configuration; a clear error for what a behaviour lacks; a slug unique in its table or
  within the scope `slugScope()` names.
- The instants of the behaviours taken from the Symfony `clock` service, written in UTC in the column types the library
  declares.
- `BehaviourServices`: the listeners, tagged on the Doctrine events each needs, the blame resolver and `Tree`;
  `TreeListener` is tagged `kernel.reset`; the public service `BlameEraser`, with `blame`.
- The documentation in `doc/`, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
