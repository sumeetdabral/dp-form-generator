<?php
/**
 * CRUD repository for the wpfb_forms table.
 *
 * All SQL goes through $wpdb->prepare(). No controller or view touches
 * $wpdb directly.
 *
 * @package WPFB
 */

namespace WPFB\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Provides find/all/paginate/insert/update/delete for forms.
 */
class Forms_Repository {

	/**
	 * Fully-qualified table name (set once from $wpdb->prefix).
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor — resolves the table name once.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpfb_forms';
	}

	/**
	 * Fetches a single form row by primary key.
	 *
	 * @param int $id Form ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id ) );

		return $row ?: null;
	}

	/**
	 * Returns all forms ordered by creation date descending.
	 *
	 * @return object[]
	 */
	public function all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM `{$this->table}` ORDER BY created_at DESC" );

		return $rows ?: [];
	}

	/**
	 * Returns a paginated slice of forms.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     1-based page number.
	 * @return array { rows: object[], total: int }
	 */
	public function paginate( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table}`" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$this->table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Inserts a new form row and returns the new ID (or false on failure).
	 *
	 * @param array $data Sanitized form data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true ); // UTC.

		$result = $wpdb->insert(
			$this->table,
			[
				'title'            => sanitize_text_field( $data['title'] ),
				'fields_json'      => wp_json_encode( $data['fields'] ),
				'admin_emails'     => sanitize_textarea_field( $data['admin_emails'] ?? '' ),
				'mail_subject'     => sanitize_text_field( $data['mail_subject'] ?? '' ),
				'attach_files'     => (int) ( $data['attach_files'] ?? 1 ),
				'form_html_before' => $data['form_html_before'] ?? null,
				'form_html_after'  => $data['form_html_after'] ?? null,
				'form_css_class'   => $data['form_css_class'] ?? '',
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates an existing form row. Returns true on success.
	 *
	 * @param int   $id   Form ID.
	 * @param array $data Sanitized form data.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table,
			[
				'title'            => sanitize_text_field( $data['title'] ),
				'fields_json'      => wp_json_encode( $data['fields'] ),
				'admin_emails'     => sanitize_textarea_field( $data['admin_emails'] ?? '' ),
				'mail_subject'     => sanitize_text_field( $data['mail_subject'] ?? '' ),
				'attach_files'     => (int) ( $data['attach_files'] ?? 1 ),
				'form_html_before' => $data['form_html_before'] ?? null,
				'form_html_after'  => $data['form_html_after'] ?? null,
				'form_css_class'   => $data['form_css_class'] ?? '',
				'updated_at'       => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Deletes a form by ID. Returns true on success.
	 *
	 * @param int $id Form ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table,
			[ 'id' => $id ],
			[ '%d' ]
		);

		return false !== $result;
	}
}
