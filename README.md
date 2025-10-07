# Base3Ilias

**Base3Ilias** is a component that integrates the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) into the [ILIAS Learning Management System](https://www.ilias.de). It serves as a bridge between the two systems, enabling seamless interoperability and leveraging the strengths of both platforms.

## Overview

This component merges the capabilities of the BASE3 framework with the extensibility of the ILIAS LMS. It allows developers to build advanced, modular, and service-oriented extensions for ILIAS using the structure and features provided by BASE3.

## Installation

1. Navigate to your ILIAS installation's `components/` directory.

2. Inside this plugin folder, create a new `components/Base3/` directory:

   ```bash
   mkdir -p components/Base3
   ```

3. Clone this repository into a new subfolder `Base3Ilias`.

4. Clone the [BASE3 Framework](https://github.com/ddbase3/Base3Framework) into the `components/Base3/` folder:

   ```bash
   git clone https://github.com/ddbase3/Base3Framework.git components/Base3/Base3Framework
   ```

5. Also place this component (`Base3Ilias`) into the same `components/Base3/` directory if it's not already there.

   Final structure:

   ```
   Ilias/
   - components/
        - Base3/
            - Base3Framework/
            - Base3Ilias/
   ```

6. Eventually configuration

## Requirements

- ILIAS version >= 10.0
- PHP >= 8.2
- BASE3 Framework

## Purpose

The main goal of **Base3Ilias** is to allow developers to:

- Use modern, modular PHP architecture inside ILIAS plugins
- Share core logic between ILIAS and non-ILIAS projects using BASE3
- Speed up development and maintain consistency across services
- Usage of already developed BASE3 Plugins like AI tools, chatbot, agent system, reporting, crm, different api connectors and more

## License

This project is open-source and available under the GPL 3.0 License.

## Author

Developed and maintained by **Daniel Dahme**  
GitHub: [@ddbase3](https://github.com/ddbase3)

---

Feel free to contribute or report issues via GitHub!
