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

$rows = $this->rows;
$arrbusy = $this->arrbusy;
$wmonthsel = $this->wmonthsel;
$tsstart = $this->tsstart;
$lim0 = $this->lim0;
$navbut = $this->navbut;

$vbo_auth_pricing = JFactory::getUser()->authorise('core.vbo.pricing', 'com_vikbooking');
$vbo_app = VikBooking::getVboApplication();
JHtml::fetch('script', VBO_SITE_URI.'resources/jquery-ui.min.js');
JHtml::fetch('script', VBO_ADMIN_URI.'resources/js_upload/jquery.stickytableheaders.min.js');

// JS lang defs
JText::script('VBSAVE');
JText::script('VBPVIEWORDERSTHREE');
JText::script('VBEDITORDERTHREE');
JText::script('VBDAYS');
JText::script('VBDAY');
JText::script('VBMAILADULTS');
JText::script('VBMAILADULT');
JText::script('VBMAILCHILDREN');
JText::script('VBMAILCHILD');
JText::script('VBO_MISSING_SUBUNIT');
JText::script('VBO_SHOW_CANCELLATIONS');

$days_labels = array(
	JText::translate('VBSUN'),
	JText::translate('VBMON'),
	JText::translate('VBTUE'),
	JText::translate('VBWED'),
	JText::translate('VBTHU'),
	JText::translate('VBFRI'),
	JText::translate('VBSAT')
);

$nowdf = VikBooking::getDateFormat();
if ($nowdf == "%d/%m/%Y") {
	$df = 'd/m/Y';
} elseif ($nowdf == "%m/%d/%Y") {
	$df = 'm/d/Y';
} else {
	$df = 'Y/m/d';
}
$currencysymb = VikBooking::getCurrencySymb(true);

$pdebug = VikRequest::getInt('e4j_debug', '', 'request');
$session = JFactory::getSession();
$show_type = $session->get('vbUnitsShowType', '');
$mnum = $session->get('vbOvwMnum', '1');
$mnum = intval($mnum);
$pcategory_id = $session->get('vbOvwCatid', 0);
$cookie = JFactory::getApplication()->input->cookie;
$cookie_uleft = $cookie->get('vboAovwUleft', '', 'string');
$cookie_sticky_heads = $cookie->get('vboAovwStheads', 'off', 'string');
$colortags = VikBooking::loadBookingsColorTags();
//View mode - Classic or Tags
$pbmode = $session->get('vbTagsMode', 'classic');
$tags_view_supported = false;
//Rooms Units Distinctive Features
$rooms_features_map = array();
$rooms_features_bookings = array();
$rooms_bids_pools = array();
$bids_checkins = array();
$index_loop = 0;
foreach ($rows as $kr => $room) {
	if ($room['units'] <= 1) {
		$tags_view_supported = true;
	} else {
		if (!empty($room['params']) && $room['units'] <= 150) {
			//sub-room units only if room type has 150 units at most
			$room_params = json_decode($room['params'], true);
			if (is_array($room_params) && array_key_exists('features', $room_params) && @count($room_params['features']) > 0) {
				$rooms_features_map[$room['id']] = array();
				foreach ($room_params['features'] as $rind => $rfeatures) {
					foreach ($rfeatures as $fname => $fval) {
						if (strlen($fval)) {
							$rooms_features_map[$room['id']][$rind] = '#'.$rind.' - '.JText::translate($fname).': '.$fval;
							break;
						}
					}
				}
				if (!(count($rooms_features_map[$room['id']]) > 0)) {
					unset($rooms_features_map[$room['id']]);
				} else {
					foreach ($rooms_features_map[$room['id']] as $rind => $indexdata) {
						$clone_room = $room;
						$clone_room['unit_index'] = (int)$rind;
						$clone_room['unit_index_str'] = $indexdata;
						array_splice($rows, ($kr + 1 + $index_loop), 0, array($clone_room));
						$index_loop++;
					}
				}
			}
		}
	}
}
//
if (!$tags_view_supported) {
	$pbmode = 'classic';
}
//
//locked (stand-by) records - new in VBO 1.9
$arrlocked = array();
if (array_key_exists('tmplock', $arrbusy)) {
	if (count($arrbusy['tmplock']) > 0) {
		$arrlocked = $arrbusy['tmplock'];
	}
	unset($arrbusy['tmplock']);
}
//
?>
<script type="text/Javascript">
function vboUnitsLeftOrBooked() {
	var set_to = jQuery('#uleftorbooked').val();
	if (jQuery('.vbo-overview-redday').length) {
		jQuery('.vbo-overview-redday').each(function(){
			jQuery(this).text(jQuery(this).attr('data-'+set_to));
		});
	}
	var nd = new Date();
	nd.setTime(nd.getTime() + (365*24*60*60*1000));
	document.cookie = "vboAovwUleft="+set_to+"; expires=" + nd.toUTCString() + "; path=/; SameSite=Lax";
}
if (jQuery.isFunction(jQuery.fn.tooltip)) {
	jQuery(".hasTooltip").tooltip();
} else {
	jQuery.fn.tooltip = function(){};
}
var vboFests = <?php echo json_encode($this->festivities); ?>;
var vboRdayNotes = <?php echo json_encode($this->rdaynotes); ?>;
</script>
<form action="index.php?option=com_vikbooking&amp;task=overv" method="post" name="vboverview" class="vbo-avov-form">
	<div class="btn-toolbar vbo-avov-toolbar" id="filter-bar" style="width: 100%; display: inline-block;">
		<div class="btn-group pull-left">
			<?php echo $wmonthsel; ?>
		</div>
		<div class="btn-group pull-left">
			<select name="mnum" onchange="document.vboverview.submit();">
			<?php
			for($i = 1; $i <= 18; $i++) {
				?>
				<option value="<?php echo $i; ?>"<?php echo $i == $mnum ? ' selected="selected"' : ''; ?>><?php echo JText::translate('VBOVWNUMMONTHS').' '.$i; ?></option>
				<?php
			}
			?>
			</select>
		</div>
	<?php
	if (count($this->categories)) {
		?>
		<div class="btn-group pull-left">
			<select name="category_id" onchange="document.vboverview.submit();">
				<option value="0">- <?php echo JText::translate('VBPVIEWROOMTWO'); ?> -</option>
			<?php
			foreach ($this->categories as $catid => $catname) {
				?>
				<option value="<?php echo $catid; ?>"<?php echo $catid == $pcategory_id ? ' selected="selected"' : ''; ?>><?php echo $catname; ?></option>
				<?php
			}
			?>
			</select>
		</div>
		<?php
	}

	$stickyheaders_cmd_on = '';
	$stickyheaders_cmd_off = '';
	if ((count($rows) * $mnum) > 10) {
		if (defined('_JEXEC')) {
			/**
			 * @joomlaonly 	fixed offset selector is ".navbar"
			 */
			$stickyheaders_cmd_on = "jQuery('table.vboverviewtable').stickyTableHeaders({cacheHeaderHeight: true, fixedOffset: jQuery('.navbar')});";
		} else {
			/**
			 * @wponly 	fixed offset selector is "#wpadminbar"
			 */
			$stickyheaders_cmd_on = "jQuery('table.vboverviewtable').stickyTableHeaders({cacheHeaderHeight: true, fixedOffset: jQuery('#wpadminbar')});";
		}
		$stickyheaders_cmd_off = "jQuery('table.vboverviewtable').stickyTableHeaders('destroy'); jQuery('th.bluedays').attr('style', '');";
	}
	?>
		<script type="text/javascript">
			function vboToggleStickyTableHeaders(val) {
				var sticky_heads_cval = 'off';
				if (val > 0) {
					sticky_heads_cval = 'on';
					jQuery('table.vboverviewtable').addClass('vbo-overv-sticky-table-head-on');
					jQuery('table.vboverviewtable').removeClass('vbo-overv-sticky-table-head-off');
					<?php echo $stickyheaders_cmd_on; ?>
				} else {
					jQuery('table.vboverviewtable').addClass('vbo-overv-sticky-table-head-off');
					jQuery('table.vboverviewtable').removeClass('vbo-overv-sticky-table-head-on');
					<?php echo $stickyheaders_cmd_off; ?>
				}
				var nd = new Date();
				nd.setTime(nd.getTime() + (365*24*60*60*1000));
				document.cookie = "vboAovwStheads=" + sticky_heads_cval + "; expires=" + nd.toUTCString() + "; path=/; SameSite=Lax";
			}
		</script>
	<?php
	if ((count($rows) * $mnum) > 10) {
		?>
		<div class="btn-group pull-left">
			<select name="stickytheads" onchange="vboToggleStickyTableHeaders(this.value);">
				<option value="1"<?php echo empty($cookie_sticky_heads) || $cookie_sticky_heads == 'on' ? ' selected="selected"' : ''; ?>><?php echo JText::translate('VBOVERVIEWSTICKYTHEADON'); ?></option>
				<option value="0"<?php echo $cookie_sticky_heads == 'off' ? ' selected="selected"' : ''; ?>><?php echo JText::translate('VBOVERVIEWSTICKYTHEADOFF'); ?></option>
			</select>
		</div>
		<?php
	}

	if ($tags_view_supported === true) {
	?>
		<div class="btn-group pull-left">
			<div class="vbo-overview-tagsorclassic">
				<select name="bmode" onchange="document.vboverview.submit();">
					<option value="classic"><?php echo JText::translate('VBOAVOVWBMODECLASSIC'); ?></option>
					<option value="tags"<?php echo $pbmode == 'tags' ? ' selected="selected"' : ''; ?>><?php echo JText::translate('VBOAVOVWBMODETAGS'); ?></option>
				</select>
			</div>
		<?php
		if ($pbmode == 'tags' && count($colortags) > 0) {
			?>
			<div class="vbo-overview-tagslegend">
				<span class="vbo-overview-tagslegend-lbl"><?php echo JText::translate('VBOAVOVWBMODETAGSLBL'); ?></span>
			<?php
			foreach ($colortags as $ctagk => $ctagv) {
				?>
				<div class="vbo-overview-legend-tag">
					<div class="vbo-colortag-circle hasTooltip" style="background-color: <?php echo $ctagv['color']; ?>;" title="<?php echo JText::translate($ctagv['name']); ?>"></div>
				</div>
				<?php
			}
			?>
			</div>
			<?php
		}
		?>
		</div>
	<?php
	}
	?>
		<div class="btn-group pull-right">
			<select name="units_show_type" id="uleftorbooked" onchange="vboUnitsLeftOrBooked();"><option value="units-booked"<?php echo (!empty($cookie_uleft) && $cookie_uleft == 'units-booked' ? ' selected="selected"' : ''); ?>><?php echo JText::translate('VBOVERVIEWUBOOKEDFILT'); ?></option><option value="units-left"<?php echo $show_type == 'units-left' || (!empty($cookie_uleft) && $cookie_uleft == 'units-left') ? ' selected="selected"' : ''; ?>><?php echo JText::translate('VBOVERVIEWULEFTFILT'); ?></option></select>
		</div>
		<div class="btn-group pull-right vbo-avov-legend">
			<span class="vbo-overview-legend-init"><?php echo JText::translate('VBOVERVIEWLEGEND'); ?></span>
			<div class="vbo-overview-legend-red">
				<span class="vbo-overview-legend-box">&nbsp;</span>
				<span class="vbo-overview-legend-title"><?php echo JText::translate('VBOVERVIEWLEGRED'); ?></span>
			</div>
			<div class="vbo-overview-legend-yellow">
				<span class="vbo-overview-legend-box">&nbsp;</span>
				<span class="vbo-overview-legend-title"><?php echo JText::translate('VBOVERVIEWLEGYELLOW'); ?></span>
			</div>
			<div class="vbo-overview-legend-green">
				<span class="vbo-overview-legend-box">&nbsp;</span>
				<span class="vbo-overview-legend-title"><?php echo JText::translate('VBOVERVIEWLEGGREEN'); ?></span>
			</div>
			<div class="vbo-overview-legend-green vbo-overview-legend-dnd">
				<span class="vbo-overview-legend-box"><i class="vboicn-enlarge" style="margin: 1px; display: block; text-align: center;"></i></span>
				<span class="vbo-overview-legend-title"><?php echo JText::translate('VBOVERVIEWLEGDND'); ?></span>
			</div>
		</div>
	</div>
</form>

