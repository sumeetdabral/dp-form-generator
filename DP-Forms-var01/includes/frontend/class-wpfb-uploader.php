<?php
/**
 * Handles file uploads for form submissions.
 *
 * Uses wp_handle_upload() and wp_insert_attachment() so every uploaded file
 * becomes a proper Media Library entry with a WP attachment ID.
 *
 * Security enforced here:
 *  - Mime allowlist from plugin settings (passed to wp_handle_upload 'mimes' arg).
 *  - Size cap from settings.
 *  - Double-extension rejection (e.g. foo.php.jpg).
 *  - wp_check_filetype_and_ext verification after upload.
 *
 * @package WPFB
 */

namespace WPFB\Frontend;

defined( 'ABSPATH' ) || exit;

use WPFB\Support\Options;
use WPFB\Support\Logger;

/**
 * Processes file field uploads and inserts them into the Media Library.
 */
class Uploader {

	/**
	 * Settings snapshot.
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Constructor — loads settings once.
	 */
	public function __construct() {
		$this->settings = Options::get();
	}

	/**
	 * Processes a single uploaded file from $_FILES.
	 *
	 * @param string $file_key The $_FILES key (usually the field id).
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function handle( string $file_key ) {
		if ( ! isset( $_FILES[ $file_key ] ) ) {
			return new \WP_Error( 'no_file', __( 'No file was uploaded.', 'wpfb' ) );
		}

		$file = $_FILES[ $file_key ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Reject double extensions like 'foo.php.jpg' before touching the file.
		$original_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( $this->has_double_extension( $original_name ) ) {
			return new \WP_Error( 'double_extension', __( 'Files with double extensions are not allowed.', 'wpfb' ) );
		}

		// Enforce max upload size from settings (bytes).
		$max_bytes = (int) $this->settings['max_upload_mb'] * MB_IN_BYTES;
		if ( isset( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
			return new \WP_Error( 'file_too_large', sprintf(
				/* translators: %d: max size in MB */
				__( 'File exceeds the maximum allowed size of %d MB.', 'wpfb' ),
				(int) $this->settings['max_upload_mb']
			) );
		}

		// Build the allowed mimes array for wp_handle_upload.
		$allowed_mimes = $this->build_mime_list();

		// wp_handle_upload needs to be in an admin context for the helper.
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		$overrides = [
			'test_form' => false, // We already verified the nonce ourselves.
			'mimes'     => $allowed_mimes,
		];

		$uploaded = wp_handle_upload( $file, $overrides );

		if ( isset( $uploaded['error'] ) ) {
			Logger::warning( 'File upload error.', [ 'error' => $uploaded['error'], 'file_key' => $file_key ] );
			return new \WP_Error( 'upload_error', $uploaded['error'] );
		}

		// Double-check the uploaded file's actual type vs claimed type.
		$check = wp_check_filetype_and_ext( $uploaded['file'], $uploaded['url'] );
		if ( ! $check['ext'] || ! $check['type'] ) {
			// File type is not allowed; clean up.
			wp_delete_file( $uploaded['file'] );
			return new \WP_Error( 'invalid_type', __( 'The uploaded file type is not allowed.', 'wpfb' ) );
		}

		// Ensure the detected type is in our allowlist.
		if ( ! in_array( $check['ext'], (array) $this->settings['allowed_mime_types'], true ) ) {
			wp_delete_file( $uploaded['file'] );
			return new \WP_Error( 'not_in_allowlist', __( 'The uploaded file type is not in the allowed list.', 'wpfb' ) );
		}

		// Insert as WP attachment.
		$attachment = [
			'post_mime_type' => $uploaded['type'],
			'post_title'     => sanitize_file_name( $original_name ),
			'post_content'   => '',
			'post_status'    => 'private', // Private — only visible to admins.
		];

		$attachment_id = wp_insert_attachment( $attachment, $uploaded['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata (thumbnails etc.) — required for proper Media Library entries.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Detects a double extension pattern like 'filename.php.jpg'.
	 *
	 * A simple heuristic: if the name (minus the final extension) still contains
	 * a dot, and the second-to-last segment is a known executable extension,
	 * reject it.
	 *
	 * @param string $filename Sanitized filename.
	 * @return bool
	 */
	private function has_double_extension( string $filename ): bool {
		$dangerous = [ 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'shtml', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'exe', 'bat', 'sh', 'csh', 'ksh', 'bash', 'cmd', 'com', 'htaccess', 'htpasswd' ];

		$parts = explode( '.', $filename );
		if ( count( $parts ) <= 2 ) {
			// Only one extension — no double extension possible.
			return false;
		}

		// If any non-final segment matches a dangerous extension, reject.
		array_pop( $parts ); // Remove the final (visible) extension.
		array_shift( $parts ); // Remove the base name.

		foreach ( $parts as $segment ) {
			if ( in_array( strtolower( $segment ), $dangerous, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the mime map accepted by wp_handle_upload from the allowed extensions list.
	 *
	 * @return array Extension → mime type.
	 */
	private function build_mime_list(): array {
		$wp_mimes  = wp_get_mime_types();
		$allowed   = (array) $this->settings['allowed_mime_types'];
		$result    = [];

		foreach ( $allowed as $ext ) {
			$ext = strtolower( trim( $ext ) );
			foreach ( $wp_mimes as $key => $mime ) {
				// WP mime keys can be pipe-delimited like 'jpg|jpeg|jpe'.
				$extensions = explode( '|', $key );
				if ( in_array( $ext, $extensions, true ) ) {
					$result[ $key ] = $mime;
					break;
				}
			}
		}

		return $result;
	}
}
