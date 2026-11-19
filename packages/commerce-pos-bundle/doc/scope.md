# Scope

The register for the core of commerce.

| Part | Content | State |
|---|---|---|
| Registers | Code, name, currency, cash increment, location, channel, main or mobile | Implemented and tested ([register.md](register.md), [devices.md](devices.md)) |
| Sessions | Float, cash sales and refunds, paid in, paid out, drops, count, over or short | Implemented and tested |
| End-of-day report | The drawer, the payments by method, tips, sales | Implemented and tested |
| Cash-difference report | The sessions closed over a period, over and short, a threshold that flags | Implemented and tested ([counter.md](counter.md)) |
| Cash | Rounding to the increment in the drawer, change | Implemented and tested |
| Recorded methods | A terminal that is not connected, a cheque, with the approval | Implemented and tested |
| Connected terminals | Driven by the server through an adapter per processor, PIN terminals at the main register, the fake adapter | Implemented and tested ([terminals.md](terminals.md)); the adapters of real processors are the application's |
| Held sales, tips | Parking a cart, a tip amount or share; a seller's sale finished at the main register | Implemented and tested |
| Layaways | Deposit, instalments, completion, cancellation, due dates | Implemented and tested ([layaways.md](layaways.md)) |
| Sale presets | A channel and a customer group over the core's, the customer identified | Implemented and tested ([counter.md](counter.md)) |
| Customer display | What the screen turned to the customer shows, nothing about the person | Implemented and tested; the screen is the application's |
| Draws at the counter | The form a drawer's package provides, entered once the order is paid | Implemented and tested |
| Tickets | A sellable of the core, sold like any other | Implemented and tested |
| Offline sales | The main register's IndexedDB queue, the import, re-pricing, discrepancies | Implemented and tested ([offline.md](offline.md)) |
| Cashier switch by PIN | A PIN per application user, hashed, attempts limited; off by default | Implemented and tested ([devices.md](devices.md)) |
| Peripherals | Cash drawer, receipt printer, scanner | Not started: the application's screen |
| Staff permissions | Who may open, refund, pay out | The application's, through Symfony's security |

## The ports of the core it implements

- `PaymentGateway`: `RegisterGateway`, for `cash` and the recorded methods; `TerminalGateway`, for the connected
  terminals.

## Changes it brought to the core

- `AdjustmentType::Tip`, an adjustment on no line, so not taxed.