<?php
$todayymd = date('Y-m-d');
$nowts = getdate($tsstart);
$curts = $nowts;
for ($mind = 1; $mind <= $mnum; $mind++) {
	$monthname = VikBooking::sayMonth($curts['mon']);
	?>
<div class="vbo-overv-montable-wrap">
	<div class="vbo-table-responsive">
		<table class="vboverviewtable vbo-roverview-table vbo-table <?php echo $cookie_sticky_heads == 'off' ? 'vbo-overv-sticky-table-head-off' : 'vbo-overv-sticky-table-head-on'; ?>">
			<thead>
				<tr class="vboverviewtablerowone">
					<th class="bluedays skip-bluedays-click vbo-overview-month"><?php echo $monthname . " " . $curts['year']; ?></th>
				<?php
				$moncurts = $curts;
				$mon = $moncurts['mon'];
				while ($moncurts['mon'] == $mon) {
					$curdayymd = date('Y-m-d', $moncurts[0]);
					$read_day  = $days_labels[$moncurts['wday']] . ' ' . $moncurts['mday'] . ' ' . $monthname . ' ' . $curts['year'];
					echo '<th class="bluedays'.($todayymd == $curdayymd ? ' vbo-overv-todaycell' : '').(isset($this->festivities[$curdayymd]) ? ' vbo-overv-festcell' : '').'" data-ymd="'.$curdayymd.'" data-readymd="'.$read_day.'"><span class="vbo-overw-tablewday">'.$days_labels[$moncurts['wday']].'</span><span class="vbo-overw-tablemday">'.$moncurts['mday'].'</span></th>';
					$moncurts = getdate(mktime(0, 0, 0, $moncurts['mon'], ($moncurts['mday'] + 1), $moncurts['year']));
				}
				?>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ($rows as $room) {
				$moncurts = $curts;
				$mon = $moncurts['mon'];
				$room_tags_view = $pbmode == 'tags' && $tags_view_supported === true && $room['units'] <= 1 ? true : false;
				$is_subunit = (array_key_exists('unit_index', $room));
				echo '<tr class="vboverviewtablerow'.($is_subunit ? ' vboverviewtablerow-subunit' : '').'"'.($is_subunit ? ' data-subroomid="'.$room['id'].'-'.$room['unit_index'].'"' : '').'>'."\n";
				if ($is_subunit) {
					echo '<td class="roomname subroomname" data-roomid="-'.$room['id'].'"><span class="vbo-overview-subroomunits"><i class="'.VikBookingIcons::i('bed').'"></i></span><span class="vbo-overview-subroomname">'.$room['unit_index_str'].'</span></td>';
				} else {
					echo '<td class="roomname" data-roomid="'.$room['id'].'"><span class="vbo-overview-room-info"><span class="vbo-overview-roomunits">'.$room['units'].'</span><span class="vbo-overview-roomname">'.$room['name'].'</span>'.(array_key_exists($room['id'], $rooms_features_map) ? '<span class="vbo-overview-subroom-toggle"><i class="'.VikBookingIcons::i('chevron-down', 'hasTooltip').'" style="margin: 0;" title="'.addslashes(JText::translate('VBOVERVIEWTOGGLESUBROOM')).'"></i></span>' : '').'</span></td>';
				}
				$room_bids_pool = array();
				while ($moncurts['mon'] == $mon) {
					$dclass = !array_key_exists('unit_index', $room) ? "notbusy" : "subnotbusy";
					$is_checkin = false;
					$is_sharedcal = false;
					$is_closure = false;
					$lastbidcheckout = null;
					$dalt = "";
					$bid = "";
					$bids_pool = array();
					$totfound = 0;
					$cur_day_key = date('Y-m-d', $moncurts[0]);
					if (!empty($arrbusy[$room['id']]) && !array_key_exists('unit_index', $room)) {
						foreach ($arrbusy[$room['id']] as $b) {
							$tmpone = getdate($b['checkin']);
							$ritts = mktime(0, 0, 0, $tmpone['mon'], $tmpone['mday'], $tmpone['year']);
							$tmptwo = getdate($b['checkout']);
							$conts = mktime(0, 0, 0, $tmptwo['mon'], $tmptwo['mday'], $tmptwo['year']);
							if ($moncurts[0] >= $ritts && $moncurts[0] < $conts) {
								$dclass = "busy";
								$bid = $b['idorder'];
								$is_sharedcal = !empty($b['sharedcal']) ? true : $is_sharedcal;
								$is_closure = !empty($b['closure']) ? true : $is_closure;
								$bid_str = '-' . $bid . '-';
								if (!in_array($bid_str, $bids_pool)) {
									$bids_pool[] = $bid_str;
								}
								if (array_key_exists($room['id'], $rooms_features_map)) {
									if (!array_key_exists($cur_day_key, $room_bids_pool)) {
										$room_bids_pool[$cur_day_key] = array();
									}
									$room_bids_pool[$cur_day_key][] = (int)$bid;
								}
								if ($moncurts[0] == $ritts) {
									$dalt = JText::translate('VBPICKUPAT')." ".date('H:i', $b['checkin']);
									$is_checkin = true;
									$lastbidcheckout = $b['checkout'];
									$bids_checkins[$bid] = $cur_day_key;
								} elseif ($moncurts[0] == $conts) {
									$dalt = JText::translate('VBRELEASEAT')." ".date('H:i', $b['checkout']);
								}
								$totfound++;
							}
						}
					}
					//locked (stand-by) records - new in VBO 1.9
					if ($room_tags_view === true && array_key_exists($room['id'], $arrlocked) && count($arrlocked[$room['id']]) > 0 && !array_key_exists('unit_index', $room)) {
						foreach ($arrlocked[$room['id']] as $l) {
							$tmpone = getdate($l['checkin']);
							$ritts = mktime(0, 0, 0, $tmpone['mon'], $tmpone['mday'], $tmpone['year']);
							$tmptwo = getdate($l['checkout']);
							$conts = mktime(0, 0, 0, $tmptwo['mon'], $tmptwo['mday'], $tmptwo['year']);
							if ($moncurts[0] >= $ritts && $moncurts[0] < $conts) {
								$dclass = $dclass == "notbusy" ? "busytmplock" : "busy busytmplock";
								$bid = $l['idorder'];
								if (!in_array($bid, $bids_pool)) {
									if (count($bids_pool) > 0) {
										array_unshift($bids_pool, '-'.$bid.'-');
									} else {
										$bids_pool[] = '-'.$bid.'-';
									}
								}
								if ($moncurts[0] == $ritts) {
									$dalt = JText::translate('VBPICKUPAT')." ".date('H:i', $l['checkin']);
								} elseif ($moncurts[0] == $conts) {
									$dalt = JText::translate('VBRELEASEAT')." ".date('H:i', $l['checkout']);
								}
								$totfound++;
							}
						}
					}
					//
					$useday = ($moncurts['mday'] < 10 ? "0".$moncurts['mday'] : $moncurts['mday']);
					$dclass .= ($totfound < $room['units'] && $totfound > 0 ? ' vbo-partially' : '');
					$dclass .= $is_sharedcal ? ' busy-sharedcalendar' : '';
					$dclass .= $is_closure ? ' busy-closure' : '';
					$dstyle = '';
					$astyle = '';
					if ($room_tags_view === true && $totfound > 0) {
						$last_bid = intval(str_replace('-', '', $bids_pool[(count($bids_pool) - 1)]));
						$binfo = VikBooking::getBookingInfoFromID($last_bid);
						if (count($binfo) > 0) {
							$bcolortag = VikBooking::applyBookingColorTag($binfo);
							if (count($bcolortag) > 0) {
								$bcolortag['name'] = JText::translate($bcolortag['name']);
								$dstyle = " style=\"background-color: ".$bcolortag['color']."; color: ".(array_key_exists('fontcolor', $bcolortag) ? $bcolortag['fontcolor'] : '#ffffff').";\" data-lastbid=\"".$last_bid."\"";
								$astyle = " style=\"color: ".(array_key_exists('fontcolor', $bcolortag) ? $bcolortag['fontcolor'] : '#ffffff').";\"";
								$dclass .= ' vbo-hascolortag';
							}
						}
					}
					if (array_key_exists('unit_index', $room) && array_key_exists($room['id'], $rooms_features_bookings) && array_key_exists($cur_day_key, $rooms_bids_pools[$room['id']]) && array_key_exists($room['unit_index'], $rooms_features_bookings[$room['id']])) {
						foreach ($rooms_bids_pools[$room['id']][$cur_day_key] as $bid) {
							$bid = intval(str_replace('-', '', $bid));
							if (in_array($bid, $rooms_features_bookings[$room['id']][$room['unit_index']])) {
								$room['units'] = 1;
								$totfound = 1;
								$dclass = "subroom-busy";
								$is_checkin = isset($bids_checkins[$bid]) && $bids_checkins[$bid] == $cur_day_key ? true : $is_checkin;
								break;
							}
						}
					}
					$write_units = $show_type == 'units-left' || (!empty($cookie_uleft) && $cookie_uleft == 'units-left') ? ($room['units'] - $totfound) : $totfound;
					// check today's date
					$curdayymd = date('Y-m-d', $moncurts[0]);
					if ($todayymd == $curdayymd) {
						$dclass .= ' vbo-overv-todaycell';
					}
					if (isset($this->festivities[$curdayymd])) {
						$dclass .= ' vbo-overv-festcell';
					}
					//

					/**
					 * Critical dates defined at room-day level.
					 * 
					 * @since 	1.13.5
					 */
					$rdaynote_keyid = $cur_day_key . '_' . $room['id'] . '_' . (isset($room['unit_index']) ? $room['unit_index'] : '0');
					if (isset($this->rdaynotes[$rdaynote_keyid])) {
						// note exists for this combination of date, room ID and subunit
						$dclass .= ' vbo-roomdaynote-full';
						$rdaynote_icn = 'sticky-note';
					} else {
						// no notes for this cell
						$dclass .= ' vbo-roomdaynote-empty';
						$rdaynote_icn = 'far fa-sticky-note';
					}
					$critical_note = '<span class="vbo-roomdaynote-trigger" data-roomday="' . $rdaynote_keyid . '"><i class="' . VikBookingIcons::i($rdaynote_icn, 'vbo-roomdaynote-display') . '"></i></span>';
					//

					if ($totfound == 1) {
						$write_units = strpos($dclass, "subroom-busy") !== false ? '&bull;' : $write_units;
						$stopdrag = ($mind == $mnum && !is_null($lastbidcheckout) && (int)date('n', $lastbidcheckout) != (int)$mon);
						$dclass .= $is_checkin === true ? ' vbo-checkinday' : '';
						$dlnk = "<a href=\"index.php?option=com_vikbooking&task=editbusy&cid[]=".$bid."&goto=overv\" class=\"".(strpos($dclass, "subroom-busy") === false ? 'vbo-overview-redday' : 'vbo-overview-subredday')."\" data-units-booked=\"".$totfound."\" data-units-left=\"".($room['units'] - $totfound)."\"".$astyle.(!empty($dalt) ? " title=\"".$dalt."\"" : "").">".$write_units."</a>";
						$cal = "<td align=\"center\" class=\"".$dclass."\"".$dstyle." data-day=\"".$cur_day_key."\" data-bids=\"".(strpos($dclass, "subroom-busy") !== false ? '-'.$bid.'-' : implode(',', $bids_pool))."\">".($is_checkin === true && !$stopdrag ? '<span class="vbo-draggable-sp" draggable="true">'.$dlnk.'</span>' : $dlnk) . $critical_note . "</td>\n";
					} elseif ($totfound > 1) {
						$dlnk = "<a href=\"index.php?option=com_vikbooking&task=choosebusy&idroom=".$room['id']."&ts=".$moncurts[0]."&goto=overv\" class=\"vbo-overview-redday\" data-units-booked=\"".$totfound."\" data-units-left=\"".($room['units'] - $totfound)."\"".$astyle.">".$write_units."</a>";
						$cal = "<td align=\"center\" class=\"".$dclass."\"".$dstyle." data-day=\"".$cur_day_key."\" data-bids=\"".implode(',', $bids_pool)."\">" . $dlnk . $critical_note . "</td>\n";
					} else {
						$dlnk = $useday;
						$cal = "<td align=\"center\" class=\"".$dclass."\" data-day=\"".$cur_day_key."\" data-bids=\"\">{$critical_note}</td>\n";
					}
					echo $cal;
					$moncurts = getdate(mktime(0, 0, 0, $moncurts['mon'], ($moncurts['mday'] + 1), $moncurts['year']));
				}
				if (array_key_exists($room['id'], $rooms_features_map) && !array_key_exists('unit_index', $room) && count($room_bids_pool) > 0) {
					//load bookings for distinctive features when parsing the parent $room array
					$room_indexes_bids = VikBooking::loadRoomIndexesBookings($room['id'], $room_bids_pool);
					if (count($room_indexes_bids) > 0) {
						$rooms_features_bookings[$room['id']] = $room_indexes_bids;
						$rooms_bids_pools[$room['id']] = $room_bids_pool;
					}
					//
				}
				echo '</tr>'."\n";
			}
			?>
			</tbody>
		</table>
	</div>
</div>
	<?php
	$curts = getdate(mktime(0, 0, 0, ($nowts['mon'] + $mind), $nowts['mday'], $nowts['year']));
}

//Prepare modal
?>
<script type="text/javascript">
var hasNewBooking = false;
var last_room_click = '',
	last_date_click = '',
	next_date_click = '';
function vboJModalHideCallback() {
	if (hasNewBooking === true) {
		location.reload();
	}
}
function vboCallOpenJModal(identif, baseurl) {
	if (last_room_click && last_room_click.length) {
		baseurl += '&cid[]='+last_room_click;
		last_room_click = '';
	}
	if (last_date_click && last_date_click.length) {
		baseurl += '&checkin='+last_date_click;
		last_date_click = '';
	}
	if (next_date_click && next_date_click.length) {
		baseurl += '&checkout='+next_date_click;
		next_date_click = '';
	}
	vboOpenJModal(identif, baseurl);
}
</script>
<?php
echo $vbo_app->getJmodalScript('', 'vboJModalHideCallback();', '');
echo $vbo_app->getJmodalHtml('vbo-new-res', JText::translate('VBOSHOWQUICKRES'));
//end Prepare modal
?>
<div class="vbo-ovrv-flt-butn" onclick="vboCallOpenJModal('vbo-new-res', 'index.php?option=com_vikbooking&task=calendar&overv=1&tmpl=component');"><span><i class="vboicn-user-plus"></i> <?php echo JText::translate('VBOSHOWQUICKRES'); ?></span></div>
<div class="vbo-info-overlay-block">
	<div class="vbo-info-overlay-loading-dnd">
		<span class="vbo-loading-dnd-head"></span>
		<span class="vbo-loading-dnd-body"></span>
		<span class="vbo-loading-dnd-footer"><?php echo JText::translate('VIKLOADING'); ?></span>
		<span id="vbo-dnd-response" class="vbo-loading-dnd-response"></span>
		<canvas id="vbo-dnd-canvas-success" height="250"></canvas>
	</div>
