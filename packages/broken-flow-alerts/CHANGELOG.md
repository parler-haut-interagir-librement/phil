# Changelog

All notable changes to `phil/broken-flow-alerts` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `Flow`: an identifier the application's history understands and a label, each 1 to 128 bytes without surrounding
  whitespace or control characters.
- `CompletionHistory`, the application's port: one count per UTC day for a flow, oldest first.
- `BrokenFlowRule` and `FlowStatus`: a baseline of 7 to 366 days (28 by default) sets the rhythm of each day of a
  window of 1 to 31 days (7 by default), weekday by weekday from two weeks of baseline; each stretch of the window that
  ends on its last day is weighed as a Poisson count, and the flow is broken when the quietest one is less likely than
  the significance (0.001 by default). The tail is summed in logarithms up to 10,000 completions, and bounded by
  Chernoff above.
- `BrokenFlowWatch`: the flows checked on the last whole UTC day, the day before evaluated again from the same history
  so that an alert goes out on the day a flow breaks and on the day it completes again, without a store of past alerts;
  a silence that has become the rhythm is not a recovery.
- `FlowAlert` and `FlowAlertKind` (`Broken`, `Recovered`): the flow, the days, the completions observed and expected.
- `AlertSender`, and `Notifier\NotifierAlertSender`, a Symfony Notifier notification of high importance for a broken
  flow and of low importance for a recovery, to the recipients it is given or to the channels that need none.
- `BrokenFlowAlertsException`, implemented by `InvalidFlowValue`.
- The documentation in `doc/`, one file per topic, indexed by the README, and its French mirror in `README.fr.md` and
  `doc/fr/`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
