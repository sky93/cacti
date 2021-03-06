<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2016 The Cacti Group                                 |
   |                                                                         |
   | This program is free software; you can redistribute it and/or           |
   | modify it under the terms of the GNU General Public License             |
   | as published by the Free Software Foundation; either version 2          |
   | of the License, or (at your option) any later version.                  |
   |                                                                         |
   | This program is snmpagent in the hope that it will be useful,           |
   | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
   | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
   | GNU General Public License for more details.                            |
   +-------------------------------------------------------------------------+
   | Cacti: The Complete RRDTool-based Graphing Solution                     |
   +-------------------------------------------------------------------------+
   | This code is designed, written, and maintained by the Cacti Group. See  |
   | about.php and/or the AUTHORS file for specific developer information.   |
   +-------------------------------------------------------------------------+
   | http://www.cacti.net/                                                   |
   +-------------------------------------------------------------------------+
*/

include('./include/auth.php');

define('MAX_DISPLAY_PAGES', 21);

$manager_actions = array(
	1 => 'Delete',
	2 => 'Enable',
	3 => 'Disable'
);

$manager_notification_actions = array(
	0 => 'Disable',
	1 => 'Enable'
);

$tabs_manager_edit = array(
	'general' => 'General',
	'notifications' => 'Notifications',
	'logs' => 'Logs',
);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();
		break;
	case 'actions':
		form_actions();
		break;
	case 'edit':
		top_header();
		manager_edit();
		bottom_footer();
		break;
	default:
		top_header();
		manager();
		bottom_footer();
	break;
}

function manager(){
	global $manager_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('rows'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_snmp_mgr_current_page');
		kill_session_var('sess_snmp_mgr_filter');
		kill_session_var('sess_snmp_mgr_rows');
		kill_session_var('sess_snmp_mgr_sort_column');
		kill_session_var('sess_snmp_mgr_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_snmp_mgr_current_page', '1');
	load_current_session_value('filter', 'sess_snmp_mgr_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_snmp_mgr_sort_column', 'hostname');
	load_current_session_value('sort_direction', 'sess_snmp_mgr_sort_direction', 'ASC');

	display_output_messages();

	?>
	<script type="text/javascript">
	function applyFilter() {
		strURL  = 'managers.php?filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'managers.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_managers').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box('SNMP Notification Receivers', '100%', '', '3', 'center', 'managers.php?action=edit');

	?>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_managers' name='form_snmpagent_managers' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
						</td>
						<td>
							Receivers
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()'>
								<?php
								if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
						</td>
						<td>
							<input type='button' id='clear' value='Clear' title='Clear Filters'>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php
	html_end_box();

	/* form the 'where' clause for our main sql query */
	$sql_where = "WHERE (snmpagent_managers.hostname LIKE '%%" . get_request_var('filter') . "%%'
						OR snmpagent_managers.description LIKE '%%" . get_request_var('filter') . "%%')";

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(snmpagent_managers.id)
		FROM snmpagent_managers
		$sql_where");

	$managers = db_fetch_assoc("SELECT
		snmpagent_managers.id,
		snmpagent_managers.description,
		snmpagent_managers.hostname,
		snmpagent_managers.disabled,
		snmpagent_managers_notifications.count_notify,
		snmpagent_notifications_log.count_log
		FROM snmpagent_managers
		LEFT JOIN (
			SELECT COUNT(*) as count_notify, manager_id 
			FROM snmpagent_managers_notifications 
			GROUP BY manager_id
		) AS snmpagent_managers_notifications
		ON snmpagent_managers_notifications.manager_id = snmpagent_managers.id
		LEFT JOIN (
			SELECT COUNT(*) as count_log, manager_id 
			FROM snmpagent_notifications_log 
			GROUP BY manager_id
		) AS snmpagent_notifications_log
		ON snmpagent_notifications_log.manager_id = snmpagent_managers.id
		$sql_where
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .  ' 
		LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	/* generate page list */
	$nav = html_nav_bar('managers.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 11, 'Receivers', 'page', 'main');
	print $nav;

	$display_text = array(
		'description' => array('Description', 'ASC'),
		'id' => array('Id', 'ASC'),
		'disabled' => array('Status', 'ASC'),
		'hostname' => array('Hostname', 'ASC'),
		'count_notify' => array('Notifications', 'ASC'),
		'count_log' => array('Logs', 'ASC')
	);

	form_start('managers.php', 'chk');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($managers) > 0) {
		foreach ($managers as $item) {
			$description = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['description']))) : htmlspecialchars($item['description']));
			$hostname = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['hostname']))): htmlspecialchars($item['hostname']));
			form_alternate_row('line' . $item['id'], false);
			form_selectable_cell( '<a class="linkEditMain" href="managers.php?action=edit&id=' . $item['id'] . '">' . $description . '</a>', $item['id']);
			form_selectable_cell( $item['id'], $item['id']);
			form_selectable_cell( $item['disabled'] ? 'disabled' : 'active', $item['id']);
			form_selectable_cell( $hostname, $item['id']);
			form_selectable_cell( '<a class="linkEditMain" href="managers.php?action=edit&tab=notifications&id=' . $item['id'] . '">' . ($item['count_notify'] ? $item['count_notify'] : 0) . '</a>' , $item['id']);
			form_selectable_cell( '<a class="linkEditMain" href="managers.php?action=edit&tab=logs&id=' . $item['id'] . '">' . ($item['count_log'] ? $item['count_log'] : 0 ) . '</a>', $item['id']);
			form_checkbox_cell($item['description'], $item['id']);
			form_end_row();
		}
		print $nav;
	}else{
		print '<tr><td><em>No SNMP Notification Receivers</em></td></tr>';
	}

	html_end_box(false);

	form_hidden_box('action_receivers', '1', '');
	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($manager_actions);
}