</div>

<form action="index.php?option=com_vikbooking" method="post" name="adminForm" id="adminForm">
	<input type="hidden" name="option" value="com_vikbooking" />
	<input type="hidden" name="task" value="overv" />
	<input type="hidden" name="month" value="<?php echo $tsstart; ?>" />
	<input type="hidden" name="mnum" value="<?php echo $mnum; ?>" />
	<input type="hidden" name="category_id" value="<?php echo $pcategory_id; ?>" />
	<?php echo '<br/>'.$navbut; ?>
</form>

<a class="vbo-basenavuri-details" href="index.php?option=com_vikbooking&task=editorder&goto=overv&cid[]=%d" style="display: none;"></a>
<a class="vbo-basenavuri-edit" href="index.php?option=com_vikbooking&task=editbusy&goto=overv&cid[]=%d" style="display: none;"></a>

<script type="text/Javascript">
var hovtimer;
var hovtip = false;
var vbodialogorph_on = false;
var isdragging = false;
var vboMessages = {
	notEnoughCells: "<?php echo addslashes(JText::translate('VBOVWDNDERRNOTENCELLS')); ?>",
	movingBookingId: "<?php echo addslashes(JText::translate('VBOVWDNDMOVINGBID')); ?>",
	switchRoomTo: "<?php echo addslashes(JText::translate('VBOVWDNDMOVINGROOM')); ?>",
	switchDatesTo: "<?php echo addslashes(JText::translate('VBOVWDNDMOVINGDATES')); ?>",
	cancelText: "<?php echo addslashes(JText::translate('VBANNULLA')); ?>",
	loadingTip: "<?php echo addslashes(JText::translate('VIKLOADING')); ?>",
	numRooms: "<?php echo addslashes(JText::translate('VBEDITORDERROOMSNUM')); ?>",
	numAdults: "<?php echo addslashes(JText::translate('VBEDITORDERADULTS')); ?>",
	numNights: "<?php echo addslashes(JText::translate('VBDAYS')); ?>",
	checkinLbl: "<?php echo addslashes(JText::translate('VBPICKUPAT')); ?>",
	checkoutLbl: "<?php echo addslashes(JText::translate('VBRELEASEAT')); ?>",
	numChildren: "<?php echo addslashes(JText::translate('VBEDITORDERCHILDREN')); ?>",
	totalAmount: "<?php echo addslashes(JText::translate('VBEDITORDERNINE')); ?>",
	totalPaid: "<?php echo addslashes(JText::translate('VBPEDITBUSYTOTPAID')); ?>",
	assignUnit: "<?php echo addslashes(JText::translate('VBOFEATASSIGNUNIT')); ?>",
	isSplitStay: "<?php echo addslashes(JText::translate('VBO_SPLIT_STAY')); ?>",
	currencySymb: "<?php echo $currencysymb; ?>"
};
var debug_mode = '<?php echo $pdebug; ?>';
var bctags_count = <?php echo count($colortags); ?>;
var bctags_pool = <?php echo json_encode($colortags); ?>;
<?php
if (count($colortags) > 0) {
	$bctags_tip = '<div class=\"vbo-overview-tip-bctag-subtip-inner\">';
	foreach ($colortags as $ctagk => $ctagv) {
		$bctags_tip .= '<div class=\"vbo-overview-tip-bctag-subtip-circle hasTooltip\" data-ctagkey=\"'.$ctagk.'\" data-ctagcolor=\"'.$ctagv['color'].'\" title=\"'.addslashes(JText::translate($ctagv['name'])).'\"><div class=\"vbo-overview-tip-bctag-subtip-circlecont\" style=\"background-color: '.$ctagv['color'].';\"></div></div>';
	}
	$bctags_tip .= '</div>';
	?>
var bctags_tip = "<?php echo $bctags_tip; ?>";
	<?php
}
?>
</script>

<script type="text/Javascript">
/**
 * Render the units view mode
 */
vboUnitsLeftOrBooked();

/**
 * Orphans dialog
 */
function hideVboDialogOverv(action) {
	if (vbodialogorph_on === true) {
		jQuery(".vbo-orphans-overlay-block").fadeOut(400, function () {
			jQuery(".vbo-info-overlay-content").show();
		});
		vbodialogorph_on = false;
	}
	// check action
	if (action < 0) {
		// stop reminding, set cookie
		var nd = new Date();
		nd.setTime(nd.getTime() + (365*24*60*60*1000));
		document.cookie = "vboHideOrphans=1; expires=" + nd.toUTCString() + "; path=/; SameSite=Lax";
	}
}

/* DnD global vars */
var cellspool = [];
var newcellspool = [];

/* DnD Count the number of consecutive cells for this booking */
function countBookingCells(cellobj, bidstart, roomid) {
	var totnights = 1;
	var loop = true;
	var cellelem = cellobj;
	cellspool.push(cellelem);
	while(loop === true) {
		var next = cellelem.next('td');
		if (next === undefined || !next.length) {
			//attempt to go to the month after
			var partable = cellelem.closest('tr').closest('table').nextAll('table.vboverviewtable').first();
			var nextmonth = false;
			if (partable !== undefined && partable.length) {
				partable.find('tr.vboverviewtablerow').each(function() {
					var roomexists = jQuery(this).find('td').first();
					if (roomexists !== undefined && roomexists.length) {
						if (roomexists.attr('data-roomid') == roomid) {
							nextmonth = true;
							next = roomexists.next('td');
							return true;
						}
					}
				});
			}
			if (nextmonth === false) {
				//nothing was found in the month after
				loop = false;
				break;
			}
		}
		cellelem = next;
		var nextbids = cellelem.attr('data-bids');
		if (nextbids.length && nextbids.indexOf(bidstart) >= 0) {
			cellspool.push(cellelem);
			totnights++;
		} else {
			loop = false;
			break;
		}
	}
	return totnights;
}

/* DnD Count the number of consecutive free date-cells for moving the booking onto the landing cell selected for drop */
function countCellFreeNights(landobj, roomid, totnights) {
	var freenights = 1;
	var loop = true;
	var cellelem = landobj;
	newcellspool.push(cellelem);
	while(loop === true) {
		var next = cellelem.next('td');
		if (next === undefined || !next.length) {
			//attempt to go to the month after
			var partable = cellelem.closest('tr').closest('table').nextAll('table.vboverviewtable').first();
			var nextmonth = false;
			if (partable !== undefined && partable.length) {
				partable.find('tr.vboverviewtablerow').each(function() {
					var roomexists = jQuery(this).find('td').first();
					if (roomexists !== undefined && roomexists.length) {
						if (roomexists.attr('data-roomid') == roomid) {
							nextmonth = true;
							next = roomexists.next('td');
							return true;
						}
					}
				});
			}
			if (nextmonth === false) {
				//nothing was found in the month after
				loop = false;
				break;
			}
		}
		cellelem = next;
		if (!cellelem.hasClass('busy') || (cellelem.hasClass('busy') && cellelem.hasClass('vbo-partially'))) {
			//bookings of 1 night can stop here because there is availability for one day
			if (parseInt(totnights) === 1) {
				loop = false;
				break;
			}
			//
			newcellspool.push(cellelem);
			freenights++;
			if (freenights >= totnights) {
				loop = false;
				break;
			}
		} else {
			loop = false;
			break;
		}
	}
	return freenights;
}

/* DnD function to perform the ajax request of the booking modification */
function doAlterBooking(bid, roomid, landrid) {
	var nowdatefrom = jQuery(newcellspool[0]).attr('data-day');
	var nowdateto = jQuery(newcellspool[(newcellspool.length -1)]).attr('data-day');
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=alterbooking'); ?>",
		data: {
			tmpl: "component",
			idorder: bid,
			oldidroom: roomid,
			idroom: landrid,
			fromdate: nowdatefrom,
			todate: nowdateto,
			e4j_debug: debug_mode
		}
	}).done(function(res) {
		if (res.indexOf('e4j.error') >= 0 ) {
			console.log('doAlterBooking-- Booking ID: '+bid+' - Old Room ID: '+roomid+' - New Room ID: '+landrid+' - New Date From: '+nowdatefrom+' - New Date To: '+nowdateto);
			console.log(res);
			alert(res.replace("e4j.error.", ""));
			//restore the old cells
			jQuery('td.vbo-dragging-cells-tmp').removeClass('vbo-dragging-cells-tmp');
			jQuery('td.vbo-dragged-cells-tmp').removeClass('vbo-dragged-cells-tmp');
			//
			jQuery(".vbo-info-overlay-block").hide();
		} else {
			//move to the new cells and if there are already bookings on those dates, reload the page without moving the blocks
			var obj_res = typeof res === 'string' ? JSON.parse(res) : res;
			var mustReload = obj_res.esit < 1 ? true : false;
			//always force reload if booking was made for more than one room
			var samebidcells = jQuery("td.vbo-checkinday[data-bids='"+bid+"']");
			if (samebidcells.length > 1) {
				mustReload = true;
			}
			//
			jQuery(newcellspool).each(function(k, v) {
				var cur_units_booked = parseInt(jQuery(cellspool[k]).find('a').attr('data-units-booked'));
				if ((v.hasClass('busy') && v.attr('data-bids') != jQuery(cellspool[k]).attr('data-bids')) || isNaN(cur_units_booked) || cur_units_booked > 1) {
					mustReload = true;
					return false;
				}
			});
			if (mustReload !== true) {
				/* switch cells */
				var switchmap = [];
				jQuery(cellspool).each(function(k, v) {
					var switchcell = {
						hcont: v.html(),
						cl: v.attr('class'),
						bids: v.attr('data-bids'),
						tit: v.attr('title')
					};
					switchmap[k] = switchcell;
					v.html("").attr('class', 'notbusy').attr('data-bids', '').attr('title', '');
				});
				jQuery(switchmap).each(function(k, v) {
					jQuery(newcellspool[k]).html(v.hcont).attr('class', v.cl).attr('data-bids', v.bids).attr('title', v.tit);
					// re-bind hover event
					jQuery(newcellspool[k]).hover(function() {
						registerHoveringTooltip(this);
					}, unregisterHoveringTooltip);
					//
				});
				jQuery('td.vbo-dragging-cells-tmp').removeClass('vbo-dragging-cells-tmp');
				jQuery('td.vbo-dragged-cells-tmp').removeClass('vbo-dragged-cells-tmp');
			}
			if (obj_res.esit < 1) {
				//some errors occurred after executing certain functions
				if (obj_res.message.length) {
					alert(obj_res.message);
				}
				document.location.href='index.php?option=com_vikbooking&task=overv';
			} else {
				finalizeDndUpdate(mustReload, obj_res);
			}
		}
	}).fail(function() { 
		alert("Request Failed");
		//restore the old cells
		jQuery('td.vbo-dragging-cells-tmp').removeClass('vbo-dragging-cells-tmp');
		jQuery('td.vbo-dragged-cells-tmp').removeClass('vbo-dragged-cells-tmp');
		//
		jQuery(".vbo-info-overlay-block").hide();
	});
}

/* DnD function to animate a success checkmark. The function may refresh the page once complete as this could be launched when there are multiple units for the rooms */
function finalizeDndUpdate(mustReload, obj_res) {
	jQuery('#vbo-dnd-response').html(obj_res.message+' '+obj_res.vcm);
	jQuery('.vbo-loading-dnd-footer').hide();
	var start = 100;
	var mid = 145;
	var end = 250;
	var width = 22;
	var leftX = start;
	var leftY = start;
	var rightX = mid - (width / 2.7);
	var rightY = mid + (width / 2.7);
	var animationSpeed = 20;
	var closingdelay = 700;
	var ctx = document.getElementById('vbo-dnd-canvas-success').getContext('2d');
	ctx.lineWidth = width;
	ctx.strokeStyle = 'rgba(0, 150, 0, 1)';
	for (var i = start; i < mid; i++) {
		var drawLeft = window.setTimeout(function () {
			ctx.beginPath();
			ctx.moveTo(start, start);
			ctx.lineTo(leftX, leftY);
			ctx.stroke();
			leftX++;
			leftY++;
		}, 1 + (i * animationSpeed) / 3);
	}
	for (var i = mid; i < end; i++) {
		var drawRight = window.setTimeout(function () {
			ctx.beginPath();
			ctx.moveTo(leftX, leftY);
			ctx.lineTo(rightX, rightY);
			ctx.stroke();
			rightX++;
			rightY--;
		}, 1 + (i * animationSpeed) / 3);
	}
	//hide modal window
	window.setTimeout(function () {
		if (obj_res.vcm.length) {
			var vcmbtn = '<br clear="all"/><button type="button" class="btn btn-danger" onclick="'+(mustReload === true ? 'document.location.href=\'index.php?option=com_vikbooking&task=overv\'' : 'closeEsitDialog();')+'">'+vboMessages.cancelText+'</button>';
			jQuery('#vbo-dnd-response').append(vcmbtn);
		} else {
			if (mustReload === true) {
				document.location.href='index.php?option=com_vikbooking&task=overv';
			} else {
				jQuery('.vbo-info-overlay-block').fadeOut(400, function(){
					//clear/reset canvas in case of previous drawing and response text
					ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
					jQuery('#vbo-dnd-response').html("");
					//
				});
			}
		}
	}, closingdelay + (i * animationSpeed) / 3);
	//
}

/* DnD function that can be called by those with VCM that have disabled the automated updates. Simply closes the modal window */
function closeEsitDialog() {
	jQuery('.vbo-info-overlay-block').fadeOut(400, function(){
		//clear/reset canvas in case of previous drawing and response text
		var ctx = document.getElementById('vbo-dnd-canvas-success').getContext('2d');
		ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
		jQuery('#vbo-dnd-response').html("");
		//
	});
}

