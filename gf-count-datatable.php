<?php
/**
 * Plugin Name:       Gravity Forms: Count Datatable
 * Plugin URI:        https://connections-pro.com
 * Description:       Adds shortcode to display form submission count by user.
 * Version:           1.0
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com
 * Contributor:       helpdesk@connections-pro.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       gf_count_datatable
 * Domain Path:       /languages
 *
 * @link              https://connections-pro.com
 * @since             1.0
 * @package           gf_count_datatable
 *
 * @wordpress-plugin
 */

namespace Easy_Plugins\Gravity_Forms_Count_Datatable;

use DateTime;
use GFAPI;
use GFForms;
use WP_User;
use WP_User_Query;

add_action(
	'gform_loaded',
	function() {

		//if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		//	return;
		//}
		//
		//GFForms::include_addon_framework();

		Gravity_Forms_Count_Datatable::init();
	},
	5
);

class Gravity_Forms_Count_Datatable {

	const VERSION = '1.0';

	/**
	 * @var self Stores the instance of this class.
	 *
	 * @since  1.0
	 */
	private static $instance;

	/**
	 * @var string The absolute path this this file.
	 *
	 * @since  1.0
	 */
	private $file = '';

	/**
	 * @var string The URL to the plugin's folder.
	 *
	 * @since  1.0
	 */
	private $url = '';

	/**
	 * @var string The absolute path to this plugin's folder.
	 *
	 * @since  1.0
	 */
	private $path = '';

	/**
	 * @var string The basename of the plugin.
	 *
	 * @since 1.0
	 */
	private $basename = '';

	public function __construct() {}

