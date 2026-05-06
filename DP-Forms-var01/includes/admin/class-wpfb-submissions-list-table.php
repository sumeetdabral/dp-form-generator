<?php
/**
 * WP_List_Table extension for the Submissions admin list.
 *
 * Columns: ID, Form, Submitted At, Mail Status, Actions.
 * Supports per-form filtering via a dropdown.
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Submissions_Repository;
use WPFB\Repository\Forms_Repository;

// WP_List_Table is not autoloaded — require explicitly.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for the Submissions admin screen.
 */
class Submissions_List_Table extends \WP_List_Table {

	/**
	 * Submissions repository.
	 *
	 * @var Submissions_Repository
	 */
	private Submissions_Repository $sub_repo;

	/**
	 * Forms repository (used for the filter dropdown).
	 *
	 * @var Forms_Repository
	 */
	private Forms_Repository $forms_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Submission', 'wpfb' ),
			'plural'   => __( 'Submissions', 'wpfb' ),
			'ajax'     => false,
		] );

		$this->sub_repo   = new Submissions_Repository();
		$this->forms_repo = new Forms_Repository();
	}

	/**
	 * Defines the visible columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return [
			'id'           => __( 'ID', 'wpfb' ),
			'form'         => __( 'Form', 'wpfb' ),
			'submitted_at' => __( 'Submitted At', 'wpfb' ),
			'mail_status'  => __( 'Mail Status', 'wpfb' ),
		];
	}

	/**
	 * Defines sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return [
			'id'           => [ 'id', true ],
			'submitted_at' => [ 'submitted_at', true ],
		];
	}

	/**
	 * Renders the filter form above the table (per-form dropdown).
	 *
	 * @param string $which Top or bottom position.
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$forms      = $this->forms_repo->all();
		$current_id = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="alignleft actions">';
		echo '<select name="form_id">';
		echo '<option value="0">' . esc_html__( '— All Forms —', 'wpfb' ) . '</option>';
		foreach ( $forms as $form ) {
			echo '<option value="' . esc_attr( $form->id ) . '"'
				. selected( $current_id, (int) $form->id, false )
				. '>' . esc_html( $form->title ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'wpfb' ), 'secondary', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Prepares items for display.
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$form_id      = isset( $_GET['form_id'] ) ? (int) wp_unslash( $_GET['form_id'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 0 === $form_id ) {
			$form_id = null;
		}

		$data = $this->sub_repo->paginate( $per_page, $current_page, $form_id );

		$this->items = $data['rows'];

		$this->set_pagination_args( [
			'total_items' => $data['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $data['total'] / $per_page ),
		] );

		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
			'id',
		];
	}

	/**
	 * Renders the ID column with view and resend row actions.
	 *
	 * @param object $item Submission row.
	 * @return string
	 */
	public function column_id( object $item ): string {
		$view_url = add_query_arg( [
			'page'          => 'wpfb-submissions',
			'action'        => 'view',
			'submission_id' => (int) $item->id,
		], admin_url( 'admin.php' ) );

		$resend_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpfb_resend_mail&submission_id=' . (int) $item->id ),
			'wpfb_resend_mail_' . (int) $item->id
		);

		$actions = [
			'view'   => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'wpfb' ) . '</a>',
			'resend' => '<a href="' . esc_url( $resend_url ) . '">' . esc_html__( 'Resend Mail', 'wpfb' ) . '</a>',
		];

		return esc_html( $item->id ) . $this->row_actions( $actions );
	}

	/**
	 * Renders the Form column — shows form title.
	 *
	 * @param object $item Submission row.
	 * @return string
	 */
	public function column_form( object $item ): string {
		$form = $this->forms_repo->find( (int) $item->form_id );
		return $form ? esc_html( $form->title ) : esc_html( sprintf(
			/* translators: %d: form ID */
			__( 'Form #%d (deleted)', 'wpfb' ),
			(int) $item->form_id
		) );
	}

	/**
	 * Renders the Submitted At column.
	 *
	 * @param object $item Submission row.
	 * @return string
	 */
	public function column_submitted_at( object $item ): string {
		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $item->created_at )
			)
		);
	}

	/**
	 * Renders the Mail Status column with a colored badge.
	 *
	 * @param object $item Submission row.
	 * @return string
	 */
	public function column_mail_status( object $item ): string {
		$status = esc_html( $item->mail_status );
		$class  = 'wpfb-status-' . sanitize_html_class( $item->mail_status );

		return '<span class="wpfb-mail-status ' . esc_attr( $class ) . '">' . $status . '</span>';
	}

	/**
	 * Fallback for unhandled columns.
	 *
	 * @param object $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	/**
	 * Displays a message when no submissions exist.
	 */
	public function no_items(): void {
		esc_html_e( 'No submissions found.', 'wpfb' );
	}
}