/* Hover Tooltip functions */
function registerHoveringTooltip(that) {
	if (hovtip) {
		return false;
	}
	if (hovtimer) {
		clearTimeout(hovtimer);
		hovtimer = null;
	}
	var elem = jQuery(that);
	var cellheight = elem.outerHeight();
	var celldata = new Array();
	if (elem.hasClass('subroom-busy')) {
		celldata.push(elem.parent('tr').attr('data-subroomid'));
		celldata.push(elem.attr('data-day'));
	}
	hovtimer = setTimeout(function() {
		if (isdragging) {
			// prevent tooltip from popping up if dragging
			unregisterHoveringTooltip();
			return;
		}
		hovtip = true;
		jQuery(
			"<div class=\"vbo-overview-tipblock\">"+
				"<div class=\"vbo-overview-tipinner\"><span class=\"vbo-overview-tiploading\">"+vboMessages.loadingTip+"</span></div>"+
				"<div class=\"vbo-overview-tipexpander\" style=\"display: none;\"><div class=\"vbo-overview-expandtoggle\"><i class=\"<?php echo VikBookingIcons::i('expand'); ?>\"></i></div></div>"+
			"</div>"
		).appendTo(elem);
		jQuery(".vbo-overview-tipblock").css("bottom", "+="+cellheight);
		loadTooltipBookings(elem.attr('data-bids'), celldata);
	}, 1500);
}

function unregisterHoveringTooltip() {
	clearTimeout(hovtimer);
	hovtimer = null;
}

function adjustHoveringTooltip() {
	setTimeout(function() {
		var ver_difflim = 35;
		var hor_difflim = 20;
		var tip_block = jQuery('.vbo-overview-tipblock');
		var table_wrap = tip_block.closest('.vbo-overv-montable-wrap');

		if (tip_block.outerHeight() > table_wrap.outerHeight()) {
			// tooltip is too tall to fit in the table, render the modal instead
			tip_block.hide().closest('td.busy').trigger('click');
			return;
		}

		// vertical positioning
		var otop = tip_block.offset().top;
		if (otop < ver_difflim) {
			// adjust tooltip position
			tip_block.css('bottom', '-=' + (ver_difflim - otop));
		} else if (otop < table_wrap.offset().top) {
			// tooltip exceeds wrapping table, move it underneath the cell
			var css_bot = tip_block.css('bottom');
			tip_block.css('top', css_bot + 'px');
			tip_block.css('bottom', 'unset');
		}

		// horizontal positioning
		if (tip_block.offset().left < table_wrap.offset().left) {
			var left_diff = table_wrap.offset().left - tip_block.offset().left;
			if (left_diff > hor_difflim) {
				// tooltip exceeds table on the left, move it to the right of the cell (i.e 1st day of month)
				tip_block.css('right', tip_block.css('left'));
				tip_block.css('left', 'unset');
			}
		}
	}, 60);
}

function hideVboTooltip() {
	jQuery('.vbo-overview-tipblock').remove();
	hovtip = false;
}

function loadTooltipBookings(bids, celldata) {
	if (!bids || bids === undefined || !bids.length) {
		hideVboTooltip();
		return false;
	}
	var subroomdata = celldata.length ? celldata[0] : '';
	// ajax request
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=getbookingsinfo'); ?>",
		data: {
			tmpl: "component",
			idorders: bids,
			subroom: subroomdata
		}
	}).done(function(res) {
		try {
			var obj_res = typeof res === 'string' ? JSON.parse(res) : res;
			jQuery('.vbo-overview-tiploading').remove();
			var container = jQuery('.vbo-overview-tipinner');
			jQuery(obj_res).each(function(k, v) {
				// get base navigation URIs
				var base_uri_details = jQuery('.vbo-basenavuri-details').attr('href');
				var base_uri_edit = jQuery('.vbo-basenavuri-edit').attr('href');

				// build content
				var bcont = "<div class=\"vbo-overview-tip-bookingcont\">";
				bcont += "<div class=\"vbo-overview-tip-bookingcont-left\">";
				bcont += "<div class=\"vbo-overview-tip-bid\"><span class=\"vbo-overview-tip-lbl\">ID <span class=\"vbo-overview-tip-lbl-innerleft\"><a href=\"" + base_uri_edit.replace('%d', v.id) + "\"><i class=\"<?php echo VikBookingIcons::i('edit'); ?>\"></i></a></span></span><span class=\"vbo-overview-tip-cnt\">"+v.id+"</span></div>";
				bcont += "<div class=\"vbo-overview-tip-bstatus\"><span class=\"vbo-overview-tip-lbl\"><?php echo addslashes(JText::translate('VBPVIEWORDERSEIGHT')); ?></span><span class=\"vbo-overview-tip-cnt\"><div class=\"label "+(v.status == 'confirmed' ? 'label-success' : 'label-warning')+"\">"+v.status_lbl+"</div></span></div>";
				bcont += "<div class=\"vbo-overview-tip-bdate\"><span class=\"vbo-overview-tip-lbl\"><?php echo addslashes(JText::translate('VBPVIEWORDERSONE')); ?></span><span class=\"vbo-overview-tip-cnt\"><a href=\"" + base_uri_details.replace('%d', v.id) + "\">"+v.ts+"</a></span></div>";
				if (bctags_count > 0) {
					var bctag_title = '';
					var bctag_color = '#ffffff';
					if (v.colortag.hasOwnProperty('color')) {
						bctag_color = v.colortag.color;
						bctag_title = v.colortag.name;
					}
					bcont += "<div class=\"vbo-overview-tip-bctag-wrap\"><div class=\"vbo-overview-tip-bctag\" data-bid=\""+v.id+"\" data-ctagcolor=\""+bctag_color+"\" style=\"background-color: "+bctag_color+"; color: "+v.colortag.fontcolor+";\" title=\""+bctag_title+"\"><i class=\"vboicn-price-tags\"></i></div></div>";
				}
				bcont += "</div>";
				bcont += "<div class=\"vbo-overview-tip-bookingcont-right\">";
				bcont += "<div class=\"vbo-overview-tip-bcustomer\"><span class=\"vbo-overview-tip-lbl\"><?php echo addslashes(JText::translate('VBOCUSTOMER')); ?></span><span class=\"vbo-overview-tip-cnt\">"+v.cinfo+"</span></div>";
				if (v.roomsnum > 1) {
					bcont += "<div class=\"vbo-overview-tip-brooms\"><span class=\"vbo-overview-tip-lbl\">"+vboMessages.numRooms+(parseInt(v.roomsnum) > 1 ? " ("+v.roomsnum+")" : "")+"</span><span class=\"vbo-overview-tip-cnt\">"+v.room_names+"</span></div>";
				}
				bcont += "<div class=\"vbo-overview-tip-bguests\"><span class=\"vbo-overview-tip-lbl\">" + vboMessages.numNights + (v.split_stay > 0 ? ' (' + vboMessages.isSplitStay + ')' : '') + "</span><span class=\"vbo-overview-tip-cnt hasTooltip\" title=\""+vboMessages.checkinLbl+" "+v.checkin+" - "+vboMessages.checkoutLbl+" "+v.checkout+"\">"+v.days+", "+vboMessages.numAdults+": "+v.tot_adults+(v.tot_children > 0 ? ", "+vboMessages.numChildren+": "+v.tot_children : "")+"</span></div>";
				if (v.hasOwnProperty('rindexes')) {
					for (var rindexk in v.rindexes) {
						if (v.rindexes.hasOwnProperty(rindexk)) {
							bcont += "<div class=\"vbo-overview-tip-brindexes\"><span class=\"vbo-overview-tip-lbl\">"+rindexk+"</span><span class=\"vbo-overview-tip-cnt\">"+v.rindexes[rindexk]+"</span></div>";
						}
					}
				}
				if (v.hasOwnProperty('channelimg')) {
					bcont += "<div class=\"vbo-overview-tip-bprovenience\"><span class=\"vbo-overview-tip-lbl\"><?php echo addslashes(JText::translate('VBPVIEWORDERCHANNEL')); ?></span><span class=\"vbo-overview-tip-cnt\">"+v.channelimg+"</span></div>";
				}
				if (v.hasOwnProperty('optindexes') && celldata.length) {
					var subroomids = celldata[0].split('-');
					bcont += "<div class=\"vbo-overview-tip-optindexes\"><span class=\"vbo-overview-tip-lbl\"> </span><span class=\"vbo-overview-tip-cnt\"><select onchange=\"vboMoveSubunit('"+v.id+"', '"+subroomids[0]+"', '"+subroomids[1]+"', this.value, '"+celldata[1]+"');\">"+v.optindexes+"</select></span></div>";
				}
				bcont += "<div class=\"vbo-overview-tip-bookingcont-total\">";
				bcont += "<div class=\"vbo-overview-tip-btot\"><span class=\"vbo-overview-tip-lbl\">"+vboMessages.totalAmount+"</span><span class=\"vbo-overview-tip-cnt\">"+vboMessages.currencySymb+" "+v.format_tot+"</span></div>";
				if (v.totpaid > 0.00) {
					bcont += "<div class=\"vbo-overview-tip-btot\"><span class=\"vbo-overview-tip-lbl\">"+vboMessages.totalPaid+"</span><span class=\"vbo-overview-tip-cnt\">"+vboMessages.currencySymb+" "+v.format_totpaid+"</span></div>";
				}
				var getnotes = v.adminnotes;
				if (getnotes !== null && getnotes.length) {
					bcont += "<div class=\"vbo-overview-tip-notes\"><span class=\"vbo-overview-tip-lbl\"><span class=\"vbo-overview-tip-notes-inner\"><i class=\"vboicn-info hasTooltip\" title=\""+getnotes+"\"></i></span></span></div>";
				}
				bcont += "</div>";
				bcont += "</div>";
				bcont += "</div>";
				container.append(bcont);
				jQuery('.vbo-overview-tipexpander').show();
			});
			// adjust the position so that it won't go under other contents
			adjustHoveringTooltip()
			//
			jQuery(".hasTooltip").tooltip();
		} catch(err) {
			// restore
			hideVboTooltip();
			// display error
			console.error('could not parse JSON response', err, res);
			alert('Could not parse JSON response');
		}
	}).fail(function(err) { 
		// restore
		hideVboTooltip();
		// display error
		console.error(err);
		alert(err.responseText);
	});
}

/**
 * Set a sub-unit to a room booking. Triggered by the room-day bookings modal
 */
function vboSetSubunit(bid, rid, orkey, rindex) {
	if (!(rindex + '').length) {
		return false;
	}

	if (!confirm(vboMessages.assignUnit + rindex + '?')) {
		return false;
	}

	// show loading
	VBOCore.emitEvent('vbo-loading-modal-overv-rdaybookings');

	// make the request to set the room sub-unit
	VBOCore.doAjax(
		"<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=bookings.set_room_booking_subunit'); ?>",
		{
			bid: bid,
			rid: rid,
			orkey: orkey,
			rindex: rindex,
			tmpl: 'component'
		},
		(res) => {
			// dismiss the modal (no need to stop the loading)
			VBOCore.emitEvent('vbo-dismiss-modal-overv-rdaybookings');

			try {
				var obj_res = typeof res === 'string' ? JSON.parse(res) : res;
				if (!obj_res.hasOwnProperty('nights') || !Array.isArray(obj_res['nights']) || !obj_res['nights'].length) {
					// invalid response
					throw new Error('Invalid response');
				}

				// check if cells can be occupied
				var sub_rows = jQuery('tr.vboverviewtablerow-subunit[data-subroomid="' + rid + '-' + rindex + '"]');
				if (!sub_rows || !sub_rows.length) {
					console.error('Could not find any room sub-unit row');
					return false;
				}

				// loop through all sub-unit rows to match the cells for the nights affected
				sub_rows.each(function() {
					var sub_row = jQuery(this);
					obj_res['nights'].forEach((ymd, kindex) => {
						let sub_row_cell = sub_row.find('td[data-day="' + ymd + '"]');
						if (sub_row_cell.length) {
							sub_row_cell.removeClass('subnotbusy').addClass('subroom-busy');
							if (kindex == 0) {
								sub_row_cell.addClass('vbo-checkinday');
							}
							sub_row_cell.attr('data-bids', '-' + obj_res['bid'] + '-');
							if (!sub_row_cell.find('span.vbo-draggable-sp').length) {
								sub_row_cell.prepend('<span class="vbo-draggable-sp"><a class="vbo-overview-subredday">&bull;</a></span>');
							}
						}
					});
				});
			} catch(err) {
				console.error('could not parse JSON response', err, res);
				alert('Could not parse JSON response');
			}
		},
		(err) => {
			// stop loading
			VBOCore.emitEvent('vbo-loading-modal-overv-rdaybookings');

			// log and display the error
			console.error(err);
			alert(err.responseText);
		}
	);
}

/**
 * Move a subunit group of cells to another subroom row. Triggered by the Hover Tooltip or by the room-day bookings modal
 */