function manager_edit() {
	global $config, $snmp_auth_protocols, $snmp_priv_protocols, $snmp_versions,
		$tabs_manager_edit, $fields_manager_edit, $manager_notification_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!isset($_REQUEST['tab'])) $_REQUEST['tab'] = 'general';
	$id	= (isset($_REQUEST['id']) ? $_REQUEST['id'] : '0');

	if($id) {
		$manager = db_fetch_row_prepared('SELECT * FROM snmpagent_managers WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars($manager['description']) . ']';
	}else{
		$header_label = '[new]';
	}

	if (sizeof($tabs_manager_edit) && isset($_REQUEST['id'])) {
		/* draw the tabs */
		print "<div class='tabs'><nav><ul>\n";

	foreach (array_keys($tabs_manager_edit) as $tab_short_name) {
		if (($id == 0 & $tab_short_name != 'general')){
				print "<li class='subTab'><span " . (($tab_short_name == $_REQUEST['tab']) ? "class='selected'" : '') . "'>$tabs_manager_edit[$tab_short_name]</span></li>\n";
		}else {
				print "<li class='subTab'><a " . (($tab_short_name == $_REQUEST['tab']) ? "class='selected'" : '') .
					" href='" . htmlspecialchars($config['url_path'] .
					'managers.php?action=edit&id=' . get_request_var('id') .
					'&tab=' . $tab_short_name) .
					"'>$tabs_manager_edit[$tab_short_name]</a></li>\n";
		}
	}

		print "</ul></nav></div>\n";

		if (read_config_option('legacy_menu_nav') != 'on') { ?>
		<script type='text/javascript'>

		$(function() {
			$('.subTab').find('a').click(function(event) {
				event.preventDefault();

				strURL  = $(this).attr('href');
				strURL += (strURL.indexOf('?') > 0 ? '&':'?') + 'header=false';
				loadPageNoHeader(strURL);
			});
		});
		</script>
		<?php }
	}

	switch($_REQUEST['tab']){
		case 'notifications':
			html_start_box("SNMP Notification Receiver $header_label", '100%', '', '3', 'center', '');

			manager_notifications($id);
			html_end_box();
			draw_actions_dropdown($manager_notification_actions);
			break;
		case 'logs':
			html_start_box("SNMP Notification Receiver $header_label", '100%', '', '3', 'center', '');

			manager_logs($id);
			html_end_box();
			break;
		default:
			form_start('managers.php');

			html_start_box("SNMP Notification Receiver $header_label", '100%', '', '3', 'center', '');

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => inject_form_variables($fields_manager_edit, (isset($manager) ? $manager : array()))
				)
			);

			html_end_box();

			form_save_button('managers.php', 'return');

			?>
			<script type="text/javascript">
			function setSNMP() {
				snmp_version = $('#snmp_version').val();
				switch(snmp_version) {
					case "1": // SNMP v1
					case "2": // SNMP v2c
						$('#row_snmp_username').hide();
						$('#row_snmp_password').hide();
						$('#row_snmp_community').show();
						$('#row_snmp_auth_password').hide();
						$('#row_snmp_auth_protocol').hide();
						$('#row_snmp_priv_password').hide();
						$('#row_snmp_priv_protocol').hide();
						$('#row_snmp_context').hide();
						$('#row_snmp_port').show();
						$('#row_snmp_timeout').show();
						break;
					case "3": // SNMP v3
						$('#row_snmp_username').show();
						$('#row_snmp_password').show();
						$('#row_snmp_community').hide();
						$('#row_snmp_auth_password').show();
						$('#row_snmp_auth_protocol').show();
						$('#row_snmp_priv_password').show();
						$('#row_snmp_priv_protocol').show();
						$('#row_snmp_context').show();
						$('#row_snmp_port').show();
						$('#row_snmp_timeout').show();
					break;
				}
			}

			$(function() {
				setSNMP();
			});
			</script>
			<?php
	}

	?>
	<script language="javascript" type="text/javascript" >
		$('.tooltip').tooltip({
			track: true,
			position: { collision: "flipfit" },
			content: function() { return $(this).attr('title'); }
		});
	</script>
	<?php
}

