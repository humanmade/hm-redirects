<?php
/**
 * WP-CLI extension for HM Redirects.
 *
 * Originally taken from Automattic's WPCOM-Legacy-Redirector plugin. ❤️
 * https://github.com/Automattic/WPCOM-Legacy-Redirector
 *
 * For familiarity reasons, these commands work the same as those in that plugin.
 * However our "redirect from" URLs need to be relative to the root of the site,
 * and have a leading slash.
 *
 * @package hm-redirects
 */

namespace HM\Redirects\CLI;

use WP_CLI, WP_CLI_Command;
use HM\Redirects\{Utilities, Handle_Redirects};
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
		$from = $args[0];

		if ( ctype_digit( $args[1] ) ) {
			$to = get_permalink( (int) $args[1] );
			if ( ! $to ) {
				WP_CLI::error( sprintf( 'Destination post %s cannot be found', $args[1] ) );
			}

		} else {
			$to = esc_url_raw( $args[1] );
		}

		$redirect = Utilities\insert_redirect( $from, $to, 301 ) );

		if ( is_wp_error( $redirect ) ) {
			WP_CLI::error( sprintf(
				"Couldn't insert %s -> %s: %s",
				$from,
				$to,
				implode( PHP_EOL, $redirect->get_error_messages() )
			) );
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

			// Convert "redirect to" post IDs to permalinks.
			if ( ctype_digit( $redirect_to ) ) {
				$redirect_to = get_permalink( (int) $redirect_to );

				if ( ! $redirect_to ) {
					$notices[] = array(
						'redirect_from' => $redirect_from,
						'redirect_to'   => $data[ 1 ],
						'message'       => 'Skipped - could not find redirect_to post',
					);

					continue;
				}
			}

			if ( $verbose ) {
				WP_CLI::line( "Adding (CSV) redirect for {$redirect_from} to {$redirect_to}" );
				WP_CLI::line( "-- at $row" );
			} elseif ( 0 === $row % 100 ) {
				WP_CLI::line( "Processing row $row" );
			}

			$redirect = Utilities\insert_redirect(
				$redirect_from,
				esc_url_raw( $redirect_to ),
				301
			);

			// Record any error notices.
			if ( is_wp_error( $redirect ) ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => sprintf(
						'Could not insert redirect: %s',
						implode( PHP_EOL, $redirect->get_error_messages() )
					),
				);

			// Record success notices.
			} elseif ( $verbose ) {
				$notices[] = array(
					'redirect_from' => $redirect_from,
					'redirect_to'   => $redirect_to,
					'message'       => 'Successfully imported',
				);
			}

			if ( 0 === $row % 100 ) {
				Utilities\clear_object_cache();

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

	/**
	 * Bulk import redirects from URLs stored as meta values for posts.
	 *
	 * ## OPTIONS
	 *
	 * [--start=<start-offset>]
	 *
	 * [--end=<end-offset>]
	 *
	 * [--skip_dupes=<skip-dupes>]
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
	 * [--dry_run]
	 *
	 * [--verbose]
	 * : Display notices for sucessful imports and duplicates (if skip_dupes is used)
	 *
	 * @subcommand import-from-meta
	 * @synopsis --meta_key=<name-of-meta-key> [--start=<start-offset>] [--end=<end-offset>] [--skip_dupes=<skip-dupes>] [--format=<format>] [--dry_run] [--verbose]
	 *
	 * @param string[] $args Positional arguments.
	 * @param string[] $assoc_args Associative arguments.
	 */
	function import_from_meta( $args, $assoc_args ) {
		global $wpdb;

		define( 'WP_IMPORTING', true );

		$meta_key   = isset( $assoc_args['meta_key'] ) ? sanitize_key( $assoc_args['meta_key'] ) : 'change-me';
		$offset     = isset( $assoc_args['start'] ) ? intval( $assoc_args['start'] ) : 0;
		$end_offset = isset( $assoc_args['end'] ) ? intval( $assoc_args['end'] ) : 99999999;;

		$skip_dupes = isset( $assoc_args['skip_dupes'] ) ? (bool) intval( $assoc_args['skip_dupes'] ) : false;
		$dry_run    = isset( $assoc_args['dry_run'] );
		$format     = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		$verbose    = isset( $assoc_args['verbose'] );

		if ( $dry_run ) {
			WP_CLI::line( '---Dry Run---' );
		} else {
			WP_CLI::line( '---Live Run--' );
		}

		// Check we have any work to do.
		$total_redirects = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( post_id ) FROM $wpdb->postmeta WHERE meta_key = %s",
				$meta_key
			)
		);

		if ( $total_redirects === 0 ) {
			WP_CLI::error( sprintf( 'No redirects found for meta_key: %s', $meta_key ) );
		}

		$progress_bar = WP_CLI\Utils\make_progress_bar( sprintf( 'Importing %s redirects', number_format( $total_redirects ) ), $total_redirects );
		$notices      = array();

		// Start the import; loop through batches of posts.
		do {
			$redirects = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id as redirect_to_post_id, meta_value as abs_redirect_from FROM $wpdb->postmeta WHERE meta_key = %s ORDER BY post_id ASC LIMIT %d, 1000",
					$meta_key,
					$offset
				)
			);

			$total = count( $redirects );
			$row   = 0;

			foreach ( $redirects as $redirect ) {
				$row++;
				$progress_bar->tick();

				// "Redirect from" parameter must be relative to the root of the site.
				$redirect_from = wp_parse_url( $redirect->abs_redirect_from, PHP_URL_PATH );
				$query_args    = wp_parse_url( $redirect->abs_redirect_from, PHP_URL_QUERY );

				if ( $query_args !== null ) {
					$redirect_from = "?{$query_args}";
				}

				// The "redirect to" value is a post ID. Grab the appropriate URL.
				$redirect_to = get_permalink( (int) $redirect->redirect_to_post_id );
				if ( ! $redirect_to ) {
					$notices[] = array(
						'redirect_from' => $redirect_from,
						'redirect_to'   => $redirect->redirect_to_post_id,
						'message'       => 'Skipped - could not find redirect_to post',
					);

					continue;
				}


				if ( $skip_dupes ) {
					$has_existing_redirect = Handle_Redirects\get_redirect_post( $redirect_from );
					$has_existing_redirect = ( $has_existing_redirect === null ) ? false : true;

					if ( $has_existing_redirect ) {
						if ( $verbose ) {
							$notices[] = array(
								'redirect_from' => $redirect_from,
								'redirect_to'   => $redirect_to,
								'message'       => sprintf( 'Skipped - "redirect from" URL already exists (%s)', $redirect_from ),
							);
						}

						continue;
					}
				}

				// Add redirects.
				if ( $dry_run === false ) {
					$redirect = Utilities\insert_redirect(
						$redirect_from,
						$redirect_to,
						301
					);

					// Record any error notices.
					if ( is_wp_error( $redirect ) ) {
						$notices[] = array(
							'redirect_from' => $redirect_from,
							'redirect_to'   => $redirect_to,
							'message'       => sprintf(
								'Could not insert redirect: %s',
								implode( PHP_EOL, $redirect->get_error_messages() )
							),
						);

					// Record success notices.
					} elseif ( $verbose ) {
						$notices[] = array(
							'redirect_from' => $redirect_from,
							'redirect_to'   => $redirect_to,
							'message'       => 'Successfully imported',
						);
					}
				}

				if ( 0 === $row % 100 ) {
					Utilities\clear_object_cache();

					// Throttle writes.
					sleep( 1 );
				}
			}

			$offset += 1000;
		} while( $total >= 1000 && $offset < $end_offset );

		$progress_bar->finish();

		if ( count( $notices ) > 0 ) {
			WP_CLI\Utils\format_items( $format, $notices, array( 'redirect_from', 'redirect_to', 'message' ) );
		} else {
			echo WP_CLI::colorize( "%GAll of your redirects have been imported. Nice work!%n " );
		}
	}
}
