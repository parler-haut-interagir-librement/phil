# Exceptions

Every exception of the library implements `Phil\AccessCodes\Exception\AccessCodesException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidCode` | `InvalidArgumentException` | `CodeFormat::parse()` refuses a text: too long, wrong length, foreign symbol, wrong check symbol. `redeem()` turns it into `Unknown` |
| `InvalidConfiguration` | `InvalidArgumentException` | A value the library cannot work with: an alphabet, a format, a key, a batch, a redeemer, a link, a table name, a code stored twice |
| `CodesAlreadyTaken` | `LogicException` | The codes of a created batch are asked for a second time |
| `UnknownBatch` | `RuntimeException` | `batch()`, `counts()` or `revoke()` names a batch the store does not hold |

No message repeats a code or a key.
