---
paths:
  - 'resources/js/**'
---

# Js

## Never hand-roll markup for a ui component
Before writing raw `<table>`, `<textarea>`, `<input>`, `<dialog>` or similar, check `resources/js/components/ui/`. Use the component that exists.

If it does not exist, add it to `components/ui/<name>/` following the shape of the neighbours — a `.vue` per part, `cn()` merging a `props.class`, a `data-slot` attribute, and an `index.ts` barrel — then use it. Do not inline the markup "just this once": it drifts from the design system immediately and the next page copies it.

This has already happened twice (a bare `<textarea>` in the assistant composer, a bare `<table>` on the projects index and the activity log). Both were replaced by `ui/textarea` and `ui/table` after the fact.
