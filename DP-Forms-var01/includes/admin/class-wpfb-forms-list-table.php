<?php
/**
 * WP_List_Table extension for the Forms admin list.
 *
 * Columns: Title, Shortcode (with copy hint), Created, Actions.
 *
 * @package WPFB
 */

namespace WPFB\Admin;

defined( 'ABSPATH' ) || exit;

use WPFB\Repository\Forms_Repository;

// WP_List_Table is not autoloaded — require explicitly.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for the DP-Forms-var01 admin screen.
 */
class Forms_List_Table extends \WP_List_Table {

	/**
	 * Repository instance.
	 *
	 * @var Forms_Repository
	 */
	private Forms_Repository $repo;

	/**
	 * Constructor — sets up the table configuration.
	 */
	public function __construct() {
		parent::__construct( [
			'singular' => __( 'Form', 'wpfb' ),
			'plural'   => __( 'Forms', 'wpfb' ),
			'ajax'     => false,
		] );

		$this->repo = new Forms_Repository();
	}

	/**
	 * Defines the visible columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return [
			'title'     => __( 'Title', 'wpfb' ),
			'shortcode' => __( 'Shortcode', 'wpfb' ),
			'created'   => __( 'Created', 'wpfb' ),
		];
	}

	/**
	 * Defines sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns(): array {
		return [
			'title'   => [ 'title', false ],
			'created' => [ 'created', true ],
		];
	}

	/**
	 * Prepares the data for display.
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$data = $this->repo->paginate( $per_page, $current_page );

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
			'title',
		];
	}

	/**
	 * Renders the Title column with inline row actions.
	 *
	 * @param object $item Form row.
	 * @return string
	 */
	public function column_title( object $item ): string {
		$edit_url   = add_query_arg( [
			'page'    => 'wpfb-form-builder',
			'form_id' => (int) $item->id,
		], admin_url( 'admin.php' ) );

		$delete_url = wp_nonce_url(
			add_query_arg( [
				'page'    => 'wpfb-forms',
				'action'  => 'delete',
				'form_id' => (int) $item->id,
			], admin_url( 'admin.php' ) ),
			'wpfb_delete_form_' . (int) $item->id
		);

		$title = '<strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $item->title ) . '</a></strong>';

		$actions = [
			'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'wpfb' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\''
				. esc_js( __( 'Delete this form and all its submissions? This cannot be undone.', 'wpfb' ) )
				. '\')">'
				. esc_html__( 'Delete', 'wpfb' ) . '</a>',
		];

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Renders the Shortcode column with a copy button.
	 *
	 * @param object $item Form row.
	 * @return string
	 */
	public function column_shortcode( object $item ): string {
		$shortcode = '[wpfb_form id="' . (int) $item->id . '"]';

		return '<code class="wpfb-shortcode-display">' . esc_html( $shortcode ) . '</code>'
			. ' <button type="button" class="button button-small wpfb-copy-shortcode" data-shortcode="'
			. esc_attr( $shortcode ) . '">'
			. esc_html__( 'Copy', 'wpfb' )
			. '</button>';
	}

	/**
	 * Renders the Created column.
	 *
	 * @param object $item Form row.
	 * @return string
	 */
	public function column_created( object $item ): string {
		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				strtotime( $item->created_at )
			)
		);
	}

	/**
	 * Fallback for any unhandled column.
	 *
	 * @param object $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( $item->$column_name ?? '' );
	}

	/**
	 * Displays a message when no forms exist.
	 */
	public function no_items(): void {
		esc_html_e( 'No forms found. Create your first form above.', 'wpfb' );
	}
}
