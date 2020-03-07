<?
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

use GFAPI;
use GFForms;
use WP_User;

add_action(
	'gform_loaded',
	function() {

		//if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		//	return;
		//}
		//
		//GFForms::include_addon_framework();

		new Gravity_Forms_Count_Datatable();
	},
	5
);

class Gravity_Forms_Count_Datatable {

	public function __construct() {

		require_once( __DIR__ . '/includes/class.gravity-forms-search-criteria.php' );

		$this->hooks();
	}

	private function hooks() {

		add_shortcode( 'gf_count_entries', array( $this, 'countEntries' ) );
		add_shortcode( 'gf_count_datatable', array( $this, 'countDatatable' ) );
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

		return $this->countFormat( $count, $atts );
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

			return '<p>No results.</p>';
		}

		$records = $this->dedupeEntries( $entries );
		$records = $this->setUserAndSort( $records );

		$html = '';

		$html .= '<table>';
		$html .= '<thead><tr><th>Name</th><th>Count</th></tr></thead>';
		$html .= '<tbody>';

		$total = 0;

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

			$html .= "<td>{$entry['user']->get( 'full_name' )}</td><td>{$this->countFormat( $count, $atts )}</td>";

			$html .= '</tr>';

			$total += $count;
		}

		$html .= '</tbody>';
		$html .= "<tfoot><tr><th></th><th>{$this->countFormat( $total, $atts )}</th></tr></tfoot>";
		$html .= '</table>';

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
	 * @param float $count
	 * @param array $atts {
	 *      @type bool   $format        Whether or not to format the number.
	 *      @type int    $decimals      Sets the number of decimal points.
	 *      @type string $dec_point     Sets the separator for the decimal point.
	 *      @type string $thousands_sep Sets the thousands separator.
	 * }
	 *
	 * @return float|string
	 */
	private function countFormat( float &$count, array $atts ) {

		if ( $atts['format'] ) {

			$count = number_format( $count, $atts['decimals'], $atts['dec_point'], $atts['thousands_sep'] );
		}

		return $count;
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
