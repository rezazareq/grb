<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TRG_DB: Database access layer for tr-grabber relational data
 *
 * Handles all CRUD operations for episodes and links using custom normalized tables.
 * Replaces fragmented term meta and post meta lookups with fast index-backed queries.
 *
 * @since 3.0
 */
class TRG_DB {

	const DB_VERSION = '1.0';
	const VERSION_OPTION = 'trg_db_version';

	/**
	 * Install schema: create tables and set version option
	 *
	 * Runs on plugin activation and on admin_init version check.
	 * Uses dbDelta() for safe, idempotent schema management.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$current_version = get_option( self::VERSION_OPTION );

		if ( $current_version === self::DB_VERSION ) {
			return; // Already installed and up-to-date
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table: trg_episodes
		// Stores normalized episode metadata indexed by (post_id, season, episode)
		$episodes_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}trg_episodes (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			post_id BIGINT UNSIGNED NOT NULL,
			term_id BIGINT UNSIGNED NULL,
			season_number INT UNSIGNED NOT NULL,
			episode_number INT UNSIGNED NOT NULL,
			is_special TINYINT(1) NOT NULL DEFAULT 0,
			name VARCHAR(255) NULL,
			air_date DATE NULL,
			overview LONGTEXT NULL,
			guest_stars VARCHAR(500) NULL,
			still_path VARCHAR(500) NULL,
			still_path_hotlink VARCHAR(2000) NULL,
			poster_path VARCHAR(500) NULL,
			poster_path_hotlink VARCHAR(2000) NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			KEY idx_post_season_episode (post_id, season_number, episode_number),
			KEY idx_term_id (term_id),
			KEY idx_post_special (post_id, is_special)
		) {$charset_collate};";

		dbDelta( $episodes_table );

		// Table: trg_links
		// Stores normalized link data with decoded URLs (no fragmentation, no base64)
		$links_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}trg_links (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			object_id BIGINT UNSIGNED NOT NULL,
			object_type ENUM('post','episode') NOT NULL,
			position INT UNSIGNED NOT NULL DEFAULT 0,
			type TINYINT UNSIGNED NOT NULL DEFAULT 1,
			server_id BIGINT UNSIGNED NULL,
			lang_id BIGINT UNSIGNED NULL,
			quality_id BIGINT UNSIGNED NULL,
			url TEXT NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			KEY idx_object (object_id, object_type, position)
		) {$charset_collate};";

		dbDelta( $links_table );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Get all distinct seasons for a series
	 *
	 * @param int $post_id Series post ID
	 * @return array Array of season objects with properties: season_number, post_id
	 */
	public static function get_seasons( int $post_id ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT season_number, post_id FROM {$wpdb->prefix}trg_episodes
			WHERE post_id = %d AND is_special = 0
			ORDER BY season_number ASC",
			$post_id
		);

