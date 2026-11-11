# Events Plugin E2E Tests

Plugin E2E tests run with Galette core tooling: see the "Plugins" section of
`tests/e2e/README.md` in Galette for prerequisites, shared fixtures and helpers,
and how to run them. The plugin database must be installed
(`bin/console galette:plugins:install-db --all`) before the fixtures are seeded, and
the calendar needs the plugin scripts (`npm install && npm run build`).

From Galette root directory:

```bash
npx playwright test galette/plugins/plugin-events/tests/e2e/specs/
```

Specs rely on the events, activities and bookings `GaletteEvents\Fixtures` seeds; those
that change data work on entries of their own, named with a unique suffix, and remove them.
`public-events.spec.ts` changes the visibility of the public events page from the settings
and puts it back.
