# Modules

Optional features shared between clients. Each module lives in `modules/<slug>/`
with a `module.json` (see `app/Support/Modules/ModuleRegistry.php` for the format).

- Installed, updated and removed by **module releases** from the central update server.
- **Core updates never touch this folder.**
- A module that needs a different core version, or fails to load, is skipped and logged:
  it can't take the shop down.