function manager_notifications($id){
	global $items_rows;

	$mibs = db_fetch_assoc('SELECT DISTINCT mib FROM snmpagent_cache');
	$registered_mibs = array();
	if($mibs && $mibs >0) {
		foreach($mibs as $mib) { $registered_mibs[] = $mib['mib']; }
	}

	/* ================= input validation ================= */
	if(!$id | !is_numeric($id)) {
		die_html_input_error();
	}
	if(!in_array(get_request_var('mib'), $registered_mibs) && get_request_var('mib') != '-1' && get_request_var('mib') != '') {
		die_html_input_error();
	}
	input_validate_input_number(get_request_var('page'));

	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_snmp_cache_mib');
		kill_session_var('sess_snmp_cache_current_page');
		kill_session_var('sess_snmp_cache_filter');
		unset($_REQUEST['mib']);
		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
	}

	/* reset the current page if the user changed the mib filter*/
	if(isset($_SESSION['sess_snmp_cache_mib']) && get_request_var('mib') != $_SESSION['sess_snmp_cache_mib']) {
		kill_session_var('sess_snmp_cache_current_page');
		unset($_REQUEST['page']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_snmp_cache_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('mib', 'sess_snmp_cache_mib', '-1');
	load_current_session_value('filter', 'sess_snmp_cache_filter', '');

	?>
	<script type="text/javascript">

	function applyFilter() {
		strURL  = 'managers.php?action=edit&tab=notifications&id=<?php echo $id; ?>&filter=' + $('#filter').val();
		strURL += '&mib=' + $('#mib').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';

		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'managers.php?action=edit&tab=notifications&id=<?php echo $id; ?>&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_snmpagent_managers').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<tr class='even noprint'>
		<td>
			<form id='form_snmpagent_managers' name='form_snmpagent_managers' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							MIB
						</td>
						<td>
							<select id='mib' name='mib' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('mib') == '-1') {?> selected<?php }?>>Any</option>
								<?php
								if (sizeof($mibs) > 0) {
								foreach ($mibs as $mib) {
									print "<option value='" . $mib['mib'] . "'"; if (get_request_var('mib') == $mib['mib']) { print ' selected'; } print '>' . $mib['mib'] . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							Search
						</td>
						<td>
							<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
						</td>
						<td>
							Receivers
						</td>
						<td>
							<select id='rows' name='rows' onChange='applyFilter()'>
								<?php
								if (sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>\n";
								}
								}
								?>
							</select>
						</td>
						<td>
							<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
						</td>
						<td>
							<input type='button' id='clear' value='Clear' title='Clear Filters'>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " AND `kind`='Notification'";

	/* filter by host */
	if (get_request_var('mib') == '-1') {
		/* Show all items */
	}elseif (!empty($_REQUEST['mib'])) {
		$sql_where .= " AND snmpagent_cache.mib='" . get_request_var('mib') . "'";
	}
	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (`oid` LIKE '%%" . get_request_var('filter') . "%%'
			OR `name` LIKE '%%" . get_request_var('filter') . "%%'
			OR `mib` LIKE '%%" . get_request_var('filter') . "%%')";
	}
	$sql_where .= ' ORDER by `oid`';

	form_start('managers.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$rows = read_config_option('num_rows_table');

	/* FIXME: Change SQL Queries to not use WHERE 1 */
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_cache WHERE 1 $sql_where");

	$snmp_cache_sql = "SELECT * FROM snmpagent_cache WHERE 1 $sql_where LIMIT " . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	$snmp_cache = db_fetch_assoc($snmp_cache_sql);

	$registered_notifications = db_fetch_assoc_prepared('SELECT notification, mib FROM snmpagent_managers_notifications WHERE manager_id = ?', array($id));
	$notifications = array();
	if ($registered_notifications && sizeof($registered_notifications) > 0) {
		foreach($registered_notifications as $registered_notification) {
			$notifications[$registered_notification['mib']][$registered_notification['notification']] = 1;
		}
	}

	/* generate page list */
	$nav = html_nav_bar('managers.php?action=edit&id=' . $id . '&tab=notifications&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 11, '', 'page', 'main');
	print $nav;

	html_header_checkbox( array('Name', 'OID', 'MIB', 'Kind', 'Max-Access', 'Monitored'), true, 'managers.php?action=edit&tab=notifications&id=' . $id);

	if (sizeof($snmp_cache) > 0) {
		foreach ($snmp_cache as $item) {
			$row_id = $item['mib'] . '__' . $item['name'];
			$oid = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['oid']))) : htmlspecialchars($item['oid']));
			$name = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['name']))): htmlspecialchars($item['name']));
			$mib = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['mib']))): htmlspecialchars($item['mib']));

			form_alternate_row('line' . $row_id, false);
			if($item['description']) {
				print '<td><a href="#" title="<div class=\'header\'>' . $name . '</div><div class=\'content preformatted\'>' . $item['description']. '</div>" class="tooltip">' . $name . '</a></td>';
			}else {
				form_selectable_cell($name, $row_id);
			}
			form_selectable_cell( $oid, $row_id);
			form_selectable_cell( $mib, $row_id);
			form_selectable_cell( $item['kind'], $row_id);
			form_selectable_cell( $item['max-access'],$row_id);
			form_selectable_cell( ( ( isset( $notifications[ $item['mib'] ]) && isset( $notifications[ $item['mib'] ][ $item['name'] ]) ) ? 'Enabled' : 'Disabled' ), $row_id);
			form_checkbox_cell($item['oid'], $row_id);
			form_end_row();
		}
		print $nav;
	}else{
		print '<tr><td><em>No SNMP Notifications</em></td></tr>';
	}

	?>
	<input type='hidden' name='page' value='1'>
	<input type='hidden' name='action' value='edit'>
	<input type='hidden' name='tab' value='notifications'>
	<input type='hidden' name='id' value='<?php print $_REQUEST['id']; ?>'>
	<?php
}

