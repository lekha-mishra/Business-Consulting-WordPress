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
 * Class handler for admin widget "guest messages".
 * 
 * @since 	1.16.0 (J) - 1.6.0 (WP)
 */
class VikBookingAdminWidgetGuestMessages extends VikBookingAdminWidget
{
	/**
	 * The instance counter of this widget.
	 *
	 * @var 	int
	 */
	protected static $instance_counter = -1;

	/**
	 * Number of messages per page. Should be an even number.
	 * 
	 * @var 	int
	 */
	protected $messages_per_page = 6;

	/**
	 * Today Y-m-d string
	 * 
	 * @var 	string
	 */
	protected $today_ymd = null;

	/**
	 * The path to the VCM lib to see if it's available.
	 * 
	 * @var 	string
	 */
	protected $vcm_lib_path = '';

	/**
	 * Tells whether VCM is installed and updated.
	 * 
	 * @var 	bool
	 */
	protected $vcm_exists = true;

	/**
	 * Class constructor will define the widget name and identifier.
	 */
	public function __construct()
	{
		// call parent constructor
		parent::__construct();

		$this->widgetName = JText::translate('VBO_W_GUESTMESSAGES_TITLE');
		$this->widgetDescr = JText::translate('VBO_W_GUESTMESSAGES_DESCR');
		$this->widgetId = basename(__FILE__, '.php');

		// define widget and icon and style name
		$this->widgetIcon = '<i class="' . VikBookingIcons::i('comment-dots') . '"></i>';
		$this->widgetStyleName = 'light-orange';

		// today Y-m-d date
		$this->today_ymd = date('Y-m-d');

		// the path to the VCM library
		$this->vcm_lib_path = VCM_SITE_PATH . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'lib.vikchannelmanager.php';

		// whether VCM is available
		if (is_file($this->vcm_lib_path)) {
			if (!class_exists('VikChannelManager') || !method_exists('VikChannelManager', 'getLatestFromGuests')) {
				// VCM is outdated
				$this->vcm_exists = false;
			}

			// attempt to require the chat handler
			try {
				VikBooking::getVcmChatInstance($oid = 0, $channel = null);
			} catch (Exception $e) {
				// do nothing
			}

			// make sure VCM is up to date for this widget
			if (!class_exists('VCMChatHandler') || !method_exists('VCMChatHandler', 'loadChatAssets')) {
				// VCM is outdated (>= 1.8.11 required)
				$this->vcm_exists = false;
			}
		} else {
			$this->vcm_exists = false;
		}

		// avoid queries on certain pages, as VCM may not have been activated yet
		if (defined('ABSPATH') && $this->vcm_exists) {
			global $pagenow;
			if (isset($pagenow) && in_array($pagenow, ['update.php', 'plugins.php', 'plugin-install.php'])) {
				$this->vcm_exists = false;
			}
		}
	}

	/**
	 * Preload the necessary CSS/JS assets from VCM.
	 * 
	 * @return 	void
	 */
	public function preload()
	{
		if ($this->vcm_exists) {
			// load chat assets from VCM
			VCMChatHandler::loadChatAssets();

			// additional language defs
			JText::script('VBO_NO_REPLY_NEEDED');
			JText::script('VBO_WANT_PROCEED');
		}
	}

