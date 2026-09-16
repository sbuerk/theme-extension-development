// Display settings, main menu toggle - and the behaviour of the three
// components that need a script: tabs, the dialog opener and tooltip
// dismissal.
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

/**
 * Tabs - the WAI-ARIA tabs pattern with automatic activation: a click or an
 * arrow key selects a tab and shows its panel in one step.
 *
 * "components/_tabs.scss" documents the contract this relies on: the first
 * tab is selected in the markup, and a panel carries nothing but its class and
 * its id there, so a page this script never ran on shows every panel, with no
 * tab panel semantics pointing at tabs nobody can see. Everything that turns
 * the panels into tab panels happens here - the role, the label, the tab stop,
 * and hiding the ones not selected - and once it has, the group is marked
 * `data-theme-tabs-bound`, the one marker the stylesheet shows the tabs on.
 *
 * Every group is bound, and every lookup is scoped to its own group, for the
 * reason the main menu toggle above gives: a tab group inside another group's
 * panel has to answer its own keys and nobody else's.
 */
function bindTabs() {
    document.querySelectorAll('.theme-tabs').forEach(bindOneTabGroup);
}

function bindOneTabGroup(group) {
    const list = Array.prototype.find.call(group.children, function (element) {
        return element.classList.contains('theme-tabs__list');
    });
    if (!list) {
        return;
    }

    const tabs = Array.prototype.filter.call(list.children, function (element) {
        return element.getAttribute('role') === 'tab';
    });
    if (tabs.length === 0) {
        return;
    }

    function panelOf(tab) {
        const id = tab.getAttribute('aria-controls');
        return id ? document.getElementById(id) : null;
    }

    function select(selected) {
        tabs.forEach(function (tab) {
            const isSelected = tab === selected;
            tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            // The roving tab stop: only the selected tab is in the tab
            // sequence, so Tab moves from the list straight into the panel.
            tab.setAttribute('tabindex', isSelected ? '0' : '-1');
            const panel = panelOf(tab);
            if (panel) {
                panel.hidden = !isSelected;
            }
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            select(tab);
        });
    });

    list.addEventListener('keydown', function (event) {
        const index = tabs.indexOf(event.target);
        if (index === -1) {
            return;
        }

        // "Next" follows the reading direction: in a right-to-left page the
        // next tab is the one to the left, and that is the key a reader
        // reaches for.
        const rightToLeft = window.getComputedStyle(list).direction === 'rtl';
        const nextKey = rightToLeft ? 'ArrowLeft' : 'ArrowRight';
        const previousKey = rightToLeft ? 'ArrowRight' : 'ArrowLeft';

        let target = null;
        if (event.key === nextKey) {
            target = tabs[(index + 1) % tabs.length];
        } else if (event.key === previousKey) {
            target = tabs[(index - 1 + tabs.length) % tabs.length];
        } else if (event.key === 'Home') {
            target = tabs[0];
        } else if (event.key === 'End') {
            target = tabs[tabs.length - 1];
        }
        if (target === null) {
            return;
        }

        // Without this, Home and End would also scroll the page.
        event.preventDefault();
        select(target);
        target.focus();
    });

    // Only now do the panels become tab panels: the role, the tab that labels
    // each one, and a tab stop of its own, so that Tab moves from the list into
    // the panel. The markup carries none of the three, which is what keeps a
    // page this never ran on free of tab panels labelled by tabs nobody can
    // see, and of one extra tab stop per panel.
    tabs.forEach(function (tab) {
        const panel = panelOf(tab);
        if (!panel) {
            return;
        }
        panel.setAttribute('role', 'tabpanel');
        if (tab.id) {
            panel.setAttribute('aria-labelledby', tab.id);
        }
        panel.setAttribute('tabindex', '0');
    });

    // The contract has the markup select the first tab. Honouring whichever
    // tab it did select costs one lookup, and keeps a template that selects
    // another one from ending up with the wrong panel on screen.
    const initial = tabs.find(function (tab) {
        return tab.getAttribute('aria-selected') === 'true';
    }) || tabs[0];
    select(initial);
    group.setAttribute('data-theme-tabs-bound', '');
}

