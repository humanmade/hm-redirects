<?php
/**
 * WP-CLI extension for HM Redirects.
 *
 * Originally taken from Automattic's WPCOM-Legacy-Redirector plugin. ❤️
 * https://github.com/Automattic/WPCOM-Legacy-Redirector
 *
 * For familarity reasons, these commands work identically.
 * `import-from-meta` is not yet supported.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\CLI;

use WP_CLI, WP_CLI_Command;
use WP_CLI\Utils;
use HM\Redirects\Utilities;
use const HM\Redirects\Post_Type\SLUG as REDIRECTS_POST_TYPE;

/**
 * Handles redirects in a scalable manner.
 */
class Commands extends WP_CLI_Command {

	/**
	 * Find domains redirected to.
	 *
	 * This is useful to populate WordPress' `allowed_redirect_hosts` filter.
	 *
	 * @subcommand find-domains
	 *
	 * @param string[] $args Not used.
	 * @param string[] $assoc_args Not used.
	 */
	public function find_domains( array $args, array $assoc_args ) {
		global $wpdb;

		$domains        = array();
		$paged          = 0;
		$posts_per_page = 500;

		$total_redirects = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( ID ) FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s",
				REDIRECTS_POST_TYPE,
				'http%'
			)
		);

		$progress_bar = WP_CLI\Utils\make_progress_bar( 'Finding domains', $total_redirects );

		do {
			$redirect_urls = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_excerpt FROM $wpdb->posts WHERE post_type = %s AND post_excerpt LIKE %s ORDER BY ID ASC LIMIT %d, %d",
					REDIRECTS_POST_TYPE,
					'http%',
					( $paged * $posts_per_page ),
					$posts_per_page
				)
			);

			foreach ( $redirect_urls as $redirect_url ) {
				$progress_bar->tick();

				if ( ! empty( $redirect_url ) ) {
					$redirect_host = parse_url( $redirect_url, PHP_URL_HOST );
					if ( $redirect_host ) {
						$domains[] = $redirect_host;
					}
				}
			}

			// Throttle.
			sleep( 1 );
			$paged++;
		} while ( count( $redirect_urls ) );

		$progress_bar->finish();

		$domains = array_unique( $domains );
		WP_CLI::line( sprintf( 'Found %s unique outbound domains', number_format( count( $domains ) ) ) );

		foreach ( $domains as $domain ) {
			WP_CLI::line( $domain );
		}
	}

	/**
	 * Insert a single redirect
	 *
	 * from_url_relative must be relative to the root of the site.
	 *
	 * @subcommand insert-redirect
	 * @synopsis <from_url_relative> <to_url_absolute>
	 *
	 * @param string[] $args Positional arguments.
	 * @param string[] $assoc_args Not used.
	 */
	public function insert_redirect( $args, $assoc_args ) {
		$from        = $args[0];
		$status_code = 301;

		if ( ctype_digit( $args[1] ) ) {
			$to = get_permalink( (int) $args[1] );
			if ( ! $to ) {
				WP_CLI::error( sprintf( 'Destination post %s cannot be found', $args[1] ) );
			}

		} else {
			$to = esc_url_raw( $args[1] );
		}

		$inserted = Utilities\insert_redirect( compact( 'from', 'to', 'status_code' ) );

		if ( ! $inserted ) {
			WP_CLI::error( sprintf( "Couldn't insert %s -> %s", $from, $to ) );
		}

		WP_CLI::success( sprintf( "Inserted %s -> %s", $from, $to ) );
	}

	/**
	 * Bulk import redirects from a CSV file matching the following structure:
	 *
	 * redirect_from_path,(redirect_to_post_id|redirect_to_path|redirect_to_url)
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: csv
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * [--verbose]
	 *
	 * @subcommand import-from-csv
	 * @synopsis --csv=<path-to-csv> [--format=<format>] [--verbose]
	 *
	 * @param string[] $args Positional arguments.
	 * @param string[] $assoc_args Associative arguments.
	 */
	public function import_from_csv( $args, $assoc_args ) {
		define( 'WP_IMPORTING', true );

		$csv     = trim( Utils\get_flag_value( $assoc_args, 'csv' ) );
		$format  = Utils\get_flag_value( $assoc_args, 'format' );
		$verbose = isset( $assoc_args['verbose'] );

		if ( ! $csv || ! file_exists( $csv ) ) {
			WP_CLI::error( "Invalid 'csv' file" );
		}

		if ( ! $verbose ) {
			WP_CLI::line( 'Processing...' );
		}

		$handle = fopen( $csv, 'r' );
		if ( $handle === false ) {
			WP_CLI::error( "Cannot open 'csv' file" );
		}

		$notices = array();
		$row     = 0;

		while ( ( $data = fgetcsv( $handle, 2000, "," ) ) !== false ) {
			$row++;
			$redirect_from = $data[ 0 ];
			$redirect_to   = $data[ 1 ];
			$skip_redirect = false;

			// Convert "redirect to" post IDs to permalinks.
			if ( ctype_digit( $redirect_to ) ) {
				$redirect_to = get_permalink( (int) $redirect_to );

				if ( ! $redirect_to ) {
					$redirect_to   = $data[ 1 ];
					$skip_redirect = true;
				}
			}

			if ( $verbose ) {
				WP_CLI::line( "Adding (CSV) redirect for {$redirect_from} to {$redirect_to}" );
				WP_CLI::line( "-- at $row" );
			} elseif ( 0 == $row % 100 ) {
				WP_CLI::line( "Processing row $row" );
			}

			if ( ! $skip_redirect ) {
				$inserted = Utilities\insert_redirect( [
					'from'        => $redirect_from,
					'to'          => $redirect_to,
					'status_code' => 301,
				] );
			}

			// Record any error notices.
			if ( $skip_redirect || ! $inserted ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => 'Could not insert redirect',
				);

			// Record success notices.
			} elseif ( $verbose ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => 'Successfully imported',
				);
			}

			if ( 0 == $row % 100 ) {
				Utilities\stop_the_insanity();

				// Throttle writes.
				sleep( 1 );
			}
		}

		fclose( $handle );

		if ( count( $notices ) > 0 ) {
			WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
		} else {
			echo WP_CLI::colorize( "%GAll of your redirects have been imported. Nice work!%n " );
		}
	}
}
