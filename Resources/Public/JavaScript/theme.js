// Appearance switcher, palette switcher and main menu toggle.
//
// Loaded as `type="module"` from "Configuration/TypoScript/Appearance.typoscript"
// - see that file for where and why. A module is deferred by specification, so
// this runs after the DOM is parsed; it needs neither a "DOMContentLoaded"
// listener nor a "defer" attribute.
//
// By the time this runs, the root already carries "data-js" and whatever
// appearance/palette the inline head script in "Appearance.typoscript" landed
// on - either the server-rendered default, or a stored choice it applied
// before first paint. This file only adds interaction on top of that state,
// it never sets it up from scratch, and it is not what makes the page usable:
// the navigation is already open and readable with no script running at all
// (see "components/_nav-main.scss"), and the switcher controls are hidden
// until "data-js" is present. The one thing here that *is* load-bearing is
// the menu toggle, once the viewport is narrow enough to need it - which is
// why it is the one control this file does not treat as optional.
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
 * `readStorage`'s counterpart lives in the inline head script in
 * "Appearance.typoscript", which needs the same guard for the same reason.
 */
function writeStorage(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (error) {
        // Nothing to recover: the choice still applies to this page load
        // through the attribute set below, it just will not survive a
        // reload.
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
 * Wires one button group - appearance or palette - to one root attribute and
 * one `localStorage` key.
 *
 * The two controls are asymmetric on purpose, and the parameters mirror that
 * rather than hiding it:
 *
 *   - the button's own `data-*` attribute never matches the root attribute it
 *     writes ("data-theme-appearance" on the button, "data-theme" on the
 *     root; "data-theme-palette" on the button, "data-palette" on the root -
 *     see "Resources/Private/Partials/Page/AppearanceSwitcher.html" and
 *     "abstracts/_tokens.scss" / "_palettes.scss");
 *   - only appearance has a value that means "remove the attribute" (`auto`,
 *     matching "Appearance.typoscript"'s server-side condition for the same
 *     value). A palette has no such value - `neutral` is rendered explicitly
 *     server side, and the switcher keeps doing the same - so `removeValue`
 *     is `null` for it, which no button's `data-*` value can ever equal.
 *
 * The initial `aria-pressed` state is read off the root's *current*
 * attribute, not off `localStorage` again: the inline head script already
 * resolved server default vs. stored choice into that attribute before this
 * module ran, and re-deriving the same answer from storage here would be a
 * second, independent path to it that could in principle disagree.
 */
function bindOptionGroup(buttonAttribute, rootAttribute, storageKey, removeValue) {
    const buttons = document.querySelectorAll('[data-' + buttonAttribute + ']');
    if (buttons.length === 0) {
        // The switcher partial is rendered by a separate template and is not
        // present on every page - there is nothing here to attach to.
        return;
    }

    function currentValue() {
        return root.getAttribute('data-' + rootAttribute) || removeValue;
    }

    function applyPressedState() {
        const value = currentValue();
        buttons.forEach(function (button) {
            const pressed = button.getAttribute('data-' + buttonAttribute) === value;
            button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        });
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-' + buttonAttribute);
            if (value === null) {
                return;
            }

            if (value === removeValue) {
                root.removeAttribute('data-' + rootAttribute);
                removeStorage(storageKey);
            } else {
                root.setAttribute('data-' + rootAttribute, value);
                writeStorage(storageKey, value);
            }

            // Deliberately no focus handling here: changing appearance or
            // palette must not move focus, and doing nothing is what leaves
            // it exactly where the click put it.
            applyPressedState();
        });
    });

    applyPressedState();
}

bindOptionGroup('theme-appearance', 'theme', 'theme-appearance', 'auto');
bindOptionGroup('theme-palette', 'palette', 'theme-palette', null);

/**
 * The main navigation toggle. `components/_nav-main.scss` only shows this
 * button once "data-js" is set - which the root already carries by the time
 * this module runs - so unlike the switcher above, a visible toggle is
 * guaranteed to need a working one. The element lookup still guards against
 * its absence: cheap, and it means a template change here fails quietly
 * instead of throwing partway through this file.
 */
function bindMainMenuToggle() {
    const toggle = document.querySelector('.theme-nav-main__toggle');
    if (!toggle) {
        return;
    }

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
