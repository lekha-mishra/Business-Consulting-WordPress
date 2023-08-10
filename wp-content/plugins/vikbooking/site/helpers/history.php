<?php
/**
 * @package     VikBooking
 * @subpackage  com_vikbooking
 * @author      Alessio Gaggii - e4j - Extensionsforjoomla.com
 * @copyright   Copyright (C) 2018 e4j - Extensionsforjoomla.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 * @link        https://vikwp.com
 */

defined('ABSPATH') or die('No script kiddies please!');

/**
 * Handles all the events involving a reservation.
 */
class VboBookingHistory
{
	/**
	 * @var  int
	 */
	protected $bid = null;

	/**
	 * @var  array
	 */
	protected $prevBooking = null;

	/**
	 * @var  mixed
	 */
	protected $data = null;

	/**
	 * @var  object
	 */
	protected $dbo = null;

	/**
	 * @var  array
	 */
	protected $typesMap = [];

	/**
	 * List of event types worthy of a notification.
	 * 
	 * @var  	array
	 * 
	 * @since 	1.15.0 (J) - 1.5.0 (WP)
	 */
	protected $worthy_events = [
		'NC',
		'MW',
		'NP',
		'P0',
		'PN',
		'CR',
		'CW',
		'MC',
		'CC',
		'NO',
		'PO',
		'IR',
		'PC',
		'UR',
	];

	/**
	 * Class constructor.
	 */
	public function __construct()
	{
		$this->dbo = JFactory::getDbo();
		$this->typesMap = $this->getTypesMap();
	}

	/**
	 * Returns an array of types mapped to
	 * the corresponding language definition.
	 * All the history types should be listed here.
	 *
	 * @return 	array
	 */
	public function getTypesMap()
	{
		return [
			//New booking with status Confirmed
			'NC' => JText::translate('VBOBOOKHISTORYTNC'),
			//Booking modified from website
			'MW' => JText::translate('VBOBOOKHISTORYTMW'),
			//Booking modified from back-end
			'MB' => JText::translate('VBOBOOKHISTORYTMB'),
			//New booking from back-end
			'NB' => JText::translate('VBOBOOKHISTORYTNB'),
			//New booking with status Pending
			'NP' => JText::translate('VBOBOOKHISTORYTNP'),
			//Booking paid for the first time
			'P0' => JText::translate('VBOBOOKHISTORYTP0'),
			//Booking paid for a second time
			'PN' => JText::translate('VBOBOOKHISTORYTPN'),
			//Cancellation request message
			'CR' => JText::translate('VBOBOOKHISTORYTCR'),
			//Booking cancelled via front-end website
			'CW' => JText::translate('VBOBOOKHISTORYTCW'),
			//Booking auto cancelled via front-end
			'CA' => JText::translate('VBOBOOKHISTORYTCA'),
			//Booking cancelled via back-end by admin
			'CB' => JText::translate('VBOBOOKHISTORYTCB'),
			//Booking Receipt generated via back-end
			'BR' => JText::translate('VBOBOOKHISTORYTBR'),
			//Booking Invoice generated
			'BI' => JText::translate('VBOBOOKHISTORYTBI'),
			//Booking registration unset status by admin
			'RA' => JText::translate('VBOBOOKHISTORYTRA'),
			//Booking checked-in status set by admin
			'RB' => JText::translate('VBOBOOKHISTORYTRB'),
			//Booking checked-out status set by admin
			'RC' => JText::translate('VBOBOOKHISTORYTRC'),
			//Booking no-show status set by admin
			'RZ' => JText::translate('VBOBOOKHISTORYTRZ'),
			//Booking set to Confirmed by admin
			'TC' => JText::translate('VBOBOOKHISTORYTTC'),
			//Booking set to Confirmed via App
			'AC' => JText::translate('VBOBOOKHISTORYTAC'),
			//Booking modified from channel
			'MC' => JText::translate('VBOBOOKHISTORYTMC'),
			//Booking cancelled from channel
			'CC' => JText::translate('VBOBOOKHISTORYTCC'),
			//Booking removed via App
			'AR' => JText::translate('VBOBOOKHISTORYTAR'),
			//Booking modified via App
			'AM' => JText::translate('VBOBOOKHISTORYTAM'),
			//New booking via App
			'AN' => JText::translate('VBOBOOKHISTORYTAN'),
			//New Booking from OTA
			'NO' => JText::translate('VBOBOOKHISTORYTNO'),
			//Report affecting the booking
			'RP' => JText::translate('VBOBOOKHISTORYTRP'),
			//Custom email sent to the customer by admin
			'CE' => JText::translate('VBOBOOKHISTORYTCE'),
			//Custom SMS sent to the customer by admin
			'CS' => JText::translate('VBOBOOKHISTORYTCS'),
			//Payment Update (a new amount paid has been set)
			'PU' => JText::translate('VBOBOOKHISTORYTPU'),
			// Upsell Extras via front-end
			'UE' => JText::translate('VBOBOOKHISTORYTUE'),
			// Report guest misconduct to OTA
			'GM' => JText::translate('VBOBOOKHISTORYTGM'),
			// Send Email Cancellation by admin
			'EC' => JText::translate('VBOBOOKHISTORYTEC'),
			// Amount refunded
			'RF' => JText::translate('VBOBOOKHISTORYTRF'),
			// Refund Updated
			'RU' => JText::translate('VBOBOOKHISTORYTRU'),
			// Payable Amount Updated
			'PB' => JText::translate('VBOBOOKHISTORYTPB'),
			// Channel Manager custom event
			'CM' => JText::translate('VBOBOOKHISTORYTCM'),
			// Channel Manager payout notification
			'PO' => JText::translate('VBOBOOKHISTORYTPO'),
			// Inquiry reservation (website)
			'IR' => JText::translate('VBOBOOKHISTORYTIR'),
			// Pre-checkin updated via front-end
			'PC' => JText::translate('VBOBOOKHISTORYTPC'),
			// Guest review received
			'GR' => JText::translate('VBOBOOKHISTORYTGR'),
			// Upgrade room
			'UR' => JText::translate('VBOBOOKHISTORYTUR'),
		];
	}

