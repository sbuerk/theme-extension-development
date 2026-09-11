// Display settings and main menu toggle.
//
// Loaded as `type="module"` from "Configuration/TypoScript/Appearance.typoscript"
// - see that file for where and why. A module is deferred by specification, so
// this runs after the DOM is parsed; it needs neither a "DOMContentLoaded"
// listener nor a "defer" attribute.
//
// By the time this runs, the root already carries "data-js" and whatever
// appearance, palette and content outline the inline head script in
// "Appearance.typoscript" landed on - either the server-rendered default, or a
// stored choice it applied before first paint. This file only adds
// interaction on top of that state, it never sets it up from scratch, and it
// is not what makes the page usable: the navigation is already open and
// readable with no script running at all (see "components/_nav-main.scss"),
// and the settings control is hidden until "data-js" is present. The one
// thing here that *is* load-bearing is the menu toggle, once the viewport is
// narrow enough to need it - which is why it is the one control this file
// does not treat as optional.
//
// --- No optional chaining ("?.") ---------------------------------------
//
// A module script only ever runs in a browser new enough to recognise
// `type="module"` in the first place - a browser that does not is specified
// to skip the element without even parsing its content, so it can never fail
// on syntax it does not support. But "recognises modules" and "supports
// optional chaining" are not the same floor: module support landed in
// 2017-2018, optional chaining in 2020, and a syntax error anywhere in a
// module aborts the whole file - unlike a classic script, there is no
// per-statement fallback. Plain `if` checks cost nothing here and remove that
// failure mode entirely, so they are used throughout instead.

const root = document.documentElement;

/**
 * Every `localStorage` write goes through this. It throws rather than
 * silently failing in Safari's private mode and when cookies are blocked -
 * the reads live in the inline head script in "Appearance.typoscript", which
 * needs the same guard for the same reason.
 */
function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (error) {
        // Nothing to recover: the choice still applies to this page load
        // through the root attribute, it just will not survive a reload.
    }
}

function removeStorage(key) {
    try {
        window.localStorage.removeItem(key);
    } catch (error) {
        // Same as "writeStorage" above.
    }
}

/**
 * The three display settings, keyed by the `data-theme-setting` value their
 * controls carry in "Resources/Private/Partials/Page/Settings.html". Each is
 * one root attribute and one `localStorage` key - the keys the inline head
 * script in "Appearance.typoscript" reads before first paint, so a key changed
 * here has to be changed there as well.
 *
 * They are asymmetric on purpose, and each entry says how:
 *
 *   - appearance: "auto" is the *absence* of "data-theme", never its value -
 *     no selector in "abstracts/_tokens.scss" matches it, and
 *     "Appearance.typoscript" renders no attribute for it either. It is still
 *     *stored* as "auto" rather than by removing the key: without a key the
 *     next page load falls back to "theme.appearance.default", which is only
 *     "auto" as long as nobody changed that constant.
 *   - palette: always an explicit attribute, `neutral` included, exactly as
 *     the server renders it.
 *   - content outline: "on" or "off" on "data-theme-content-outline". Only
 *     "off" has a rule of its own ("components/_content-element.scss").
 *
 * `fallback` is not a server default. It is only what `current()` reports for
 * a missing root attribute and what "Reset" applies when the partial carries
 * no `data-theme-default-*` value at all - a site package overriding the
 * partial without it. The server defaults themselves come from those
 * attributes, see `serverDefault()`.
 */
const displaySettings = {
    appearance: {
        storageKey: 'theme-appearance',
        fallback: 'auto',
        current: function () {
            return root.getAttribute('data-theme') || 'auto';
        },
        apply: function (value) {
            if (value === 'auto') {
                root.removeAttribute('data-theme');
            } else {
                root.setAttribute('data-theme', value);
            }
        },
    },
    palette: {
        storageKey: 'theme-palette',
        fallback: 'neutral',
        current: function () {
            return root.getAttribute('data-palette') || 'neutral';
        },
        apply: function (value) {
            root.setAttribute('data-palette', value);
        },
    },
    'content-outline': {
        storageKey: 'theme-content-outline',
        fallback: 'on',
        current: function () {
            return root.getAttribute('data-theme-content-outline') === 'off' ? 'off' : 'on';
        },
        apply: function (value) {
            root.setAttribute('data-theme-content-outline', value);
        },
    },
};