/**
 * The dialog. "components/_dialog.scss" documents why the opener is a
 * `data-theme-dialog-open` attribute naming the dialog's id rather than an
 * invoker command, and hides every opener until the root carries "data-js".
 *
 * Closing is the browser's - Escape, and the `<form method="dialog">` around
 * the content. Two things are added on top: a click on the backdrop closes
 * the dialog too, and focus is put back on the opener explicitly whichever
 * way it was closed, rather than left to how each browser restores it.
 */
function bindDialogs() {
    document.querySelectorAll('dialog.theme-dialog').forEach(bindOneDialog);
    document.querySelectorAll('[data-theme-dialog-open]').forEach(bindOneDialogOpener);
}

function bindOneDialog(dialog) {
    // A click on the backdrop is dispatched to the dialog element itself - and
    // so is a click on the dialog's own border, which only the position tells
    // apart. A click also goes to the nearest element the press and the
    // release have in common: a press on the body text, dragged out and let go
    // over the scrim, arrives as a click on the dialog at a point outside it.
    // That is someone selecting text, not dismissing the dialog, so the press
    // has to have started on the backdrop as well.
    let pressedOnBackdrop = false;

    function isOnBackdrop(event) {
        if (event.target !== dialog) {
            return false;
        }
        const box = dialog.getBoundingClientRect();
        return event.clientX < box.left || event.clientX > box.right
            || event.clientY < box.top || event.clientY > box.bottom;
    }

    dialog.addEventListener('pointerdown', function (event) {
        pressedOnBackdrop = isOnBackdrop(event);
    });

    dialog.addEventListener('click', function (event) {
        const close = pressedOnBackdrop && isOnBackdrop(event);
        pressedOnBackdrop = false;
        if (close) {
            dialog.close('cancel');
        }
    });
}

function bindOneDialogOpener(opener) {
    const dialog = document.getElementById(opener.getAttribute('data-theme-dialog-open'));
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    opener.addEventListener('click', function () {
        if (dialog.open) {
            return;
        }
        // Registered per opening, and only once: with two openers for the
        // same dialog, focus has to go back to the one that opened it.
        dialog.addEventListener('close', function () {
            opener.focus();
        }, { once: true });
        dialog.showModal();
    });
}

/**
 * Tooltip dismissal. WCAG 1.4.13 asks that a tooltip can be hidden without
 * moving the pointer or focus; "components/_tooltip.scss" shows the bubble on
 * hover and focus with CSS alone and keeps it hidden while the wrapper carries
 * `data-theme-tooltip-dismissed`. This sets that on Escape - only on a
 * tooltip the pointer or focus is inside, and without moving focus anywhere -
 * and takes it off again once pointer and focus have both left, so the next
 * hover or focus shows the tooltip as usual.
 *
 * One Escape listener on the document serves every tooltip on the page; each
 * tooltip adds only the two listeners that restore it. It neither stops the
 * event nor prevents its default, and neither does the display settings'
 * listener above: one Escape closes an open settings panel, a modal dialog and
 * a tooltip under the pointer alike, each handler minding only its own.
 */
function bindTooltips() {
    const tooltips = document.querySelectorAll('.theme-tooltip');
    if (tooltips.length === 0) {
        return;
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        tooltips.forEach(function (tooltip) {
            if (tooltip.matches(':hover') || tooltip.matches(':focus-within')) {
                tooltip.setAttribute('data-theme-tooltip-dismissed', '');
            }
        });
    });

    tooltips.forEach(bindOneTooltip);
}

function bindOneTooltip(tooltip) {
    function restore() {
        tooltip.removeAttribute('data-theme-tooltip-dismissed');
    }

    tooltip.addEventListener('mouseleave', function () {
        if (!tooltip.matches(':focus-within')) {
            restore();
        }
    });

    tooltip.addEventListener('focusout', function (event) {
        if (!tooltip.contains(event.relatedTarget) && !tooltip.matches(':hover')) {
            restore();
        }
    });
}