	/**
	 * Accesses the groups of history event types, useful to group multiple events.
	 * Groups are categories of events containing worthy event types of the same context.
	 * 
	 * @param 	string 	$group 	get the list of event types of this group.
	 * 
	 * @return 	array 			associative array of types group or empty array.
	 * 
	 * @since 	1.16.0 (J) - 1.6.0 (WP)
	 */
	public function getTypeGroups($group = null)
	{
		// we group various types of events per category
		$type_groups = [
			// group "Bookings"
			'GBK' => [
				'name' 	=> JText::translate('VBMENUTHREE'),
				'types' => [
					'NC',
					'MW',
					'NP',
					'CR',
					'CW',
					'MC',
					'CC',
					'NO',
					'IR',
					'UR',
				],
			],
			// group "Channel Manager"
			'GCM' => [
				'name' 	=> JText::translate('VBMENUCHANNELMANAGER'),
				'types' => [
					'MC',
					'CC',
					'NO',
					'PO',
					'CM',
				],
			],
			// group "Website"
			'GWB' => [
				'name' 	=> JText::translate('VBORDFROMSITE'),
				'types' => [
					'NC',
					'MW',
					'NP',
					'CR',
					'CW',
					'IR',
					'PC',
					'UE',
					'GR',
					'UR',
				],
			],
			// group "Payments"
			'GPM' => [
				'name' 	=> JText::translate('VBO_HISTORY_GPM'),
				'types' => [
					'P0',
					'PN',
					'PO',
					'RF',
				],
			],
		];

		if (!empty($group)) {
			// attempt to fetch the requested group
			return isset($type_groups[$group]) ? $type_groups[$group] : [];
		}

		// return the whole associative list
		return $type_groups;
	}

	/**
	 * Sets the current booking ID.
	 * 
	 * @param 	int 	$bid
	 *
	 * @return 	self
	 **/
	public function setBid($bid)
	{
		$this->bid = (int)$bid;

		return $this;
	}