/**
 * The setting a control operates, or `null`. An own-property check rather
 * than a plain lookup, so a stray `data-theme-setting="constructor"` resolves
 * to nothing instead of to `Object`.
 */
function settingOf(input) {
    const name = input.getAttribute('data-theme-setting');
    if (name === null || !Object.prototype.hasOwnProperty.call(displaySettings, name)) {
        return null;
    }

    return displaySettings[name];
}

/**
 * The value a control stands for: a radio its `value`, the outline switch
 * "on" or "off" by its checked state.
 */
function valueOf(input) {
    if (input.type === 'checkbox') {
        return input.checked ? 'on' : 'off';
    }

    return input.value;
}

/**
 * Checks the controls that match the root's *current* attributes.
 *
 * Read off the root, not off `localStorage` again: the inline head script
 * already resolved server default vs. stored choice into those attributes
 * before this module ran, and re-deriving the same answer from storage here
 * would be a second, independent path to it that could in principle disagree.
 * The server-rendered `checked` in the partial is only the answer for a
 * visitor who has stored nothing.
 */
function syncControls(container) {
    container.querySelectorAll('input[data-theme-setting]').forEach(function (input) {
        const setting = settingOf(input);
        if (setting === null) {
            return;
        }

        const value = setting.current();
        if (input.type === 'checkbox') {
            input.checked = value === 'on';
        } else {
            input.checked = input.value === value;
        }
    });
}

/**
 * A page may carry more than one settings control - see the `idPrefix`
 * argument of the partial - and a choice made in one has to show in all of
 * them, since they all operate the same root.
 */
function syncAllControls() {
    document.querySelectorAll('.theme-settings').forEach(syncControls);
}

/**
 * The server default of one setting, as the partial renders it from the
 * TypoScript constants onto the control's root element. Not read off the html
 * tag: once a stored choice has been applied there, the server's value is not
 * on the tag any more.
 */
function serverDefault(container, name) {
    return container.getAttribute('data-theme-default-' + name) || displaySettings[name].fallback;
}

/**
 * Wires one settings control: the disclosure, the choices and "Reset".
 *
 * A disclosure, not a menu - see the partial's header comment. The trigger
 * owns `aria-expanded`, the panel's `hidden` follows it, and focus stays on
 * the trigger when the panel opens: the controls inside are reached with Tab
 * like any other form, and keep their native keyboard behaviour.
 */
