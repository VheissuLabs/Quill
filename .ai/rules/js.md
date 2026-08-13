---
paths:
  - 'resources/js/**'
---

# Js

## Never hand-roll markup for a ui component
Before writing raw `<table>`, `<textarea>`, `<input>`, `<dialog>` or similar, check `resources/js/components/ui/`. Use the component that exists.

If it does not exist, add it to `components/ui/<name>/` following the shape of the neighbours — a `.vue` per part, `cn()` merging a `props.class`, a `data-slot` attribute, and an `index.ts` barrel — then use it. Do not inline the markup "just this once": it drifts from the design system immediately and the next page copies it.

This has already happened twice (a bare `<textarea>` in the assistant composer, a bare `<table>` on the projects index and the activity log). Both were replaced by `ui/textarea` and `ui/table` after the fact.

## Three frontend traps that look like arbitrary code
These were code comments until the comments were stripped; the constraints are still real.

1. `echo.ts` skips connecting when the page is HTTPS but `REVERB_SCHEME` is http. pusher-js upgrades to `wss` whenever the page is secure, whatever `forceTLS` says, so a plain-`ws` Reverb (Herd's shared service on 8080) is unreachable and every attempt floods the console. Do not "fix" the skip by forcing a connection.

2. `echo.ts` is wrapped in `typeof window !== 'undefined'`. app.ts imports it and Inertia's SSR renderer evaluates it server-side, where there is no `window`. Unguarded, Vite fails to warm the SSR module graph and the dev server never starts — invisible to the test suite.

3. The assistant composer reads the CSRF token from the `XSRF-TOKEN` cookie, not the `<meta name="csrf-token">` tag. Laravel refreshes the cookie on every response; the meta tag is baked in at render and goes stale if the session regenerates mid-chat, which produced intermittent 419s.