function vboMoveSubunit(bid, rid, old_rindex, new_rindex, dday) {
	if (confirm(vboMessages.assignUnit+new_rindex.replace('#', '')+'?')) {
		// check if movement can be made
		var cur_tr = jQuery('tr.vboverviewtablerow-subunit[data-subroomid="'+rid+'-'+old_rindex+'"]');
		if (!cur_tr || !cur_tr.length) {
			console.error('could not find the parent row of the subunit cells');
			return false;
		}
		var maincell = cur_tr.find('td.subroom-busy[data-day="'+dday+'"]');
		if (!maincell || !maincell.length) {
			console.error('could not find the main cell of the subunit to move');
			return false;
		}
		var dest_tr = jQuery('tr.vboverviewtablerow-subunit[data-subroomid="'+rid+'-'+new_rindex+'"]');
		if (!dest_tr || !dest_tr.length) {
			console.error('could not find the destination row for the subunit cells');
			return false;
		}
		var targetbids = maincell.attr('data-bids');
		if (targetbids.indexOf('-'+bid+'-') < 0) {
			console.error('given bid does not match with cell bids', bid, targetbids);
			return false;
		}
		var firstcell = maincell;
		var loop = true;
		while (loop === true) {
			var prevsib = firstcell.prev();
			if (prevsib && prevsib.length && prevsib.attr('data-bids').length && prevsib.attr('data-bids').indexOf('-'+bid+'-') >= 0) {
				firstcell = prevsib;
			} else {
				loop = false;
			}
		}
		dday = firstcell.attr('data-day');
		var destcell = dest_tr.find('td.subnotbusy[data-day="'+dday+'"]');
		if (!destcell || !destcell.length) {
			console.error('could not find the first free destination cell');
			return false;
		}
		// check if all dates are free before making movements. Redundant, but useful.
		var freedates = true;
		var copyfirstcell = firstcell;
		var copydday = dday;
		loop = true;
		while (loop === true) {
			var nextsib = copyfirstcell.next();
			if (nextsib && nextsib.length && nextsib.attr('data-bids').length && nextsib.attr('data-bids').indexOf('-'+bid+'-') >= 0) {
				copyfirstcell = nextsib;
				copydday = copyfirstcell.attr('data-day');
				var copydestcell = dest_tr.find('td.subnotbusy[data-day="'+copydday+'"]');
				if (!copydestcell || !copydestcell.length) {
					console.error('could not find the next free destination cell for '+copydday);
					freedates = false;
					loop = false;
				}
			} else {
				loop = false;
			}
		}
		if (freedates === false) {
			return false;
		}
		// ajax request
		jQuery('.vbo-overview-tipblock').css('opacity', '0.6');
		var jqxhr = jQuery.ajax({
			type: "POST",
			url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=switchRoomIndex'); ?>",
			data: {
				tmpl: "component",
				bid: bid,
				rid: rid,
				old_rindex: old_rindex,
				new_rindex: new_rindex
			}
		}).done(function(res) {
			if (res.indexOf('e4j.error') >= 0 ) {
				console.log(res);
				alert(res.replace("e4j.error.", ""));
				// restore loading opacity in container
				jQuery('.vbo-overview-tipblock').css('opacity', '1');
			} else {
				// hide the tooltip
				hideVboTooltip();
				// move the cells
				loop = true;
				while (loop === true) {
					destcell.attr('class', firstcell.attr('class'))
						.attr('data-bids', firstcell.attr('data-bids'))
						.html(firstcell.html());
					// re-bind hover event
					jQuery(destcell).hover(function() {
						registerHoveringTooltip(this);
					}, unregisterHoveringTooltip);
					firstcell.attr('class', 'subnotbusy')
						.attr('data-bids', '')
						.html('&nbsp;');
					var nextsib = firstcell.next();
					if (nextsib && nextsib.length && nextsib.attr('data-bids').length && nextsib.attr('data-bids').indexOf('-'+bid+'-') >= 0) {
						firstcell = nextsib;
						dday = firstcell.attr('data-day');
						destcell = dest_tr.find('td.subnotbusy[data-day="'+dday+'"]');
						if (!destcell || !destcell.length) {
							console.error('could not find the next free destination cell');
							loop = false;
						}
					} else {
						loop = false;
					}
				}
			}
		}).fail(function() {
			alert("Request Failed");
			// restore loading opacity in container
			jQuery('.vbo-overview-tipblock').css('opacity', '1');
		});
		return true;
	}
	return false;
}

/**
 * Open a booking in a new tab.
 */
function vboOvervOpenBooking(bid) {
	var open_url = jQuery('.vbo-basenavuri-details').attr('href');
	open_url = open_url.replace('%d', bid);
	// navigate in a new tab
	window.open(open_url, '_blank');
}

/**
 * jQuery ready state
 */