function manager_logs($id) {
	$severity_levels = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => 'LOW',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => 'MEDIUM',
		SNMPAGENT_EVENT_SEVERITY_HIGH => 'HIGH',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => 'CRITICAL'
);

	$severity_colors = array(
		SNMPAGENT_EVENT_SEVERITY_LOW => '#00FF00',
		SNMPAGENT_EVENT_SEVERITY_MEDIUM => '#FFFF00',
		SNMPAGENT_EVENT_SEVERITY_HIGH => '#FF0000',
		SNMPAGENT_EVENT_SEVERITY_CRITICAL => '#FF00FF'
	);

	/* ================= input validation ================= */
	if(!$id | !is_numeric($id)) {
		die_html_input_error();
	}
	if(!in_array(get_request_var('severity'), array_keys($severity_levels)) && get_request_var('severity') != '-1' && get_request_var('severity') != '') {
		die_html_input_error();
	}
	input_validate_input_number(get_request_var('page'));
	/* ==================================================== */

	/* clean up search filter */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}
	if (isset($_REQUEST['purge_snmpagent__manager_logs_x'])) {
		db_execute_prepared('DELETE FROM snmpagent_notifications_log WHERE manager_id = ?', array($id));
		/* reset filters */
		$_REQUEST['clear_snmpagent__manager_logs_x'] = true;
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear_snmpagent__manager_logs_x'])) {
		kill_session_var('sess_snmp_logs_severity');
		kill_session_var('sess_snmp_logs_current_page');
		kill_session_var('sess_snmp_logs_filter');
		unset($_REQUEST['severity']);
		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
	}

	/* reset the current page if the user changed the mib filter*/
	if(isset($_SESSION['sess_snmp_logs_severity']) && get_request_var('severity') != $_SESSION['sess_snmp_logs_severity']) {
		kill_session_var('sess_snmp_cache_current_page');
		unset($_REQUEST['page']);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_snmp_logs_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('severity', 'sess_snmp_logs_severity', '-1');
	load_current_session_value('filter', 'sess_snmp_logs_filter', '');

	?>
	<script type='text/javascript'>

	function applyFilter(objForm) {
		strURL = '?severity=' + objForm.severity.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&action=edit&tab=logs&id=<?php print $_REQUEST['id']; ?>';
		document.location = strURL;
	}

	</script>
	<tr class='even'>
		<td>
			<form name='form_snmpagent_manager_logs' action='managers.php'>
				<table class='filterTable'>
					<tr>
						<td>
							Search
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
						</td>
						<td>
							Severity
						</td>
						<td>
							<select id='severity' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('severity') == '-1') {?> selected<?php }?>>Any</option>
								<?php
								foreach ($severity_levels as $level => $name) {
									print "<option value='" . $level . "'"; if (get_request_var('severity') == $level) { print ' selected'; } print '>' . $name . "</option>\n";
								}
								?>
							</select>
						</td>
						<td>
							<input type='button' id='refresh' value='Go' title='Set/Refresh Filters'>
						</td>
						<td>
							<input type='button' id='clear' value='Clear' title='Clear Filters'>
						</td>
						<td>
							<input type='button' id='purge' value='Purge' title='Purge Notification Log'>
						</td>
					</tr>
				</table>
				<input type='hidden' id='page' value='<?php print $_REQUEST['page'];?>'>
				<input type='hidden' name='action' value='edit'>
				<input type='hidden' name='tab' value='logs'>
				<input type='hidden' id='id' value='<?php print $_REQUEST['id']; ?>'>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = " snmpagent_notifications_log.manager_id='" . $id . "'";

	/* filter by severity */
	if (get_request_var('severity') == '-1') {
		/* Show all items */
	}elseif (!empty($_REQUEST['severity'])) {
		$sql_where .= " AND snmpagent_notifications_log.severity='" . get_request_var('severity') . "'";
	}

	/* filter by search string */
	if (get_request_var('filter') != '') {
		$sql_where .= " AND (`varbinds` LIKE '%%" . get_request_var('filter') . "%%')";
	}
	$sql_where .= ' ORDER by `id` DESC';
	$sql_query = "SELECT snmpagent_notifications_log.*, snmpagent_cache.description 
		FROM snmpagent_notifications_log
		LEFT JOIN snmpagent_cache 
		ON snmpagent_cache.name = snmpagent_notifications_log.notification
		WHERE $sql_where 
		LIMIT " . (read_config_option('num_rows_table')*(get_request_var('page')-1)) . ',' . read_config_option('num_rows_table');

	form_start('managers.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM snmpagent_notifications_log WHERE $sql_where");

	$logs = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('managers.php?action=exit&id=' . $id . '&tab=logs&mib=' . get_request_var('mib') . '&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 7, 'Receivers', 'page', 'main');

	print $nav;

	html_header(array(' ', 'Time', 'Notification', 'Varbinds' ));

	if (sizeof($logs)) {
		foreach ($logs as $item) {
			$varbinds = (strlen(get_request_var('filter')) ? (preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($item['varbinds']))): htmlspecialchars($item['varbinds']));
			form_alternate_row('line' . $item['id'], true);
			print "<td title='Severity Level: " . $severity_levels[ $item['severity'] ] . "' style='width:10px;background-color: " . $severity_colors[ $item['severity'] ] . ";border-top:1px solid white;border-bottom:1px solid white;'></td>";
			print "<td class='nowrap'>" . date( 'Y/m/d H:i:s', $item['time']) . '</td>';

			if($item['description']) {
				$description = '';
				$lines = preg_split( '/\r\n|\r|\n/', $item['description']);
				foreach($lines as $line) {
					$description .= addslashes(trim($line)) . '<br>';
				}
				print '<td><a href="#" onMouseOut="hideTooltip(snmpagentTooltip)" onMouseMove="showTooltip(event, snmpagentTooltip, \'' . $item['notification'] . '\', \'' . $description . '\')">' . $item['notification'] . '</a></td>';
			}else {
				print "<td>{$item['notification']}</td>";
			}
			print "<td>$varbinds</td>";
			form_end_row();
		}
		print $nav;
	}else{
		print '<tr><td><em>No SNMP Notification Log Entries</em></td></tr>';
	}

	?>
	<input type='hidden' name='id' value='<?php print $_REQUEST['id']; ?>'>
	<div style='display:none' id='snmpagentTooltip'></div>
	<script language='javascript' type='text/javascript'>
		function showTooltip(e, div, title, desc) {
			div.style.display = 'inline';
			div.style.position = 'fixed';
			div.style.backgroundColor = '#EFFCF0';
			div.style.border = 'solid 1px grey';
			div.style.padding = '10px';
			div.innerHTML = '<b>' + title + '</b><div style="padding-left:10px; padding-right:5px;"><pre>' + desc + '</pre></div>';
			div.style.left = e.clientX + 15 + 'px';
			div.style.top = e.clientY + 15 + 'px';
		}

		function hideTooltip(div) {
			div.style.display = 'none';
		}
		function highlightStatus(selectID){
			if (document.getElementById('status_' + selectID).value == 'ON') {
				document.getElementById('status_' + selectID).style.backgroundColor = 'LawnGreen';
			}else {
				document.getElementById('status_' + selectID).style.backgroundColor = 'OrangeRed';
			}
		}
	</script>
	<?php
}