	/**
	 * Custom method for this widget only to load the latest guest messages.
	 * The method is called by the admin controller through an AJAX request.
	 * The visibility should be public, it should not exit the process, and
	 * an associative array is returned thanks to the request value "return":1.
	 * 
	 * It's the actual rendering of the widget which also allows navigation.
	 */
	public function loadMessages()
	{
		$bid_convo = VikRequest::getInt('bid_convo', 0, 'request');
		$offset = VikRequest::getInt('offset', 0, 'request');
		$length = VikRequest::getInt('length', $this->messages_per_page, 'request');
		$wrapper = VikRequest::getString('wrapper', '', 'request');

		if (!$this->vcm_exists) {
			VBOHttpDocument::getInstance()->close(500, 'Vik Channel Manager is either not available or outdated');
		}

		// load latest messages
		$latest_messages = [];
		try {
			$latest_messages = VikChannelManager::getLatestFromGuests(['guest_messages'], $offset, $length);
		} catch (Exception $e) {
			// do nothing
		}

		// the multitask data and notifications can request a specific conversation to be opened
		$bubble_convo = null;

		// current year Y and timestamp
		$current_y  = date('Y');
		$current_ts = time();

		// start output buffering
		ob_start();

		foreach ($latest_messages as $gmessage) {
			$gmessage_content = $gmessage->content;
			if (empty($gmessage_content)) {
				$gmessage_content = '.....';
			} elseif (strlen($gmessage_content) > 90) {
				if (function_exists('mb_substr')) {
					$gmessage_content = mb_substr($gmessage_content, 0, 90, 'UTF-8');
				} else {
					$gmessage_content = substr($gmessage_content, 0, 90);
				}
				$gmessage_content .= '...';
			}

			if ($bid_convo && $bid_convo == $gmessage->idorder) {
				// specific conversation to bubble found
				$bubble_convo = $bid_convo;
			}

			?>
			<div class="vbo-dashboard-guest-activity vbo-w-guestmessages-message<?php echo empty($gmessage->read_dt) ? ' vbo-w-guestmessages-message-new' : ''; ?>" data-idorder="<?php echo $gmessage->idorder; ?>" data-idthread="<?php echo !empty($gmessage->id_thread) ? $gmessage->id_thread : ''; ?>" data-idmessage="<?php echo !empty($gmessage->id_message) ? $gmessage->id_message : ''; ?>" onclick="vboWidgetGuestMessagesOpenChat('<?php echo $gmessage->idorder; ?>');">
				<div class="vbo-dashboard-guest-activity-avatar">
				<?php
				if (!empty($gmessage->guest_avatar)) {
					// highest priority goes to the profile picture, not always available
					?>
					<img class="vbo-dashboard-guest-activity-avatar-profile" src="<?php echo $gmessage->guest_avatar; ?>" />
					<?php
				} elseif (!empty($gmessage->pic)) {
					// customer profile picture is not the same as the photo avatar
					?>
					<img class="vbo-dashboard-guest-activity-avatar-profile" src="<?php echo strpos($gmessage->pic, 'http') === 0 ? $gmessage->pic : VBO_SITE_URI . 'resources/uploads/' . $gmessage->pic; ?>" />
					<?php
				} elseif (!empty($gmessage->channel_logo)) {
					// channel logo goes as second option
					?>
					<img class="vbo-dashboard-guest-activity-avatar-profile" src="<?php echo $gmessage->channel_logo; ?>" />
					<?php
				} else {
					// we use an icon as fallback
					VikBookingIcons::e('user', 'vbo-dashboard-guest-activity-avatar-icon');
				}
				?>
				</div>
				<div class="vbo-dashboard-guest-activity-content">
					<div class="vbo-dashboard-guest-activity-content-head">
						<div class="vbo-dashboard-guest-activity-content-info-details">
							<h4 class="vbo-w-guestmessages-message-gtitle"><?php
							echo $gmessage->first_name . (!empty($gmessage->last_name) ? ' ' . $gmessage->last_name : '');
							if (empty($gmessage->read_dt)) {
								// print also an icon to inform that the message was not read
								echo ' ';
								VikBookingIcons::e('exclamation-circle', 'message-new');
							}
							?></h4>
							<div class="vbo-dashboard-guest-activity-content-info-icon">
							<?php
							if (!empty($gmessage->b_status)) {
								switch ($gmessage->b_status) {
									case 'standby':
										$badge_class = 'badge-warning';
										$badge_text  = JText::translate('VBSTANDBY');
										break;
									case 'cancelled':
										$badge_class = 'badge-danger';
										$badge_text  = JText::translate('VBCANCELLED');
										break;
									default:
										$badge_class = 'badge-success';
										$badge_text  = JText::translate('VBCONFIRMED');
										if (!empty($gmessage->b_checkout) && $gmessage->b_checkout < $current_ts) {
											$badge_text  = JText::translate('VBOCHECKEDSTATUSOUT');
										}
										break;
								}
								?>
								<span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
								<?php
							}
							if (!empty($gmessage->b_checkin)) {
								$stay_info_in  = getdate($gmessage->b_checkin);
								$stay_info_out = getdate($gmessage->b_checkout);
								$str_checkin = date('d', $gmessage->b_checkin);
								$str_checkin .= $stay_info_in['mon'] != $stay_info_out['mon'] ? ' ' . VikBooking::sayMonth($stay_info_in['mon'], $short = true) : '';
								$str_checkout = date('d', $gmessage->b_checkout) . ' ' . VikBooking::sayMonth($stay_info_in['mon'], $short = true);
								if ($stay_info_in['year'] != $stay_info_out['year'] || $stay_info_in['year'] != $current_y || $stay_info_out['year'] != $current_y) {
									$str_checkout .= ' ' . $stay_info_in['year'];
								}
								?>
								<span class="vbo-w-guestmessages-message-staydates">
									<span class="vbo-w-guestmessages-message-staydates-in"><?php echo $str_checkin; ?></span>
									<span class="vbo-w-guestmessages-message-staydates-sep">-</span>
									<span class="vbo-w-guestmessages-message-staydates-out"><?php echo $str_checkout; ?></span>
								</span>
								<?php
							}
							?>
							</div>
						</div>
						<div class="vbo-dashboard-guest-activity-content-info-date">
						<?php
						$gmessage_ts = strtotime($gmessage->last_updated);
						?>
							<span><?php echo date('H:i', $gmessage_ts); ?></span>
						<?php
						if (date('Y-m-d', $gmessage_ts) != $this->today_ymd) {
							// format and print the date
							?>
							<span><?php echo date(str_replace('/', $this->datesep, $this->df), $gmessage_ts); ?></span>
							<?php
						} else {
							// print "today"
							?>
							<span><?php echo JText::translate('VBTODAY'); ?></span>
							<?php
						}
						?>
						</div>
					</div>
					<div class="vbo-dashboard-guest-activity-content-info-msg">
						<p><?php echo $gmessage_content; ?></p>
					</div>
				</div>
			</div>
			<?php
		}

		// append navigation
		?>
		<div class="vbo-guestactivitywidget-commands">
			<div class="vbo-guestactivitywidget-commands-main">
			<?php
			if ($offset > 0) {
				// show backward navigation button
				?>
				<div class="vbo-guestactivitywidget-command-chevron vbo-guestactivitywidget-command-prev">
					<span class="vbo-guestactivitywidget-prev" onclick="vboWidgetGuestMessagesNavigate('<?php echo $wrapper; ?>', -1);"><?php VikBookingIcons::e('chevron-left'); ?></span>
				</div>
				<?php
			}
			?>
				<div class="vbo-guestactivitywidget-command-chevron vbo-guestactivitywidget-command-next">
					<span class="vbo-guestactivitywidget-next" onclick="vboWidgetGuestMessagesNavigate('<?php echo $wrapper; ?>', 1);"><?php VikBookingIcons::e('chevron-right'); ?></span>
				</div>
			</div>
		</div>
		<?php

		// check if we should bubble a specific conversation
		if ($bubble_convo) {
			?>
		<script type="text/javascript">
			setTimeout(() => {
				vboWidgetGuestMessagesOpenChat('<?php echo $bubble_convo; ?>');
			}, 300);
		</script>
			<?php
		}

		// append the total number of messages displayed, the current offset and the latest message datetime
		$tot_messages  = count($latest_messages);
		$latest_datetime = $tot_messages > 0 && $offset === 0 ? $latest_messages[0]->last_updated : null;

		// get the HTML buffer
		$html_content = ob_get_contents();
		ob_end_clean();

		// return an associative array of values
		return [
			'html' 		   => $html_content,
			'tot_messages' => $tot_messages,
			'offset' 	   => ($offset + $length),
			'latest_dt'    => $latest_datetime,
		];
	}

