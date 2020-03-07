<?php

namespace Easy_Plugins\Gravity_Forms_Count_Datatable;

use DateTime;
use Exception;
use WP_Error;

class Search_Criteria {

	/**
	 * @var string
	 */
	private $createdBy = '';

	/**
	 * @var null|DateTime
	 */
	private $dateEnd = null;

	/**
	 * @var null|DateTime
	 */
	private $dateStart = null;

	/**
	 * @var string
	 */
	private $filterMode = 'all';

	/**
	 * @var null|bool
	 */
	private $isRead = null;

	/**
	 * @var null|bool
	 */
	private $isStarred = null;

	/**
	 * @var string
	 */
	private $status = 'active';

	/**
	 * @var array
	 */
	private $criteria = array();

	/**
	 * @return string
	 */
	public function getCreatedBy() {

		return $this->createdBy;
	}

	/**
	 * Search_Criteria constructor.
	 *
	 * @param array $untrusted
	 */
	public function __construct( array $untrusted = array() ) {

		$atts = shortcode_atts( $this->defaults(), $untrusted );

		$this->setFormStatus( $atts['status'] );
		$this->setCreatedBy( $atts['created_by'] );
		$this->setDateStart( $atts['start_date'], $atts['date_format'] );
		$this->setDateEnd( $atts['end_date'], $atts['date_format'] );
		$this->setFilterMode( $atts['filter_mode'] );
		$this->setIsRead( $atts['is_read'] );
		$this->setIsStarred( $atts['is_starred'] );

		$this->addFiltersFromArray( $untrusted );
	}

	/**
	 * Criteria defaults properties and values.
	 *
	 * @return array
	 */
	private function defaults() {

		return array(
			'status'           => 'active',
			'filter_mode'      => 'all',
			'created_by'       => '',
			'is_read'          => null,
			'is_starred'       => null,
			//'is_approved'      => '',
			'start_date'       => null,
			'end_date'         => null,
			'date_format'      => 'm/d/Y',
			//'workflow_step' => '', // Takes the step number
			//'workflow_step_status' => 'pending', // String separated by commas
			//'workflow_step_is_current' => true // Getting entries pause at the step when set to true; getting entries have moved forward to other steps when set to false.
		);
	}

	/**
	 * Query form submissions based on WP User ID.
	 *
	 * Set to `current` to filter by the current logged in WP User.
	 * Set to `0` to remove filter.
	 *
	 * @param int|string $createdBy WP User ID or `current`.
	 *
	 * @return Search_Criteria
	 */
	public function setCreatedBy( $createdBy ) {

		if ( 'current' === $createdBy || is_numeric( $createdBy ) ) {

			$this->createdBy = is_numeric( $createdBy ) ? absint( $createdBy ) : $createdBy;
		}

		return $this;
	}

	/**
	 * @return DateTime|null
	 */
	public function getDateEnd() {

		return $this->dateEnd;
	}

	/**
	 * Set to `null` to remove date end criteria.
	 *
	 * @param null|string $dateString
	 * @param string      $format
	 *
	 * @return Search_Criteria|WP_Error
	 */
	public function setDateEnd( $dateString, $format = 'm/d/Y' ) {

		if ( is_null( $dateString ) ) {

			$this->dateEnd = null;

			return $this;
		}

		if ( 'relative' === $format ) {

			$date = $this->createDateTimeFrom( $dateString );

			if ( false === $date ) {

				$this->dateEnd = null;

				return new WP_Error(
					'date_create_from_relative',
					'Failed to create date.',
					array(
						'date'   => $dateString,
						'format' => $format,
					)
				);
			}

		} else {

			$date = date_create_from_format( $format, $dateString );

			if ( false === $date ) {

				$this->dateEnd = null;

				return new WP_Error(
					'date_create_from_format',
					'Failed to create date.',
					array(
						'date'   => $dateString,
						'format' => $format,
					)
				);
			}

		}

		$date->setTime( 23, 59 , 59 );

		$this->dateEnd = $date;

		return $this;
	}

	/**
	 * @return DateTime|null
	 */
	public function getDateStart() {

		return $this->dateStart;
	}