/**
 * The carousel - the previous and next buttons, and which indicator is marked
 * as the one the reader is on.
 *
 * "components/_carousel.scss" documents the contract, and the short version is
 * that this file adds nothing the component needs in order to work: the track
 * is a scroll container that snaps, so it scrolls by touch, trackpad, wheel,
 * scroll bar and - because it carries a tab stop - by the arrow keys, with
 * nothing bound to it; and the indicators are links to the ids of the slides,
 * so they move the reader with no script either. What is added here is the two
 * buttons, which the stylesheet keeps hidden until this function has marked the
 * carousel "data-theme-carousel-bound", and "aria-current" on the indicator of
 * the slide in view, which is a fact only the running page knows.
 *
 * **Nothing here starts on its own.** There is no timer, no autoplay and no
 * "play/pause" control to go with one, which is the WCAG 2.2.2 decision the
 * component's header comment makes; every scroll below is the answer to
 * something the reader did.
 *
 * Every carousel on the page is bound, each scoped to its own element, for the
 * reason the main menu toggle above gives.
 */
function bindCarousels() {
    document.querySelectorAll('.theme-carousel').forEach(bindOneCarousel);
}

function bindOneCarousel(carousel) {
    const track = carousel.querySelector('.theme-carousel__track');
    if (!track) {
        return;
    }

    const slides = Array.prototype.filter.call(track.children, function (element) {
        return element.classList.contains('theme-carousel__slide');
    });
    if (slides.length === 0) {
        return;
    }

    const indicators = carousel.querySelectorAll('.theme-carousel__indicator');

    // Scrolling by the width of one slide plus the gap, read off the two
    // slides rather than from a token: the gap is a custom property the page
    // may have re-pointed, and the measured distance is right whatever it
    // holds. With one slide there is nothing to measure and nothing to scroll.
    function step() {
        if (slides.length < 2) {
            return track.clientWidth;
        }

        return Math.abs(slides[1].offsetLeft - slides[0].offsetLeft);
    }

    function scrollBy(direction) {
        // "scrollBy" with "behavior: smooth" is animation the reader asked
        // for, and the browser already suppresses it for
        // "prefers-reduced-motion: reduce" - it is the one place where
        // honouring that preference needs no rule of ours.
        track.scrollBy({ left: direction * step(), behavior: 'smooth' });
    }

    const previous = carousel.querySelector('.theme-carousel__control--previous');
    if (previous) {
        previous.addEventListener('click', function () {
            // The start edge is to the right in a right-to-left page, and
            // "scrollLeft" is negative there; multiplying by the direction of
            // the track is what makes "previous" mean previous in both.
            scrollBy(window.getComputedStyle(track).direction === 'rtl' ? 1 : -1);
        });
    }

    const next = carousel.querySelector('.theme-carousel__control--next');
    if (next) {
        next.addEventListener('click', function () {
            scrollBy(window.getComputedStyle(track).direction === 'rtl' ? -1 : 1);
        });
    }

    // Which slide the reader is on, marked on its indicator. An
    // IntersectionObserver against the track rather than a scroll listener:
    // it reports only when a slide crosses the threshold, and it is right
    // after a jump by a fragment link, after a resize and after a scroll the
    // reader made by hand, none of which a click handler would see.
    //
    // "aria-current" and nothing else: the indicator is a link to a slide, and
    // the statement being made is "this is the one you are on", which is what
    // that attribute says. It is not "selected" - nothing here is a tab.
    if (indicators.length === slides.length && typeof window.IntersectionObserver === 'function') {
        const observer = new window.IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                const index = slides.indexOf(entry.target);
                if (index === -1) {
                    return;
                }
                if (entry.isIntersecting) {
                    indicators[index].setAttribute('aria-current', 'true');
                } else {
                    indicators[index].removeAttribute('aria-current');
                }
            });
        }, { root: track, threshold: 0.6 });

        slides.forEach(function (slide) {
            observer.observe(slide);
        });
    }

    // Last, as for the tabs: the marker says the controls above are wired, so
    // it must not be set before they are.
    carousel.setAttribute('data-theme-carousel-bound', '');
}

