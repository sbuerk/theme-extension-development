#
# This file exists because of TYPO3 v12. It is applied on both supported core
# versions - nothing in it is version aware - but on v13 it is redundant.
#
# TYPO3 v13 creates a database column for every TCA "columns" entry by itself
# (Feature #101553, extended by #104311 in 13.3 for the "ctrl" derived system
# columns). TYPO3 v12.4 does not: its DefaultTcaSchema
# (.Build/vendor/typo3/cms-core/Classes/Database/Schema/DefaultTcaSchema.php)
# only derives the management columns from "ctrl", the types
# category|datetime|slug|json|uuid and MM tables - it has no branch for
# "input", "text", "link", "file" or "inline", and it only touches tables that
# an ext_tables.sql defined in the first place. Without this file the
# tx_theme_list_item table and the four tx_theme_* columns on tt_content are
# never created on v12, and every theme element using them fails.
#
# The definitions below are not written by hand: except for the two "link"
# columns, which the next paragraph is about, they reproduce column for column
# what v13's own schema analyzer derives from this extension's TCA, so the
# analyzer stays quiet on both versions. Feature #101553 states that an
# explicit ext_tables.sql definition takes precedence over the derived one,
# which is why one version independent file serves v12 and v13 alike.
#
# Only the columns TCA declares are listed. The management columns - uid, pid,
# tstamp, crdate, deleted, hidden, sorting_foreign, the language fields and
# the t3ver_* fields - plus the "parent", "translation_source" and
# "t3ver_oid" indexes are derived from "ctrl" by both versions and are
# deliberately absent here, exactly as core's own ext_tables.sql leaves them
# out for sys_file_reference.
#
# ---------------------------------------------------------------------------
# Why "tt_content.tx_theme_link" and "tx_theme_list_item.link" are nullable
# ---------------------------------------------------------------------------
#
# v13's DefaultTcaSchema derives a TCA "type=link" field as
# "TEXT DEFAULT '' NOT NULL" (Classes/Database/Schema/DefaultTcaSchema.php,
# "case 'link'"), and that definition survives on MySQL only because v13 ships
# its own Doctrine platform classes: Classes/Database/Platform/MySQL80Platform
# uses MySQLDefaultValueDeclarationSQLOverrideTrait, which renders the default
# of a TEXT/BLOB/JSON column as the expression default "DEFAULT ('')" MySQL
# 8.0.13 introduced (Feature #103578, TYPO3 13.1; the platform overrides
# themselves are Important #102402, TYPO3 13.0).
#
# TYPO3 v12.4 has no such override - Classes/Database/Platform/ holds nothing
# but PlatformInformation.php - so Doctrine DBAL's own
# AbstractMySQLPlatform::getDefaultValueDeclarationSQL() applies, and it drops
# the default of a TextType column outright. The column reaches MySQL as
# "TEXT NOT NULL" with no default at all, and every INSERT that does not name
# it fails under strict mode with "Field 'tx_theme_link' doesn't have a default
# value" - which is what a CSV fixture import and any integrator writing rows
# without the extension's own fields do. MariaDB is unaffected because DBAL's
# MariaDBPlatform routes back to AbstractPlatform and keeps the default;
# PostgreSQL and SQLite are unaffected anyway. So the failure is MySQL only,
# and on v12 only.
#
# There is no v12 spelling of "TEXT NOT NULL DEFAULT ''" that MySQL accepts:
# the expression default is exactly the thing v13 added and v12 cannot render.
# The two goals - portable across the four DBMS, and identical to what v13
# derives - therefore genuinely conflict for these two columns, and portability
# wins: they are declared nullable, without a default. That is a definition all
# four platforms render (`TEXT DEFAULT NULL`), and an INSERT omitting the column
# stores NULL instead of failing.
#
# What it costs is that on v13 this explicit definition takes precedence over
# the derived one (Feature #101553), so the column is nullable there too, rather
# than "NOT NULL DEFAULT ''" as on the v13/v14 line. Nothing reads the value as
# anything but a link: the templates guard it with "f:if", which treats NULL and
# '' alike, and DataHandler writes '' rather than NULL whenever the field is
# edited through the TCA, which does not declare it nullable. The schema
# analyzer stays quiet on both versions, because it compares the database
# against this file and not against the TCA.
#
# @todo Drop the nullability together with this file. Once v12 is gone the
#       derived definition applies again and the columns become
#       "TEXT NOT NULL DEFAULT ''" without anything to declare here.
#

#
# Table structure for table "tt_content"
#
CREATE TABLE tt_content (
	tx_theme_link text,
	tx_theme_link_label varchar(255) DEFAULT '' NOT NULL,
	tx_theme_link_variant varchar(255) DEFAULT '' NOT NULL,
	tx_theme_list_items int(11) unsigned DEFAULT '0' NOT NULL
);

#
# Table structure for table "tx_theme_list_item"
#
CREATE TABLE tx_theme_list_item (
	# Written by the "tx_theme_list_items" inline relation on tt_content:
	# foreign_field, foreign_table_field and the foreign_match_fields target.
	uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
	tablename varchar(255) DEFAULT '' NOT NULL,
	fieldname varchar(255) DEFAULT '' NOT NULL,

	header varchar(255) DEFAULT '' NOT NULL,
	text longtext,
	image int(11) unsigned DEFAULT '0' NOT NULL,
	link text,
	link_label varchar(255) DEFAULT '' NOT NULL
);