	/**
	 * Custom method for this widget only to watch the latest guest messages.
	 * The method is called by the admin controller through an AJAX request.
	 * The visibility should be public, it should not exit the process, and
	 * any content sent to output will be returned to the AJAX response.
	 * 
	 * Outputs the new number of messages found from the latest datetime.
	 */
	public function watchMessages()
	{
		$latest_dt = VikRequest::getString('latest_dt', '', 'request');
		if (empty($latest_dt)) {
			echo '0';
			return;
		}

		if (!$this->vcm_exists) {
			VBOHttpDocument::getInstance()->close(500, 'Vik Channel Manager is either not available or outdated');
		}

		// load the latest guest message (one is sufficient)
		$latest_messages = [];
		try {
			$latest_messages = VikChannelManager::getLatestFromGuests(['guest_messages'], 0, 1);
		} catch (Exception $e) {
			// do nothing
		}

		if (!count($latest_messages) || $latest_messages[0]->last_updated == $latest_dt) {
			// no newest messages found
			echo '0';
			return;
		}

		// print 1 to indicate that new messages should be reloaded
		echo '1';
	}

	/**
	 * Custom method for this widget only to render the chat of a booking.
	 * The method is called by the admin controller through an AJAX request.
	 * The visibility should be public, it should not exit the process, and
	 * no values should be returned, as the response must be sent to output
	 * in case the JS/CSS assets will be echoed within the response.
	 * 
	 * Returns the necessary HTML code to render the chat.
	 */
	public function renderChat()
	{
		$bid = VikRequest::getInt('bid', 0, 'request');

		$booking = VikBooking::getBookingInfoFromID($bid);

		if (!$booking) {
			VBOHttpDocument::getInstance()->close(404, 'Could not find booking');
		}

		// initialize chat instance by getting the proper channel name
		if (empty($booking['channel'])) {
			// front-end reservation chat handler
			$chat_channel = 'vikbooking';
		} else {
			$channelparts = explode('_', $booking['channel']);
			// check if this is a meta search channel
			$is_meta_search = false;
			if (preg_match("/(customer).*[0-9]$/", $channelparts[0]) || !strcasecmp($channelparts[0], 'googlehotel') || !strcasecmp($channelparts[0], 'trivago')) {
				$is_meta_search = empty($booking['idorderota']);
			}
			if ($is_meta_search) {
				// customer of type sales channel should use front-end reservation chat handler
				$chat_channel = 'vikbooking';
			} else {
				// let the getInstance method validate the channel chat handler
				$chat_channel = $booking['channel'];
			}
		}
		$messaging = VikBooking::getVcmChatInstance($booking['id'], $chat_channel);

		if (is_null($messaging)) {
			VBOHttpDocument::getInstance()->close(500, 'Could not render chat');
		}

		// send content to output
		echo $messaging->renderChat([
			'hideThreads' => 1,
		], $load_assets = false);
	}

