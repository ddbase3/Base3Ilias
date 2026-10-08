# Base3Ilias

**Base3Ilias** is a component that integrates the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) into the [ILIAS Learning Management System](https://www.ilias.de). It serves as a bridge between the two systems, enabling seamless interoperability and leveraging the strengths of both platforms.

## Overview

This component merges the capabilities of the BASE3 framework with the extensibility of the ILIAS LMS. It allows developers to build advanced, modular, and service-oriented extensions for ILIAS using the structure and features provided by BASE3.

## Installation

Base3Ilias has two roles:

- it is a reusable BASE3 module identified by `base3.json`
- it can provide the native ILIAS component wrapper for private BASE3 installations

The BASE3 module identity is independent from the physical installation path.

### Private BASE3 ILIAS installation

Clone this repository directly as the ILIAS component:

```text
components/Base3/Base3Ilias/
├── Base3Ilias.php
├── base3.json
├── src/
└── ...
```

The `Base3Ilias.php` file is only the `Base3` vendor component wrapper. The reusable integration logic stays inside the Base3Ilias module.

### Qualitus product installation

Qualitus installations use `Qualitus/Base3Core` as the native ILIAS component and include this repository as a module below `lib/`:

```text
components/Qualitus/Base3Core/
├── Base3Core.php
├── resources/
└── lib/
    ├── Base3Framework/
    ├── Base3Ilias/
    └── ...
```

In this layout the nested `Base3Ilias.php` wrapper is not an ILIAS component because it is not a direct child of an ILIAS vendor directory. `Qualitus\Base3Core` delegates to the same Base3Ilias component bootstrap.

Only one native wrapper must be installed in a concrete ILIAS installation. Do not install both `components/Base3/Base3Ilias` and `components/Qualitus/Base3Core` with another copy of Base3Ilias at the same time.

## Requirements

- ILIAS version >= 10.0
- PHP >= 8.2
- BASE3 Framework
- ResourceFoundation

## Purpose

The main goal of **Base3Ilias** is to allow developers to:

- Use modern, modular PHP architecture inside ILIAS plugins
- Share core logic between ILIAS and non-ILIAS projects using BASE3
- Speed up development and maintain consistency across services
- Usage of already developed BASE3 Plugins like AI tools, chatbot, agent system, reporting, crm, different api connectors and more
- Expose ILIAS Resource Storage Service resources through the ResourceFoundation file-storage contracts

## ILIAS Resource Storage adapter

Base3Ilias binds `ResourceFoundation\Api\IFileStorageFactory` to an ILIAS-specific factory. The factory opens an existing ILIAS Resource Storage Service area and returns it as a normal `IFileStorage`.

The adapter supports three modes:

- `single_file`: one IRSS resource identified by a RID
- `collection`: one IRSS resource collection identified by an RCID, exposed as a flat file storage
- `container`: one IRSS container resource identified by a RID, exposed with directory structure

The identifier lifecycle stays with the calling domain object. Base3Ilias does not persist RID or RCID values for consumers. Collection member resources created through `write()` or `copy()` are managed internally by the collection storage implementation. The adapter also implements the `IFileStorage` `copy()` and `move()` operations according to the capabilities of each IRSS mode.

See [ILIAS Resource Storage adapter](docs/ilias-resource-storage.md) for the operation mapping, ownership rules, and compatibility notes.

## License

This project is open-source and available under the GPL 3.0 License.

## Author

Developed and maintained by **Daniel Dahme**  
GitHub: [@ddbase3](https://github.com/ddbase3)

---

Feel free to contribute or report issues via GitHub!

## Documentation

- [Frequently Asked Questions](docs/faq.md)
- [Privacy and data processing](PRIVACY.md)
- [ILIAS Resource Storage adapter](docs/ilias-resource-storage.md)
