# Exceptions

Every exception implements `Phil\Ujo\Exception\UjoException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidJson` | `InvalidArgumentException` | A text above the size limit, that is not JSON, nested deeper than the limit, or whose top level is not an object |
| `InvalidDocument` | `InvalidArgumentException` | The structure of a document or block, or the data of a block, breaks the model; a text that cannot be written as JSON. Its `path` names where, as `blocks[2].data.level` |
| `UnknownBlockType` | `InvalidArgumentException` | A block or tune of no registered type, or a block with no renderer. Its `type` names it |
| `InvalidConfiguration` | `LogicException` | An inline profile that is not registered |

Messages say where and why, for example:

- `The UJO JSON cannot be parsed: Syntax error.`
- `The UJO document is invalid at blocks[0].data.level: 7 is above the maximum 6.`
- `The UJO document is invalid at blocks[3].id: the identifier "a1" is already used by the block at blocks[0].`
- `The block type "raw" at blocks[1] is not registered.`
- `No HTML renderer is registered for the block type "callout".`

An application that receives documents from writers catches `UjoException` and shows the message: it names the block
and the value at fault.
