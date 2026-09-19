#
# TYPO3 v12.4 derives no column from a TCA "select" without an MM table, so
# the column of the icon picker is declared here. The definition is the one
# v13.4 derives for it: a select with an "itemsProcFunc" and no
# "dbFieldLength" ends in the final fallback of DefaultTcaSchema, a nullable
# TEXT. On v13 this file is redundant.
#
CREATE TABLE tt_content (
	tx_iconpickerfixture_icon text
);
