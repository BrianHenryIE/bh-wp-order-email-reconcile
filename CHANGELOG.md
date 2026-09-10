# Changelog

## Unreleased

* Add: "Add account" on WooCommerce payment gateway settings — `Credentials_Settings_Fields::append_imap_reconcile_fields()` adds a `bh_wp_oer_mailbox_accounts` field (rendered by `Mailbox_Settings_Field`) showing bh-wp-mailboxes' add/edit IMAP account modal button and a "Manage accounts" link to the emails list, whose accounts table handles enable/disable, edit, check now and delete
* Breaking: the email server/username/password settings fields are removed from `Credentials_Settings_Fields` in favour of the modal
* Requires bh-wp-mailboxes with `Admin\Email_Account_Modal`, `API::set_email_account_active()`, the fix for `get_email_accounts()` returning at most ten accounts, and its own (Secrets API) credentials storage; this library no longer stores or supplies credentials