function form_save() {
	if (!isset($_REQUEST['tab'])) $_REQUEST['tab'] = 'general';

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('max_log_size'));

	if(!in_array(get_request_var_post('max_log_size'), range(1,31) ) ) {
		//	die_html_input_error();
	}
	/* ================= input validation ================= */

	switch($_REQUEST['tab']){
		case 'notifications':
			header('Location: managers.php?action=edit&tab=notifications&id=' . (empty($manager_id) ? $_POST['id'] : $manager_id) );
			break;
		default:
			$save['id']                       = $_REQUEST['id'];
			$save['description']              = form_input_validate(trim(get_request_var_post('description')), 'description', '', false, 3);
			$save['hostname']                 = form_input_validate(trim(get_request_var_post('hostname')), 'hostname', '', false, 3);
			$save['disabled']                 = form_input_validate(get_request_var_post('disabled'), 'disabled', '^on$', true, 3);
			$save['max_log_size']             = $_POST['max_log_size'];

			$save['snmp_version']             = form_input_validate(get_request_var_post('snmp_version'), 'snmp_version', '^[1-3]$', false, 3);
			$save['snmp_community']           = form_input_validate(get_request_var_post('snmp_community'), 'snmp_community', '', true, 3);

			if ($save['snmp_version'] == 3) {
				$save['snmp_username']        = form_input_validate(get_request_var_post('snmp_username'), 'snmp_username', '', true, 3);
				$save['snmp_auth_password']   = form_input_validate(get_request_var_post('snmp_auth_password'), 'snmp_auth_password', '', true, 3);
				$save['snmp_auth_protocol']   = form_input_validate(get_request_var_post('snmp_auth_protocol'), 'snmp_auth_protocol', "^\[None\]|MD5|SHA$", true, 3);
				$save['snmp_priv_password']   = form_input_validate(get_request_var_post('snmp_priv_password'), 'snmp_priv_password', '', true, 3);
				$save['snmp_priv_protocol']   = form_input_validate(get_request_var_post('snmp_priv_protocol'), 'snmp_priv_protocol', "^\[None\]|DES|AES128$", true, 3);
			} else {
				$save['snmp_username']        = '';
				$save['snmp_auth_password']   = '';
				$save['snmp_auth_protocol']   = '';
				$save['snmp_priv_password']   = '';
				$save['snmp_priv_protocol']   = '';
			}

			$save['snmp_port']                = form_input_validate(get_request_var_post('snmp_port'), 'snmp_port', '^[0-9]+$', false, 3);
			$save['snmp_message_type']        = form_input_validate(get_request_var_post('snmp_message_type'), 'snmp_message_type', '^[1-2]$', false, 3);
			$save['notes']                    = form_input_validate(get_request_var_post('notes'), 'notes', '', true, 3);

			if ($save['snmp_version'] == 3 && ($save['snmp_auth_password'] != $save['snmp_auth_password_confirm'])) {
				raise_message(4);
			}

			$manager_id = 0;
			if (!is_error_message()) {
				$manager_id = sql_save($save, 'snmpagent_managers');
				raise_message( ($manager_id)? 1 : 2 );
			}
			break;
	}

	header('Location: managers.php?action=edit&header=false&id=' . (empty($manager_id) ? $_POST['id'] : $manager_id) );
}

