# The document register

Every commercial document type is declared in one register, each numbered in its own series without gaps: the core
declares its receipts and credit notes, a module its own — a packing slip, a return slip —, the application others.

```yaml
phil_commerce:
    document_types:
        packing_slip: { prefix: 'PS-', digits: 6 }
        delivery_note: { prefix: 'DN-', visibility: unlisted }
```

```php
use Phil\CommerceBundle\Application\DocumentRegister;

// $register: DocumentRegister, from the container, inside the transaction that stores the document
$number = $register->next('packing_slip'); // PS-000001
```

- **A type** is a code of up to 16 lowercase letters, digits or `_`, a prefix of up to 16 visible ASCII characters,
  and 1 to 18 digits; a number takes 32 characters at most.
- **Two types never share a code or a prefix**, so two series never give the same number; the core checks the types of
  the configuration when the container is built, and every type on the register's first read.
- **A module declares its types** with a service implementing `DocumentTypes`, which autoconfiguration tags
  `phil_commerce.document_types` ([extension-points.md](extension-points.md#documenttypes)).
- **The numbers come from `DocumentSeries`**, which `DoctrineDocumentNumbering` implements with a row per series of
  `phil_commerce_document_sequence`, locked until the transaction ends: a number taken is used, or given back with the
  transaction.

The receipts and credit notes keep their own numbering through `DocumentNumbering` ([receipts.md](receipts.md)); the
register knows them under the codes `receipt` and `credit_note`, with the prefixes of `phil_commerce.receipts`.

## Visibility

Each type says how far its buyer sees a document of the type, on the three levels of the channels' `Visibility`:

| `visibility` | The buyer |
|---|---|
| `listed` | Finds it among the documents of the account: receipts and credit notes |
| `unlisted` | Reaches it by its direct link only — the link of the email that sends it — never in a list |
| `hidden` | Never sees it: a packing slip, a pick list, kept for the store; the default |

`DocumentRegister::listed()` gives the types a buyer's list of documents shows; `type($code)->visibility` tells a
page whether a link may open a document. A type is hidden unless its declaration says otherwise, so a module's new
document never reaches a buyer by mistake.