/**
 * The lightbox - the enlarged images of one gallery in one dialog.
 * "components/_lightbox.scss" documents the markup contract.
 *
 * The opener is the gallery's zoom link, and it is **not** a
 * `data-theme-dialog-open` opener: that attribute is hidden without "data-js",
 * which is right for a button that could do nothing and wrong for a link that
 * still leads to the image. So the link keeps its "href", carries
 * `data-theme-lightbox` (the dialog's id) and `data-theme-lightbox-item` (the
 * id of the figure showing that image), and the navigation is prevented here -
 * only once everything the dialog needs has actually been found. A page this
 * file never reached is a page whose zoom links still work.
 *
 * Items are addressed by id rather than by position: a "textmedia" gallery may
 * hold a video between two images, and an index would have to agree with a
 * list the template does not have.
 */
function bindLightboxes() {
    document.querySelectorAll('dialog.theme-lightbox').forEach(bindOneLightbox);
    document.querySelectorAll('[data-theme-lightbox]').forEach(bindOneLightboxOpener);
}

/**
 * The figures of one lightbox, in document order.
 */
function lightboxItems(dialog) {
    return Array.prototype.slice.call(dialog.querySelectorAll('[data-theme-lightbox-item]'));
}

/**
 * Shows one figure and hides the others. The markup hides nothing - see the
 * contract - so this is also what puts the dialog into a usable state the
 * first time it is bound.
 */
function showLightboxItem(dialog, item) {
    lightboxItems(dialog).forEach(function (candidate) {
        candidate.hidden = candidate !== item;
    });
}

function bindOneLightbox(dialog) {
    const items = lightboxItems(dialog);
    if (items.length === 0) {
        return;
    }

    // One image has nowhere to go, and two arrows that do nothing are two more
    // tab stops. The count is known here and not in the template, which would
    // have to count the images among the files of the element.
    const controls = dialog.querySelector('.theme-lightbox__controls');
    if (controls && items.length < 2) {
        controls.hidden = true;
    }

    showLightboxItem(dialog, items[0]);

    function move(step) {
        let current = 0;
        items.forEach(function (item, index) {
            if (!item.hidden) {
                current = index;
            }
        });
        // Wraps in both directions, like the arrow keys of the tab list above.
        showLightboxItem(dialog, items[(current + step + items.length) % items.length]);
    }

    const previous = dialog.querySelector('[data-theme-lightbox-previous]');
    if (previous) {
        previous.addEventListener('click', function () {
            move(-1);
        });
    }
    const next = dialog.querySelector('[data-theme-lightbox-next]');
    if (next) {
        next.addEventListener('click', function () {
            move(1);
        });
    }

    // Escape is the browser's. The arrows are listened for on the dialog
    // rather than on the document: a modal dialog holds focus, so the event
    // arrives here, and nothing is caught while the lightbox is closed.
    dialog.addEventListener('keydown', function (event) {
        if (items.length < 2) {
            return;
        }

        // "Next" follows the reading direction, as in the tab list above.
        const rightToLeft = window.getComputedStyle(dialog).direction === 'rtl';
        if (event.key === (rightToLeft ? 'ArrowLeft' : 'ArrowRight')) {
            event.preventDefault();
            move(1);
        } else if (event.key === (rightToLeft ? 'ArrowRight' : 'ArrowLeft')) {
            event.preventDefault();
            move(-1);
        }
    });
}

function bindOneLightboxOpener(opener) {
    const dialog = document.getElementById(opener.getAttribute('data-theme-lightbox'));
    if (!dialog || typeof dialog.showModal !== 'function') {
        return;
    }
    const item = document.getElementById(opener.getAttribute('data-theme-lightbox-item') || '');
    if (!item) {
        return;
    }

    opener.addEventListener('click', function (event) {
        if (dialog.open) {
            return;
        }
        // Only now: everything needed to show the image in the dialog is
        // present, so the link may stop leading to the file.
        event.preventDefault();
        showLightboxItem(dialog, item);
        // Registered per opening, and only once: every zoom link of the
        // gallery opens the same dialog, and focus has to go back to the one
        // that was pressed.
        dialog.addEventListener('close', function () {
            opener.focus();
        }, { once: true });
        dialog.showModal();
    });
}