	/**
	 * Set to `null` to remove date start criteria.
	 *
	 * @param null|string $dateString
	 * @param string      $format
	 *
	 * @return Search_Criteria|WP_Error
	 */
	public function setDateStart( $dateString, $format = 'm/d/Y' ) {

		if ( is_null( $dateString ) ) {

			$this->dateStart = null;

			return $this;
		}

		if ( 'relative' === $format ) {

			$date = $this->createDateTimeFrom( $dateString );

			if ( false === $date ) {

				$this->dateStart = null;

				return new WP_Error(
					'date_create_from_relative',
					'Failed to create date.',
					array(
						'date'   => $dateString,
						'format' => $format,
					)
				);
			}

		} else {

			$date = date_create_from_format( $format, $dateString );

			if ( false === $date ) {

				$this->dateStart = null;

				return new WP_Error(
					'date_create_from_format',
					'Failed to create date.',
					array(
						'date'   => $dateString,
						'format' => $format,
					)
				);
			}

		}

		$date->setTime( 0, 0 , 0 );

		$this->dateStart = $date;

		return $this;
	}

	/**
	 * @param string $dateString
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 *
	 * @return bool|DateTime
	 */
	private function createDateTimeFrom( string $dateString ) {

		try {
			$date = new DateTime( $dateString );
		}
		catch ( Exception $e ) {

			$date = new DateTime();
			$time = strtotime( $dateString );

			if ( false === $date ) {

				return false;
			}

			$date->setTimestamp( $time );
		}

		return $date;
	}

	/**
	 * @return string
	 */
	public function getFilterMode() {

		return $this->filterMode;
	}

	/**
	 * @param $mode
	 *
	 * @return Search_Criteria
	 */
	public function setFilterMode( $mode ) {

		$valid = array( 'all', 'any' );

		$this->filterMode = in_array( $mode, $valid ) ? $mode : 'all';

		return $this;
	}

	/**
	 * Parse flat array and add filters to search criteria.
	 *
	 * Search for array keys for the following matches:
	 * - filter_field
	 * - filter_value
	 * - filter_operator
	 * - filter_field_{+d}
	 * - filter_value_{+d}
	 * - filter_operator_{+d}
	 *
	 * @param array $untrusted
	 *
	 * @return Search_Criteria
	 */
	public function addFiltersFromArray( array $untrusted ) {

		$filters = array();

		foreach ( $untrusted as $subject => $value ) {

			if ( 1 === preg_match( '/^filter_(field|operator|value)(?:_(\d+)$|$)/i', $subject, $matches ) ) {

				if ( isset( $matches[2] ) ) {

					$index = $matches[2];

				} else {

					$index = 0;
				}

				// Rename `field` to `key`.
				$key = 'field' === $matches[1] ? 'key' : $matches[1];

				$filters[ $index ][ $key ] = $value;
			}
		}

		if ( 0 < count( $filters ) ) {

			foreach ( $filters as $filter ) {

				if ( ( array_key_exists( 'key', $filter ) && ! empty( $filter['key'] ) ) &&
				     ( array_key_exists( 'value', $filter ) && ! empty( $filter['value'] ) )
				) {

					$this->addFilter(
						$filter['key'],
						$filter['value'],
						array_key_exists( 'operator', $filter ) && ! empty( $filter['operator'] ) ? $filter['operator'] : '='
					);
				}
			}
		}

		return $this;
	}

	/**
	 * For supported operators options see:
	 * - gravityforms/includes/api.php line 527 @see GFAPI::get_entries()
	 * - gravityforms/includes/query/class-gf-query.php @see \GF_Query::parse()
	 * - gravityforms/includes/query/class-gf-query-condition.php @see \GF_Query_Condition
	 *
	 * @param string                    $key
	 * @param int|string|int[]|string[] $value
	 * @param string                    $operator
	 */
	public function addFilter( string $key, $value, string $operator = '=' ) {

		$this->criteria['field_filters'][ $key ] = array( 'key' => $key, 'operator' => $operator, 'value' => $value );
	}

	/**
	 * @return bool|null
	 */
	public function getIsRead() {

		return $this->isRead;
	}

