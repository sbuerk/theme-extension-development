..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

The extension has to be installed like any other TYPO3 CMS extension.

..  warning::

    This is a development tool, not a production theme. It belongs in a
    development or test instance, not in a live site — that applies to every
    installation method described below.

Composer mode
=============

Being a development tool, it usually belongs in ``require-dev`` — of the
extension repository whose frontend is to be looked at, or of the test instance
set up for it:

..  code-block:: bash

    composer require --dev sbuerk/theme-extension-development

A TYPO3 extension required that way is installed and activated exactly like any
other one, because ``typo3/cms-composer-installers`` makes no distinction
between ``require`` and ``require-dev``. A deployment installing with
``composer install --no-dev`` simply leaves it out, which is the point.

..  note::

    As long as no stable version has been released, the development version of
    the main branch has to be required explicitly:

    ..  code-block:: bash

        composer require --dev sbuerk/theme-extension-development:^2.0@dev

    This additionally requires ``minimum-stability`` to be set to ``dev``
    together with ``prefer-stable`` set to ``true`` in the root
    :file:`composer.json` file.

Classic mode
============

#.  **Get it from the Extension Manager**:
    Switch to the module :guilabel:`Admin Tools > Extensions`, switch to
    :guilabel:`Get Extensions` and search for the extension key
    *theme_extension_development*, then import the extension from the repository.

#.  **Get it from typo3.org**:
    You can always get the current version from `TER`_ by downloading the zip
    version. Upload the file afterwards in the Extension Manager.

..  _TER: https://extensions.typo3.org/extension/theme_extension_development

Installing the extension does not render anything yet. The theme still has to
be enabled for a site — see :ref:`configuration`.
