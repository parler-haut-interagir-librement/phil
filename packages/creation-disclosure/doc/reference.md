# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `DigitalSourceType` | The IPTC Digital Source Type vocabulary: codes, addresses, labels, retired codes, the view of each |
| `Part` | A part of a work declared on its own: text, image, audio, video, code |
| `CreationDisclosure` | How a work was made, part by part, immutable; its views and its storage as an array |
| `GenerativeAi` | What a part or a work owes to generative AI, their combination and their order |
| `DisclosureRequirement` | The parts a contest or a shop asks to declare, and the ceiling of generative AI |
| `Exception\CreationDisclosureException` | The interface of every exception of the library |
| `Exception\InvalidDisclosure` | A code, an address, a part or a stored disclosure the library does not hold |