jQuery(function() {
	/* calculate padding to increase draggable area and avoid "display: table" for the parent TD and "display: table-cell" for the draggable SPAN with 100% width and height, middle aligns */
	jQuery("td.vbo-checkinday span").each(function(k, v) {
		var parentheight = jQuery(this).closest('td').height();
		var spheight = jQuery(this).height();
		var padsp = Math.floor((parentheight - spheight) / 2);
		jQuery(this).css({"padding": padsp+"px 0"});
	});
	/* end padding calculation */
	
	/* Expand/Collapse tooltip */
	jQuery(document.body).on("click", ".vbo-overview-expandtoggle", function() {
		jQuery(this).closest('.vbo-overview-tipblock').toggleClass('vbo-overview-tipblock-expanded');
	});
	/* ----------------------- */

	/* DnD Start */

	/* DnD Event dragstart */
	jQuery(document.body).on("dragstart", "td.vbo-checkinday span", function(e) {
		// start dragging and prevent tooltip from popping up
		isdragging = true;
		//
		if (hovtip === true) {
			hideVboTooltip();
		}
		if (jQuery(this).hasClass("busytmplock")) {
			return false;
		}
		cellspool = [];
		var dt = e.originalEvent.dataTransfer;
		var parentcell = jQuery(this).closest('td');
		var bidstart = parentcell.attr('data-bids');
		var checkind = parentcell.attr('data-day');
		var roomid = parentcell.parent('tr').find('td').first().attr('data-roomid');
		if (!bidstart.length || !checkind.length || !roomid.length) {
			return false;
		}
		var totnights = countBookingCells(parentcell, bidstart, roomid);
		jQuery(this).addClass('vbo-dragging-sp').attr('id', 'vbo-dragging-elem');
		dt.setData('Id', 'vbo-dragging-elem');
		dt.setData('Checkin', checkind);
		dt.setData('Roomid', roomid);
		dt.setData('Bid', bidstart);
		dt.setData('Nights', totnights);
	});

	/* DnD Events dragenter dragover drop */
	jQuery(document.body).on("dragenter dragover drop", "td.notbusy, td.busy", function(e) {
		e.preventDefault();
		if (e.type === 'drop') {
			newcellspool = [];
			var dt = e.originalEvent.dataTransfer;
			var cid = dt.getData('Id');
			var checkind = dt.getData('Checkin');
			var roomid = dt.getData('Roomid');
			var bid = dt.getData('Bid');
			var totnights = dt.getData('Nights');
			/* check if drop is allowed */
			var landrid = jQuery(this).closest('tr').find('td').first().attr('data-roomid');
			if (landrid === undefined || !landrid.length) {
				return false;
			}
			if (jQuery(this).hasClass('busy') && !jQuery(this).hasClass('vbo-partially')) {
				if (bid != jQuery(this).attr('data-bids')) {
					//landed on a occupied date but not for the same booking ID so drop is not allowed on this day
					return false;
				}
			}
			var freenights = countCellFreeNights(jQuery(this), landrid, totnights);
			if (freenights < totnights) {
				alert(vboMessages.notEnoughCells.replace('%s', freenights).replace('%d', totnights));
				return false;
			}
			/* make request */
			var nowdatefrom = jQuery(newcellspool[0]).attr('data-day');
			var nowdateto = jQuery(newcellspool[(newcellspool.length -1)]).attr('data-day');
			/* populate temporary class for the new cells */
			jQuery(cellspool).each(function(k, v){
				v.addClass('vbo-dragging-cells-tmp');
			});
			//bookings for multiple rooms should add the same dragging class also to the booking for the other rooms
			var samebidcells = jQuery("td.vbo-checkinday[data-bids='"+bid+"']");
			if (samebidcells.length > 1) {
				jQuery("td[data-bids='"+bid+"']").each(function(k, v) {
					if (!jQuery(v).hasClass('vbo-dragging-cells-tmp')) {
						jQuery(v).addClass('vbo-dragging-cells-tmp');
					}
				});
			}
			//
			jQuery(newcellspool).each(function(k, v){
				v.addClass('vbo-dragged-cells-tmp');
			});
			/* populate and showing loading message */
			jQuery('.vbo-loading-dnd-head').text(vboMessages.movingBookingId.replace('%d', bid));
			var movingmess = '';
			if (roomid != landrid) {
				movingmess += vboMessages.switchRoomTo.replace('%s', jQuery("td[data-roomid='"+landrid+"']").first().find('span.vbo-overview-roomname').text() );
			}
			if (nowdatefrom != jQuery(cellspool[0]).attr('data-day') || nowdateto != jQuery(cellspool[(cellspool.length -1)]).attr('data-day')) {
				if (roomid != landrid) {
					movingmess += ', ';
				}
				movingmess += vboMessages.switchDatesTo.replace('%s', nowdatefrom+' - '+nowdateto);
			}
			hideVboTooltip();
			jQuery('.vbo-loading-dnd-body').text(movingmess);
			jQuery('.vbo-info-overlay-block').fadeIn();
			jQuery('.vbo-loading-dnd-footer').show();
			/* fire the Ajax request after 1.5 seconds just for giving visibility to the loading message */
			setTimeout(function() {
				doAlterBooking(bid, roomid, landrid);
			}, 1500);
			/*
			//we do not need to copy just the dragged element so detaching it and appending it to the landing cell is useless
			var de = jQuery('#'+cid).detach();
			de.appendTo(jQuery(this));
			jQuery('#'+cid).attr('id', '').removeClass('vbo-dragging-sp');
			*/
		}
	});

	/* DnD Event dragend: remove class, attribute and dataTransfer Data if drag ends on the same position as dragstart or onto an invalid date */
	jQuery(document.body).on("dragend", "td.vbo-checkinday span", function(e) {
		// stop dragging to restore tooltip functions
		isdragging = false;
		//
		var dt = e.originalEvent.dataTransfer;
		var cid = dt.getData('Id');
		if (cid !== undefined && cid.length > 0) {
			jQuery('#'+cid).attr('id', '').removeClass('vbo-dragging-sp');
		} else {
			//Safari fix
			jQuery('.vbo-dragging-sp').removeClass('vbo-dragging-sp');
		}
	});
	/* DnD End */

	/* Show New Reservation Form & Modal with bookings - Start */
	jQuery('td.busy, td.notbusy').dblclick(function() {
		var curday = jQuery(this).attr('data-day');
		var roomid = jQuery(this).parent('tr').find('td.roomname').attr('data-roomid');
		roomid = roomid && roomid.length ? roomid : '';
		last_room_click = '';
		last_date_click = '';
		next_date_click = '';
		vboCallOpenJModal('vbo-new-res', 'index.php?option=com_vikbooking&task=calendar&cid[]='+roomid+'&checkin='+curday+'&overv=1&tmpl=component');
	});

	jQuery(document.body).on('click', 'td.busy, td.notbusy, td.busytmplock, td.subroom-busy', function(e) {
		if (jQuery(this).hasClass('vbo-widget-booskcal-cell-mday')) {
			return;
		}

		if (jQuery(this).hasClass('busy') || jQuery(this).hasClass('busytmplock') || jQuery(this).hasClass('subroom-busy')) {
			// make sure the clicked target is not inside the tooltip
			if (e && e.target) {
				if (jQuery(e.target).closest('.vbo-overview-tipblock').length) {
					// abort when click originated from the tooltip
					return;
				}
				if (jQuery(e.target).is('a')) {
					// abort when click is made on a link (View choosebusy)
					return;
				}
				if (jQuery(e.target).hasClass('vbo-roomdaynote-display') || jQuery(e.target).hasClass('vbo-roomdaynote-trigger')) {
					// abort when click is made on a room-day note
					return;
				}
			}

			// trigger mouseleave event to prevent tooltip from showing
			jQuery(this).trigger('mouseleave');

			// get room name, date and day-bids
			var room_name = '';
			var sub_room_data = '';
			var room_id = 0;
			if (jQuery(this).hasClass('subroom-busy')) {
				sub_room_data = jQuery(this).parent('tr').attr('data-subroomid');
				room_id = sub_room_data.split('-')[0];
				room_name = jQuery('td.roomname[data-roomid="' + room_id + '"]').not('.subroomname').first().find('.vbo-overview-roomname').text();
			} else {
				var main_room_cell = jQuery(this).parent('tr').find('td.roomname');
				room_id = main_room_cell.attr('data-roomid');
				room_name = main_room_cell.find('.vbo-overview-roomname').text();
			}
			var date_ymd  = jQuery(this).attr('data-day');
			var date_read = jQuery('.bluedays[data-ymd="' + date_ymd + '"]').attr('data-readymd');
			var date_bids = jQuery(this).attr('data-bids');
			var def_bicon = '<?php VikBookingIcons::e('user', 'vbo-dashboard-guest-activity-avatar-icon'); ?>';
			var closure_i = '<?php VikBookingIcons::e('ban', 'vbo-dashboard-guest-activity-avatar-icon'); ?>';

			// display modal with booking details
			var rday_bookings_modal_body = VBOCore.displayModal({
				suffix: 	   'overv-rdaybookings',
				extra_class:   'vbo-modal-rounded vbo-modal-tall vbo-modal-nofooter',
				title: 		   room_name + (sub_room_data ? ' #' + sub_room_data.split('-')[1] : '') + ' - ' + date_read,
				dismiss_event: 'vbo-dismiss-modal-overv-rdaybookings',
				loading_event: 'vbo-loading-modal-overv-rdaybookings',
				loading_body:  '<?php VikBookingIcons::e('circle-notch', 'fa-spin fa-fw'); ?>',
			});

			// show loading
			VBOCore.emitEvent('vbo-loading-modal-overv-rdaybookings');

			// make the request to get the bookings information
			VBOCore.doAjax(
				"<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=getbookingsinfo'); ?>",
				{
					status: 'any',
					idroom: room_id,
					stay_date: date_ymd,
					idorders: date_bids,
					subroom: sub_room_data,
					tmpl: 'component'
				},
				(res) => {
					// stop loading
					VBOCore.emitEvent('vbo-loading-modal-overv-rdaybookings');
					try {
						var obj_res = typeof res === 'string' ? JSON.parse(res) : res;

						// build the HTML response nodes
						var rday_bookings_wrap = jQuery('<div></div>').addClass('vbo-dashboard-guests-latest');
						var rday_bookings_list = jQuery('<div></div>').addClass('vbo-dashboard-guest-messages-list');

						// flag to indicate if the button to toggle the cancelled reservations was displayed
						var show_canc_res_flag = false;

						// loop through all bookings
						for (var b in obj_res) {
							if (!obj_res.hasOwnProperty(b)) {
								continue;
							}
							// nights and guests
							var nights_guests = [
								obj_res[b]['roomsnum'] + ' ' + Joomla.JText._((obj_res[b]['roomsnum'] > 1 ? 'VBPVIEWORDERSTHREE' : 'VBEDITORDERTHREE')),
								obj_res[b]['days'] + ' ' + Joomla.JText._((obj_res[b]['days'] > 1 ? 'VBDAYS' : 'VBDAY')),
								obj_res[b]['tot_adults'] + ' ' + Joomla.JText._((obj_res[b]['tot_adults'] > 1 ? 'VBMAILADULTS' : 'VBMAILADULT'))
							];
							if (obj_res[b]['tot_children'] > 0) {
								nights_guests.push(obj_res[b]['tot_children'] + ' ' + Joomla.JText._((obj_res[b]['tot_children'] > 1 ? 'VBMAILCHILDREN' : 'VBMAILCHILD')));
							}

							// OTA booking ID
							var ota_bid_info = '';
							if (obj_res[b].hasOwnProperty('idorderota') && obj_res[b].hasOwnProperty('channel') && obj_res[b]['idorderota'] && obj_res[b]['channel']) {
								if (obj_res[b]['idorderota'].length <= 16) {
									// try to display the information only for API channels
									ota_bid_info = '<span class="label label-info">' + obj_res[b]['idorderota'] + '</span> ';
								}
							}

							// the separator between confirmed and cancelled reservations
							var res_type_separator = null;

							// booking status and badge
							var badge_type = 'warning';
							if (obj_res[b]['status'] == 'confirmed') {
								badge_type = 'success';
							} else if (obj_res[b]['status'] == 'cancelled') {
								badge_type = 'danger';
								if (!show_canc_res_flag) {
									// build the separator between confirmed and cancelled reservations to toggle the latter ones
									res_type_separator = jQuery('<div></div>').addClass('vbo-bookings-status-separator');
									var btn_separator = jQuery('<button></button>').attr('type', 'button').addClass('btn btn-small btn-secondary').text(Joomla.JText._('VBO_SHOW_CANCELLATIONS'));
									btn_separator.on('click', function() {
										jQuery(this).closest('.vbo-dashboard-guest-messages-list').find('[data-type="cancelled"]').toggle();
									});
									res_type_separator.append(btn_separator);
									// turn flag on at the very first cancelled reservation found
									show_canc_res_flag = true;
								}
							}

							// build main booking node
							var rday_booking = jQuery('<div></div>').addClass('vbo-dashboard-guest-activity vbo-w-guestmessages-message');
							if (badge_type == 'danger') {
								// hide cancelled booking by default and set proper attribute
								rday_booking.attr('data-type', 'cancelled').css('display', 'none');
							}
							rday_booking.attr('data-idorder', obj_res[b]['id']);
							rday_booking.on('click', function(e) {
								var click_target = jQuery(e.target);
								if (click_target.is('a') || click_target.is('select') || click_target.is('option')) {
									return false;
								}
								vboOvervOpenBooking(jQuery(this).attr('data-idorder'));
							});

							// build booking structure
							var rday_booking_html = '';
							rday_booking_html += '<div class="vbo-dashboard-guest-activity-avatar">' + "\n";
							if (obj_res[b]['avatar_src']) {
								rday_booking_html += '<img class="vbo-dashboard-guest-activity-avatar-profile" src="' + obj_res[b]['avatar_src'] + '" alt="' + obj_res[b]['avatar_alt'] + '" />' + "\n";
							} else if (obj_res[b]['closure']) {
								rday_booking_html += closure_i + "\n";
							} else {
								rday_booking_html += def_bicon + "\n";
							}
							rday_booking_html += '</div>' + "\n";
							rday_booking_html += '<div class="vbo-dashboard-guest-activity-content">' + "\n";
							rday_booking_html += '	<div class="vbo-dashboard-guest-activity-content-head">' + "\n";
							rday_booking_html += '		<div class="vbo-dashboard-guest-activity-content-info-details">' + "\n";
							rday_booking_html += '			<h4 class="vbo-w-guestmessages-message-gtitle">' + (!obj_res[b]['closure'] ? obj_res[b]['cinfo'] : obj_res[b]['closure_txt']) + '</h4>' + "\n";
							rday_booking_html += '			<div class="vbo-dashboard-guest-activity-content-info-icon">' + "\n";
							rday_booking_html += '				<span class="label label-info">' + obj_res[b]['id'] + '</span> ' + "\n";
							rday_booking_html += '				<span class="badge badge-' + badge_type + '">' + obj_res[b]['status_lbl'] + '</span>' + "\n";
							rday_booking_html += '					<span class="vbo-w-guestmessages-message-staydates">' + "\n";
							rday_booking_html += '					<span class="vbo-w-guestmessages-message-staydates-in">' + obj_res[b]['checkin_short'] + '</span>' + "\n";
							rday_booking_html += '					<span class="vbo-w-guestmessages-message-staydates-sep">-</span>' + "\n";
							rday_booking_html += '					<span class="vbo-w-guestmessages-message-staydates-out">' + obj_res[b]['checkout_short'] + '</span>' + "\n";
							rday_booking_html += '				</span>' + "\n";
							rday_booking_html += '			</div>' + "\n";
							rday_booking_html += '		</div>' + "\n";
							rday_booking_html += '		<div class="vbo-dashboard-guest-activity-content-info-date">' + "\n";
							rday_booking_html += '			<span>' + obj_res[b]['book_date'] + '</span>' + "\n";
							rday_booking_html += '			<span>' + obj_res[b]['book_time'] + '</span>' + "\n";
							rday_booking_html += '		</div>' + "\n";
							rday_booking_html += '	</div>' + "\n";
							rday_booking_html += '	<div class="vbo-dashboard-guest-activity-content-info-msg">' + "\n";
							rday_booking_html += '		<div>' + ota_bid_info + nights_guests.join(', ') + '</div>' + "\n";
							if (obj_res[b].hasOwnProperty('sub_units_data')) {
								rday_booking_html += '	<div class="vbo-rdaybooking-subunits">' + "\n";
								for (let sub_rname in obj_res[b]['sub_units_data']) {
									if (!obj_res[b]['sub_units_data'].hasOwnProperty(sub_rname)) {
										continue;
									}
									rday_booking_html += '<span class="label label-success">' + (obj_res[b]['roomsnum'] > 1 ? sub_rname + ' ' : '') + '#' + obj_res[b]['sub_units_data'][sub_rname] + '</span>' + "\n";
								}
								rday_booking_html += '	</div>' + "\n";
							} else if (obj_res[b].hasOwnProperty('missing_index') && obj_res[b]['missing_index']) {
								rday_booking_html += '	<div class="vbo-rdaybooking-subunits">' + "\n";
								rday_booking_html += '		<span class="label label-warning">' + Joomla.JText._('VBO_MISSING_SUBUNIT') + '</span>' + "\n";
								if (obj_res[b].hasOwnProperty('av_room_indexes')) {
									// build drop downs to set the sub-unit to each room with no data
									rday_booking_html += '	<div class="vbo-rdaybooking-subunits-list">' + "\n";
									for (var av_rindex in obj_res[b]['av_room_indexes']) {
										if (!obj_res[b]['av_room_indexes'].hasOwnProperty(av_rindex) || !obj_res[b]['av_room_indexes'][av_rindex].hasOwnProperty('name') || !obj_res[b]['av_room_indexes'][av_rindex].hasOwnProperty('list')) {
											continue;
										}
										var set_subunit_node = '<select onchange="vboSetSubunit(\'' + obj_res[b]['id'] + '\', \'' + obj_res[b]['av_room_indexes'][av_rindex]['rid'] + '\', \'' + av_rindex + '\', this.value);">' + "\n";
										set_subunit_node += '<option value="">' + obj_res[b]['av_room_indexes'][av_rindex]['name'] + '</option>' + "\n";
										for (var rindex_k in obj_res[b]['av_room_indexes'][av_rindex]['list']) {
											if (!obj_res[b]['av_room_indexes'][av_rindex]['list'].hasOwnProperty(rindex_k)) {
												continue;
											}
											set_subunit_node += '<option value="' + rindex_k + '">' + obj_res[b]['av_room_indexes'][av_rindex]['list'][rindex_k] + '</option>' + "\n";
										}
										set_subunit_node += '</select>' + "\n";
										// append drop down
										rday_booking_html += set_subunit_node;
									}
									rday_booking_html += '	</div>' + "\n";
								}
								rday_booking_html += '	</div>' + "\n";
							}
							rday_booking_html += '	</div>' + "\n";
							rday_booking_html += '</div>' + "\n";

							// set booking HTML to node
							rday_booking.append(rday_booking_html);

							if (res_type_separator !== null) {
								// append the separator to toggle the cancelled reservations
								rday_bookings_list.append(res_type_separator);
							}

							// append booking node to list
							rday_bookings_list.append(rday_booking);
						}

						// finalize response nodes
						rday_bookings_wrap.append(rday_bookings_list);

						// append the response
						rday_bookings_modal_body.append(rday_bookings_wrap);
					} catch(err) {
						console.error('could not parse JSON response', err, res);
						alert('Could not parse JSON response');
					}
				},
				(err) => {
					// stop loading and display alert message
					VBOCore.emitEvent('vbo-loading-modal-overv-rdaybookings');
					console.error(err);
					alert(err.responseText);
				}
			);
		}

		if (jQuery(this).hasClass('busy') && !jQuery(this).hasClass('vbo-partially')) {
			// abort when fully booked date
			return;
		}

		if (jQuery(this).hasClass('busytmplock') || jQuery(this).hasClass('subroom-busy')) {
			// abort when pending record or sub-room
			return;
		}

		// update clicked dates info
		var curday = jQuery(this).attr('data-day');
		var roomid = jQuery(this).parent('tr').find('td.roomname').attr('data-roomid');
		roomid = roomid && roomid.length ? roomid : '';
		last_room_click = roomid;
		if (!last_date_click) {
			last_date_click = curday;
		} else if (last_date_click && next_date_click) {
			last_date_click = curday;
			next_date_click = '';
		} else {
			var from_info = new Date(last_date_click);
			var to_info = new Date(curday);
			if (from_info.getTime() < to_info.getTime()) {
				next_date_click = curday;
			} else {
				last_date_click = curday;
				next_date_click = '';
			}
		}

		// button shake effect
		jQuery('.vbo-ovrv-flt-butn').addClass('vbo-ovrv-flt-butn-shake');
		setTimeout(function() {
			jQuery('.vbo-ovrv-flt-butn').removeClass('vbo-ovrv-flt-butn-shake');
		}, 1000);
	});
	/* Show New Reservation Form & Modal with bookings - End */

	/* Hover Tooltip Start */
	jQuery('td.busy, td.busytmplock, td.subroom-busy').not('.vbo-widget-booskcal-cell-mday').hover(function() {
		registerHoveringTooltip(this);
	}, unregisterHoveringTooltip);

	jQuery(document.body).on('click', '.vbo-overview-tip-bctag', function() {
		if (!jQuery(this).parent().find(".vbo-overview-tip-bctag-subtip").length) {
			jQuery(".vbo-overview-tip-bctag-subtip").remove();
			var cur_color = jQuery(this).attr("data-ctagcolor");
			var cur_bid = jQuery(this).attr("data-bid");
			jQuery(this).after("<div class=\"vbo-overview-tip-bctag-subtip\">"+bctags_tip+"</div>");
			jQuery(this).parent().find(".vbo-overview-tip-bctag-subtip").find(".vbo-overview-tip-bctag-subtip-circle[data-ctagcolor='"+cur_color+"']").addClass("vbo-overview-tip-bctag-activecircle").css('border-color', cur_color);
			jQuery(this).parent().find(".vbo-overview-tip-bctag-subtip").find(".vbo-overview-tip-bctag-subtip-circle").attr('data-bid', cur_bid);
			jQuery(".vbo-overview-tip-bctag-subtip .hasTooltip").tooltip();
		} else {
			jQuery(".vbo-overview-tip-bctag-subtip").remove();
		}
	});

	var applying_tag = false;

	jQuery(document.body).on('click', '.vbo-overview-tip-bctag-subtip-circle', function() {
		if (applying_tag === true) {
			return false;
		}
		applying_tag = true;
		var clickelem = jQuery(this);
		var ctagkey = clickelem.attr('data-ctagkey');
		var bid = clickelem.attr('data-bid');
		//set opacity to circles as loading
		jQuery('.vbo-overview-tip-bctag-subtip-circle').css('opacity', '0.6');
		//
		var jqxhr = jQuery.ajax({
			type: "POST",
			url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=setbookingtag'); ?>",
			data: {
				tmpl: "component",
				idorder: bid,
				tagkey: ctagkey
			}
		}).done(function(res) {
			applying_tag = false;
			if (res.indexOf('e4j.error') >= 0 ) {
				console.log(res);
				alert(res.replace("e4j.error.", ""));
				//restore loading opacity in circles
				jQuery('.vbo-overview-tip-bctag-subtip-circle').css('opacity', '1');
			} else {
				var obj_res = typeof res === 'string' ? JSON.parse(res) : res;
				var last_bid = jQuery(clickelem).closest(".vbo-hascolortag").attr('data-lastbid');
				<?php if ($pbmode == 'tags') { echo 'jQuery(".vbo-hascolortag[data-lastbid=\'"+last_bid+"\']").each(function(k, v) { jQuery(this).css("background-color", obj_res.color).find("a").first().css("color", obj_res.fontcolor); });'; } ?>
				hideVboTooltip();
			}
		}).fail(function() {
			applying_tag = false;
			alert("Request Failed");
			//restore loading opacity in circles
			jQuery('.vbo-overview-tip-bctag-subtip-circle').css('opacity', '1');
		});
	});

	jQuery(document).keydown(function(e) {
		if ( e.keyCode == 27 ) {
			if (hovtip === true) {
				hideVboTooltip();
			}
			if (vbodialogorph_on === true) {
				hideVboDialogOverv(1);
			}
		}
	});

	jQuery(document).mouseup(function(e) {
		if (!hovtip && !vbodialogorph_on) {
			return false;
		}
		if (hovtip) {
			var vbo_overlay_cont = jQuery(".vbo-overview-tipblock");
			if (!vbo_overlay_cont.is(e.target) && vbo_overlay_cont.has(e.target).length === 0) {
				hideVboTooltip();
				return true;
			}
			if (jQuery(".vbo-overview-tip-bctag-subtip").length) {
				var vbo_overlay_subtip_cont = jQuery(".vbo-overview-tip-bctag-wrap");
				if (!vbo_overlay_subtip_cont.is(e.target) && vbo_overlay_subtip_cont.has(e.target).length === 0) {
					jQuery(".vbo-overview-tip-bctag-subtip").remove();
					return true;
				}
			}
		}
		if (vbodialogorph_on) {
			var vbo_overlay_cont = jQuery(".vbo-info-overlay-content");
			if (!vbo_overlay_cont.is(e.target) && vbo_overlay_cont.has(e.target).length === 0) {
				hideVboDialogOverv(1);
			}
		}
	});
	/* Hover Tooltip End */

	/* Toggle Sub-units Start */
	jQuery(".vbo-overview-subroom-toggle").click(function() {
		var roomid = jQuery(this).closest("td").attr("data-roomid");
		if (jQuery(this).hasClass("vbo-overview-subroom-toggle-active")) {
			jQuery("td.roomname[data-roomid='"+roomid+"']").find("span.vbo-overview-subroom-toggle").removeClass("vbo-overview-subroom-toggle-active").find("i.fa").removeClass("fa-chevron-up").addClass("fa-chevron-down");
			// do not use .hide() or "display: none" may not work due to forced "display: table-row"
			jQuery("td.subroomname[data-roomid='-"+roomid+"']").parent("tr").css('display', 'none');
		} else {
			jQuery("td.roomname[data-roomid='"+roomid+"']").find("span.vbo-overview-subroom-toggle").addClass("vbo-overview-subroom-toggle-active").find("i.fa").removeClass("fa-chevron-down").addClass("fa-chevron-up");
			// do not use .show() or "display: block" may be added rather than "display: table-row"
			jQuery("td.subroomname[data-roomid='-"+roomid+"']").parent("tr").css('display', 'table-row');
		}
	});
	/* Toggle Sub-units End */

	/* Default state for sticky table heads */
	vboToggleStickyTableHeaders(<?php echo (count($rows) * $mnum) > 10 && $cookie_sticky_heads != 'off' ? 1 : 0; ?>);

	// check orphans (only if not disabled with the cookie)
	var hideorphans = false;
	var buiscuits = document.cookie;
	if (buiscuits.length) {
		var hideorphansck = "vboHideOrphans=1";
		if (buiscuits.indexOf(hideorphansck) >= 0) {
			hideorphans = true;
		}
	}
	if (!hideorphans && <?php echo $vbo_auth_pricing ? 'true' : 'false'; ?>) {
		// make the request
		var jqxhr = jQuery.ajax({
			type: "POST",
			url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=orphanscount'); ?>",
			data: {
				from: '<?php echo date($df, $tsstart); ?>',
				months: <?php echo $mnum; ?>,
				tmpl: "component"
			}
		}).done(function(res) {
			var obj_res = typeof res === 'string' ? JSON.parse(res) : res;
			var orphans_list = '';
			for (var rid in obj_res) {
				if (!obj_res.hasOwnProperty(rid)) {
					continue;
				}
				orphans_list += '<div class="vbo-orphans-info-room">';
				orphans_list += '	<h4 class="vbo-orphans-roomname">'+obj_res[rid]['name']+'</h4>';
				orphans_list += '	<div class="vbo-orphans-info-dates">';
				for (var dind in obj_res[rid]['rdates']) {
					if (!obj_res[rid]['rdates'].hasOwnProperty(dind)) {
						continue;
					}
					orphans_list += '	<div class="vbo-orphans-info-date">'+obj_res[rid]['rdates'][dind]+'</div>';
				}
				orphans_list += '	</div>';
				orphans_list += '	<div class="vbo-orphans-info-btn">';
				orphans_list += '		<a href="index.php?option=com_vikbooking&task=ratesoverv&cid[]='+rid+'&startdate='+obj_res[rid]['linkd']+'" class="btn btn-primary" target="_blank"><?php echo addslashes(JText::translate('VBORPHANSCHECKBTN')); ?></a>';
				orphans_list += '	</div>';
				orphans_list += '</div>';
			}
			if (orphans_list.length) {
				// show the modal
				jQuery('.vbo-orphans-info-list').html(orphans_list);
				jQuery('.vbo-orphans-overlay-block').fadeIn();
				vbodialogorph_on = true;
			}
		}).fail(function() {
			console.log("orphanscount Request Failed");
		});
	}
	//

	// fests
	jQuery(document.body).on("click", "th.bluedays", function() {
		if (jQuery(this).hasClass('skip-bluedays-click')) {
			return;
		}
		var ymd = jQuery(this).attr('data-ymd');
		var daytitle = jQuery(this).attr('data-readymd');
		if (jQuery(this).hasClass('vbo-overv-festcell')) {
			// cell has fests
			if (!vboFests.hasOwnProperty(ymd)) {
				return;
			}
			vboRenderFests(ymd, daytitle);
		} else {
			// let the admin create a new fest

			// update ymd key for the selected date, useful for adding new fests
			jQuery('.vbo-overlay-fests-addnew').attr('data-ymd', ymd);

			// unset content and display modal for just adding a new fest
			jQuery('.vbo-overlay-fests-list').html('');
			var fests_modal = VBOCore.displayModal({
				suffix: 	   'overv-mng-fests',
				extra_class:   'vbo-modal-rounded vbo-modal-tall',
				title: 		   '<?php VikBookingIcons::e('star'); ?> <span>' + daytitle + '</span>',
				footer_right:  '<button type="button" class="btn btn-success" onclick="vboAddFest();">' + Joomla.JText._('VBSAVE') + '</button>',
				dismiss_event: 'vbo-dismiss-modal-overv-mng-fests',
				onDismiss: 	   () => {
					jQuery('.vbo-overv-mngfest-wrap').appendTo('.vbo-overv-mngfest-block');
				},
			});

			// set modal body
			jQuery('.vbo-overv-mngfest-wrap').appendTo(fests_modal);
		}
	});
	//

	// room-day notes
	jQuery(document.body).on("click", ".vbo-roomdaynote-display", function() {
		if (!jQuery(this).closest('.vbo-roomdaynote-trigger').length) {
			return;
		}
		var daytitle = new Array;
		var roomday_info = jQuery(this).closest('.vbo-roomdaynote-trigger').attr('data-roomday').split('_');
		// readable day
		var readymd = roomday_info[0];
		if (jQuery('.bluedays[data-ymd="' + roomday_info[0] + '"]').length) {
			readymd = jQuery('.bluedays[data-ymd="' + roomday_info[0] + '"]').attr('data-readymd');
		}
		daytitle.push(readymd);
		// room name
		if (jQuery('.roomname[data-roomid="' + roomday_info[1] + '"]').length) {
			daytitle.push(jQuery('.roomname[data-roomid="' + roomday_info[1] + '"]').first().find('.vbo-overview-roomname').text());
		}
		// sub-unit
		if (parseInt(roomday_info[2]) > 0 && jQuery('.subroomname[data-roomid="-' + roomday_info[1] + '"]').length) {
			daytitle.push(jQuery('.subroomname[data-roomid="-' + roomday_info[1] + '"]').find('.vbo-overview-subroomname').eq((parseInt(roomday_info[2]) - 1)).text());
		}

		// display modal
		var rdaynotes_modal = VBOCore.displayModal({
			suffix: 	   'overv-mng-rdaynotes',
			extra_class:   'vbo-modal-rounded vbo-modal-tall',
			title: 		   '<?php VikBookingIcons::e('comment'); ?> <span>' + daytitle.join(', ') + '</span>',
			footer_right:  '<button type="button" class="btn btn-success" onclick="vboAddRoomDayNote();">' + Joomla.JText._('VBSAVE') + '</button>',
			dismiss_event: 'vbo-dismiss-modal-overv-mng-rdaynotes',
			onDismiss: 	   () => {
				jQuery('.vbo-overv-mngroomdaynotes-wrap').appendTo('.vbo-overv-mngroomdaynotes-block');
			},
		});

		// set modal body
		jQuery('.vbo-overv-mngroomdaynotes-wrap').appendTo(rdaynotes_modal);

		// populate current room day notes
		vboRenderRdayNotes(roomday_info[0], roomday_info[1], roomday_info[2], readymd);
	});
	//
});

