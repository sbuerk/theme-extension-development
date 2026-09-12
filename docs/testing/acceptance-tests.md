# Acceptance tests

The functional tests render pages in-process and compare markup. What they
cannot show is what happens after the markup: that the stylesheet and the images
load, that the script of the theme runs, that a login form logs somebody in,
that a backend account can work. The acceptance suite looks at exactly that, in
a real browser, against a development instance that was built from nothing for
the run.

```bash
Build/Scripts/runTests.sh -t 12 -s acceptance
Build/Scripts/runTests.sh -t 13 -s acceptance

# Arguments after "--" go to "playwright test".
Build/Scripts/runTests.sh -t 12 -s acceptance -- --grep "log in"
```

Like every suite it needs nothing on the host but a container runtime. Unlike
the others it does not use the root dependency set: `-t` picks the instance, and
no `composerUpdate` is needed before it.

## What a run does

1. **Builds an instance below `.Build/acceptance/instance/`** from the committed
   `instance-core-<version>/`: its `composer.json`, its site configurations and
   its `config/system/additional.php`. `.Build/acceptance/theme` and
   `.Build/acceptance/packages-dev` are symlinks recreating the two paths an
   instance resolves one level up, the way the repository root provides them
   for `instance-core-*/`.
2. **Sets it up with the instance tooling itself** — `composer install`, then
   `composer system:setup`, the script `ddev start` runs on a fresh clone. The
   suite is therefore also the test of that tooling: `typo3 setup`, the database
   move, the seed import, the seed state.
3. **Serves it with the PHP built-in server**, in a container of its own on the
   network of the run, through the router of the core version,
   `Tests/Acceptance/Core<version>/router.php`.
