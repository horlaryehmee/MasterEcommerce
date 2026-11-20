# Client extensions

Code written for **this client only** (custom payment integrations, checkout tweaks,
templates…). Same layout as a module, with `"type": "extension"` in `module.json`.

- Updated only by **extension releases** bound to this client on the central server;
  another client's extension can never be installed here.
- **Core updates never touch this folder.** Put customisations here instead of editing
  core files: core updates refuse to overwrite hand-edited core files.