/**
 * Fests
 */
function vboRenderFests(day, daytitle) {
	// compose fests information
	var fests_html = '';
	if (vboFests[day] && vboFests[day]['festinfo'] && vboFests[day]['festinfo'].length) {
		for (var i = 0; i < vboFests[day]['festinfo'].length; i++) {
			var fest = vboFests[day]['festinfo'][i];
			fests_html += '<div class="vbo-overlay-fest-details">';
			fests_html += '	<div class="vbo-fest-info">';
			fests_html += '		<div class="vbo-fest-name">' + fest['trans_name'] + '</div>';
			fests_html += '		<div class="vbo-fest-desc">' + fest['descr'].replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + '<br />' + '$2') + '</div>';
			fests_html += '	</div>';
			fests_html += '	<div class="vbo-fest-cmds">';
			fests_html += '		<button type="button" class="btn btn-danger" onclick="vboRemoveFest(\'' + day + '\', \'' + i + '\', \'' + fest['type'] + '\', this);"><?php VikBookingIcons::e('trash-alt', 'no-margin'); ?></button>';
			fests_html += '	</div>';
			fests_html += '</div>';
		}
	}

	// update ymd key for the selected date, useful for adding new fests
	jQuery('.vbo-overlay-fests-addnew').attr('data-ymd', day);

	// set content and display modal
	jQuery('.vbo-overlay-fests-list').html(fests_html);

	if (typeof daytitle !== 'undefined') {
		// display modal only when we are not deleting a fest
		var fests_modal = VBOCore.displayModal({
			suffix: 	   'overv-mng-fests',
			extra_class:   'vbo-modal-rounded vbo-modal-tall',
			title: 		   '<?php VikBookingIcons::e('star'); ?> <span>' + daytitle + '</span>',
			footer_right:  '<button type="button" class="btn btn-success" onclick="vboAddFest();">' + Joomla.JText._('VBSAVE') + '</button>',
			dismiss_event: 'vbo-dismiss-modal-overv-mng-fests',
			onDismiss: 	   () => {
				jQuery('.vbo-overv-mngfest-wrap').appendTo('.vbo-overv-mngfest-block');
			},
		});

		// set modal body
		jQuery('.vbo-overv-mngfest-wrap').appendTo(fests_modal);
	}
}

function vboRemoveFest(day, index, fest_type, that) {
	if (!confirm('<?php echo addslashes(JText::translate('VBDELCONFIRM')); ?>')) {
		return false;
	}
	var elem = jQuery(that);
	// make the AJAX request to the controller to remove this fest from the DB
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=remove_fest'); ?>",
		data: {
			tmpl: "component",
			dt: day,
			ind: index,
			type: fest_type
		}
	}).done(function(res) {
		if (res.indexOf('e4j.ok') >= 0) {
			// delete fest also from the json-decode array of objects
			if (vboFests[day] && vboFests[day]['festinfo']) {
				// use splice to remove the desired index from array, or delete would not make the length of the array change
				vboFests[day]['festinfo'].splice(index, 1);
				// re-build indexes of delete buttons, fundamental for removing the right index at next click
				vboRenderFests(day);
				if (!vboFests[day]['festinfo'].length) {
					// delete also this date object from fests
					delete vboFests[day];
					// no more fests, remove the class for this date from all cells
					jQuery('th.bluedays[data-ymd="'+day+'"]').removeClass('vbo-overv-festcell');
					jQuery('td.notbusy[data-day="'+day+'"]').removeClass('vbo-overv-festcell');
					jQuery('td.subnotbusy[data-day="'+day+'"]').removeClass('vbo-overv-festcell');
					jQuery('td.busy[data-day="'+day+'"]').removeClass('vbo-overv-festcell');
					jQuery('td.subroom-busy[data-day="'+day+'"]').removeClass('vbo-overv-festcell');
				}
			}
			elem.closest('.vbo-overlay-fest-details').remove();
		} else {
			console.log(res);
			alert('Invalid response');
		}
	}).fail(function() {
		alert('Request failed');
	});
}

