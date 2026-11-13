# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Accept longer or deeper formulas, or more macros | Pass `$maxLength`, `$maxDepth` and `$maxExpansions` to `LatexToMathMl` |
| Show the reason of a refusal where it happened | Read `InvalidLatex::$offset` |

The set of commands is not extensible from outside: a command is added in the package, with its tests. A formula
may define its own macros with `\newcommand`; an application that wants shared ones prepends their definitions to
the formulas it converts. The classes
under `Internal` are not extension points.
