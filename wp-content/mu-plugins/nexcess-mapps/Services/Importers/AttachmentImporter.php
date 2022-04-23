<?php

namespace Nexcess\MAPPS\Services\Importers;

use Nexcess\MAPPS\Exceptions\IngestionException;

class AttachmentImporter {

	/**
	 * Import attachments from the given URL.
	 *
	 * @param string  $url  The remote URL for the attachment.
	 * @param mixed[] $args Optional. Additional arguments to pass to wp_insert_attachment().
	 *                      Default is empty.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\IngestionException If the attachment cannot be imported.
	 *
	 * @return int The ID of the newly-imported attachment.
	 */
	public function import( $url, array $args = [] ) {
		$this->sideloadIncludes();

		$args = wp_parse_args( $args, [
			'alt'     => null,
			'post_id' => 0,
			'title'   => null,
		] );

		$attachment_id = media_sideload_image( $url, $args['post_id'], $args['title'], 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new IngestionException(
				sprintf( 'Unable to import attachment from %s: %s', $url, $attachment_id->get_error_message() )
			);
		}

		// If it's not an error, make sure we're handling an integer.
		$attachment_id = (int) $attachment_id;

		// Explicitly set the alt text, if it was provided.
		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $args['alt'] );
		}

		return $attachment_id;
	}

	/**
	 * Load the required files for sideloading media.
	 *
	 * @link https://developer.wordpress.org/reference/functions/media_sideload_image/#more-information
	 */
	protected function sideloadIncludes() {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
}