	/**
	 * Set to `null` to remove is_read criteria.
	 *
	 * @param bool|null|string $isRead Filter entries based on whether or not the entry has been read or not.
	 *                                 Valid parameter values are bool, null, 0|1, yes|no.
	 *
	 * @return Search_Criteria
	 */
	public function setIsRead( $isRead ) {

		if ( is_null( $isRead ) ) {

			$this->isRead = null;

			return $this;
		}

		$isRead = filter_var( $isRead, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		$this->isRead = $isRead;

		return $this;
	}

	/**
	 * @return bool|null
	 */
	public function getIsStarred() {

		return $this->isStarred;
	}

	/**
	 * Set to `null` to remove is_starred criteria.
	 *
	 * @param bool|null|string $isStarred Filter entries based on whether or not the entry has been starred or not.
	 *                                    Valid parameter values are bool, null, 0|1, yes|no.
	 *
	 * @return Search_Criteria
	 */
	public function setIsStarred( $isStarred ) {

		if ( is_null( $isStarred ) ) {

			$this->isStarred = null;

			return $this;
		}

		$isStarred = filter_var( $isStarred, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

		$this->isStarred = $isStarred;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getFormStatus() {

		return $this->status;
	}

	/**
	 * Query form submissions based on GF Form status.
	 *
	 * Set to `all` to query all form statuses.
	 *
	 * @param $status
	 *
	 * @return Search_Criteria
	 */
	public function setFormStatus( $status ) {

		$valid = array( 'all', 'active', 'inactive', 'trash' );

		$this->status = in_array( $status, $valid ) ? $status : 'active';

		return $this;
	}

	/**
	 * Get form status to query. If set to `all` remove `status`
	 * from search criteria so GFAPI will query all form status.
	 */
	private function prepareFormStatus() {

		$status = $this->getFormStatus();

		if ( 'all' !== $status ) {

			$this->criteria['status'] = $status;

		} elseif (  'all' === $status && array_key_exists( 'status', $this->criteria ) ) {

			unset( $this->criteria['status'] );
		}
	}

	/**
	 * Prepare the created by field filter.
	 */
	private function prepareCreatedBy() {

		$createdBy = $this->getCreatedBy();

		if ( 'current' === $createdBy ) {

			$createdBy = get_current_user_id();
		}

		if ( ! empty( $createdBy ) ) {

			$this->criteria['field_filters']['created_by'] = array( 'key' => 'created_by', 'value' => $createdBy );

		} elseif ( array_key_exists( 'field_filters', $this->criteria ) &&
		           array_key_exists( 'created_by', $this->criteria['field_filters'] )
		) {

			unset( $this->criteria['field_filters']['created_by'] );
		}
	}

	private function prepareDateEnd() {

		$date = $this->getDateEnd();

		if ( $date instanceof DateTime ) {

			$this->criteria['end_date'] = $date->format( 'Y-m-d H:i:s' );

		} elseif ( array_key_exists( 'end_date', $this->criteria ) ) {

			unset( $this->criteria['end_date'] );
		}
	}

	private function prepareDateStart() {

		$date = $this->getDateStart();

		if ( $date instanceof DateTime ) {

			$this->criteria['start_date'] = $date->format( 'Y-m-d H:i:s' );

		} elseif ( array_key_exists( 'start_date', $this->criteria ) ) {

			unset( $this->criteria['start_date'] );
		}
	}

	private function prepareFilterMode() {

		$mode = $this->getFilterMode();

		if ( array_key_exists( 'field_filters', $this->criteria ) &&
		     0 < count( $this->criteria['field_filters'] )
		) {

			$this->criteria['field_filters']['mode'] = $mode;
		}
	}

	private function prepareIsRead() {

		$isRead = $this->getIsRead();

		if ( is_bool( $isRead ) ) {

			$this->criteria['field_filters']['is_read'] = array( 'key' => 'is_read', 'value' => $isRead );

		} elseif ( array_key_exists( 'field_filters', $this->criteria ) &&
		           array_key_exists( 'is_read', $this->criteria['field_filters'] )
		) {

			unset( $this->criteria['field_filters']['is_read'] );
		}
	}

	private function prepareIsStarred() {

		$isStarred = $this->getIsStarred();

		if ( is_bool( $isStarred ) ) {

			$this->criteria['field_filters']['is_starred'] = array( 'key' => 'is_starred', 'value' => $isStarred );

		} elseif ( array_key_exists( 'field_filters', $this->criteria ) &&
		           array_key_exists( 'is_starred', $this->criteria['field_filters'] )
		) {

			unset( $this->criteria['field_filters']['is_starred'] );
		}
	}

	/**
	 * Prepare properties/create array for the GFAPI search criteria.
	 */
	private function prepare() {

		$this->prepareCreatedBy();
		$this->prepareDateStart();
		$this->prepareDateEnd();
		$this->prepareFilterMode();
		$this->prepareFormStatus();
		$this->prepareIsRead();
		$this->prepareIsStarred();
	}

	/**
	 * Get the seach criteria array prepared for the GFAPI.
	 *
	 * @return array
	 */
	public function get() {

		$this->prepare();

		error_log( var_export( $this->criteria, true ) );

		return $this->criteria;
	}
}