function form_actions(){
	global $manager_actions, $manager_notification_actions;

	if (isset($_POST['selected_items'])) {
		if(isset($_POST['action_receivers'])) {
			$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

			if ($selected_items != false) {
				if ($_POST['drp_action'] == '1') { /* delete */
					db_execute('DELETE FROM snmpagent_managers WHERE id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_managers_notifications WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
					db_execute('DELETE FROM snmpagent_notifications_log WHERE manager_id IN (' . implode(',' ,$selected_items) . ')');
				}elseif ($_POST['drp_action'] == '2') { /* enable */
					db_execute("UPDATE snmpagent_managers SET disabled = '' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				}elseif ($_POST['drp_action'] == '3') { /* disable */
					db_execute("UPDATE snmpagent_managers SET disabled = 'on' WHERE id IN (" . implode(',' ,$selected_items) . ')');
				}

				header('Location: managers.php?header=false');
				exit;
			}
		}elseif(isset($_POST['action_receiver_notifications'])) {
			/* ================= input validation ================= */
			input_validate_input_number(get_request_var_post('id'));
			/* ==================================================== */

			$selected_items = unserialize(stripslashes($_POST['selected_items']));

			if ($_POST['drp_action'] == '0') { /* disable */
				foreach($selected_items as $mib => $notifications) {
					foreach($notifications as $notification => $state) {
						db_execute_prepared('DELETE FROM snmpagent_managers_notifications WHERE `manager_id` = ? AND `mib` = ? AND `notification` = ? LIMIT 1', array($_POST['id'], $mib, $notification));
					}
				}
			}elseif ($_POST['drp_action'] == '1') { /* enable */
				foreach($selected_items as $mib => $notifications) {
					foreach($notifications as $notification => $state) {
						db_execute_prepared('INSERT IGNORE INTO snmpagent_managers_notifications (`manager_id`, `notification`, `mib`) VALUES (?, ?, ?)', array($_POST['id'], $notification), $mib);
					}
				}
			}

			header('Location: managers.php?action=edit&id=' . $_POST['id'] . '&tab=notifications&header=false');
			exit;
		}
	}else {
		if(isset($_POST['action_receivers'])) {
			$selected_items = array();
			$list = '';
			foreach($_POST as $key => $value) {
				if(strstr($key, 'chk_')) {
					/* grep manager's id */
					$id = substr($key, 4);
					/* ================= input validation ================= */
					input_validate_input_number($id);
					/* ==================================================== */
					$list .= '<li><b>' . db_fetch_cell_prepared('SELECT description FROM snmpagent_managers WHERE id = ?', array($id)) . '</b></li>';
					$selected_items[] = $id;
				}
			}

			top_header();

			form_start('managers.php');

			html_start_box($manager_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

			if (sizeof($selected_items)) {
				print "<tr>
					<td class='textArea'>
						<p>Click 'Continue' to " . strtolower($manager_actions[$_POST['drp_action']]) . " the following Notification Receiver(s).</p>
						<p><ul>$list</ul></p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'><input type='submit' value='Continue' title='" . $manager_actions[$_POST['drp_action']] . " Notification Receiver(s)'>";
			} else {
				print "<tr><td class='even'><span class='textError'>You must select at least one Notification Receiver.</span></td></tr>\n";
				$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receivers' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
				</td>
			</tr>\n";

			html_end_box();

			form_end();

			bottom_footer();
		}else {
			$selected_items = array();
			$list = '';

			/* ================= input validation ================= */
			input_validate_input_number( get_request_var_post('id'));
			/* ==================================================== */

			foreach($_POST as $key => $value) {
				if(strstr($key, 'chk_')) {
					/* grep mib and notification name */
					$row_id = substr($key, 4);
					list($mib, $name) = explode('__', $row_id);
					$list .= '<li><b>' . $name . ' (' . $mib .')</b></li>';
					$selected_items[$mib][$name] = 1;
				}
			}

			top_header();

			form_start('managers.php');

			html_start_box($manager_notification_actions[$_POST['drp_action']], '60%', '', '3', 'center', '');

			if (sizeof($selected_items)) {
				$msg = ($_POST['drp_action'] == 1)
					 ? "Click 'Continue' to forward the following Notification Objects to this Noticification Receiver."
					 : "Click 'Continue' to disable forwarding the following Notification Objects to this Noticification Receiver.";

				print "<tr>
					<td class='textArea'>
						<p>$msg</p>
						<p><ul>$list</ul></p>
					</td>
				</tr>\n";

				$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Disable Notification Objects'>";
			} else {
				print "<tr><td><span class='textError'>You must select at least one notification object.</span></td></tr>\n";
				$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
			}

			print "<tr>
				<td class='saveRow'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='action_receiver_notifications' value='1'>
				<input type='hidden' name='selected_items' value='" . (isset($selected_items) ? serialize($selected_items) : '') . "'>
				<input type='hidden' name='id' value='" . $_POST['id'] . "'>
				<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
				$save_html
				</td>
			</tr>\n";

			html_end_box();

			form_end();

			bottom_footer();
		}
	}
}