function vboAddFest() {
	var ymd = jQuery('.vbo-overlay-fests-addnew').attr('data-ymd');
	var fest_name = jQuery('#vbo-newfest-name').val();
	var fest_descr = jQuery('#vbo-newfest-descr').val();
	if (!fest_name || !fest_name.length) {
		return false;
	}
	// make the AJAX request to the controller to add this fest to the DB
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=add_fest'); ?>",
		data: {
			tmpl: "component",
			dt: ymd,
			type: "custom",
			name: fest_name,
			descr: fest_descr
		}
	}).done(function(res) {
		// parse the JSON response that contains the fest object for the passed date
		try {
			var stored_fest = typeof res === 'string' ? JSON.parse(res) : res;
			if (!vboFests.hasOwnProperty(stored_fest['dt'])) {
				// we need to add the proper class to all cells to show that there is a fest
				jQuery('th.bluedays[data-ymd="'+stored_fest['dt']+'"]').addClass('vbo-overv-festcell');
				jQuery('td.notbusy[data-day="'+stored_fest['dt']+'"]').addClass('vbo-overv-festcell');
				jQuery('td.subnotbusy[data-day="'+stored_fest['dt']+'"]').addClass('vbo-overv-festcell');
				jQuery('td.busy[data-day="'+stored_fest['dt']+'"]').addClass('vbo-overv-festcell');
				jQuery('td.subroom-busy[data-day="'+stored_fest['dt']+'"]').addClass('vbo-overv-festcell');
			}
			vboFests[stored_fest['dt']] = stored_fest;
			// hide modal
			VBOCore.emitEvent('vbo-dismiss-modal-overv-mng-fests');
			// reset input fields
			jQuery('#vbo-newfest-name').val('');
			jQuery('#vbo-newfest-descr').val('');
		} catch (e) {
			console.log(res);
			alert('Invalid response');
			return false;
		}
	}).fail(function() {
		alert('Request failed');
	});
}

/**
 * Room-day notes
 */
var rdaynote_icn_full = '<?php echo VikBookingIcons::i('sticky-note', 'vbo-roomdaynote-display'); ?>';
var rdaynote_icn_empty = '<?php echo VikBookingIcons::i('far fa-sticky-note', 'vbo-roomdaynote-display'); ?>';
function vboRenderRdayNotes(day, idroom, subunit, readymd) {
	// compose fests information
	var notes_html = '';
	var keyid = day + '_' + idroom + '_' + subunit;
	if (vboRdayNotes.hasOwnProperty(keyid) && vboRdayNotes[keyid]['info'] && vboRdayNotes[keyid]['info'].length) {
		for (var i = 0; i < vboRdayNotes[keyid]['info'].length; i++) {
			var note_data = vboRdayNotes[keyid]['info'][i];
			notes_html += '<div class="vbo-overlay-fest-details vbo-modal-roomdaynotes-note-details">';
			notes_html += '	<div class="vbo-fest-info vbo-modal-roomdaynotes-note-info">';
			notes_html += '		<div class="vbo-fest-name vbo-modal-roomdaynotes-note-name">' + note_data['name'] + '</div>';
			notes_html += '		<div class="vbo-fest-desc vbo-modal-roomdaynotes-note-desc">' + note_data['descr'].replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + '<br />' + '$2') + '</div>';
			notes_html += '	</div>';
			notes_html += '	<div class="vbo-fest-cmds vbo-modal-roomdaynotes-note-cmds">';
			notes_html += '		<button type="button" class="btn btn-danger" onclick="vboRemoveRdayNote(\'' + i + '\', \'' + day + '\', \'' + idroom + '\', \'' + subunit + '\', \'' + note_data['type'] + '\', this);"><?php VikBookingIcons::e('trash-alt', 'no-margin'); ?></button>';
			notes_html += '	</div>';
			notes_html += '</div>';
		}
	}
	// update attributes keys for the selected date, useful for adding new notes
	jQuery('.vbo-modal-roomdaynotes-addnew').attr('data-ymd', day).attr('data-roomid', idroom).attr('data-subroomid', subunit);
	if (readymd !== null) {
		jQuery('.vbo-modal-roomdaynotes-addnew').attr('data-readymd', readymd);
		jQuery('.vbo-newrdnote-dayto-val').text(readymd);
	}
	// set content and display modal
	jQuery('.vbo-modal-roomdaynotes-list').html(notes_html);
}

function vboAddRoomDayNote(that) {
	var mainelem = jQuery('.vbo-modal-roomdaynotes-addnew');
	var ymd = mainelem.attr('data-ymd');
	var roomid = mainelem.attr('data-roomid');
	var subroomid = mainelem.attr('data-subroomid');
	var note_name = jQuery('#vbo-newrdnote-name').val();
	var note_descr = jQuery('#vbo-newrdnote-descr').val();
	var note_cdays = jQuery('#vbo-newrdnote-cdays').val();
	if (!note_name.length && !note_descr.length) {
		alert('Missing required fields');
		return false;
	}
	// make the AJAX request to the controller to add this note to the DB
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=add_roomdaynote'); ?>",
		data: {
			tmpl: "component",
			dt: ymd,
			idroom: roomid,
			subunit: subroomid,
			type: "custom",
			name: note_name,
			descr: note_descr,
			cdays: note_cdays
		}
	}).done(function(res) {
		// parse the JSON response that contains the note object for the passed date
		try {
			var stored_notes = typeof res === 'string' ? JSON.parse(res) : res;
			for (var keyid in stored_notes) {
				if (!stored_notes.hasOwnProperty(keyid)) {
					continue;
				}
				if (!vboRdayNotes.hasOwnProperty(keyid) && jQuery('.vbo-roomdaynote-trigger[data-roomday="' + keyid + '"]').length) {
					// we need to add the proper class to the cell for this note (if it's visible)
					jQuery('.vbo-roomdaynote-trigger[data-roomday="' + keyid + '"]').parent('td').removeClass('vbo-roomdaynote-empty').addClass('vbo-roomdaynote-full').find('i').attr('class', rdaynote_icn_full);
				}
				// update global object with the new notes in any case
				vboRdayNotes[keyid] = stored_notes[keyid];
			}
			// hide modal
			VBOCore.emitEvent('vbo-dismiss-modal-overv-mng-rdaynotes');
			// reset input fields
			jQuery('#vbo-newrdnote-name').val('');
			jQuery('#vbo-newrdnote-descr').val('');
			jQuery('#vbo-newrdnote-cdays').val('0').trigger('change');
		} catch (e) {
			console.log(res);
			alert('Invalid response');
			return false;
		}
	}).fail(function() {
		alert('Request failed');
	});
}

function vboRemoveRdayNote(index, day, idroom, subunit, note_type, that) {
	if (!confirm('<?php echo addslashes(JText::translate('VBDELCONFIRM')); ?>')) {
		return false;
	}
	var elem = jQuery(that);
	// make the AJAX request to the controller to remove this note from the DB
	var jqxhr = jQuery.ajax({
		type: "POST",
		url: "<?php echo VikBooking::ajaxUrl('index.php?option=com_vikbooking&task=remove_roomdaynote'); ?>",
		data: {
			tmpl: "component",
			dt: day,
			idroom: idroom,
			subunit: subunit,
			ind: index,
			type: note_type
		}
	}).done(function(res) {
		if (res.indexOf('e4j.ok') >= 0) {
			var keyid = day + '_' + idroom + '_' + subunit;
			// delete note also from the json-decode array of objects
			if (vboRdayNotes[keyid] && vboRdayNotes[keyid]['info']) {
				// use splice to remove the desired index from array, or delete would not make the length of the array change
				vboRdayNotes[keyid]['info'].splice(index, 1);
				// re-build indexes of delete buttons, fundamental for removing the right index at next click
				vboRenderRdayNotes(day, idroom, subunit, null);
				if (!vboRdayNotes[keyid]['info'].length) {
					// delete also this date object from notes
					delete vboRdayNotes[keyid];
					// no more notes, update the proper class attribute for this cell (should be visible)
					if (jQuery('.vbo-roomdaynote-trigger[data-roomday="' + keyid + '"]').length) {
						jQuery('.vbo-roomdaynote-trigger[data-roomday="' + keyid + '"]').parent('td').removeClass('vbo-roomdaynote-full').addClass('vbo-roomdaynote-empty').find('i').attr('class', rdaynote_icn_empty);
					}
				}
			}
			elem.closest('.vbo-modal-roomdaynotes-note-details').remove();
		} else {
			console.log(res);
			alert('Invalid response');
		}
	}).fail(function() {
		alert('Request failed');
	});
}

function vboRdayNoteCdaysCount() {
	var cdays = parseInt(jQuery('#vbo-newrdnote-cdays').val());
	var defymd = jQuery('.vbo-modal-roomdaynotes-addnew').attr('data-ymd');
	var defreadymd = jQuery('.vbo-modal-roomdaynotes-addnew').attr('data-readymd');
	defreadymd = !defreadymd || !defreadymd.length ? defymd : defreadymd;
	if (isNaN(cdays) || cdays < 1) {
		jQuery('.vbo-newrdnote-dayto-val').text(defreadymd);
		return;
	}
	// calculate target (until) date
	var targetdate = new Date(defymd);
	targetdate.setDate(targetdate.getDate() + cdays);
	var target_y = targetdate.getFullYear();
	var target_m = targetdate.getMonth() + 1;
	target_m = target_m < 10 ? '0' + target_m : target_m;
	var target_d = targetdate.getDate();
	target_d = target_d < 10 ? '0' + target_d : target_d;
	// display target date
	var display_target = target_y + '-' + target_m + '-' + target_d;
	// check if we can get the "read ymd property"
	if (jQuery('.bluedays[data-ymd="' + display_target + '"]').length) {
		display_target = jQuery('.bluedays[data-ymd="' + display_target + '"]').attr('data-readymd');
	}
	jQuery('.vbo-newrdnote-dayto-val').text(display_target);
}
</script>

<div class="vbo-orphans-overlay-block">
	<a class="vbo-info-overlay-close" href="javascript: void(0);"></a>
	<div class="vbo-info-overlay-content vbo-info-overlay-content-orphans">
		<h3><?php echo $vbo_app->createPopover(array('title' => JText::translate('VBORPHANSFOUND'), 'content' => JText::translate('VBORPHANSFOUNDSHELP'), 'icon_class' => VikBookingIcons::i('exclamation-triangle'))); ?> <?php echo JText::translate('VBORPHANSFOUND'); ?></h3>
		<div class="vbo-info-overlay-scroll-content">
			<div class="vbo-orphans-info-list"></div>
		</div>
		<div class="vbo-orphans-info-cmds">
			<div class="vbo-orphans-info-cmd">
				<button type="button" class="btn btn-success" onclick="javascript: hideVboDialogOverv(1);"><?php echo JText::translate('VBOBTNKEEPREMIND'); ?></button>
			</div>
			<div class="vbo-orphans-info-cmd">
				<button type="button" class="btn btn-danger" onclick="javascript: hideVboDialogOverv(-1);"><?php echo JText::translate('VBOBTNDONTREMIND'); ?></button>
			</div>
		</div>
	</div>
</div>

<div class="vbo-overv-mngfest-block" style="display: none;">
	<div class="vbo-overv-mngfest-wrap">
		<div class="vbo-overlay-fests-list"></div>
		<div class="vbo-overlay-fests-addnew" data-ymd="">
			<h4><?php echo JText::translate('VBOADDCUSTOMFESTTODAY'); ?></h4>
			<div class="vbo-overlay-fests-addnew-elem">
				<label for="vbo-newfest-name"><?php echo JText::translate('VBPVIEWPLACESONE'); ?></label>
				<input type="text" id="vbo-newfest-name" value="" />
			</div>
			<div class="vbo-overlay-fests-addnew-elem">
				<label for="vbo-newfest-descr"><?php echo JText::translate('VBPLACEDESCR'); ?></label>
				<textarea id="vbo-newfest-descr"></textarea>
			</div>
		</div>
	</div>
</div>

<div class="vbo-overv-mngroomdaynotes-block" style="display: none;">
	<div class="vbo-overv-mngroomdaynotes-wrap">
		<div class="vbo-modal-roomdaynotes-list"></div>
		<div class="vbo-modal-roomdaynotes-addnew" data-readymd="" data-ymd="" data-roomid="" data-subroomid="">
			<h4><?php echo JText::translate('VBOADDCUSTOMFESTTODAY'); ?></h4>
			<div class="vbo-modal-roomdaynotes-addnew-elem">
				<label for="vbo-newrdnote-name"><?php echo JText::translate('VBPVIEWPLACESONE'); ?></label>
				<input type="text" id="vbo-newrdnote-name" value="" />
			</div>
			<div class="vbo-modal-roomdaynotes-addnew-elem">
				<label for="vbo-newrdnote-descr"><?php echo JText::translate('VBPLACEDESCR'); ?></label>
				<textarea id="vbo-newrdnote-descr"></textarea>
			</div>
			<div class="vbo-modal-roomdaynotes-addnew-elem">
				<label for="vbo-newrdnote-cdays"><?php echo JText::translate('VBOCONSECUTIVEDAYS'); ?></label>
				<input type="number" id="vbo-newrdnote-cdays" min="0" max="365" value="0" onchange="vboRdayNoteCdaysCount();" onkeyup="vboRdayNoteCdaysCount();" />
				<span class="vbo-newrdnote-dayto">
					<span class="vbo-newrdnote-dayto-lbl"><?php echo JText::translate('VBOUNTIL'); ?></span>
					<span class="vbo-newrdnote-dayto-val"></span>
				</span>
			</div>
		</div>
	</div>
</div>
