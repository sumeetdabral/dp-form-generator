<?php
/**
 * CRUD repository for the wpfb_submissions table.
 *
 * All SQL goes through $wpdb->prepare(). No controller or view touches
 * $wpdb directly.
 *
 * @package WPFB
 */

namespace WPFB\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Provides find/all/paginate/insert/update for submissions.
 */
class Submissions_Repository {

	/**
	 * Fully-qualified table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor — resolves the table name once.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpfb_submissions';
	}

	/**
	 * Fetches a single submission by primary key.
	 *
	 * @param int $id Submission ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d LIMIT 1", $id ) );

		return $row ?: null;
	}

	/**
	 * Returns a paginated slice of submissions, optionally filtered by form.
	 *
	 * @param int      $per_page Items per page.
	 * @param int      $page     1-based page number.
	 * @param int|null $form_id  Optional form filter.
	 * @return array { rows: object[], total: int }
	 */
	public function paginate( int $per_page = 20, int $page = 1, ?int $form_id = null ): array {
		global $wpdb;

		$offset = ( max( 1, $page ) - 1 ) * $per_page;

		if ( null !== $form_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM `{$this->table}` WHERE form_id = %d",
				$form_id
			) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM `{$this->table}` WHERE form_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$form_id,
				$per_page,
				$offset
			) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$this->table}`" );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM `{$this->table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			) );
		}

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Inserts a new submission row and returns the new ID (or false on failure).
	 *
	 * @param array $data Submission data.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$result = $wpdb->insert(
			$this->table,
			[
				'form_id'        => (int) $data['form_id'],
				'payload_json'   => wp_json_encode( $data['payload'] ),
				'attachment_ids' => implode( ',', array_map( 'intval', (array) ( $data['attachment_ids'] ?? [] ) ) ),
				'submitter_ip'   => sanitize_text_field( $data['submitter_ip'] ?? '' ),
				'user_agent'     => substr( sanitize_text_field( $data['user_agent'] ?? '' ), 0, 255 ),
				'mail_status'    => 'pending',
				'mail_error'     => null,
				'created_at'     => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', null, '%s' ]
		);

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Updates the mail_status and optional mail_error for a submission.
	 *
	 * @param int    $id         Submission ID.
	 * @param string $status     'sent', 'failed', or 'pending'.
	 * @param string $mail_error Error message string, or empty on success.
	 * @return bool
	 */
	public function update_mail_status( int $id, string $status, string $mail_error = '' ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table,
			[
				'mail_status' => $status,
				'mail_error'  => '' !== $mail_error ? $mail_error : null,
			],
			[ 'id' => $id ],
			[ '%s', null ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Returns all submissions for a given form (used by CSV export — no pagination).
	 *
	 * @param int $form_id Form ID.
	 * @return object[]
	 */
	public function all_for_form( int $form_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM `{$this->table}` WHERE form_id = %d ORDER BY created_at DESC",
			$form_id
		) );

		return $rows ?: [];
	}

	/**
	 * Returns all submissions (used by full CSV export — no pagination).
	 *
	 * @return object[]
	 */
	public function all(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM `{$this->table}` ORDER BY created_at DESC" );

		return $rows ?: [];
	}
}
