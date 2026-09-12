# Form showcase

The page `/forms` of the seeded tree (uid 10, backend layout `forms`) renders
one request form, put together from the form contract of the
[component library](component-library.md#forms) and nothing else, followed by
the same form as a server returns it after a failed submission and by the
summary of a successful one.

The implementation is
[`Resources/Private/Templates/Page/Forms.html`](../../Resources/Private/Templates/Page/Forms.html),
the layout
[`Configuration/PageTsConfig/BackendLayouts/Forms.tsconfig`](../../Configuration/PageTsConfig/BackendLayouts/Forms.tsconfig),
and the guards
[`Tests/Functional/FormShowcaseRenderingTest.php`](../../Tests/Functional/FormShowcaseRenderingTest.php)
and [`Tests/Acceptance/forms.spec.ts`](../../Tests/Acceptance/forms.spec.ts).

## Why it exists next to the styleguide

The [styleguide](styleguide.md)'s forms section shows the contract selector by
selector — every control, every state, each in a specimen of its own. It
cannot show whether the pieces work together in the order a form actually
needs them: fieldsets in sequence, an input group next to a plain field, a
choice group inside a fieldset, a switch next to a checkbox, the hints of
one field and the errors of the next. This page is that composition, with the
page chrome around it and no specimen frame, the way a site would render it.

## A page, not a content element

It follows the styleguide: literal markup in a page template, from a backend
layout whose only column is the inert `colPos 999`, and no `f:cObject`
anywhere in the template — anything an editor places on the page stays off it.

A form content element was the alternative, and was rejected:

- Its output would not depend on its record at all. A content element with
  nothing to configure is a template in the wrong place.
- It would be one more `CType` in the wizard, in the grants of the seeded
  editor group, and in every list that holds the showcase complete —
  `ShowcaseTreeTest` and `AccountsTest` derive theirs from the TypoScript.
- A real form content element is EXT:form's, and theming that is its own step
  — see [Content elements](../architecture/content-elements.md#not-yet-extform).

## It sends nothing

Both forms carry `method="dialog"` and no `action`. The submit buttons are real
submit buttons, which is the point: the HTML form submission algorithm runs
the browser's interactive constraint validation first — `required`, the
syntax of `email`, `pattern`, `minlength` — and a field that fails it takes
focus and matches `:user-invalid`. Only then does the method matter: a form
whose method is `dialog` and that has no `dialog` ancestor ends the submission
there. Nothing is sent, and the page does not navigate. The reset button is a
real reset.

The page copy says the same to a reader. `forms.spec.ts` holds the page to it
in a browser — a submit attempt with empty required fields focuses the first
and marks it, and a valid submission raises no request and stays on `/forms` —
and `FormShowcaseRenderingTest` fails the moment a form gains an `action` or
loses its method.

The styleguide's specimens use the same method but `type="button"`
throughout, which never validates; here validation is what is being shown.

## What the page covers

| Part                          | Shows                                                                                                                                                                                                                                                                                          |
|-------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| The request form              | Four fieldsets; text, email, telephone, number, date, colour, range with `output`, file, password, select, textarea; an input group with a leading and one with a trailing addon; an inline and a stacked choice group, a disabled choice; a switch and a required checkbox; submit and reset. |
| The same form, returned       | `.theme-form-summary--error` linking to every invalid field; `.theme-field--invalid` with `aria-invalid` and the error in `aria-describedby`, on a plain field, an input group, a textarea and a checkbox; `.theme-field--valid` with its success message.                                     |
| After a successful submission | `.theme-form-summary--success`.                                                                                                                                                                                                                                                                |

The one switch sits in the fieldset *Your account* as a setting of the account
rather than an answer to the request — the contract reserves the switch for a
setting, and a choice that is only sent with the rest of a form stays a
checkbox.

Ids carry the prefix `form-request-` in the first form and `form-returned-` in
the second, so neither collides with the other nor with the page chrome.
Copy is literal English, for the reason the styleguide gives.

## What the tests guard

| Test                                                    | Guards                                                                                              |
|---------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| `thePageRendersThroughTheFormsLayout`                   | The page resolves to the `forms` layout.                                                            |
| `contentPlacedOnTheFormShowcasePageIsNotRendered`       | Neither the element in `colPos 999` nor the one in `colPos 0` reaches the frontend.                 |
| `noFormOnThePageSendsAnything`                          | Two forms, both `method="dialog"`, neither with an `action`.                                        |
| `theShowcaseUsesEveryPartOfTheFormContract`             | Every class of the form contract appears in the main column. Data provider, one case per class.     |
| `theShowcaseUsesEveryKindOfControl`                     | One case per input type.                                                                            |
| `theSwitchCarriesTheSwitchRole`                         | The switch is announced as one.                                                                     |
| `everyIdOnThePageIsUnique`                              | No id twice, two forms of the same fields and the page chrome included.                             |
| `everyIdReferenceOnThePageResolves`                     | Every `for`, `aria-describedby`, `aria-labelledby` and every link of the error summary names an id. |
| `everyInvalidFieldIsInvalidToAssistiveTechnologyAsWell` | Every control of an invalid field carries `aria-invalid` and is described by its error.             |

`ShowcaseTreeTest` holds the seeded page to the layout it declares and keeps it
out of the main navigation, like the styleguide. The page is not part of the
[visual suite](../testing/visual-tests.md), which renders styleguide partials
only: the form is made of the same components the styleguide's forms section
already compares pixel for pixel.

## See also

- [Component library](component-library.md#forms) — the form contract.
- [Styleguide page](styleguide.md)
- [Seeding](seeding.md)
- [Acceptance tests](../testing/acceptance-tests.md)