	/**
	 * Custom method for this widget only to update the thread of a booking.
	 * The method is called by the admin controller through an AJAX request.
	 * The visibility should be public, it should not exit the process, and
	 * no values should be returned, as the response must be sent to output.
	 * 
	 * Returns a successful string or throws an error.
	 */
	public function setNoReplyNeededThread()
	{
		$bid 	   = VikRequest::getInt('bid', 0, 'request');
		$id_thread = VikRequest::getInt('id_thread', 0, 'request');

		$booking = VikBooking::getBookingInfoFromID($bid);

		if (!$booking || empty($id_thread)) {
			VBOHttpDocument::getInstance()->close(404, 'Could not find booking thread');
		}

		// build thread object for update
		$thread = new stdClass;
		$thread->id = $id_thread;
		$thread->idorder = $bid;
		$thread->no_reply_needed = 1;

		$dbo = JFactory::getDbo();

		if (!$dbo->updateObject('#__vikchannelmanager_threads', $thread, ['id', 'idorder'])) {
			VBOHttpDocument::getInstance()->close(500, 'Could not update thread');
		}

		echo '1';
	}

	public function render(VBOMultitaskData $data = null)
	{
		// increase widget's instance counter
		static::$instance_counter++;

		// check whether the widget is being rendered via AJAX when adding it through the customizer
		$is_ajax = $this->isAjaxRendering();

		// generate a unique ID for the guest messages wrapper instance
		$wrapper_instance = !$is_ajax ? static::$instance_counter : rand();
		$wrapper_id = 'vbo-widget-guest-messages-' . $wrapper_instance;

		// this widget will work only if VCM is available and updated, and if permissions are met
		$vbo_auth_bookings = JFactory::getUser()->authorise('core.vbo.bookings', 'com_vikbooking');
		if (!$this->vcm_exists || !$vbo_auth_bookings) {
			return;
		}

		// multitask data event identifier for clearing intervals
		$js_intvals_id = '';
		$bid_convo = 0;
		if ($data && $data->isModalRendering()) {
			$js_intvals_id = $data->getModalJsIdentifier();
			// check if a specific conversation should be opened
			if ($data->get('id_message', 0)) {
				$bid_convo = $data->getBookingId();
			}
		}

		?>
		<div class="vbo-admin-widget-wrapper">
			<div class="vbo-admin-widget-head">
				<h4><?php echo $this->widgetIcon; ?> <span><?php echo $this->widgetName; ?></span></h4>
			</div>
			<div id="<?php echo $wrapper_id; ?>" class="vbo-dashboard-guests-latest" data-offset="0" data-length="<?php echo $this->messages_per_page; ?>" data-eventsid="<?php echo $js_intvals_id; ?>" data-latestdt="">
				<div class="vbo-dashboard-guest-messages-inner">
					<div class="vbo-dashboard-guest-messages-list">
					<?php
					for ($i = 0; $i < $this->messages_per_page; $i++) {
						?>
						<div class="vbo-dashboard-guest-activity vbo-dashboard-guest-activity-skeleton">
							<div class="vbo-dashboard-guest-activity-avatar">
								<div class="vbo-skeleton-loading vbo-skeleton-loading-avatar"></div>
							</div>
							<div class="vbo-dashboard-guest-activity-content">
								<div class="vbo-dashboard-guest-activity-content-head">
									<div class="vbo-skeleton-loading vbo-skeleton-loading-title"></div>
								</div>
								<div class="vbo-dashboard-guest-activity-content-subhead">
									<div class="vbo-skeleton-loading vbo-skeleton-loading-subtitle"></div>
								</div>
								<div class="vbo-dashboard-guest-activity-content-info-msg">
									<div class="vbo-skeleton-loading vbo-skeleton-loading-content"></div>
								</div>
							</div>
						</div>
						<?php
					}
					?>
					</div>
				</div>
			</div>
		</div>

		<?php
		if (static::$instance_counter === 0 || $is_ajax) {
			// HTML helper tag for URL routing and some JS functions should be loaded once per widget instance
			$admin_file_base = VBOPlatformDetection::isWordPress() ? 'admin.php' : 'index.php';
		?>
		<a class="vbo-widget-guest-messages-basenavuri" href="<?php echo $admin_file_base; ?>?option=com_vikbooking&task=editorder&cid[]=%d" style="display: none;"></a>

		<script type="text/javascript">

			/**
			 * Open the chat for the clicked booking guest message
			 */
			function vboWidgetGuestMessagesOpenChat(id) {
				// clicked message
				var message_el = jQuery('.vbo-w-guestmessages-message[data-idorder="' + id + '"]').first();

				if (message_el.hasClass('vbo-w-guestmessages-message-new')) {
					// get rid of the "new/unread" status
					message_el.removeClass('vbo-w-guestmessages-message-new');
					if (message_el.find('i.message-new').length) {
						message_el.find('i.message-new').remove();
					}
				}

				// modal events unique id to avoid conflicts
				var eventsid = message_el.closest('.vbo-dashboard-guests-latest').attr('data-eventsid') || (Math.floor(Math.random() * 100000));

				// define unique modal event names to avoid conflicts
				var modal_dismiss_event = 'dismiss-modal-wguestmessages-chat' + eventsid;
				var modal_loading_event = 'loading-modal-wguestmessages-chat' + eventsid;

				// base navigation URI to booking details
				var booking_base_uri = jQuery('.vbo-widget-guest-messages-basenavuri').first().attr('href');

				// build modal content
				var chat_head_title = jQuery('<span></span>');
				var chat_head_title_wrap = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-info');
				var chat_head_title_top = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-info-customer');
				var chat_head_title_bot = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-info-booking');

				var chat_head_title_img = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-guestavatar');
				var chat_head_title_txt = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-guestname');
				var chat_head_title_bid = jQuery('<span></span>').addClass('vbo-modal-wguestmessages-chat-guestbid');
				chat_head_title_bid.append('<a class="badge badge-info" href="' + booking_base_uri.replace('%d', id) + '" target="_blank">' + id + '</a>');

				var guest_avatar = message_el.find('.vbo-dashboard-guest-activity-avatar img');
				var guest_name = message_el.find('.vbo-w-guestmessages-message-gtitle').text();
				chat_head_title_txt.text(guest_name);
				if (guest_avatar && guest_avatar.length) {
					chat_head_title_img.append(guest_avatar.clone());
					chat_head_title.append(chat_head_title_img);
				}
				chat_head_title_top.append(chat_head_title_txt);
				chat_head_title_top.append(chat_head_title_bid);

				chat_head_title_bot.append(message_el.find('.vbo-dashboard-guest-activity-content-info-icon').html());

				// register callback for no-reply-needed click
				var no_reply_needed_el = jQuery('<a></a>').addClass('label').attr('href', 'JavaScript: void(0);').text(Joomla.JText._('VBO_NO_REPLY_NEEDED'));
				no_reply_needed_el.on('click', function() {
					if (confirm(Joomla.JText._('VBO_WANT_PROCEED'))) {
						var id_thread = message_el.attr('data-idthread');
						if (!id_thread || !id_thread.length) {
							return false;
						}

						// perform the request to flag the thread as no-reply-needed
						var call_method = 'setNoReplyNeededThread';

						VBOCore.doAjax(
							"<?php echo $this->getExecWidgetAjaxUri(); ?>",
							{
								widget_id: "<?php echo $this->getIdentifier(); ?>",
								call: call_method,
								bid: id,
								id_thread: id_thread,
								tmpl: "component"
							},
							(response) => {
								try {
									var obj_res = typeof response === 'string' ? JSON.parse(response) : response;
									if (!obj_res.hasOwnProperty(call_method)) {
										console.error('Unexpected JSON response', obj_res);
										return false;
									}

									// emit the event to close (dismiss) the modal
									VBOCore.emitEvent(modal_dismiss_event);

									// reload the widget
									vboWidgetGuestMessagesLoad(message_el.closest('.vbo-dashboard-guests-latest').attr('id'));
								} catch(err) {
									console.error('could not parse JSON response', err, response);
								}
							},
							(error) => {
								console.error(error);
							}
						);
					}
				});
				chat_head_title_bot.append(no_reply_needed_el);

				chat_head_title_wrap.append(chat_head_title_top);
				chat_head_title_wrap.append(chat_head_title_bot);

				chat_head_title.append(chat_head_title_wrap);

				// display modal
				var chat_modal_body = VBOCore.displayModal({
					suffix: 'wguestmessages-chat',
					extra_class: 'vbo-modal-rounded vbo-modal-tall vbo-modal-nofooter',
					title: chat_head_title,
					dismiss_event: modal_dismiss_event,
					onDismiss: () => {
						if (typeof VCMChat !== 'undefined') {
							VCMChat.getInstance().destroy();
						}
					},
					loading_event: modal_loading_event,
				});

				// start loading
				VBOCore.emitEvent(modal_loading_event);

				// perform the request to render the chat in the apposite modal wrapper
				var call_method = 'renderChat';

				VBOCore.doAjax(
					"<?php echo $this->getExecWidgetAjaxUri(); ?>",
					{
						widget_id: "<?php echo $this->getIdentifier(); ?>",
						call: call_method,
						bid: id,
						tmpl: "component"
					},
					(response) => {
						// stop loading
						VBOCore.emitEvent(modal_loading_event);

						try {
							var obj_res = typeof response === 'string' ? JSON.parse(response) : response;
							if (!obj_res.hasOwnProperty(call_method)) {
								console.error('Unexpected JSON response', obj_res);
								return false;
							}

							// append HTML code to render the chat
							chat_modal_body.html(obj_res[call_method]);

							// register scroll to bottom with a small delay
							setTimeout(() => {
								if (typeof VCMChat !== 'undefined') {
									VCMChat.getInstance().scrollToBottom();
								}
							}, 150);
						} catch(err) {
							console.error('could not parse JSON response', err, response);
						}
					},
					(error) => {
						// stop loading
						VBOCore.emitEvent(modal_loading_event);
						// display error
						console.error(error);
						alert(error.responseText);
					}
				);
			}

			/**
			 * Display the loading skeletons.
			 */
			function vboWidgetGuestMessagesSkeletons(wrapper) {
				var widget_instance = jQuery('#' + wrapper);
				if (!widget_instance.length) {
					return false;
				}
				widget_instance.find('.vbo-dashboard-guest-messages-list').html('');
				for (var i = 0; i < <?php echo $this->messages_per_page; ?>; i++) {
					var skeleton = '';
					skeleton += '<div class="vbo-dashboard-guest-activity vbo-dashboard-guest-activity-skeleton">';
					skeleton += '	<div class="vbo-dashboard-guest-activity-avatar">';
					skeleton += '		<div class="vbo-skeleton-loading vbo-skeleton-loading-avatar"></div>';
					skeleton += '	</div>';
					skeleton += '	<div class="vbo-dashboard-guest-activity-content">';
					skeleton += '		<div class="vbo-dashboard-guest-activity-content-head">';
					skeleton += '			<div class="vbo-skeleton-loading vbo-skeleton-loading-title"></div>';
					skeleton += '		</div>';
					skeleton += '		<div class="vbo-dashboard-guest-activity-content-subhead">';
					skeleton += '			<div class="vbo-skeleton-loading vbo-skeleton-loading-subtitle"></div>';
					skeleton += '		</div>';
					skeleton += '		<div class="vbo-dashboard-guest-activity-content-info-msg">';
					skeleton += '			<div class="vbo-skeleton-loading vbo-skeleton-loading-content"></div>';
					skeleton += '		</div>';
					skeleton += '	</div>';
					skeleton += '</div>';
					// append skeleton
					jQuery(skeleton).appendTo(widget_instance.find('.vbo-dashboard-guest-messages-list'));
				}
			}

			/**
			 * Perform the request to load the latest messages.
			 */
			function vboWidgetGuestMessagesLoad(wrapper, bid_convo) {
				var widget_instance = jQuery('#' + wrapper);
				if (!widget_instance.length) {
					return false;
				}
				var current_offset  = parseInt(widget_instance.attr('data-offset'));
				var length_per_page = parseInt(widget_instance.attr('data-length'));

				// the widget method to call
				var call_method = 'loadMessages';

				// make a request to load the messages
				VBOCore.doAjax(
					"<?php echo $this->getExecWidgetAjaxUri(); ?>",
					{
						widget_id: "<?php echo $this->getIdentifier(); ?>",
						call: call_method,
						return: 1,
						bid_convo: bid_convo,
						offset: current_offset,
						length: length_per_page,
						wrapper: wrapper,
						tmpl: "component"
					},
					(response) => {
						try {
							var obj_res = typeof response === 'string' ? JSON.parse(response) : response;
							if (!obj_res.hasOwnProperty(call_method)) {
								console.error('Unexpected JSON response', obj_res);
								return false;
							}

							// replace HTML with new messages
							widget_instance.find('.vbo-dashboard-guest-messages-list').html(obj_res[call_method]['html']);

							// check if latest datetime is set
							if (obj_res[call_method]['latest_dt']) {
								widget_instance.attr('data-latestdt', obj_res[call_method]['latest_dt']);
							}

							// check results
							if (!isNaN(obj_res[call_method]['tot_messages']) && parseInt(obj_res[call_method]['tot_messages']) < 1) {
								// no results can indicate the offset is invalid or too high
								if (!isNaN(current_offset) && parseInt(current_offset) > 0) {
									// reset offset to 0
									widget_instance.attr('data-offset', 0);
									// show loading skeletons
									vboWidgetGuestMessagesSkeletons(wrapper);
									// reload the first page
									vboWidgetGuestMessagesLoad(wrapper);
								}
							}
						} catch(err) {
							console.error('could not parse JSON response', err, response);
						}
					},
					(error) => {
						// remove the skeleton loading
						widget_instance.find('.vbo-dashboard-guest-messages-list').find('.vbo-dashboard-guest-activity-skeleton').remove();
						console.error(error);
					}
				);
			}

			/**
			 * Navigate between the various pages of the messages.
			 */
			function vboWidgetGuestMessagesNavigate(wrapper, direction) {
				var widget_instance = jQuery('#' + wrapper);
				if (!widget_instance.length) {
					return false;
				}

				// current offset
				var current_offset = parseInt(widget_instance.attr('data-offset'));

				// steps per type
				var steps = <?php echo $this->messages_per_page; ?>;

				// show loading skeletons
				vboWidgetGuestMessagesSkeletons(wrapper);

				// check direction and update offset for next nav
				if (direction > 0) {
					// navigate forward
					widget_instance.attr('data-offset', (current_offset + steps));
				} else {
					// navigate backward
					var new_offset = current_offset - steps;
					new_offset = new_offset >= 0 ? new_offset : 0;
					widget_instance.attr('data-offset', new_offset);
				}
				
				// launch navigation
				vboWidgetGuestMessagesLoad(wrapper);
			}

			/**
			 * Watch periodically if there are new messages to be displayed.
			 */
			function vboWidgetGuestMessagesWatch(wrapper) {
				var widget_instance = jQuery('#' + wrapper);
				if (!widget_instance.length) {
					return false;
				}

				var latest_dt = widget_instance.attr('data-latestdt');
				if (!latest_dt || !latest_dt.length) {
					return false;
				}

				// the widget method to call
				var call_method = 'watchMessages';

				// make a request to watch the messages
				VBOCore.doAjax(
					"<?php echo $this->getExecWidgetAjaxUri(); ?>",
					{
						widget_id: "<?php echo $this->getIdentifier(); ?>",
						call: call_method,
						latest_dt: latest_dt,
						tmpl: "component"
					},
					(response) => {
						try {
							var obj_res = typeof response === 'string' ? JSON.parse(response) : response;
							if (!obj_res.hasOwnProperty(call_method)) {
								console.error('Unexpected JSON response', obj_res);
								return false;
							}
							// response will contain the number of new messages
							if (isNaN(obj_res[call_method]) || parseInt(obj_res[call_method]) < 1) {
								// do nothing
								return;
							}
							// new messages found, reset the offset and re-load the first page
							widget_instance.attr('data-offset', 0);
							// show loading skeletons
							vboWidgetGuestMessagesSkeletons(wrapper);
							// reload the first page
							vboWidgetGuestMessagesLoad(wrapper);
						} catch(err) {
							console.error('could not parse JSON response', err, response);
						}
					},
					(error) => {
						// do nothing
						console.error(error);
					}
				);
			}

		</script>
		<?php
		}
		?>

		<script type="text/javascript">

			jQuery(function() {

				// when document is ready, load latest messages for this widget's instance
				vboWidgetGuestMessagesLoad('<?php echo $wrapper_id; ?>', '<?php echo $bid_convo; ?>');

				// set interval for loading new messages automatically
				var watch_intv = setInterval(function() {
					vboWidgetGuestMessagesWatch('<?php echo $wrapper_id; ?>');
				}, 60000);

			<?php
			if ($js_intvals_id) {
				// widget can be dismissed through the modal
				?>
				document.addEventListener(VBOCore.widget_modal_dismissed + '<?php echo $js_intvals_id; ?>', (e) => {
					clearInterval(watch_intv);
				});
				<?php
			}
			?>

			});

		</script>

		<?php
	}
}
