# Icons

Every icon the theme draws comes from **Font Awesome Free, solid style only**.
The full solid set of one pinned version of the npm package
`@fortawesome/fontawesome-free` is copied into the extension and committed, and
a page gets the icons it uses as **inline SVG**. There is no webfont, no CDN,
no sprite and no request for an icon of any kind.

```bash
# Copy the solid icons of the pinned version into the extension.
Build/Scripts/runTests.sh -s buildIcons

# Check the committed set still equals the pinned package, as CI does.
Build/Scripts/runTests.sh -s checkIconsBuild
```

| Path                                                 | Is                                                                    |
|------------------------------------------------------|-----------------------------------------------------------------------|
| `Resources/Public/Icons/FontAwesome/Solid/`          | The 2001 files of `svgs/solid/` of 7.3.1, 1 648 470 bytes, unchanged. |
| `Resources/Public/Icons/FontAwesome/LICENSE.txt`     | The licence of the package, copied from the same version.             |
| `Resources/Public/Icons/FontAwesome/ATTRIBUTION.txt` | The attribution, written by hand: set, version, author, licence.      |
| `package.json`, `package-lock.json`                  | The exact pin — `"7.3.1"`, no range — and its integrity hash.         |

## Why the whole set, and why inline

The whole solid set ships, not the handful of icons the templates use today,
because the next step is letting an editor pick an icon by name. A set cut to
what the theme needs would turn every icon an editor wants into a build change.

Inline SVG is the one way to use an icon that needs nothing from the network
and nothing from a font:

- **Colour follows the text.** Every file paints its path with
  `fill="currentColor"`, so an icon takes the colour of the text it sits in —
  the accent of an alert, the hover colour of a button, and the system colours
  of forced colours mode, with no rule of its own for any of them.
- **Accessibility is decided per use.** A decorative icon is `aria-hidden`, an
  icon that is the only content of its context gets `role="img"` and a name.
  A webfont glyph is a private use character that assistive technology may
  read out, and it is gone when a reader blocks web fonts.
- **No request.** A sprite referenced with `<use href="…">` is one more
  request, has to be same origin, and puts the icon into a shadow tree the page
  stylesheet reaches only through inherited properties. A file of the set is
  824 bytes on average; the few a page uses cost less inlined than a request.

## Licence and attribution

`LICENSE.txt` of the package, checked for 7.3.1: the **icons** are
**CC BY 4.0**, the fonts SIL OFL 1.1 and the code MIT. Only icons are shipped
here — no font file and no line of Font Awesome code — so CC BY 4.0 is the
licence that applies, and it requires attribution.

Two things meet that requirement, and both ship:

- **Every file keeps the comment it comes with**, naming Font Awesome Free, the
  version, the licence and Fonticons, Inc. The files are copied byte for byte.
  `LICENSE.txt` of the package says why that comment is there and asks for it
  to stay:

  > Attribution is required by MIT, SIL OFL, and CC BY licenses. Downloaded
  > Font Awesome Free files already contain embedded comments with sufficient
  > attribution, so you shouldn't need to do anything additional when using
  > these files normally.
  >
  > We've kept attribution comments terse, so we ask that you do not actively
  > work to remove them from files, especially code.

  Keeping every file identical to the package is also what makes the gate below
  a plain `diff`.
- **`LICENSE.txt` and `ATTRIBUTION.txt`** sit next to the set, below
  `Resources/Public/`, which is not `export-ignore`d: they reach the composer
  dist archive and the TER artifact together with the icons.

## The build and the gate

The build is two npm scripts in the root `package.json`, next to the CSS build,
run in the same node image through `runTests.sh`:

| Script               | Suite             | Does                                                                                                             |
|----------------------|-------------------|------------------------------------------------------------------------------------------------------------------|
| `build:icons`        | `buildIcons`      | Empties `Solid/`, copies `svgs/solid/*.svg` and `LICENSE.txt` of the installed package into place, nothing else. |
| `build:icons:verify` | `checkIconsBuild` | `diff -r` of the installed `svgs/solid/` against `Solid/`, and `diff -u` of the two licence files.               |

Both start with `npm ci`, which installs exactly the version of
`package-lock.json` and refuses a tarball whose integrity hash does not match.
The comparison fails in every direction: an edited file, a file missing from
the committed set and a file in the committed set that the package does not
have — a hand-drawn icon dropped next to the vendored ones — each make it exit
non-zero. It is the icon counterpart of
[`checkCssBuild`](frontend-assets.md#the-checkcssbuild-gate), and like it
compares files instead of asking `git`, for the same reason.

The scripts are plain shell in `package.json` rather than a script below
`Build/`, because `Build/` is `export-ignore`d and `package.json` ships: the
rebuild path travels with the sources, as it does for the stylesheet.
`ATTRIBUTION.txt` is not written by the build; it names the version and has to
be changed by hand when the pin moves.

In CI the gate is a step of the `css` job: it needs neither PHP nor a core
version, and it uses the node image and the lockfile that job already uses.

### Why it is committed

For the reason the stylesheet is: both distribution paths are exports of
committed content and run no build — see
[Frontend assets § Why the compiled CSS is committed](frontend-assets.md#why-the-compiled-css-is-committed).
The package is a `devDependency`: nothing that installs the extension ever runs
`npm`, and `node_modules/` is git-ignored and `export-ignore`d.

## Updating the pinned version

1. Pick the version and read its `LICENSE.txt` — the licence of the icons has to
   still be CC BY 4.0.
2. Pin it exactly:
   `Build/Scripts/runTests.sh -s npm -- install --save-dev --save-exact --no-audit --no-fund @fortawesome/fontawesome-free@<version>`.
3. `Build/Scripts/runTests.sh -s buildIcons`, and change the version in
   `ATTRIBUTION.txt`.
4. `Build/Scripts/runTests.sh -s checkIconsBuild`.
5. Look at the diff of `Solid/`. Font Awesome renames and removes icons between
   versions, a major one in particular, so a name the theme uses may be gone.
6. Commit the lockfile, `package.json`, the set and both text files together.

## See also

- [Frontend assets](frontend-assets.md)
- [Quality gates](quality-gates.md)
- [`DESIGN.md`](../../DESIGN.md#icons)
