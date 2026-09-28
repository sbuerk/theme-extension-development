# Acceptance tests

The functional tests render pages in-process and compare markup. What they
cannot show is what happens after the markup: that the stylesheet and the images
load, that the script of the theme runs, that a login form logs somebody in,
that a backend account can work. The acceptance suite looks at exactly that, in
a real browser — Chromium, and then Firefox — against a development instance
that was built from nothing for the run.

```bash
Build/Scripts/runTests.sh -t 13 -s acceptance
Build/Scripts/runTests.sh -t 14 -s acceptance

# Arguments after "--" go to "playwright test".
Build/Scripts/runTests.sh -t 13 -s acceptance -- --grep "logs in"

# One engine only: the name of its project.
Build/Scripts/runTests.sh -t 13 -s acceptance -- --project firefox
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
   for `instance-core-*/`. One line is appended to the **copy** of
   `additional.php`: `security.frontend.enforceContentSecurityPolicy = true`.
   The instance of the suite enforces TYPO3's frontend Content Security Policy,
   while the committed `instance-core-*/` and every DDEV instance built from it
   stay as they are — see [under a Content Security Policy](#under-a-content-security-policy).
2. **Sets it up with the instance tooling itself** — `composer install`, then
   `composer system:setup`, the script `ddev start` runs on a fresh clone. The
   suite is therefore also the test of that tooling: `typo3 setup`, the database
   move, the seed import, the seed state.
3. **Serves it with the PHP built-in server**, in a container of its own on the
   network of the run, through the router `Tests/Acceptance/router.php`.
4. **Pins the address of the instance.** The address of the server container
   is written into `/etc/hosts` of the Playwright container as
   `acceptance-instance`, and Playwright runs against
   `http://acceptance-instance:8000` — see
   [below](#the-address-of-the-instance).
5. **Runs Playwright**, every spec in Chromium and then in Firefox — see
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
taken for a file and answered with 404 when there is none. TYPO3 v14 routes such
paths: its backend loads its labels from
`/typo3/language/domain/en/<hash>/backend.messages`, and without them no backend
module renders — the module area stays empty, with no error anywhere but the
browser console. The router serves an existing file as it is and sends
everything else to the front controller, the way a web server rewrite does.

## Why one browser at a time and one PHP worker

`workers: 1` in `Tests/Acceptance/playwright.config.ts`, and the built-in server
runs its single default worker. The instance is SQLite, which allows one writer
at a time: with two browsers rendering uncached pages at once, one request
failed now and then with *database is locked* and answered 500. Parallel
requests of one backend page can collide the same way, so they are served one
after the other as well. The one worker holds across the two engines too:
Firefox starts once Chromium is done, never beside it.

## The address of the instance

The browser used to reach the instance by the name of its container, resolved
by the DNS server of the container network. On a busy host a page load failed
now and then with `NS_ERROR_UNKNOWN_HOST` in Firefox or
`net::ERR_NAME_NOT_RESOLVED` in Chromium, and the log of the PHP server showed
that the request never arrived. It was a single lookup that failed, not the
instance, and every failed lookup was a failed test; the Firefox pass doubles
the lookups of a run. The [visual suite](visual-tests.md#the-address-of-the-fixture-server)
had the same failure first, and the same fix.

So no lookup of the run goes to DNS. `runTests.sh` reads the address of the
server container with `inspect` and passes it to the Playwright container as
`--add-host acceptance-instance:<address>`. `BASE_URL` names
`acceptance-instance`, a host no DNS server knows: the address in `/etc/hosts`
is the only way to it, so a pin that did not work would fail every test instead
of one in a hundred — without it the global setup cannot resolve the name at
all. An address that cannot be read fails the run with a message saying so.
Unlike the visual suite, there is no wait in `runTests.sh` before it: the
address is known as soon as the container runs, and `global-setup.ts` polls
the instance through the pinned name for 60 seconds, which covers the start of
the server.

Nothing in the instance depends on the name. The site bases are relative and
`trustedHostsPattern` is `.*`, so TYPO3 takes the host from the request. An
absolute URL it renders — the backend login page carries one, the link
`#t3js-login-url` — therefore names `acceptance-instance:8000` as well, which
the browser resolves through the same pin.

With the pin, the whole suite also passed in both engines with a `resolv.conf`
mounted into the Playwright container that names a nameserver which does not
answer: no lookup of a run needs DNS — the one external provider a spec clicks
is blocked at the route. `--dns` is no way to make that check: podman and
docker both keep resolving the containers of the network with their own DNS
server and use the one `--dns` names only for other names.

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
behaviour and run only those in Firefox. The run that was to pick the subset
ran all 173 specs in Firefox instead, on both cores, and every one of them
passed unchanged. None depends on the font metrics of one engine: the geometry
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
  engine-sensitive components the subset was to be drawn from.
- A tag has to be remembered. With the whole suite, a new spec runs in Firefox
  without anybody deciding that it should.

The price is the second pass. Measured with `-s acceptance` on one developer
machine, busy with other work at the time, so the ratios say more than the
minutes. Two kinds of number, not to be mixed: *run* is the time Playwright
reports at the end of a run, *summed* is the sum of the durations of the tests
of one project in a run of both, which leaves out what lies between the tests.

| Core | Chromium only, run | Chromium then Firefox, run | Firefox of that, summed | Firefox only, run |
|------|--------------------|----------------------------|-------------------------|-------------------|
| v13  | 6.5 min            | 9.0 min                    | 3.0 min                 | 6.9 min           |
| v14  | 6.7 min            | 9.2 min                    | 3.1 min                 | 7.2 min           |

What the data show is that the second pass is the cheap one, not that Firefox
is: as the only browser on a fresh instance it takes about as long as Chromium
does. When it runs second, the instance has served every page once already.
Building the instance comes on top, the same in either case.

One Firefox run failed in `frontend-login.spec.ts`: the click on "Login" sent
no POST, focus stayed in the password field, and the test failed on the
success message that never came. It did not reproduce: the file ten times
over, in both engines, passed all 100 tests. The form is a plain native submit
and no handler in `theme.js` touches it. The submit now waits for the request,
so a recurrence fails as *pressing "Login" sent no POST*. `retries` stays at 0,
where a retry would let a recurrence pass the run.

It has not recurred since. None of the 44 acceptance jobs that ran the suite
in Firefox in the pull request and nightly runs of both branches from
2026-09-25 to 2026-09-28 failed there. The wait therefore stays as a permanent
assertion, not a probe: a recurrence fails with that message and keeps its
trace (`trace: 'retain-on-failure'`), which is where an investigation starts.
Only a proven loss of input in the browser driver would justify a retry.

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

## Under a Content Security Policy

A browser blocks what a Content Security Policy does not allow without an error
the page can see: a script that does not run, a source that does not load. So
the instance of the suite enforces TYPO3's frontend policy, and
`frontend.spec.ts` collects every `securitypolicyviolation` event on a list of
showcase pages — the start page, typography, media, the styleguide of both
trees, a form, the cheatsheet and the external media page with every
click-to-load embed opened, and a composed page — and requires none, with
`data-js` set by the no-flash script and `data-js-bound` by `theme.js`.

Before the policy was enforced here, two things of the theme were blocked
under it: the inline no-flash script of the head, which
`Configuration/ContentSecurityPolicies.php` allows by its hash since, and the
`data:` sources and caption tracks of the media specimen, which `media-src`
does not allow and which are files below `Resources/Public/Media/Styleguide/`
since. Every other spec runs under the policy too, so a component that needs
something it does not allow fails where it is used.

The flag is appended by `runTests.sh` rather than committed to
`instance-core-*/config/system/additional.php`: a development instance is for
looking at the theme, and a policy there is a decision of whoever runs it.

## What the specs cover

| Spec                     | Covers                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
|--------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `frontend.spec.ts`       | Every showcase page in both trees: status, theme layout, the brand link, the head script, the stylesheet applied, no failed request and no JavaScript error; the images of `/media`; the pages hidden from the navigation; the display settings - a choice kept across a reload and applied before the module script runs, "Auto" stored as `auto`, Escape, Tab past the last control and a click outside closing the panel, one header row at 1280px and at 375px with the panel inside the screen, the seven top level entries of the showcase in one row at 1280px and at the breakpoint of `simple` with the title no narrower than its longest word, and behind the toggle one pixel below it and at 1024px, the checked states under forced colours, Reset to the server defaults; both panels of the header controls slot - the language dropdown and the display settings - opening under the header row and over no control of it at 1280px and at 375px, in a right-to-left document, to the same edge as one another, and from the keyboard, with the dropdown also opening without JavaScript and the cog not being rendered at all without it; both panels, copied into the styleguide specimens of all four header variants, dropping under the whole header and ending at its content container at 1280px, 1024px, 768px and 375px, with no title reaching under a trigger and the title of `centred` on the centre of its row from 768px up; the page's own header rearranged into each of the four arrangements, its menu one row with nothing reaching out of a row at the arrangement's breakpoint in both reading directions and at 1280px, no second level opened over an entry or a control there, and the menu behind its toggle, opening as a band under the header, one pixel below the breakpoint; a navigation outside the header still collapsing at 768px; `/typography` and `/styleguide` in all four arrangements, in both reading directions, with JavaScript and without, at 320px, 360px and 375px, without sideways scroll of the page or the header and without two of title, toggle, controls and call to action overlapping, and the menu band and both panels inside the screen at 320px; the footer meta row wrapping a long word of inline code at 320px and 360px; with JavaScript disabled, the header of each arrangement listing its seven sections and no second level at 800px for `simple`, one pixel below every breakpoint and at 767px, at most 640px tall, and at 375px, at most 900px tall, with no sideways spill, while the navigation specimen outside the header keeps its second level below 768px; a `theme.js` that is not found or throws before the toggles are bound, at 1000px and 375px, leaving the menu open with no dead toggle and no cog, and a working page with the module 600 ms late keeping its menu collapsed in every frame; the navigation toggle on a narrow screen; the second level of the main menu closed until hovered on a wide screen. Every page of each tree, crawled from its root - and from `/login` in the site set tree - through the main navigation and the links of `main`, at 305px - 320 less a classic scrollbar - answering 200 and without sideways scroll, a page that scrolls named with the outermost elements reaching out of the client width. The crawl has to reach every page of the list the spec keeps, so a crawl that finds next to nothing fails, and the list has to name every page the crawl reaches, so a page added to the seed without it fails too. |
| `elements.spec.ts`       | The tabs content element on `/elements/theme` bound by the script and switched with the arrow keys, its panels with their headings without JavaScript; the accordion keeping one item open; the six notice kinds with their role and glyph; the gallery lightbox on `/elements/core/image` opening the thumbnail that was pressed, moving with the buttons and the arrow keys, wrapping, giving focus back to the thumbnail, and the zoom link leading to the file with JavaScript off; the external media element on `/elements/theme/external-media` requesting nothing of the provider until the button is pressed and then exactly one iframe of the cookieless address, a host that is not recognised offering no button, and no button at all with JavaScript off or with `theme.js` blocked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `forms.spec.ts`          | The form showcase on `/forms`: a submit attempt runs the browser's validation and marks the first invalid field, a valid submission sends nothing and stays on the page, the reset clears it, every link of the error summary names an invalid field, every date, time, colour and single select keeps its intrinsic width at 305px, and every control stays inside its field at 1280px and 305px.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `frontend-login.spec.ts` | The members page refused to a visitor; a member logs in, opens it and logs out again; a user without the group is refused; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `backend.spec.ts`        | Both trees for the administrator and the editor; the layout module for the editor, and the admin only `site_configuration` for the administrator alone; a wrong password.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `styleguide.spec.ts`     | The components that need the theme's script, on `/styleguide`: the tabs under the arrow keys, Home and End with the roving tab stop, and the panels turned into tab panels only once bound; the bar that marks the selected tab by more than colour, moving with the selection; the dialog opening as a modal on Cancel and giving focus back after Escape, Cancel, the close button and a click on the scrim, but not after a drag out of it; the tooltip on focus and Escape, and inside a 400px viewport; icon buttons square in every size; the embed's two states side by side - the unbound specimen offering no play button and the one carrying the marker offering one, both keeping the ratio of their frame and neither holding an iframe; the tip's tint in every palette and both appearances; forced colours; and the page with JavaScript switched off and with `theme.js` blocked.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |

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
    -e BASE_URL=https://core13-theme-v2.ddev.site -e npm_config_cache="$PWD/.cache/npm" \
    mcr.microsoft.com/playwright:v1.63.0-noble \
    /bin/sh -c "npm ci && npx playwright test"
```

The certificate DDEV signs its sites with is not trusted inside the image; the
browser and the readiness check of `global-setup.ts` both ignore it through
`ignoreHTTPSErrors`.

## In CI

The job `acceptance` runs per core version from the start of the run, beside
the functional jobs, and uploads the report, the failure traces and the logs of
the instance when it fails — see [Quality gates](../development/quality-gates.md#continuous-integration).

Both engines run in that one job. With Chromium alone it took 5.4 minutes on
v13 and 5.5 on v14, about two of them building the instance; with Firefox
added it took 8.2 and 8.7 (the runs of #86 and #88). Since the functional jobs
run their suite in four chunks, acceptance is the longest job of a run: 8.3 and
8.4 minutes in the first chunked run (#94), against at most 6.8 for a
functional job and 11.8 minutes for the whole run.

The crawl of every page at 305 pixels in `frontend.spec.ts` adds two and a half
to three minutes to a run of the suite: 53 to 56 seconds for the site set tree
and 72 to 78 for the `sys_template` tree in Chromium, 15 to 17 each in Firefox,
measured locally in the full suite on v13 and v14. Chromium is the slower
because it comes first: the pages the other specs of the file have not opened
yet are rendered by TYPO3 for the first time. The spec has a timeout of five
minutes for that reason.

## See also

- [Development instances](../development/instances.md)
- [Functional tests](functional-tests.md)
- [Development environment](../development/environment.md)
