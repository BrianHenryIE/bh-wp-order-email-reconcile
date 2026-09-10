<?php
/**
 * WooCommerce Settings API field type rendering an "Add account" button that opens bh-wp-mailboxes'
 * add/edit IMAP account modal, and a link to the emails list where the accounts table lives.
 *
 * The modal is bh-wp-mailboxes' own (`Email_Account_Modal`): it is printed in the admin footer of
 * the gateway settings screen so its form and password input stay outside the settings form. Saving
 * goes through the mailboxes library, which stores the account and its credentials (encrypted, via
 * the WordPress Secrets API). Enabling/disabling, editing, checking and deleting accounts happen in
 * the accounts table on the emails list screen, which the field links to.
 *
 * @see Credentials_Settings_Fields Adds the field to a gateway's form fields.
 *
 * @package brianhenryie/bh-wp-order-email-reconcile
 */

declare(strict_types=1);

namespace BrianHenryIE\WP_Order_Email_Reconcile\Integrations\WooCommerce;

use BrianHenryIE\WP_Mailboxes\Admin\Email_Account_Modal;
use BrianHenryIE\WP_Order_Email_Reconcile\Email_Reconcile_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Registers the `bh_wp_oer_mailbox_accounts` field type and prints the mailboxes modal with it.
 */
class Mailbox_Settings_Field {

	use LoggerAwareTrait;

	/**
	 * The WooCommerce Settings API field `type` this class renders.
	 *
	 * @see \WC_Settings_API::generate_settings_html()
	 */
	const FIELD_TYPE = 'bh_wp_oer_mailbox_accounts';

	/**
	 * Set when the field has been rendered on the current request, so the modal is printed once.
	 *
	 * @var bool
	 */
	protected bool $rendered = false;

	/**
	 * Constructor.
	 *
	 * @param Email_Account_Modal                $modal    The bh-wp-mailboxes add/edit account modal.
	 * @param Email_Reconcile_Settings_Interface $settings Provides the emails post type (for the accounts link).
	 * @param LoggerInterface                    $logger   PSR-3 logger.
	 */
	public function __construct(
		protected Email_Account_Modal $modal,
		protected Email_Reconcile_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Register the field type and the footer hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_generate_' . self::FIELD_TYPE . '_html', array( $this, 'generate_html' ), 10, 3 );
		add_action( 'admin_footer', array( $this, 'print_modal' ) );
	}

	/**
	 * The admin URL of the emails list screen, where the accounts table is.
	 */
	public function get_accounts_url(): string {
		return admin_url( 'edit.php?post_type=' . $this->settings->get_emails_cpt_underscored_20() );
	}

	/**
	 * Render the settings row: an "Add account" button and a link to manage the accounts.
	 *
	 * Also enqueues the modal's script and style.
	 *
	 * @hooked woocommerce_generate_bh_wp_oer_mailbox_accounts_html
	 *
	 * @param string               $html The (empty) markup being generated.
	 * @param string               $key  The field key.
	 * @param array<string, mixed> $data The field definition.
	 */
	public function generate_html( $html, $key, $data ): string {
		$this->rendered = true;
		$this->modal->enqueue_assets();

		$title       = isset( $data['title'] ) && is_string( $data['title'] ) ? $data['title'] : __( 'Payment mailbox', 'bh-wp-order-email-reconcile' );
		$description = isset( $data['description'] ) && is_string( $data['description'] ) ? $data['description'] : '';

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<span class="bh-wp-oer-mailbox-field__title"><?php echo esc_html( $title ); ?></span>
			</th>
			<td class="forminp" data-field-key="<?php echo esc_attr( (string) $key ); ?>">
				<?php $this->modal->print_add_button(); ?>
				<a class="button-link bh-wp-oer-manage-accounts" href="<?php echo esc_url( $this->get_accounts_url() ); ?>">
					<?php esc_html_e( 'Manage accounts', 'bh-wp-order-email-reconcile' ); ?>
				</a>
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Print the modal once, after the settings form, on pages where the field was rendered.
	 *
	 * @hooked admin_footer
	 *
	 * @return void
	 */
	public function print_modal(): void {
		if ( ! $this->rendered ) {
			return;
		}
		$this->modal->print_modal();
	}
}