4. **Runs Playwright** in the official image `mcr.microsoft.com/playwright`,
   pinned in `runTests.sh` to the exact version of `@playwright/test` in
   `Tests/Acceptance/package.json` — the image carries the browser builds of one
   release, and a different library version refuses to start them. Update both
   together, and rebaseline `-s visual` (`-- --update-snapshots`) in the same
   commit — the browser build decides the pixels of every
   [visual baseline](visual-tests.md#baselines).

The suite has a `package.json` of its own rather than a dependency in the root
one. The root `package.json` ships with the extension, because an integrator
rebuilding the stylesheet needs it; `Tests/` does not ship, and a test runner
has no business in a stylesheet rebuild.

The [visual suite](visual-tests.md) shares that package, its lockfile and so the
one Playwright pin; its specs and configuration are in `Visual/`, which
`playwright.config.ts` of this suite ignores.

The committed instances are never touched, and a DDEV instance can run beside
it. The run writes below `.Build/acceptance/` — the instance, `test-results/`
with a trace and a screenshot of every failure, the HTML report in `report/`,
and the log of the PHP server next to the TYPO3 logs in `instance/var/log/` —
which `composerUpdate` and `-s cleanTests` remove. Besides that it installs the
Playwright dependency into `Tests/Acceptance/node_modules/`, which
`-s cleanTests` removes as well, and it uses the composer and npm download
caches in `.cache/`.

## Why the router

Without a router the built-in server falls back to `index.php` for a path
without a dot in its last segment. A path whose last segment contains a dot is
taken for a file and answered with 404 when there is none. The router serves an
existing file as it is and sends everything else to a front controller, the way
the web server of an installation does, so the instance answers the paths it
routes whatever their form.

Which front controller is a core version difference, so there is one router per
version, chosen by `-t`:

| Router                               | Sends a missing path below `/typo3/` to | Why                                                                                                                                     |
|--------------------------------------|-----------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| `Tests/Acceptance/Core12/router.php` | `public/typo3/index.php`                | The backend entry point of TYPO3 v12, as the `root-htaccess` template of its EXT:install rewrites.                                      |
| `Tests/Acceptance/Core13/router.php` | `public/index.php`                      | TYPO3 v13 deprecated `typo3/index.php` (#87889) and handles backend and frontend requests with `index.php`; the file is only a wrapper. |

Every other path goes to `public/index.php` on both. Sending `/typo3/` to it on
TYPO3 v12 reached the frontend, which answered 404, and every backend spec
failed on the missing login form.

The router came with the suite from `main`, where TYPO3 v14 routes a path with
a dot: its backend loads its labels from
`/typo3/language/domain/en/<hash>/backend.messages`, and without the router no
backend module rendered there.

## Why one browser and one PHP worker

`workers: 1` in `Tests/Acceptance/playwright.config.ts`, and the built-in server
runs its single default worker. The instance is SQLite, which allows one writer
at a time: with two browsers rendering uncached pages at once, one request
failed now and then with *database is locked* and answered 500. Parallel
requests of one backend page can collide the same way, so they are served one
after the other as well. A run takes about 45 seconds of specs either way.

## What the specs cover

| Spec                     | Covers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
|--------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `frontend.spec.ts`       | Every showcase page in both trees: status, theme layout, the brand link, the head script, the stylesheet applied, no failed request and no JavaScript error; the images of `/media`; the pages hidden from the navigation; the display settings - a choice kept across a reload and applied before the module script runs, "Auto" stored as `auto`, Escape, Tab past the last control and a click outside closing the panel, one header row at 1280px and at 375px with the panel inside the screen, the checked states under forced colours, Reset to the server defaults; the navigation toggle on a narrow screen; the second level of the main menu closed until hovered on a wide screen. |
| `elements.spec.ts`       | The tabs content element on `/elements/theme` bound by the script and switched with the arrow keys, its panels with their headings without JavaScript; the accordion keeping one item open; the six notice kinds with their role and glyph.                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `forms.spec.ts`          | The form showcase on `/forms`: a submit attempt runs the browser's validation and marks the first invalid field, a valid submission sends nothing and stays on the page, the reset clears it, and every link of the error summary names an invalid field.                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `frontend-login.spec.ts` | The members page refused to a visitor; a member logs in, opens it and logs out again; a user without the group is refused; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `backend.spec.ts`        | Both trees for the administrator and the editor; the layout module for the editor, and the admin only `site_configuration` for the administrator alone; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `styleguide.spec.ts`     | The components that need the theme's script, on `/styleguide`: the tabs under the arrow keys, Home and End with the roving tab stop, and the panels turned into tab panels only once bound; the dialog opening as a modal on Cancel and giving focus back after Escape, Cancel, the close button and a click on the scrim, but not after a drag out of it; the tooltip on focus and Escape, and inside a 400px viewport; icon buttons square in every size; the tip's tint in every palette and both appearances; forced colours; and the page with JavaScript switched off and with `theme.js` blocked.                                                                                       |

The backend specs stay with what both core versions render alike — the login,
the page tree, the module menu. The backend UI itself is the core's, and a test
of it here would be a test of TYPO3.

The accounts they log in with are the seeded ones, documented on
[Development instances](../development/instances.md#accounts).

## Against a DDEV instance

The specs read the instance from `BASE_URL` and run against any seeded
instance. Against DDEV, from the Playwright image on the host network:

```bash
podman run --rm --network host -v "$PWD:$PWD:Z" -w "$PWD/Tests/Acceptance" \
    -e BASE_URL=https://core13-theme-v1.ddev.site -e npm_config_cache="$PWD/.cache/npm" \
    mcr.microsoft.com/playwright:v1.63.0-noble \
    /bin/sh -c "npm ci && npx playwright test"
```

The certificate DDEV signs its sites with is not trusted inside the image; the
browser and the readiness check of `global-setup.ts` both ignore it through
`ignoreHTTPSErrors`.

## In CI

The job `acceptance` runs per core version once the unit tests passed, beside
the functional jobs, and uploads the report, the failure traces and the logs of
the instance when it fails — see [Quality gates](../development/quality-gates.md#continuous-integration).

## See also

- [Development instances](../development/instances.md)
- [Functional tests](functional-tests.md)
- [Development environment](../development/environment.md)