/**
 * The privacy friendly embed of "theme_external_media"
 * ("components/_embed.scss"): the page carries a poster and a button, and the
 * iframe is built here, on the press, from the two data attributes the button
 * carries. Nothing of the other site is requested before that - which is the
 * whole point of the element, and why the iframe is not in the markup with a
 * "loading=lazy" that would still connect as soon as the box scrolls into
 * view.
 *
 * The button is hidden while the root carries no "data-js", so a page this
 * file never reached shows the poster and the link to the source instead.
 */
function bindEmbeds() {
    document.querySelectorAll('[data-theme-embed-play]').forEach(bindOneEmbed);
}

function bindOneEmbed(button) {
    const frame = button.closest('.theme-embed__frame');
    const embed = button.closest('.theme-embed');
    const source = button.getAttribute('data-theme-embed-src');
    if (!frame || !embed || !source) {
        return;
    }

    button.addEventListener('click', function () {
        const iframe = document.createElement('iframe');

        // The press is the user gesture that lets the player start on its own.
        // Without it the reader would press play twice: once here, and once
        // more inside a player that has just appeared.
        iframe.setAttribute('src', source + (source.indexOf('?') === -1 ? '?' : '&') + 'autoplay=1');
        // An iframe is a document of its own in the page, and a screen reader
        // announces it by this name. The template guarantees one.
        iframe.setAttribute('title', button.getAttribute('data-theme-embed-title') || '');
        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media');
        iframe.setAttribute('allowfullscreen', '');
        // The provider learns which page the request came from, not which
        // page of it.
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');

        while (frame.firstChild) {
            frame.removeChild(frame.firstChild);
        }
        frame.appendChild(iframe);
        // Focus moves into the player: the control that had it has just been
        // removed from the document, and focus would otherwise fall back to
        // the body and lose the reader's place.
        iframe.focus();
    });

    // Only now is the button shown - the stylesheet keeps it out of the page
    // until this marker is set, so a page whose "theme.js" never arrived shows
    // the poster and the link rather than a button that does nothing.
    embed.setAttribute('data-theme-embed-bound', '');
}

/**
 * The dropdown of the site header.
 *
 * The opening and closing are the browser's: the trigger carries
 * `popovertarget`, the panel carries `popover`, and the Popover API gives them
 * the top layer, Escape and light dismiss with no script at all. See
 * "components/_dropdown.scss".
 *
 * What is left for this file is the one thing the platform does not do: the
 * Popover API says nothing to assistive technology about the *trigger*, so the
 * open state is mirrored onto its `aria-expanded` from the panel's own
 * `toggle` event.
 *
 * This only ever *reports* a state the browser already changed. It never opens
 * or closes anything, which is why the dropdown is not hidden behind
 * "data-js" the way the display settings and the dialog opener are: a page
 * whose script never ran still has a working dropdown, with a trigger whose
 * `aria-expanded` is stale - a far smaller failure than a control that does
 * nothing at all.
 *
 * `toggle` is bound on the panel rather than `click` on the trigger, because
 * a popover also closes by routes the trigger never sees: Escape, a click
 * anywhere outside, and another popover opening.
 */
function bindDropdowns() {
    document.querySelectorAll('.theme-dropdown__trigger').forEach(bindOneDropdownTrigger);
}

function bindOneDropdownTrigger(trigger) {
    const panel = document.getElementById(trigger.getAttribute('popovertarget') || '');
    if (!panel) {
        return;
    }

    // "ToggleEvent.newState" is "open" or "closed". Older engines that have
    // the Popover API but not the event simply leave the attribute at its
    // server-rendered "false", which is the same degradation as no script.
    panel.addEventListener('toggle', function (event) {
        trigger.setAttribute('aria-expanded', event.newState === 'open' ? 'true' : 'false');
    });
}

bindTabs();
bindDialogs();
bindLightboxes();
bindEmbeds();
bindTooltips();
bindCarousels();
bindDropdowns();
