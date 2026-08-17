..  include:: /Includes.rst.txt

..  _feature-seed-records-of-any-table:

==================================================
Feature: Records of any table in a seed definition
==================================================

Description
===========

A seed definition can now declare records of **any** table on a page, through
the structural key :yaml:`records`. Until now a definition expressed pages
(:yaml:`children`), content elements (:yaml:`content`) and the children of a
relation (:yaml:`inline`) — so the page tree of a development instance could be
seeded, but not the data a plugin on it reads.

A record under :yaml:`records` declares the table it belongs to itself, exactly
as an inline child does:

..  code-block:: yaml

    pages:
      - identifier: persons-storage
        title: 'Persons'
        doktype: 254
        records:
          - identifier: profile-doe
            table: tx_academicpersons_domain_model_profile
            first_name: 'Jane'
            last_name: 'Doe'
            inline:
              contracts:
                - identifier: contract-doe
                  table: tx_academicpersons_domain_model_contract
                  position: 'Professor'

The structural keys of the format are therefore :yaml:`identifier`,
:yaml:`uid`, :yaml:`children`, :yaml:`content`, :yaml:`records`, :yaml:`files`
and :yaml:`inline`, plus :yaml:`table` on an inline or :yaml:`records` child.

A record declared this way is a record like any other: it may declare a
:yaml:`uid`, carry :yaml:`files` and carry :yaml:`inline` children, and its
:sql:`pid` is the page that declares it. Declaration order is kept per table,
so records of several tables on one page do not disturb each other's sorting.

Relations to seeded records
===========================

A relation is expressed by declaring the uid of the target and writing it into
the relation field. DataHandler resolves the rest, **including MM relations**,
whose rows go into a table the seeding never names:

..  code-block:: yaml

    records:
      - identifier: category-news
        table: sys_category
        uid: 4711
        title: 'News'

..  code-block:: yaml

    categories: 4711

Where ``records`` may appear
============================

:yaml:`records` is structure on a page and an ordinary field everywhere else.
:sql:`tt_content` has a column of that name — the one the :guilabel:`Insert
records` element writes ``tt_content_<uid>`` into — so the key is decided per
level, exactly as :yaml:`table` is. Declaring :yaml:`records` on a content
element therefore writes a field and nests nothing.

Impact
======

Existing seed definitions are unaffected: the key is new, and on the level where
it could collide with a column it keeps being a column. A development instance
can now be rebuilt from nothing including its records, rather than including
only the pages around them.