function bindDisplaySettings(container) {
    const trigger = container.querySelector('.theme-settings__trigger');
    if (!trigger) {
        return;
    }
    const panel = document.getElementById(trigger.getAttribute('aria-controls') || '');
    if (!panel) {
        return;
    }

    function isOpen() {
        return trigger.getAttribute('aria-expanded') === 'true';
    }

    function setOpen(open) {
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        panel.hidden = !open;
    }

    trigger.addEventListener('click', function () {
        setOpen(!isOpen());
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !isOpen()) {
            return;
        }

        // Focus returns to the cog - left inside, it would sit on a control
        // that just disappeared - unless it is on an element elsewhere on the
        // page: an Escape pressed there still closes the panel, but must not
        // pull focus away from where the reader is. Focus on no element at
        // all (the body, after a click on the panel's padding, or in Safari,
        // which does not focus a clicked button) is not "elsewhere", and
        // returning it to the cog is the helpful answer.
        const active = document.activeElement;
        const focusIsElsewhere = active !== null && active !== document.body && !container.contains(active);
        setOpen(false);
        if (!focusIsElsewhere) {
            trigger.focus();
        }
    });

    document.addEventListener('click', function (event) {
        // "container.contains()" is also true for a click on the trigger,
        // which is what keeps opening the panel from being undone by this
        // same handler on the same click.
        if (isOpen() && !container.contains(event.target)) {
            setOpen(false);
        }
    });

    // Tabbing out of the control closes the panel, so it cannot stay open
    // over the content that now has focus (WCAG 2.2, 2.4.11 Focus Not
    // Obscured). Focus stays where the reader moved it. Only a move to an
    // element outside counts: "relatedTarget" is null when focus goes
    // nowhere - a click on the panel's own padding or a legend, the window
    // losing focus, or Safari, which does not focus a radio or a button it
    // is clicked on - and none of those is leaving the control. A click
    // outside is the click handler's part.
    container.addEventListener('focusout', function (event) {
        const next = event.relatedTarget;
        if (isOpen() && next instanceof Node && !container.contains(next)) {
            setOpen(false);
        }
    });

    // One listener for every control: "change" bubbles, and it fires for a
    // click, for the arrow keys within a radio group and for Space alike.
    // A choice applies at once - there is no "apply" step to forget - and
    // focus is left exactly where the reader put it.
    panel.addEventListener('change', function (event) {
        const setting = settingOf(event.target);
        if (setting === null) {
            return;
        }

        const value = valueOf(event.target);
        setting.apply(value);
        writeStorage(setting.storageKey, value);
        syncAllControls();
    });

    // "Reset" forgets the stored choices rather than storing the defaults:
    // a visitor who reset follows the site from then on, including a
    // constant changed after the reset.
    const reset = container.querySelector('[data-theme-settings-reset]');
    if (reset) {
        reset.addEventListener('click', function () {
            Object.keys(displaySettings).forEach(function (name) {
                const setting = displaySettings[name];
                removeStorage(setting.storageKey);
                setting.apply(serverDefault(container, name));
            });
            syncAllControls();
        });
    }

    syncControls(container);
}

document.querySelectorAll('.theme-settings').forEach(bindDisplaySettings);

/**
 * The main navigation toggle. `components/_nav-main.scss` only shows this
 * button once "data-js" is set - which the root already carries by the time
 * this module runs - so unlike the settings control above, a visible toggle
 * is guaranteed to need a working one. The element lookup still guards
 * against its absence: cheap, and it means a template change here fails
 * quietly instead of throwing partway through this file.
 *
 * **Every** toggle is bound, not the first one. The collapse rule in
 * `_nav-main.scss` matches any ".theme-nav-main" whose own toggle is not
 * expanded, so a second navigation on the page - the styleguide's specimen,
 * a site package repeating the menu in its footer - would be collapsed below
 * the breakpoint by a control that was never wired up, with nothing on the
 * page able to open it again. Binding one and styling all of them is the kind
 * of mismatch that only shows on a narrow viewport of a page nobody tested.
 */
function bindMainMenuToggle() {
    document.querySelectorAll('.theme-nav-main__toggle').forEach(bindOneMainMenuToggle);
}

function bindOneMainMenuToggle(toggle) {
    // Scoped to the navigation this particular toggle sits in, so two menus on
    // one page cannot close each other.
    const nav = toggle.closest('.theme-nav-main');
    if (!nav) {
        return;
    }

    function isOpen() {
        return toggle.getAttribute('aria-expanded') === 'true';
    }

    function close() {
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        toggle.setAttribute('aria-expanded', isOpen() ? 'false' : 'true');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
            close();
            // Focus has to return to the control that opened the menu - left
            // where it was, it would sit on (or under) content that just
            // disappeared.
            toggle.focus();
        }
    });

    document.addEventListener('click', function (event) {
        // "nav.contains(event.target)" is also true for a click on the
        // toggle itself, since the toggle is inside the nav it controls -
        // which is what keeps opening the menu from being immediately
        // undone by this same handler on the same click.
        if (isOpen() && !nav.contains(event.target)) {
            close();
        }
    });
}

bindMainMenuToggle();
