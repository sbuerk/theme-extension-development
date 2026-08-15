..  include:: /Includes.rst.txt

..  _feature-seeding:

===========================================
Feature: Seed a page tree from a definition
===========================================

Description
===========

The extension ships a console command that writes a page tree and its content
from a YAML definition:

..  code-block:: bash

    vendor/bin/typo3 theme:seed

It is meant for development and test instances, so a frontend to look at can be
rebuilt from nothing rather than clicked together by hand. The definition lives
in the repository, which makes the instance reproducible for everyone working
on it.

The shipped definition is
:file:`EXT:theme_extension_development/Configuration/Seeds/Demo.yaml`. Another
one is written by passing its path:

..  code-block:: bash

    vendor/bin/typo3 theme:seed EXT:my_package/Configuration/Seeds/Other.yaml
    vendor/bin/typo3 theme:seed --root-page=12 --force

The format keeps its structural keys to ``identifier``, ``uid``, ``children``
and ``content``; every other key is a field of the record:

..  code-block:: yaml

    identifier: demo

    pages:
      - identifier: home
        uid: 1
        title: 'Theme demo'
        slug: '/'
        is_siteroot: 1
        content:
          - identifier: home-heading
            CType: header
            header: 'A frontend to look at'

Records are written through DataHandler rather than as database rows, so slugs,
TCA defaults, sorting, the reference index and the caches are handled by the
core rather than reimplemented.

..  note::

    Seeding requires an admin backend user, and refuses to run into a page tree
    that is not empty: a definition declaring uids collides with existing
    records rather than adding to them.

    Files are not covered. A definition cannot reference an image, because
    ``sys_file`` and FAL references are outside what the seeding writes.
