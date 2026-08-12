---
paths:
  - 'app/Providers/**'
---

# Providers

## Reordering `artisan dev` panes by re-registering a default
Pane order in `artisan dev` is the registration order of `DevCommands`, not the `priority` value — priority only decides who wins a name clash, and `dev:list` uses it to flag vendor entries.

To move a default pane, re-register it in `AppServiceProvider::register()`:

    DevCommands::node('dev', 'vite');   // Vite first

Two undocumented mechanics make that work. `register()` on every provider runs before any `boot()`, and the framework registers its defaults in `ArtisanServiceProvider::boot()`, so an app registration lands in the array first. And `DevCommands::$commands` is keyed by name with `if (! $existing || $devCommand->priority() >= $existing->priority())` — an app registration is PRIORITY_USERLAND (2) against the default's PRIORITY_DEFAULT (0), so the later default is discarded instead of duplicating, and the app entry keeps its position.

Also: `artisan dev` calls `pcntl_exec()`, replacing the PHP process with `@laravel/multiplex`. No shutdown function, `CommandFinished` listener or signal handler of ours can run once it starts, so anything that must happen after `dev` exits belongs in the shell, not in PHP.
