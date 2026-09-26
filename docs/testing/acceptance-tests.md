# Acceptance tests

The functional tests render pages in-process and compare markup. What they
cannot show is what happens after the markup: that the stylesheet and the images
load, that the script of the theme runs, that a login form logs somebody in,
that a backend account can work. The acceptance suite looks at exactly that, in
a real browser — Chromium, and then Firefox — against a development instance
that was built from nothing for the run.

```bash
Build/Scripts/runTests.sh -t 12 -s acceptance
Build/Scripts/runTests.sh -t 13 -s acceptance

# Arguments after "--" go to "playwright test".
Build/Scripts/runTests.sh -t 12 -s acceptance -- --grep "logs in"

# One engine only: the name of its project.
Build/Scripts/runTests.sh -t 12 -s acceptance -- --project firefox
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
4. **Runs Playwright**, every spec in Chromium and then in Firefox — see
   [Two engines](#two-engines). It runs in the official image
   `mcr.microsoft.com/playwright`, pinned in `runTests.sh` to the exact version
   of `@playwright/test` in `Tests/Acceptance/package.json` — the image carries
   the browser builds of one release, and a different library version refuses
   to start them. Update both together, and rebaseline `-s visual`
   (`-- --update-snapshots`) in the same commit — the browser build decides the
   pixels of every [visual baseline](visual-tests.md#baselines). Update the
   browser versions named under [Two engines](#two-engines) in that commit as
   well.

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

## Why one browser at a time and one PHP worker

`workers: 1` in `Tests/Acceptance/playwright.config.ts`, and the built-in server
runs its single default worker. The instance is SQLite, which allows one writer
at a time: with two browsers rendering uncached pages at once, one request
failed now and then with *database is locked* and answered 500. Parallel
requests of one backend page can collide the same way, so they are served one
after the other as well. The one worker holds across the two engines too:
Firefox starts once Chromium is done, never beside it.

## Two engines

Every spec runs twice, as the two projects of `playwright.config.ts`:
`chromium` (`devices['Desktop Chrome']`) and then `firefox`
(`devices['Desktop Firefox']`), in the same run against the same instance. The
pinned image carries both browsers, so the second engine needs no image, no pin
and no second instance. `-- --project chromium` or `-- --project firefox` runs
one of them, and combines with `--grep`.

A second engine, because much of what the specs drive is implemented by each
engine on its own: the `details`/`summary` toggle of the sub navigation and the
header dropdown, the modal `dialog` of the lightbox and its focus return, the
Popover API of the toggletip, the `relatedTarget` of the `focusout` that the
light dismiss in `theme.js` reads, `:has()`, the logical properties of a
right-to-left page, scroll snapping. `theme.js` is written against the platform
with no polyfill, so a call only Chromium implements passes every Chromium run.

**The whole suite, not a tagged subset.** The plan was to tag the specs of that
behaviour and run only those in Firefox. The run that was to pick the subset,
on the 2.x line, ran all 173 specs in Firefox instead, on both of its cores, and
every one of them passed unchanged; on this branch they pass unchanged on v12
and v13 as well. None depends on the font metrics of one engine: the geometry
specs read the edges they compare off the page — `inlineEndOfTheRow`, the
computed line height, `toBeCloseTo` to half a pixel — rather than writing down
the numbers Chromium produced. So there was no spec to leave out, and two
reasons against leaving any out:

- A difference fails where it is hit, not where it was expected. The check that
  the job catches a Firefox-only defect was a call of
  `Element.scrollIntoViewIfNeeded()`, which Firefox does not implement, put into
  the carousel's Next button: Chromium passed, and Firefox failed
  `the carousel scrolls by its buttons and marks the slide in view` with
  `TypeError: track.scrollIntoViewIfNeeded is not a function` in the trace.
  What failed the test was its assertion on `scrollLeft`, not the error: an
  uncaught page error does not fail a Playwright test by itself, so a
  Chromium-only option, event or property that fails silently is caught only
  where a spec asserts its effect. The carousel was not on the list of
  engine-sensitive components the subset was to be drawn from. The same check
  on this branch, on v12, failed the same way.
- A tag has to be remembered. With the whole suite, a new spec runs in Firefox
  without anybody deciding that it should.

The price is the second pass. Measured with `-s acceptance` on one developer
machine, busy with other work at the time, so the ratios say more than the
minutes. Two kinds of number, not to be mixed: *run* is the time Playwright
reports at the end of a run, *summed* is the sum of the durations of the tests
of one project in a run of both, which leaves out what lies between the tests.

| Core | Chromium only, run | Chromium then Firefox, run | Firefox of that, summed | Firefox only, run |
|------|--------------------|----------------------------|-------------------------|-------------------|
| v12  | 3.7 min            | 7.4 min                    | 3.4 min                 | 4.4 min           |
| v13  | 5.8 min            | 9.0 min                    | 3.0 min                 | 6.7 min           |

What the data show is that the second pass is the cheap one, not that Firefox
is: as the only browser on a fresh instance it takes longer than Chromium does.
When it runs second, the instance has served every page once already.
Building the instance comes on top, the same in either case.

One Firefox run on the 2.x line failed in `frontend-login.spec.ts`: the click
on "Login" sent no POST, focus stayed in the password field, and the test
failed on the success message that never came. It did not reproduce: the file
ten times over, in both engines, passed all 100 tests. The form is a plain
native submit and no handler in `theme.js` touches it. The submit now waits for
the request, so a recurrence fails as *pressing "Login" sent no POST*.
`retries` stays at 0, where a retry would let a recurrence pass the run.

A spec therefore has to hold in both engines. Where an assertion needs a
measurement, derive it from the page the way the header specs do, rather than
writing down what one engine drew. Do not branch on `browserName` and do not
skip a spec in one project: a spec that fails in one engine only has found
either a defect of the theme or an assumption of the test, and either is fixed -
the [strictness policy](phpunit-configuration.md#strictness-policy) holds here
as well.

What the two engines are not:

- **Not the floor.** The browsers are the ones of the pinned Playwright
  release, Chromium 153 and Firefox 155 with 1.63.0, not the Firefox 125 and
  Chrome 125 of [the browser floor](../../DESIGN.md#the-browser-floor). The
  suite shows that the theme works in both engines today; the floor is kept by
  using no feature that one of its versions lacks.
- **Not WebKit.** The image carries it, and it is not run. Playwright's WebKit
  is not Safari, and adding it is a decision of its own.
- **Not the visual suite.** Its screenshots and its axe pass run in Chromium
  only — see [what it does not cover](visual-tests.md#what-it-does-not-cover).

## What the specs cover

| Spec                     | Covers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
|--------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `frontend.spec.ts`       | Every showcase page in both trees: status, theme layout, the brand link, the head script, the stylesheet applied, no failed request and no JavaScript error; the images of `/media`; the pages hidden from the navigation; the display settings - a choice kept across a reload and applied before the module script runs, "Auto" stored as `auto`, Escape, Tab past the last control and a click outside closing the panel, one header row at 1280px and at 375px with the panel inside the screen, seven top level entries beside a one line title at 1280px, three in one row at 768px and seven without spilling sideways at 768px and 900px, the checked states under forced colours, Reset to the server defaults; both panels of the header controls slot - the language dropdown and the display settings - opening under the header row and over no control of it at 1280px and at 375px, in a right-to-left document, to the same edge as one another, and from the keyboard, with the dropdown also opening without JavaScript and the cog not being rendered at all without it; both panels, copied into the styleguide specimens of all four header variants, dropping under the whole header and ending at its content container at 1280px, 768px and 375px, with no title reaching under a trigger and the title of `centred` on the centre of its row from 768px up; the navigation toggle on a narrow screen; the second level of the main menu closed until hovered on a wide screen. |
| `elements.spec.ts`       | The tabs content element on `/elements/theme` bound by the script and switched with the arrow keys, its panels with their headings without JavaScript; the accordion keeping one item open; the six notice kinds with their role and glyph; the gallery lightbox on `/elements/core/image` opening the thumbnail that was pressed, moving with the buttons and the arrow keys, wrapping, giving focus back to the thumbnail, and the zoom link leading to the file with JavaScript off; the external media element on `/elements/theme/external-media` requesting nothing of the provider until the button is pressed and then exactly one iframe of the cookieless address, a host that is not recognised offering no button, and no button at all with JavaScript off or with `theme.js` blocked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `forms.spec.ts`          | The form showcase on `/forms`: a submit attempt runs the browser's validation and marks the first invalid field, a valid submission sends nothing and stays on the page, the reset clears it, and every link of the error summary names an invalid field.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `frontend-login.spec.ts` | The members page refused to a visitor; a member logs in, opens it and logs out again; a user without the group is refused; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| `backend.spec.ts`        | Both trees for the administrator and the editor; the layout module for the editor, and the admin only `site_configuration` for the administrator alone; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| `styleguide.spec.ts`     | The components that need the theme's script, on `/styleguide`: the tabs under the arrow keys, Home and End with the roving tab stop, and the panels turned into tab panels only once bound; the bar that marks the selected tab by more than colour, moving with the selection; the dialog opening as a modal on Cancel and giving focus back after Escape, Cancel, the close button and a click on the scrim, but not after a drag out of it; the tooltip on focus and Escape, and inside a 400px viewport; icon buttons square in every size; the embed's two states side by side - the unbound specimen offering no play button and the one carrying the marker offering one, both keeping the ratio of their frame and neither holding an iframe; the tip's tint in every palette and both appearances; forced colours; and the page with JavaScript switched off and with `theme.js` blocked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |

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

Both engines run in that one job. With Chromium alone it took 3.4 minutes on
v12 and 5.4 on v13, of which Playwright reported 2.0 and 3.8 for the specs (the
run of #87). How long it takes with Firefox added has not been measured on this
branch yet. On the 2.x line the first run with both engines took 8.2 minutes
on v13 and 8.7 on v14, against 5.4 and 5.5 with Chromium alone (the runs of #86
and #88). The job runs beside functional jobs of 13 to 23 minutes that the
database matrix waits for, so the Firefox pass lengthens a job that is not on
the longest path of the pipeline.

## See also

- [Development instances](../development/instances.md)
- [Functional tests](functional-tests.md)
- [Development environment](../development/environment.md)