		$results = $wpdb->get_results( $query );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get episodes for a series, optionally filtered by season
	 *
	 * @param int      $post_id    Series post ID
	 * @param int|null $season     Optional season number filter; null = all episodes
	 * @param bool     $special    If true, get special episodes (season 0); ignored if $season is set
	 * @return array Array of episode objects with properties: id, post_id, term_id, season_number, episode_number, name, air_date, is_special
	 */
	public static function get_episodes( int $post_id, ?int $season = null, bool $special = false ): array {
		global $wpdb;

		if ( null !== $season ) {
			$query = $wpdb->prepare(
				"SELECT id, post_id, term_id, season_number, episode_number, name, air_date, is_special, overview, guest_stars, still_path, still_path_hotlink, poster_path, poster_path_hotlink
				FROM {$wpdb->prefix}trg_episodes
				WHERE post_id = %d AND season_number = %d
				ORDER BY episode_number ASC",
				$post_id,
				$season
			);
		} elseif ( $special ) {
			$query = $wpdb->prepare(
				"SELECT id, post_id, term_id, season_number, episode_number, name, air_date, is_special, overview, guest_stars, still_path, still_path_hotlink, poster_path, poster_path_hotlink
				FROM {$wpdb->prefix}trg_episodes
				WHERE post_id = %d AND is_special = 1
				ORDER BY episode_number ASC",
				$post_id
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT id, post_id, term_id, season_number, episode_number, name, air_date, is_special, overview, guest_stars, still_path, still_path_hotlink, poster_path, poster_path_hotlink
				FROM {$wpdb->prefix}trg_episodes
				WHERE post_id = %d
				ORDER BY season_number ASC, episode_number ASC",
				$post_id
			);
		}

		$results = $wpdb->get_results( $query );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Upsert (insert or update) an episode
	 *
	 * @param array $data Episode data: post_id, term_id, season_number, episode_number, is_special, name, air_date, overview, guest_stars, still_path, still_path_hotlink, poster_path, poster_path_hotlink
	 * @return int Episode ID (newly inserted or existing)
	 */
	public static function upsert_episode( array $data ): int {
		global $wpdb;

		$post_id = isset( $data['post_id'] ) ? intval( $data['post_id'] ) : 0;
		$term_id = isset( $data['term_id'] ) ? intval( $data['term_id'] ) : null;
		$season_number = isset( $data['season_number'] ) ? intval( $data['season_number'] ) : 0;
		$episode_number = isset( $data['episode_number'] ) ? intval( $data['episode_number'] ) : 0;
		$is_special = isset( $data['is_special'] ) ? intval( $data['is_special'] ) : 0;

		// Try to find existing episode
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}trg_episodes
				WHERE post_id = %d AND season_number = %d AND episode_number = %d AND is_special = %d",
				$post_id,
				$season_number,
				$episode_number,
				$is_special
			)
		);

		$insert_data = array(
			'post_id'             => $post_id,
			'term_id'             => $term_id,
			'season_number'       => $season_number,
			'episode_number'      => $episode_number,
			'is_special'          => $is_special,
			'name'                => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : null,
			'air_date'            => isset( $data['air_date'] ) && ! empty( $data['air_date'] ) ? sanitize_text_field( $data['air_date'] ) : null,
			'overview'            => isset( $data['overview'] ) ? wp_kses_post( $data['overview'] ) : null,
			'guest_stars'         => isset( $data['guest_stars'] ) ? sanitize_text_field( $data['guest_stars'] ) : null,
			'still_path'          => isset( $data['still_path'] ) ? sanitize_text_field( $data['still_path'] ) : null,
			'still_path_hotlink'  => isset( $data['still_path_hotlink'] ) ? esc_url( $data['still_path_hotlink'] ) : null,
			'poster_path'         => isset( $data['poster_path'] ) ? sanitize_text_field( $data['poster_path'] ) : null,
			'poster_path_hotlink' => isset( $data['poster_path_hotlink'] ) ? esc_url( $data['poster_path_hotlink'] ) : null,
		);

		$insert_format = array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $existing ) {
			// Update existing
			$wpdb->update(
				"{$wpdb->prefix}trg_episodes",
				$insert_data,
				array( 'id' => $existing->id ),
				$insert_format,
				array( '%d' )
			);
			return $existing->id;
		} else {
			// Insert new
			$wpdb->insert(
				"{$wpdb->prefix}trg_episodes",
				$insert_data,
				$insert_format
			);
			return intval( $wpdb->insert_id );
		}
	}

	/**
	 * Delete episodes by term_id (when term is deleted)
	 *
	 * @param int $term_id Episode term ID
	 * @return int Number of rows deleted
	 */
	public static function delete_episode_by_term( int $term_id ): int {
		global $wpdb;

		return $wpdb->delete(
			"{$wpdb->prefix}trg_episodes",
			array( 'term_id' => $term_id ),
			array( '%d' )
		);
	}

	/**
	 * Count distinct seasons for a series
	 *
	 * @param int $post_id Series post ID
	 * @return int Count of seasons
	 */
	public static function count_seasons( int $post_id ): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT season_number) FROM {$wpdb->prefix}trg_episodes
				WHERE post_id = %d AND is_special = 0",
				$post_id
			)
		);

		return intval( $count );
	}

	/**
	 * Count episodes for a series, optionally filtered by season
	 *
	 * @param int      $post_id Series post ID
	 * @param int|null $season  Optional season number filter
	 * @return int Count of episodes
	 */
	public static function count_episodes( int $post_id, ?int $season = null ): int {
		global $wpdb;

		if ( null !== $season ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}trg_episodes
					WHERE post_id = %d AND season_number = %d",
					$post_id,
					$season
				)
			);
		} else {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}trg_episodes
					WHERE post_id = %d",
					$post_id
				)
			);
		}

		return intval( $count );
	}

	/**
	 * Get all links for an object (post or episode term)
	 *
	 * @param int    $object_id   Post ID or episode term ID
	 * @param string $object_type 'post' or 'episode'
	 * @return array Array of link objects: id, object_id, object_type, position, type, server_id, lang_id, quality_id, url
	 */
	public static function get_links( int $object_id, string $object_type ): array {
		global $wpdb;

		if ( ! in_array( $object_type, array( 'post', 'episode' ), true ) ) {
			return array();
		}

		$query = $wpdb->prepare(
			"SELECT id, object_id, object_type, position, type, server_id, lang_id, quality_id, url
			FROM {$wpdb->prefix}trg_links
			WHERE object_id = %d AND object_type = %s
			ORDER BY position ASC",
			$object_id,
			$object_type
		);

		$results = $wpdb->get_results( $query );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Replace all links for an object (delete old, insert new) in one prepared transaction
	 *
	 * @param int    $object_id   Post ID or episode term ID
	 * @param string $object_type 'post' or 'episode'
	 * @param array  $links       Array of link arrays: each with type, server_id, lang_id, quality_id, url
	 * @return void
	 */
	public static function replace_links( int $object_id, string $object_type, array $links ): void {
		global $wpdb;

		if ( ! in_array( $object_type, array( 'post', 'episode' ), true ) ) {
			return;
		}

		// Delete old links
		$wpdb->delete(
			"{$wpdb->prefix}trg_links",
			array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
			),
			array( '%d', '%s' )
		);

		if ( empty( $links ) ) {
			return; // No new links to insert
		}

		// Build multi-row insert with prepared values
		$insert_values = array();
		$insert_format = array();
		$position = 0;

		foreach ( $links as $link ) {
			$type       = isset( $link['type'] ) ? intval( $link['type'] ) : 1;
			$server_id  = isset( $link['server_id'] ) ? intval( $link['server_id'] ) : null;
			$lang_id    = isset( $link['lang_id'] ) ? intval( $link['lang_id'] ) : null;
			$quality_id = isset( $link['quality_id'] ) ? intval( $link['quality_id'] ) : null;
			$url        = isset( $link['url'] ) ? esc_url_raw( $link['url'] ) : '';

			if ( empty( $url ) ) {
				continue; // Skip empty URLs
			}

			$insert_values[] = array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'position'    => $position,
				'type'        => $type,
				'server_id'   => $server_id,
				'lang_id'     => $lang_id,
				'quality_id'  => $quality_id,
				'url'         => $url,
			);

			$position++;
		}

		if ( empty( $insert_values ) ) {
			return;
		}

		// Multi-row insert
		foreach ( $insert_values as $row ) {
			$wpdb->insert(
				"{$wpdb->prefix}trg_links",
				$row,
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
			);
		}
	}

	/**
	 * Delete all links for an object
	 *
	 * @param int    $object_id   Post ID or episode term ID
	 * @param string $object_type 'post' or 'episode'
	 * @return int Number of rows deleted
	 */
	public static function delete_links( int $object_id, string $object_type ): int {
		global $wpdb;

		if ( ! in_array( $object_type, array( 'post', 'episode' ), true ) ) {
			return 0;
		}

		return $wpdb->delete(
			"{$wpdb->prefix}trg_links",
			array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
			),
			array( '%d', '%s' )
		);
	}
}