	/**
	 * Sets the previous booking array.
	 * To calculate what has changed in the booking after the
	 * modification, VBO uses the method getLogBookingModification().
	 * VCM instead should use this method to tell the class that
	 * what has changed should be calculated to obtain the 'descr'
	 * text of the history record that will be stored.
	 * 
	 * @param 	array 	$booking
	 *
	 * @return 	self
	 **/
	public function setPrevBooking($booking)
	{
		if (is_array($booking)) {
			$this->prevBooking = $booking;
		}

		return $this;
	}

	/**
	 * Sets extra data for the current history log.
	 * 
	 * @param 	mixed 	$data 	array, object or string of extra data.
	 * 							Useful to store the amount paid to be invoiced,
	 * 							or the transaction details of the payments.
	 *
	 * @return 	self
	 **/
	public function setExtraData($data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Checks whether the type for the history record is valid.
	 *
	 * @param 	string 		$type
	 * @param 	[bool] 		$returnit 	if true, the translated value is returned. Otherwise boolean is returned
	 *
	 * @return 	boolean
	 */
	public function validType($type, $returnit = false)
	{
		if ($returnit) {
			return isset($this->typesMap[strtoupper($type)]) ? $this->typesMap[strtoupper($type)] : $type;
		}

		return isset($this->typesMap[strtoupper($type)]);
	}

	/**
	 * Reads the booking record.
	 * Returns false in case of failure.
	 *
	 * @param 	mixed 	
	 *
	 * @return 	array
	 */
	protected function getBookingInfo()
	{
		$q = "SELECT * FROM `#__vikbooking_orders` WHERE `id`=" . (int)$this->bid;
		$this->dbo->setQuery($q, 0, 1);

		$record = $this->dbo->loadAssoc();

		if (!$record) {
			return false;
		}

		return $record;
	}

	/**
	 * Stores a new history record for the booking.
	 * 
	 * @param 	string 		$type 	the char-type of store we are making for the history
	 * @param 	[string] 	$descr 	the description of this booking record (optional)
	 *
	 * @return 	boolean
	 */
	public function store($type, $descr = '')
	{
		if (is_null($this->bid) || !$this->validType($type)) {
			return false;
		}

		if (!$booking_info = $this->getBookingInfo()) {
			return false;
		}

		if (empty($descr) && is_array($this->prevBooking)) {
			/**
			 * VCM (including the App) could set the previous booking information,
			 * so we need to calculate what has changed with the booking.
			 * Load VBO language.
			 */
			$lang = JFactory::getLanguage();
			$lang->load('com_vikbooking', (defined('ABSPATH') ? VIKBOOKING_ADMIN_LANG : JPATH_ADMINISTRATOR), $lang->getTag(), true);
			if (!class_exists('VikBooking')) {
				require_once(VBO_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikbooking.php');
			}
			$descr = VikBooking::getLogBookingModification($this->prevBooking);
		}

		// get the event dispatcher
		$dispatcher = VBOFactory::getPlatform()->getDispatcher();

		// build record object
		$history_record = new stdClass;
		$history_record->idorder = $this->bid;
		$history_record->dt 	 = JFactory::getDate()->toSql();
		$history_record->type 	 = $type;
		$history_record->descr 	 = $descr ?: null;
		$history_record->totpaid = (float)$booking_info['totpaid'];
		$history_record->total 	 = (float)$booking_info['total'];
		$history_record->data 	 = $this->data;

		/**
		 * Trigger event before saving a new history record.
		 * 
		 * @since 	1.16.0 (J) - 1.6.0 (WP)
		 */
		$results = $dispatcher->filter('onBeforeSaveBookingHistoryVikBooking', [$history_record, $this->prevBooking]);
		if (in_array(false, $results, true)) {
			return false;
		}

		/**
		 * Store the extra data for this history log. Useful to store
		 * information about the amount paid and its invoicing status.
		 * 
		 * @since 	1.12 (J) - 1.1.7 (WP)
		 */
		if (!is_null($history_record->data) && !is_scalar($history_record->data)) {
			$history_record->data = json_encode($history_record->data);
		}

		// store record
		if (!$this->dbo->insertObject('#__vikbooking_orderhistory', $history_record, 'id') || !isset($history_record->id)) {
			return false;
		}

		/**
		 * Trigger event for the newly history record added.
		 * 
		 * @since 	1.16.0 (J) - 1.6.0 (WP)
		 */
		$dispatcher->trigger('onAfterSaveBookingHistoryVikBooking', [$history_record, $this->prevBooking]);

		return true;
	}

	/**
	 * Loads all the history records for this booking
	 *
	 * @return 	array
	 */
	public function loadHistory()
	{
		if (empty($this->bid)) {
			return [];
		}

		$q = "SELECT * FROM `#__vikbooking_orderhistory` WHERE `idorder`=" . (int)$this->bid . " ORDER BY `dt` DESC;";
		$this->dbo->setQuery($q);

		$history = $this->dbo->loadAssocList();

		if (!$history) {
			return [];
		}

		return $history;
	}

	/**
	 * Checks whether this booking has an event of the given type.
	 * 
	 * @param 	string 	$type 	the type of the event.
	 *
	 * @return 	mixed 			last date on success, false otherwise.
	 * 
	 * @since 	1.13.5 (J) - 1.3.5 (WP)
	 */
	public function hasEvent($type)
	{
		if (empty($type) || empty($this->bid)) {
			return false;
		}

		$q = "SELECT `dt` FROM `#__vikbooking_orderhistory` WHERE `idorder`=" . (int)$this->bid . " AND `type`=" . $this->dbo->quote($type) . " ORDER BY `dt` DESC";
		$this->dbo->setQuery($q, 0, 1);

		$dt = $this->dbo->loadResult();

		if (!$dt) {
			return false;
		}

		return $dt;
	}

	/**
	 * Returns a list of records with data defined for the given event type.
	 * Useful to get a list of transactions data for the refund operations.
	 * 
	 * @param 	mixed 		$type 		string or array event type(s).
	 * @param 	callable 	$callvalid 	callback for the data validation.
	 * @param 	bool 		$onlydata 	whether to get just the event data.
	 *
	 * @return 	mixed 					false or array with data records.
	 * 
	 * @since 	1.14.0 (J) - 1.4.0 (WP)
	 */
	public function getEventsWithData($type, $callvalid = null, $onlydata = true)
	{
		if (empty($type) || empty($this->bid)) {
			return false;
		}

		if (!is_array($type)) {
			$type = [$type];
		}

		// quote all given types
		$types = array_map(array($this->dbo, 'quote'), $type);

		$q = "SELECT * FROM `#__vikbooking_orderhistory` WHERE `idorder`=" . (int)$this->bid . " AND `type` IN (" . implode(', ', $types) . ") ORDER BY `dt` ASC;";
		$this->dbo->setQuery($q);
		$events = $this->dbo->loadAssocList();

		if ($events) {
			$datas  = [];
			foreach ($events as $k => $e) {
				$data = json_decode($e['data']);
				if (!empty($data)) {
					$events[$k]['data'] = $data;
				}
				$valid_data = true;
				if (is_callable($callvalid)) {
					$valid_data = call_user_func($callvalid, $events[$k]['data']);
				}
				if ($valid_data) {
					array_push($datas, $events[$k]['data']);
				}
			}

			return $onlydata ? $datas : $events;
		}

		return false;
	}

	/**
	 * Gets the latest history event per booking within a list.
	 * Only the latest event per booking will be considered.
	 * 
	 * @param 	int 	$start 	 the query start offset.
	 * @param 	int 	$limit 	 the query limit.
	 * @param 	int 	$min_id  optional minimum history ID to fetch.
	 * @param 	array 	$types 	 optional list of event types (or groups) to fetch.
	 * 
	 * @return 	array 			 the list of recent history record objects.
	 * 
	 * @since 	1.15.0 (J) - 1.5.0 (WP)
	 * @since 	1.16.0 (J) - 1.6.0 (WP) query modified to fetch the customer picture.
	 * @since 	1.16.0 (J) - 1.6.0 (WP) fourth argument $types introduced.
	 */
	public function getLatestBookingEvents($start = 0, $limit = 20, $min_id = 0, array $types = [])
	{
		$clauses = [];

		if ($min_id > 0) {
			$clauses[] = "`h`.`id` > " . (int)$min_id;
		} else {
			$clauses[] = "`h`.`id` > 0";
		}

		if ($types) {
			// scan for groups
			$groups = [];
			foreach ($types as $k => $type) {
				if ($type && strlen($type) === 3 && $group = $this->getTypeGroups($type)) {
					$groups = array_merge($groups, $group['types']);
					unset($types[$k]);
				}
			}

			if ($groups) {
				// merge all types
				$types = array_merge(array_values($types), array_unique($groups));
			}

			// quote all values
			$types = array_map(array($this->dbo, 'q'), array_filter($types));

			if ($types) {
				// add query clause
				$clauses[] = "`h`.`type` IN (" . implode(', ', $types) . ")";
			}
		}

		/**
		 * On some servers with just 30k records in the history and 5k in bookings,
		 * the INNER JOIN to group the latest history events by booking ID is taking
		 * ~25 seconds to complete, which is not acceptable. This doesn't seem to happen
		 * on every database, but since the admin widget "latest events" requires to get
		 * just the latest history event ID by passing the limit to 1, we should not
		 * use the INNER JOIN all the times to save server resources.
		 * Refactoring performed to abandon also the LEFT JOINS which were timing out in
		 * case of several thousands of records. The widget "latest_events" crashed websites.
		 * 
		 * @since 	1.15.6 (J) - 1.5.12 (WP)
		 * @since 	1.16.0 (J) - 1.6.0 (WP) INNER JOIN dismissed because too slow and not needed.
		 * @since 	1.16.1 (J) - 1.6.1 (WP) LEFT JOIN dismissed because too slow, queries split.
		 */

		// query just the history records without joining any other table, or the records would become hundreds of thousands
		$q = "SELECT `h`.*
			FROM `#__vikbooking_orderhistory` AS `h`
			WHERE " . implode(' AND ', $clauses) . " ORDER BY `h`.`dt` DESC, `h`.`id` DESC";

		$this->dbo->setQuery($q, $start, $limit);
		$rows = $this->dbo->loadObjectList();
		if (!$rows) {
			return [];
		}

		// build a list of reservation IDs
		$history_orders = [];
		foreach ($rows as $row) {
			$history_orders[] = (int)$row->idorder;
		}
		$history_orders = array_unique($history_orders);

		// fetch the rest of the information
		$q = "SELECT `o`.`id`, `o`.`custdata`, `o`.`status`, `o`.`days`, `o`.`checkin`, `o`.`checkout`, `o`.`totpaid`,
			`o`.`total`, `o`.`idorderota`, `o`.`channel`, `o`.`country`, `o`.`closure`, `c`.`pic`,
			(
				SELECT CONCAT_WS(' ',`or`.`t_first_name`,`or`.`t_last_name`) 
				FROM `#__vikbooking_ordersrooms` AS `or` 
				WHERE `or`.`idorder`=`o`.`id` LIMIT 1
			) AS `nominative`
			FROM `#__vikbooking_orders` AS `o`
			LEFT JOIN `#__vikbooking_customers_orders` AS `co` ON `co`.`idorder`=`o`.`id`
			LEFT JOIN `#__vikbooking_customers` AS `c` ON `c`.`id`=`co`.`idcustomer`
			WHERE `o`.`id` IN (" . implode(', ', $history_orders) . ");";

		$this->dbo->setQuery($q);
		$rows_data = $this->dbo->loadObjectList();
		if (!$rows_data) {
			return [];
		}

		// merge information to object records
		foreach ($rows as &$row) {
			foreach ($rows_data as $row_data) {
				if ($row_data->id != $row->idorder) {
					continue;
				}

				// unset the id property before merging to keep the id of the history event
				unset($row_data->id);

				// merge object properties by using casting
				$row = (object) array_merge((array) $row, (array) $row_data);

				break;
			}
		}

		// unset last reference
		unset($row);

		// return the final list
		return $rows;
	}

	/**
	 * Helper method to set a new list of worthy event types.
	 * 
	 * @param 	array 	$worthy_events  list of event type identifiers.
	 * 
	 * @return 	self
	 * 
	 * @since 	1.15.0 (J) - 1.5.0 (WP)
	 */
	public function setWorthyEventTypes($worthy_events = [])
	{
		if (is_array($worthy_events)) {
			$this->worthy_events = $worthy_events;
		}

		return $this;
	}

	/**
	 * Helper method to get the list of worthy event types.
	 * 
	 * @return 	array
	 * 
	 * @since 	1.15.4 (J) - 1.5.10 (WP)
	 */
	public function getWorthyEventTypes()
	{
		return $this->worthy_events;
	}

	/**
	 * Gets the latest history events worthy of a notification.
	 * 
	 * @param 	int 	$min_id  optional minimum history ID to fetch.
	 * @param 	int 	$limit 	 the query limit.
	 * 
	 * @return 	array 			 the list of worthy history record objects.
	 * 
	 * @since 	1.15.0 (J) - 1.5.0 (WP)
	 * @since 	1.16.0 (J) - 1.6.0 (WP) query modified to fetch the customer picture.
	 * 									INNER JOIN dismissed.
	 */
	public function getWorthyEvents($min_id = 0, $limit = 0)
	{
		$clauses = [
			"`o`.`status` IS NOT NULL",
			"`o`.`closure` = 0"
		];

		if ($min_id > 0) {
			$clauses[] = "`h`.`id` > " . (int)$min_id;
		}

		if (count($this->worthy_events)) {
			// quote all worthy types of event
			$worthy_types = array_map(array($this->dbo, 'quote'), $this->worthy_events);
			$clauses[] = "`h`.`type` IN (" . implode(', ', $worthy_types) . ")";
		}

		// join as few tables as possible to reduce the execution timing
		$q = "SELECT `h`.*, `o`.`status`, `o`.`days`, `o`.`checkin`, `o`.`checkout`,
			`o`.`totpaid`, `o`.`total`, `o`.`idorderota`, `o`.`channel`, `o`.`country`
			FROM `#__vikbooking_orderhistory` AS `h`
			LEFT JOIN `#__vikbooking_orders` AS `o` ON `h`.`idorder`=`o`.`id`
			WHERE " . implode(' AND ', $clauses) . " ORDER BY `h`.`dt` DESC";

		$this->dbo->setQuery($q, 0, $limit);
		$rows = $this->dbo->loadObjectList();
		if (!$rows) {
			return [];
		}

		// set the "pic" property to a default empty value
		foreach ($rows as &$row) {
			$row->pic = '';
		}

		// unset last reference
		unset($row);

		// build a list of reservation IDs
		$history_orders = [];
		foreach ($rows as $row) {
			$history_orders[] = (int)$row->idorder;
		}
		$history_orders = array_unique($history_orders);

		// fetch the rest of the information
		$q = "SELECT `o`.`id`, `c`.`pic`
			FROM `#__vikbooking_orders` AS `o`
			LEFT JOIN `#__vikbooking_customers_orders` AS `co` ON `co`.`idorder`=`o`.`id`
			LEFT JOIN `#__vikbooking_customers` AS `c` ON `c`.`id`=`co`.`idcustomer`
			WHERE `o`.`id` IN (" . implode(', ', $history_orders) . ");";

		$this->dbo->setQuery($q);
		$rows_data = $this->dbo->loadObjectList();
		if ($rows_data) {
			// merge information
			foreach ($rows as &$row) {
				foreach ($rows_data as $row_data) {
					if ($row_data->id != $row->idorder) {
						continue;
					}

					// set picture property
					$row->pic = $row_data->pic;
					break;
				}
			}
		}

		// return all records found
		return $rows;
	}
}