	/**
	 * The main plugin instance.
	 *
	 * @since  1.0
	 *
	 * @return self
	 */
	public static function init() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof self ) ) {

			self::$instance = $self = new self;

			$self->file     = __FILE__;
			$self->url      = plugin_dir_url( $self->file );
			$self->path     = plugin_dir_path( $self->file );
			$self->basename = plugin_basename( $self->file );

			$self->loadDependencies();
			$self->hooks();
		}

		return self::$instance;
	}

	/**
	 * @return self
	 */
	public static function instance() {

		return self::init();
	}

	private function loadDependencies() {

		require_once( __DIR__ . '/includes/class.gravity-forms-search-criteria.php' );
	}

	private function hooks() {

		add_shortcode( 'gf_count_entries', array( $this, 'countEntries' ) );
		add_shortcode( 'gf_count_datatable', array( $this, 'countDatatable' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'registerScripts' ), -1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybeEnqueueCSS' ), 1 );
	}

	/**
	 * Get the absolute directory path (with trailing slash) for the plugin.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function pluginPath() {

		return $this->path;
	}

	/**
	 * @since 1.0
	 *
	 * @return string
	 */
	public function getBaseURL() {

		return $this->url;
	}

	/**
	 * Callback for the `wp_enqueue_scripts` action.
	 *
	 * @since 1.0
	 */
	public function registerScripts() {

		$path = Gravity_Forms_Count_Datatable()->pluginPath();
		$url  = Gravity_Forms_Count_Datatable()->getBaseURL();

		// If SCRIPT_DEBUG is set and TRUE load the non-minified JS files, otherwise, load the minified files.
		$min = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		//$min = '';

		wp_register_style(
			'materialize',
			"{$url}includes/vendor/materialize/css/materialize{$min}.css",
			array(),
			'1.0.0-' . filemtime( "{$path}includes/vendor/materialize/css/materialize{$min}.css" )
		);

		wp_register_script(
			'Easy_Plugins\Gravity_Forms_Count_Datatable\JavaScript\Vendor\Materialize',
			"{$url}includes/vendor/materialize/js/materialize{$min}.js",
			array(),
			'1.0.0-' . filemtime( "{$path}includes/vendor/materialize/js/materialize{$min}.js" ),
			TRUE
		);

		wp_register_script(
			'Easy_Plugins\Gravity_Forms_Count_Datatable\JavaScript\Frontend',
			"{$url}assets/js/frontend.js",
			array(
				'jquery',
				'Easy_Plugins\Gravity_Forms_Count_Datatable\JavaScript\Vendor\Materialize',
			),
			self::VERSION . '-' . filemtime( "{$path}assets/js/frontend.js" ),
			TRUE
		);
	}

	/**
	 * Callback for the `wp_enqueue_scripts` action.
	 *
	 * @since 1.0
	 */
	public function maybeEnqueueCSS() {

		global $post, $wpdb;

		if ( ! $post instanceof \WP_Post ) {

			return;
		}

		$needle       = '[gf_count_datatable';
		$hasShortcode = false;

		if ( false !== strpos( $post->post_content, $needle ) ) {

			$hasShortcode = true;

		} else {

			$result = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT count(*) FROM {$wpdb->postmeta} WHERE post_id = %d and meta_value LIKE '%{$needle}%'",
					$post->ID
				)
			);

			$hasShortcode = ! empty( $result );
		}

		if ( $hasShortcode ) {

			wp_enqueue_style( 'materialize' );
		}
	}

	/**
	 * @return array
	 */
	private function getShortcodeDefaults() {

		return array(
			'form_id'          => '0',
			'addend'           => 0,
			'factor'           => 1,
			'sum'              => null,
			'format'           => false,
			'decimals'         => 2,
			'dec_point'        => '.',
			'thousands_sep'    => ',',
			'page_size'        => 10000, // Use page_size='20000' or higher in shortcode for more entries to count
			'search'           => false,
			'thead'            => 'Name|Count',
			'tfoot'            => '|%sum%'
			//'number_field'     => false,
		);
	}

	/**
	 * Parse shortcode attributes.
	 *
	 * @param array  $untrusted
	 * @param string $tag
	 *
	 * @return array
	 */
	private function shortcodeAtts( array $untrusted, string $tag = '' ) {

		$atts = shortcode_atts( $this->getShortcodeDefaults(), $untrusted, $tag );

		if ( ! is_null( $atts['sum'] ) ) {

			$strings = explode( '|', $atts['sum'] );

			$atts['sum'] = array();

			foreach ( $strings as $string ) {

				$string = trim( trim( $string ), '{}' );

				array_push( $atts['sum'], shortcode_parse_atts( $string ) );
			}
		}

		$atts['factor'] = filter_var( $atts['factor'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
		$atts['addend'] = filter_var( $atts['addend'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );

		$atts['format'] = filter_var( $atts['format'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		$atts['format'] = is_null( $atts['format'] ) ? false : $atts['format'];

		$atts['decimals']  = filter_var( $atts['decimals'], FILTER_SANITIZE_NUMBER_INT );
		$atts['page_size'] = filter_var( $atts['page_size'], FILTER_SANITIZE_NUMBER_INT );

		if ( 'gf_count_datatable' === $tag ) {

			$thead = explode( '|', $atts['thead'] );
			$thead = array_map( 'trim', $thead );
			$thead = array_map( 'wp_kses_data', $thead );
			$atts['thead'] = array_pad( $thead, 2, '' );

			$tfoot = explode( '|', $atts['tfoot'] );
			$tfoot = array_map( 'trim', $tfoot );
			$tfoot = array_map( 'wp_kses_data', $tfoot );
			$atts['tfoot'] = array_pad( $tfoot, 2, '' );

			$atts['search'] = filter_var( $atts['search'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
			$atts['search'] = is_null( $atts['search'] ) ? false : $atts['search'];

		} else {

			unset( $atts['search'], $atts['thead'], $atts['tfoot'] );
		}

		return $atts;
	}

	/**
	 * Callback for `gf_count_entries` shortcode.
	 *
	 * @param array|string $untrusted
	 * @param null         $content
	 * @param string       $tag
	 *
	 * @return int
	 */
	public function countEntries( $untrusted, $content = null, $tag = 'gf_count_entries' ) {

		$atts  = $this->shortcodeAtts( $untrusted, $tag );
		$count = 0;

		if ( is_null( $atts['sum'] ) ) {

			// Set form id's to query.
			$formID = wp_parse_id_list( $atts['form_id'] );

			$criteria = new Search_Criteria( $untrusted );

			$this->countModifier( $count, 'sum', GFAPI::count_entries( $formID, $criteria->get() ) );
			$this->countModifier( $count, 'product', $atts['factor'] );
			$this->countModifier( $count, 'sum', $atts['addend'] );

			//$count = GFAPI::count_entries( $formID, $criteria->get() ) * $atts['factor'];

		} else {

			foreach ( $atts['sum'] as $sumAtts ) {

				// Do not format counts while calculating the total.
				$sumAtts['format'] = false;

				$this->countModifier( $count, 'sum', $this->countEntries( $sumAtts, $content, 'gf_count_entries_recursion' ) );
				$this->countModifier( $count, 'product', $atts['factor'] );
				$this->countModifier( $count, 'sum', $atts['addend'] );
			}
		}

		return $this->numberFormat( $count, $atts );
	}

	/**
	 * Callback for `gf_count_datatable` shortcode.
	 *
	 * @param array|string $untrusted
	 * @param null         $content
	 * @param string       $tag
	 *
	 * @return string
	 */
	public function countDatatable( $untrusted, $content = null, $tag = 'gf_count_datatable' ) {

		$atts = $this->shortcodeAtts( $untrusted, $tag );

		// Set form id's to query.
		$formID = wp_parse_id_list( $atts['form_id'] );

		$html = '';

		if ( $atts['search'] ) $html .= $this->getForm();

		if ( $atts['search'] ) $untrusted = $this->parseRequest( $untrusted );
		$criteria = new Search_Criteria( $untrusted );

		$sorting = null;
		$paging  = array(
			'offset'    => 0,
			'page_size' => absint( $atts['page_size'] ),
		);

		if ( is_null( $atts['sum'] ) ) {

			$entries = GFAPI::get_entries( $formID, $criteria->get(), $sorting, $paging );

			if ( is_wp_error( $entries ) ) {

				return '<p>An error has occurred.</p>';
			}

		} else {

			$entries = array();

			foreach ( $atts['sum'] as $untrustedSumAtts ) {

				$sumAtts     = $this->shortcodeAtts( $untrustedSumAtts, 'gf_count_datatable_sum_atts' );
				if ( $atts['search'] ) $untrustedSumAtts = $this->parseRequest( $untrustedSumAtts );
				$sumCriteria = new Search_Criteria( $untrustedSumAtts );
				$sumEntries  = GFAPI::get_entries( $sumAtts['form_id'], $sumCriteria->get(), $sorting, $paging );

				if ( is_wp_error( $entries ) ) {

					return '<p>An error has occurred.</p>';

				} elseif ( is_array( $sumEntries ) ) {

					$entries = array_merge( $entries, $sumEntries );
				}
			}

		}

		if ( 0 == count( $entries ) ) {

			return $html . '<p>No results.</p>';
		}

		$records = $this->dedupeEntries( $entries );
		$records = $this->setUserAndSort( $records );

		$html .= '<table class="striped">';
		$html .= "<thead><tr><th>{$atts['thead'][0]}</th><th>{$atts['thead'][1]}</th></tr></thead>";
		$html .= '<tbody>';

		$sum = 0;

		foreach ( $records as $entry ) {

			$count = 0;
			$html .= '<tr>';

			$criteria->setCreatedBy( $entry['created_by'] );

			if ( is_null( $atts['sum'] ) ) {

				$this->countModifier( $count, 'sum', GFAPI::count_entries( $formID, $criteria->get() ) );
				$this->countModifier( $count, 'product', $atts['factor'] );
				$this->countModifier( $count, 'sum', $atts['addend'] );

			} else {

				foreach ( $atts['sum'] as $sumAtts ) {

					// Do not format counts while calculating the total.
					$sumAtts['format']     = false;
					$sumAtts['created_by'] = $entry['created_by'];

					$this->countModifier( $count, 'sum', $this->countEntries( $sumAtts, $content, 'ggf_count_datatable_recursion' ) );
					$this->countModifier( $count, 'product', $atts['factor'] );
					$this->countModifier( $count, 'sum', $atts['addend'] );
				}

			}

			$html .= "<td>{$entry['user']->get( 'full_name' )}</td><td>{$this->numberFormat( $count, $atts )}</td>";

			$html .= '</tr>';

			$sum += $count;
		}

		$average = $sum / count( $records );

		$atts['tfoot'][1] = str_ireplace(
			array(
				'%average%',
				'%sum%',
			),
			array(
				$this->numberFormat( $average, $atts ),
				$this->numberFormat( $sum, $atts ),
			),
			$atts['tfoot'][1]
		);

		$html .= '</tbody>';
		$html .= "<tfoot><tr><th>{$atts['tfoot'][0]}</th><th>{$atts['tfoot'][1]}</th></tr></tfoot>";
		$html .= '</table>';

		wp_enqueue_script( 'Easy_Plugins\Gravity_Forms_Count_Datatable\JavaScript\Frontend' );

		return $html;
	}

	private function getForm() {

		$createdBy = wp_unslash( $_REQUEST['created_by'] );
		$dateRange = wp_unslash( $_REQUEST['date_range'] );
		$startDate = filter_var( preg_replace( '([^0-9/] | [^0-9-])', '', htmlentities( $_REQUEST['start_date'] ) ) );
		$endDate   = filter_var( preg_replace( '([^0-9/] | [^0-9-])', '', htmlentities( $_REQUEST['end_date'] ) ) );

		$html = '<div class="row">';
		$html .= '<form id="datatable_search_form" action="#datatable_search_form">';

		$html .= '<div class="row">';
		$html .= '<div class="input-field">';
		$html .= '<input type="text" id="created_by" name="created_by" value="' . esc_attr( $createdBy ) .'" />';
		$html .= '<label for="created_by">Enter Name</label>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="row">';
		$html .= '<div class="input-field">';
		$html .= '<select id="date_range" name="date_range">';
		$html .= '<option value="" disabled ' . selected( $dateRange, '', false ) . '>Choose</option>';
		$html .= '<option value="today" ' . selected( $dateRange, 'today', false ) . '>Today</option>';
		$html .= '<option value="yesterday" ' . selected( $dateRange, 'yesterday', false ) . '>Yesterday</option>';
		$html .= '<option value="this_week" ' . selected( $dateRange, 'this_week', false ) . '>This Week</option>';
		$html .= '<option value="last_week" ' . selected( $dateRange, 'last_week', false ) . '>Last Week</option>';
		$html .= '<option value="this_month" ' . selected( $dateRange, 'this_month', false ) . '>This Month</option>';
		$html .= '<option value="last_month" ' . selected( $dateRange, 'last_month', false ) . '>Last Month</option>';
		$html .= '<option value="custom" ' . selected( $dateRange, 'custom', false ) . '>Custom</option>';
		$html .= '</select>';
		$html .= '<label for="date_range">Date Range</label>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div id="custom_date_range" style="display: ' . ( 'custom' === $dateRange ? 'block' : 'none' ) . ';">';

		$html .= '<div class="row">';
		$html .= '<div class="input-field">';
		$html .= '<input type="text" class="datepicker" id="start_date" name="start_date" pattern="\d{1,2}/\d{1,2}/\d{4}" value="' . esc_attr( $startDate ) .'" />';
		$html .= '<label for="start_date">Start Date (mm/dd/yyyy)</label>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="row">';
		$html .= '<div class="input-field">';
		$html .= '<input type="text" class="datepicker" id="end_date" name="end_date" pattern="\d{1,2}/\d{1,2}/\d{4}" value="' . esc_attr( $endDate ) .'" />';
		$html .= '<label for="end_date">End Date (mm/dd/yyyy)</label>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '</div>';

		$html .= '<div class="row" style="text-align: right;">';
		$html .= '<button class="btn waves-effect waves-light" type="submit" />Search</button>';
		$html .= '</div>';

		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Dedupe the results from `GFAPI::get_entries()` by the `created_by` array key.
	 *
	 * NOTE: Faster than array_unique().
	 *
	 * @param array $entries
	 *
	 * @return array
	 */
	private function dedupeEntries( $entries ) {

		$records = array();

		foreach ( $entries as $entry ) {

			$records[ $entry['created_by'] ] = $entry;
		}

		return $records;
	}

	/**
	 * Add the WP User object to the results from `GFAPI::get_entries()` by the `created_by` array key.
	 *
	 * NOTE: This will discard results from `GFAPI::get_entries()` where the `created_by` ID results in no WP User being found.
	 *
	 * @param array $records
	 *
	 * @return array
	 */
	private function setUserAndSort( $records ) {

		$sortBy = array();

		foreach ( $records as $id => &$entry ) {

			$user = get_user_by( 'id', $entry['created_by'] );

			if ( ! $user instanceof WP_User ) {

				error_log( "WP User ID NOT Found: {$entry['created_by']}" );
				unset( $records[ $id ] );
				continue;
			}

			array_push( $sortBy, $user->get( 'full_name' ) );

			$entry['user'] = $user;
		}

		array_multisort( $sortBy, SORT_ASC, SORT_NATURAL|SORT_FLAG_CASE, $records );

		return $records;
	}

	/**
	 * Modify the count based on operation and modifier amount.
	 *
	 * @param float  $count
	 * @param string $operation
	 * @param float  $modifier
	 *
	 * @return float
	 */
	private function countModifier( float &$count, string $operation, float $modifier ) {

		switch ( $operation ) {

			case 'sum':

				$count += $modifier;
				break;

			case 'product':

				$count *= $modifier;
				break;
		}

		return $count;
	}

	/**
	 * Format the count.
	 *
	 * @param float $number
	 * @param array $atts {
	 *      @type bool   $format        Whether or not to format the number.
	 *      @type int    $decimals      Sets the number of decimal points.
	 *      @type string $dec_point     Sets the separator for the decimal point.
	 *      @type string $thousands_sep Sets the thousands separator.
	 * }
	 *
	 * @return float|string
	 */
	private function numberFormat( float &$number, array $atts ) {

		if ( $atts['format'] ) {

			$number = number_format( $number, $atts['decimals'], $atts['dec_point'], $atts['thousands_sep'] );
		}

		return $number;
	}

	private function parseRequest( $untrusted ) {

		//error_log( var_export( $_REQUEST, true ) );
		$created_by = isset( $_REQUEST['created_by'] ) ? wp_unslash( trim( $_REQUEST['created_by'] ) ) : '';

		if ( ! empty( $created_by ) ) {

			$result = $this->userSearch( $created_by );

			//error_log( $created_by );
			//error_log( var_export( $result, true ) );

			if ( ! empty( $result ) ) {

				// Search returns an array, use the first WP User object.
				//$ID = reset( $result );
				$untrusted['created_by'] = $result;

			} else {

				// No WP User found.
				$untrusted['created_by'] = -1;
			}
		}

		switch ( $_REQUEST['date_range'] ) {

			case 'today':

				$untrusted['start_date']  = 'today';
				$untrusted['date_format'] = 'relative';
				break;

			case 'yesterday':

				$untrusted['start_date']  = 'yesterday';
				$untrusted['end_date']    = 'yesterday';
				$untrusted['date_format'] = 'relative';
				break;

			case 'this_week':

				$untrusted['start_date']  = '-1 week sunday';
				$untrusted['date_format'] = 'relative';
				break;

			case 'last_week':

				$untrusted['start_date']  = '-2 week sunday';
				$untrusted['end_date']    = '-1 week saturday';
				$untrusted['date_format'] = 'relative';
				break;

			case 'this_month':

				$untrusted['start_date']  = 'first day of this month';
				$untrusted['date_format'] = 'relative';
				break;

			case 'last_month':

				$untrusted['start_date']  = 'first day of last month';
				$untrusted['end_date']    = 'last day of last month';
				$untrusted['date_format'] = 'relative';
				break;

			case 'custom':

				$date      = new DateTime();
				$startDate = filter_var( preg_replace( '([^0-9/] | [^0-9-])', '', htmlentities( $_REQUEST['start_date'] ) ) );
				$endDate   = filter_var( preg_replace( '([^0-9/] | [^0-9-])', '', htmlentities( $_REQUEST['end_date'] ) ) );

				if ( false !== $startDate ) {

					$time = strtotime( $startDate );

					if ( false !== $time ) {

						$date->setTimestamp( $time );
						$untrusted['start_date'] = $date->format( 'm/d/Y' );
					}
				}

				if ( false !== $endDate ) {

					$time = strtotime( $endDate );

					if ( false !== $time ) {

						$date->setTimestamp( $time );
						$untrusted['end_date'] = $date->format( 'm/d/Y' );
					}
				}

				break;
		}

		//error_log( var_export( $untrusted, true ) );

		return $untrusted;
	}

	/**
	 * @param $search
	 *
	 * @return WP_User[]
	 */
	private function userSearch( $search ) {

		$results = array();
		$terms   = explode( ' ', $search );

		/*
		 * First search for the User by first and/or last name.
		 */
		foreach ( $terms as $term ) {

			$query = new WP_User_Query(
				array(
					'fields'     => array(
						'ID',
					),
					'meta_query' => array(
						'relation' => 'OR',
						array(
							'key'     => 'first_name',
							'value'   => $term,
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'last_name',
							'value'   => $term,
							'compare' => 'LIKE',
						),
					),
					'orderby'    => 'ID',
					'order'      => 'ASC',
				)
			);

			$result = $query->get_results();

			$resultIDs = wp_list_pluck( $result, 'ID' );

			if ( empty( $results ) ) {

				$results = array_merge( $results, $resultIDs );

			} else {

				if ( ! empty( $resultIDs ) ) {

					$results = array_unique( array_intersect( $results, $resultIDs ) );
				}
			}

		}

		/*
		 * If User is not found by first and/or last name, then search the WP User fields.
		 */
		foreach ( $terms as $term ) {

			$query = new WP_User_Query(
				array(
					'search' => "$term*",
					'search_columns' => array(
						//'ID',
						'user_login',
						'user_nicename',
						'display_name',
						//'user_email',
						//'user_url',
					),
					'fields'     => array(
						'ID',
					),
					'orderby'    => 'ID',
					'order'      => 'ASC',
				)
			);

			$result = $query->get_results();

			$resultIDs = wp_list_pluck( $result, 'ID' );

			if ( empty( $results ) ) {

				$results = array_merge( $results, $resultIDs );

			} else {

				if ( ! empty( $resultIDs ) ) {

					$results = array_unique( array_merge( $results, $resultIDs ) );
				}
			}

		}

		return $results;
	}
}

/**
 * Filter to add the `full_name` metadata to the WP User object.
 */
add_filter(
	'get_user_metadata',
	/**
	 * @param string $value    The user meta value .
	 * @param string $user_id  The user ID.
	 * @param string $meta_key The user meta key.
	 * @param bool   $single   Whether or not $meta_key is array or single.
	 *
	 * @return mixed|string
	 */
	function( $value, $user_id, $meta_key, $single ) {

		if ( 'full_name' === $meta_key ) {

			$user = get_user_by( 'id', $user_id );

			if ( ! $user instanceof WP_User ) {

				return $value;
			}

			$name  = trim( "{$user->get( 'first_name' )} {$user->get( 'last_name' )}" );
			$value = 0 < strlen( $name ) ? ucwords( $name ) : $user->get( 'display_name' );
		}

		return $value;
	},
	10,
	4
);

/**
 * @link https://laubsterboy.com/blog/2015/07/search-wordpress-users-by-name/
 *
 * user_search_by_multiple_parameters
 *
 * Modifies the wp_user_query to allow for User searching (within the WordPress dashboard > Users > All Users) by:
 *    first_name
 *    last_name
 *    nickname
 *    any other custom meta_key added to user profiles (manually or through something like Advanced Custom Fields)
 *
 * @param object $wp_user_query a WordPress query object
 *
 * @return   object    $wp_user_query  a modified version of the WordPress query object parameter
 */
function user_search_by_multiple_parameters( $wp_user_query ) {

	if ( false === strpos( $wp_user_query->query_where, '@' ) && ! empty( $_GET["s"] ) ) {
		global $wpdb;

		$user_ids          = array();
		$user_ids_per_term = array();

		// Usermeta fields to search
		$usermeta_keys = array( 'first_name', 'last_name', 'nickname' );

		$query_string_meta  = "";
		$search_terms       = $_GET["s"];
		$search_terms_array = explode( ' ', $search_terms );

		// Search users for each search term (word) individually
		foreach ( $search_terms_array as $search_term ) {
			// reset ids per loop
			$user_ids_per_term = array();

			// add all custom fields into the query
			if ( ! empty( $usermeta_keys ) ) {
				$query_string_meta = "meta_key='" . implode( "' OR meta_key='", $wpdb->escape( $usermeta_keys ) ) . "'";
			}
error_log( $query_string_meta );
			// Query usermeta table
			$usermeta_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT user_id FROM $wpdb->usermeta WHERE (" . $query_string_meta . ") AND LOWER(meta_value) LIKE '%%%s%%'",
					$search_term
				)
			);
error_log( var_export( $usermeta_results, true ) );
			foreach ( $usermeta_results as $usermeta_result ) {
				if ( ! in_array( $usermeta_result->user_id, $user_ids_per_term ) ) {
					array_push( $user_ids_per_term, $usermeta_result->user_id );
				}
			}

			// Query users table
			$users_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT ID FROM $wpdb->users WHERE LOWER(user_nicename) LIKE '%%%s%%' OR LOWER(user_email) LIKE '%%%s%%' OR LOWER(display_name) LIKE '%%%s%%'",
					$search_term,
					$search_term,
					$search_term
				)
			);

			foreach ( $users_results as $users_result ) {
				if ( ! in_array( $users_result->ID, $user_ids_per_term ) ) {
					array_push( $user_ids_per_term, $users_result->ID );
				}
			}

			// Limit results to matches of all search terms
			if ( empty( $user_ids ) ) {
				$user_ids = array_merge( $user_ids, $user_ids_per_term );
			} else {
				if ( ! empty( $user_ids_per_term ) ) {
					$user_ids = array_unique( array_intersect( $user_ids, $user_ids_per_term ) );
				}
			}
		}

		// Convert IDs to comma separated string
		$ids_string = implode( ',', $user_ids );
error_log( $ids_string );
error_log( $wp_user_query->query_where );
		if ( ! empty( $ids_string ) ) {
			//// network users search (multisite)
			//$wp_user_query->query_where = str_replace(
			//	"user_nicename LIKE '" . $wpdb->esc_like( $search_terms ) . "'",
			//	"ID IN(" . $ids_string . ")",
			//	$wp_user_query->query_where
			//);

			//// site (blog) users search
			//$wp_user_query->query_where = str_replace(
			//	"user_nicename LIKE '%" . $wpdb->esc_like( $search_terms ) . "%'",
			//	"ID IN(" . $ids_string . ")",
			//	$wp_user_query->query_where
			//);

			//// network/site users search by number (WordPress assumes user ID number)
			//$wp_user_query->query_where = str_replace(
			//	"ID = '" . $search_terms . "'",
			//	"ID = '" . $search_terms . "' OR ID IN(" . $ids_string . ")",
			//	$wp_user_query->query_where
			//);

			$search_meta = "ID IN ($ids_string)";

			$wp_user_query->query_where = str_replace(
				'WHERE 1=1 AND (',
				'WHERE 1=1 AND ( ' . $search_meta . ' AND ',
				$wp_user_query->query_where
			);

			$wp_user_query->query_where = str_replace(
				'AND (user_login',
				'OR (user_login',
				$wp_user_query->query_where
			);

error_log( $wp_user_query->query_where );
		}
	}

	return $wp_user_query;
}

//add_action( 'pre_user_query', __NAMESPACE__ . '\user_search_by_multiple_parameters' );

/**
 * @since 1.0
 *
 * @return Gravity_Forms_Count_Datatable
 */
function Gravity_Forms_Count_Datatable() {

	return Gravity_Forms_Count_Datatable::instance();
}
