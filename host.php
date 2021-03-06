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
 | This program is distributed in the hope that it will be useful,         |
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
include_once('./lib/utility.php');
include_once('./lib/api_data_source.php');
include_once('./lib/api_tree.php');
include_once('./lib/html_tree.php');
include_once('./lib/api_graph.php');
include_once('./lib/snmp.php');
include_once('./lib/ping.php');
include_once('./lib/data_query.php');
include_once('./lib/api_device.php');

define('MAX_DISPLAY_PAGES', 21);

$device_actions = array(
	1 => 'Delete',
	2 => 'Enable',
	3 => 'Disable',
	4 => 'Change SNMP Options',
	5 => 'Clear Statistics',
	6 => 'Change Availability Options',
	7 => 'Apply Automation Rules to Device(s)'
);

$device_actions = api_plugin_hook_function('device_action_array', $device_actions);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'gt_add':
		host_add_gt();

		header('Location: host.php?header=false&action=edit&id=' . $_REQUEST['host_id']);
		break;
	case 'gt_remove':
		host_remove_gt();

		header('Location: host.php?header=false&action=edit&id=' . $_REQUEST['host_id']);
		break;
	case 'query_add':
		host_add_query();

		header('Location: host.php?header=false&action=edit&id=' . $_REQUEST['host_id']);
		break;
	case 'query_remove':
		host_remove_query();

		header('Location: host.php?header=false&action=edit&id=' . $_REQUEST['host_id']);
		break;
	case 'query_reload':
		host_reload_query();

		header('Location: host.php?header=false&action=edit&id=' . $_REQUEST['host_id']);
		break;
	case 'query_verbose':
		host_reload_query();

		header('Location: host.php?header=' . (isset($_REQUEST['header']) && $_REQUEST['header'] == 'true' ? 'true':'false') . '&action=edit&id=' . $_REQUEST['host_id'] . '&display_dq_details=true#dqdbg');
		break;
	case 'edit':
		top_header();

		host_edit();

		bottom_footer();
		break;
	case 'ping_host':
		ping_host();
		break;
	default:
		top_header();

		host();

		bottom_footer();
		break;
}

/* --------------------------
    Global Form Functions
   -------------------------- */

function add_tree_names_to_actions_array() {
	global $device_actions;

	/* add a list of tree names to the actions dropdown */
	$trees = db_fetch_assoc('SELECT id, name FROM graph_tree ORDER BY name');

	if (sizeof($trees)) {
		foreach ($trees as $tree) {
			$device_actions{'tr_' . $tree['id']} = 'Place on a Tree (' . $tree['name'] . ')';
		}
	}
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_host'])) {
		if ($_POST['snmp_version'] == 3 && ($_POST['snmp_password'] != $_POST['snmp_password_confirm'])) {
			raise_message(4);
		}else{
			input_validate_input_number(get_request_var_post('id'));
			input_validate_input_number(get_request_var_post('host_template_id'));

			$host_id = api_device_save($_POST['id'], $_POST['host_template_id'], $_POST['description'],
				trim($_POST['hostname']), $_POST['snmp_community'], $_POST['snmp_version'],
				$_POST['snmp_username'], $_POST['snmp_password'],
				$_POST['snmp_port'], $_POST['snmp_timeout'],
				(isset($_POST['disabled']) ? $_POST['disabled'] : ''),
				$_POST['availability_method'], $_POST['ping_method'],
				$_POST['ping_port'], $_POST['ping_timeout'],
				$_POST['ping_retries'], $_POST['notes'],
				$_POST['snmp_auth_protocol'], $_POST['snmp_priv_passphrase'],
				$_POST['snmp_priv_protocol'], $_POST['snmp_context'], $_POST['max_oids'], $_POST['device_threads']);
		}

		header('Location: host.php?header=false&action=edit&id=' . (empty($host_id) ? $_POST['id'] : $host_id));
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $device_actions, $fields_host_edit;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '2') { /* Enable Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE host SET disabled = '' WHERE id = ?", array($selected_items[$i]));

					/* update poller cache */
					$data_sources = db_fetch_assoc_prepared('SELECT id FROM data_local WHERE host_id = ?', array($selected_items[$i]));
					$poller_items = $local_data_ids = array();

					if (sizeof($data_sources)) {
						foreach ($data_sources as $data_source) {
							$local_data_ids[] = $data_source['id'];
							$poller_items     = array_merge($poller_items, update_poller_cache($data_source['id']));
						}
					}

					if (sizeof($local_data_ids)) {
						poller_update_poller_cache_from_buffer($local_data_ids, $poller_items);
					}
				}
			}elseif ($_POST['drp_action'] == '3') { /* Disable Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE host SET disabled='on' WHERE id = ?", array($selected_items[$i]));

					/* update poller cache */
					db_execute_prepared('DELETE FROM poller_item WHERE host_id = ?', array($selected_items[$i]));
					db_execute_prepared('DELETE FROM poller_reindex WHERE host_id = ?', array($selected_items[$i]));
				}
			}elseif ($_POST['drp_action'] == '4') { /* change snmp options */
				for ($i=0;($i<count($selected_items));$i++) {
					reset($fields_host_edit);
					while (list($field_name, $field_array) = each($fields_host_edit)) {
						if (isset($_POST["t_$field_name"])) {
							db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array($_POST[$field_name], $selected_items[$i]));
						}
					}

					push_out_host($selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == '5') { /* Clear Statisitics for Selected Devices */
				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared("UPDATE host SET min_time = '9.99999', max_time = '0', cur_time = '0', avg_time = '0',
						total_polls = '0', failed_polls = '0',	availability = '100.00'
						where id = ?", array($selected_items[$i]));
				}
			}elseif ($_POST['drp_action'] == '6') { /* change availability options */
				for ($i=0;($i<count($selected_items));$i++) {
					reset($fields_host_edit);
					while (list($field_name, $field_array) = each($fields_host_edit)) {
						if (isset($_POST["t_$field_name"])) {
							db_execute_prepared("UPDATE host SET $field_name = ? WHERE id = ?", array($_POST[$field_name], $selected_items[$i]));
						}
					}

					push_out_host($selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == '1') { /* delete */
				if (!isset($_POST['delete_type'])) { $_POST['delete_type'] = 2; }

				$data_sources_to_act_on = array();
				$graphs_to_act_on       = array();
				$devices_to_act_on      = array();

				for ($i=0; $i<count($selected_items); $i++) {
					$data_sources = db_fetch_assoc('SELECT
						data_local.id AS local_data_id
						FROM data_local
						WHERE ' . array_to_sql_or($selected_items, 'data_local.host_id'));

					if (sizeof($data_sources)) {
						foreach ($data_sources as $data_source) {
							$data_sources_to_act_on[] = $data_source['local_data_id'];
						}
					}

					if ($_POST['delete_type'] == 2) {
						$graphs = db_fetch_assoc('SELECT
							graph_local.id AS local_graph_id
							FROM graph_local
							WHERE ' . array_to_sql_or($selected_items, 'graph_local.host_id'));

						if (sizeof($graphs)) {
							foreach ($graphs as $graph) {
								$graphs_to_act_on[] = $graph['local_graph_id'];
							}
						}
					}

					$devices_to_act_on[] = $selected_items[$i];
				}

				switch ($_POST['delete_type']) {
					case '1': /* leave graphs and data_sources in place, but disable the data sources */
						api_data_source_disable_multi($data_sources_to_act_on);

						api_plugin_hook_function('data_source_remove', $data_sources_to_act_on);

						break;
					case '2': /* delete graphs/data sources tied to this device */
						api_data_source_remove_multi($data_sources_to_act_on);

						api_graph_remove_multi($graphs_to_act_on);

						api_plugin_hook_function('graphs_remove', $graphs_to_act_on);

						break;
				}

				api_device_remove_multi($devices_to_act_on);

				api_plugin_hook_function('device_remove', $devices_to_act_on);
			}elseif (preg_match('/^tr_([0-9]+)$/', $_POST['drp_action'], $matches)) { /* place on tree */
				input_validate_input_number(get_request_var_post('tree_id'));
				input_validate_input_number(get_request_var_post('tree_item_id'));

				for ($i=0;($i<count($selected_items));$i++) {
					api_tree_item_save(0, $_POST['tree_id'], TREE_ITEM_TYPE_HOST, $_POST['tree_item_id'], '', 0, read_graph_config_option('default_rra_id'), $selected_items[$i], 1, 1, false);
				}
			}elseif ($action == 7) { /* automation */
				cacti_log(__FUNCTION__ . ' called, action: ' . $action, true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

				cacti_log(__FUNCTION__ . ', items: ' . $_POST['selected_items'], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

				/* work on all selected hosts */
				for ($i=0;($i<count($selected_items));$i++) {
					$host_id = $selected_items[$i];

					cacti_log(__FUNCTION__ . ' Host[' . $host_id . ']', true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

					/* select all graph templates associated with this host, but exclude those where
					*  a graph already exists (table graph_local has a known entry for this host/template) */
					$sql = 'SELECT gt.*
						FROM graph_templates AS gt
						INNER JOIN host_graph AS hg
						ON gt.id=hg.graph_template_id
						WHERE hg.host_id=' . $host_id . ' 
						AND gt.id NOT IN (
							SELECT gl.graph_template_id 
							FROM graph_local AS gl
							WHERE host_id=' . $host_id . '
						)';

					$graph_templates = db_fetch_assoc($sql);

					cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], sql: ' . $sql, true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

					/* create all graph template graphs */
					if (sizeof($graph_templates)) {
						foreach ($graph_templates as $graph_template) {
							cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], graph: ' . $graph_template['id'], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

							automation_execute_graph_template($host_id, $graph_template['id']);
						}
					}

					/* all associated data queries */
					$data_queries = db_fetch_assoc('SELECT sq.*
						host_snmp_query.reindex_method 
						FROM snmp_query AS sq
						INNER JOIN host_snmp_query AS hsq
						ON sq.id=hsq.snmp_query_id
						WHERE hsq.host_id=' . $host_id);

					/* create all data query graphs */
					if (sizeof($data_queries)) {
						foreach ($data_queries as $data_query) {
							cacti_log(__FUNCTION__ . ' Host[' . $data['host_id'] . '], dq: ' . $data['snmp_query_id'], true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

							automation_execute_data_query($host_id, $data_query['snmp_query_id']);
						}
					}

					/* now handle tree rules for that host */
					cacti_log(__FUNCTION__ . ' Host[' . $host_id . '], create_tree for host: ' . $host_id, true, 'AUTOMATION TRACE', POLLER_VERBOSITY_MEDIUM);

					automation_execute_device_create_tree($host_id);
				}
			} else {
				api_plugin_hook_function('device_action_execute', $_POST['drp_action']);
			}
		}

		/* update snmpcache */
		snmpagent_device_action_bottom(array($_POST['drp_action'], $selected_items));
		
		api_plugin_hook_function('device_action_bottom', array($_POST['drp_action'], $selected_items));

		header('Location: host.php?header=false');
		exit;
	}

	/* setup some variables */
	$host_list = ''; $i = 0;

	/* loop through each of the host templates selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$host_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($matches[1]))) . '</li>';
			$host_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	form_start('host.php');

	html_start_box($device_actions[get_request_var_post('drp_action')], '60%', '', '3', 'center', '');

	if (isset($host_array) && sizeof($host_array)) {
		if ($_POST['drp_action'] == '2') { /* Enable Devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>Click 'Continue' to enable the following Device(s).</p>
					<p><ul>$host_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Enable Device(s)'>";
		}elseif ($_POST['drp_action'] == '3') { /* Disable Devices */
			print "	<tr>
				<td colspan='2' class='textArea'>
					<p>Click 'Continue' to disable the following Device(s).</p>
					<p><ul>$host_list</ul></p>
				</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Disable Device(s)'>";
		}elseif ($_POST['drp_action'] == '4') { /* change snmp options */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>Click 'Continue' to change SNMP parameters for the following Device(s).  
					Please check the box next to the fields you want to update, and then fill in the new value.</p>
					<p><ul>$host_list</ul></p>
				</td>
			</tr>\n";

			$form_array = array();

			while (list($field_name, $field_array) = each($fields_host_edit)) {
				if ((preg_match('/^snmp_/', $field_name)) ||
					($field_name == 'max_oids')) {
					$form_array += array($field_name => $fields_host_edit[$field_name]);

					$form_array[$field_name]['value'] = '';
					$form_array[$field_name]['description'] = '';
					$form_array[$field_name]['form_id'] = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name' => 't_' . $field_name,
						'friendly_name' => 'Update this Field',
						'value' => ''
						);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Change Device(s) SNMP Options'>";
		}elseif ($_POST['drp_action'] == '6') { /* change availability options */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>Click 'Continue' to change Availability parameters for the following Device(s). 
					Please check the box next to the fields you want to update, then fill in the new value.</p>
					<p><ul>$host_list</ul></p>
				</td>
			</tr>\n";

			$form_array = array();

			while (list($field_name, $field_array) = each($fields_host_edit)) {
				if (preg_match('/(availability_method|ping_method|ping_port)/', $field_name)) {
					$form_array += array($field_name => $fields_host_edit[$field_name]);

					$form_array[$field_name]['value'] = '';
					$form_array[$field_name]['description'] = '';
					$form_array[$field_name]['form_id'] = 0;
					$form_array[$field_name]['sub_checkbox'] = array(
						'name' => 't_' . $field_name,
						'friendly_name' => 'Update this Field',
						'value' => ''
						);
				}
			}

			draw_edit_form(
				array(
					'config' => array('no_form_tag' => true),
					'fields' => $form_array
				)
			);

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Change Device(s) Availability Options'>";
		}elseif ($_POST['drp_action'] == '5') { /* Clear Statisitics for Selected Devices */
			print "<tr>
				<td colspan='2' class='textArea'>
					<p>Click 'Continue' to clear the counters for the following Device(s).</p>
					<p><ul>$host_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Clear Statistics on Device(s)'>";
		}elseif ($_POST['drp_action'] == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to delete the following Device(s).</p>
					<p><ul>$host_list</ul></p>\n";

					form_radio_button('delete_type', '2', '1', 'Leave all Graph(s) and Data Source(s) untouched.  Data Source(s) will be disabled however.', '1'); print '<br>';
					form_radio_button('delete_type', '2', '2', 'Delete all associated <strong>Graph(s)</strong> and <strong>Data Source(s)</strong>.', '1'); print '<br>';

			print "</td></tr>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Device(s)'>";
		}elseif (preg_match('/^tr_([0-9]+)$/', $_POST['drp_action'], $matches)) { /* place on tree */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to place the following Device(s) under the branch selected below.</p>
					<p><ul>$host_list</ul></p>
					<p><strong>Destination Branch:</strong><br>\n";
					grow_dropdown_tree($matches[1], '0', 'tree_item_id', '0'); 

			print "</p>
				</td>
			</tr>
			<input type='hidden' name='tree_id' value='" . $matches[1] . "'>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Place Device(s) on Tree'>";
		}elseif ($save['drp_action'] == 7) { /* automation */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to apply Automation Rules to the following Devices(s)</p>
					<p><ul>$host_list</ul></p>
				</td>
			</tr>\n";
		} else {
			$save['drp_action'] = $_POST['drp_action'];
			$save['host_list'] = $host_list;
			$save['host_array'] = (isset($host_array)? $host_array : array());
			api_plugin_hook_function('device_action_prepare', $save);
			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one device.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($host_array) ? serialize($host_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* -------------------
    Data Query Functions
   ------------------- */

function host_add_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('host_id'));
	input_validate_input_number(get_request_var_post('snmp_query_id'));
	input_validate_input_number(get_request_var_post('reindex_method'));
	/* ==================================================== */

	db_execute_prepared('REPLACE INTO host_snmp_query (host_id, snmp_query_id, reindex_method) VALUES (?, ?, ?)', array($_POST['host_id'], $_POST['snmp_query_id'], $_POST['reindex_method']));

	/* recache snmp data */
	run_data_query($_POST['host_id'], $_POST['snmp_query_id']);
}

function host_reload_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_id'));
	/* ==================================================== */

	run_data_query($_REQUEST['host_id'], $_REQUEST['id']);
}

function host_remove_query() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_id'));
	/* ==================================================== */

	api_device_dq_remove($_REQUEST['host_id'], $_REQUEST['id']);
}

function host_add_gt() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('host_id'));
	input_validate_input_number(get_request_var_post('graph_template_id'));
	/* ==================================================== */

	db_execute_prepared('REPLACE INTO host_graph (host_id, graph_template_id) VALUES (?, ?)', array($_POST['host_id'], $_POST['graph_template_id']));

	automation_hook_graph_template($_POST['host_id'], $_POST['graph_template_id']);

	api_plugin_hook_function('add_graph_template_to_host', array('host_id' => $_POST['host_id'], 'graph_template_id' => $_POST['graph_template_id']));
}

function host_remove_gt() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_id'));
	/* ==================================================== */

	api_device_gt_remove($_REQUEST['host_id'], $_REQUEST['id']);
}

/* ---------------------
    Device Functions
   --------------------- */

function host_remove() {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_REQUEST['confirm']))) {
		top_header();

		form_confirm('Are You Sure?', "Are you sure you want to delete the host <strong>'" . htmlspecialchars(db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', array($_REQUEST['id']))) . "'</strong>?", htmlspecialchars('host.php'), htmlspecialchars('host.php?action=remove&id=' . $_REQUEST['id']));

		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_REQUEST['confirm']))) {
		api_device_remove($_REQUEST['id']);
	}
}

function ping_host() {
	input_validate_input_number($_REQUEST['id']);

	if (empty($_REQUEST['id'])) {
		return "";
	}

	$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($_REQUEST['id']));
	$am   = $host['availability_method'];
	$anym = false;

	if ($am == AVAIL_SNMP || $am == AVAIL_SNMP_GET_NEXT ||
		$am == AVAIL_SNMP_GET_SYSDESC || $am == AVAIL_SNMP_AND_PING ||
		$am == AVAIL_SNMP_OR_PING) {

		$anym = true;

		print "SNMP Information<br>\n";
		print "<span class='monoSpace'>\n";

		if (($host['snmp_community'] == '' && $host['snmp_username'] == '') || $host['snmp_version'] == 0) {
			print "<span style='color: #ab3f1e; font-weight: bold;'>SNMP not in use</span>\n";
		}else{
			$snmp_system = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.1.0', $host['snmp_version'],
				$host['snmp_username'], $host['snmp_password'],
				$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
				$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'),SNMP_WEBUI);

			/* modify for some system descriptions */
			/* 0000937: System output in host.php poor for Alcatel */
			if (substr_count($snmp_system, '00:')) {
				$snmp_system = str_replace('00:', '', $snmp_system);
				$snmp_system = str_replace(':', ' ', $snmp_system);
			}

			if ($snmp_system == '') {
				print "<span class='hostDown'>SNMP error</span>\n";
			}else{
				$snmp_uptime   = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.3.0', $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'],
					$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
					$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

				$snmp_hostname = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.5.0', $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'],
					$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
					$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

				$snmp_location = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.6.0', $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'],
					$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
					$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

				$snmp_contact  = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.4.0', $host['snmp_version'],
					$host['snmp_username'], $host['snmp_password'],
					$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
					$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

				print '<strong>System:</strong>' . html_split_string($snmp_system) . "<br>\n";
				$days      = intval($snmp_uptime / (60*60*24*100));
				$remainder = $snmp_uptime % (60*60*24*100);
				$hours     = intval($remainder / (60*60*100));
				$remainder = $remainder % (60*60*100);
				$minutes   = intval($remainder / (60*100));
				print "<strong>Uptime:</strong> $snmp_uptime";
				print "&nbsp;($days days, $hours hours, $minutes minutes)<br>\n";
				print "<strong>Hostname:</strong> $snmp_hostname<br>\n";
				print "<strong>Location:</strong> $snmp_location<br>\n";
				print "<strong>Contact:</strong> $snmp_contact<br>\n";
			}
		}
		print "</span>\n";
	}

	if ($am == AVAIL_PING || $am == AVAIL_SNMP_AND_PING || $am == AVAIL_SNMP_OR_PING) {
		$anym = true;

		/* create new ping socket for host pinging */
		$ping = new Net_Ping;

		$ping->host = $host;
		$ping->port = $host['ping_port'];

		/* perform the appropriate ping check of the host */
		$ping_results = $ping->ping(AVAIL_PING, $host['ping_method'], $host['ping_timeout'], $host['ping_retries']);

		if ($ping_results == true) {
			$host_down = false;
			$class     = 'hostUp';
		}else{
			$host_down = true;
			$class     = 'hostDown';
		}

		print "Ping Results<br>\n";
		print "<span class='" . $class . "'>" . $ping->ping_response . "</span>\n";
	}

	if ($anym == false) {
		print "No Ping or SNMP Availability Check In Use<br><br>\n";
	}
}

function host_edit() {
	global $fields_host_edit, $reindex_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	api_plugin_hook('host_edit_top');

	if (!empty($_REQUEST['id'])) {
		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars($host['description']) . ']';
	}else{
		$header_label = '[new]';
	}

	if (!empty($host['id'])) {
		?>
		<table style='width:100%'>
			<tr>
				<td class='textInfo left'>
					<?php print htmlspecialchars($host['description']);?> (<?php print htmlspecialchars($host['hostname']);?>)
				</td>
				<td rowspan='2' class='textInfo right' style='vertical-align:top'>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('graphs_new.php?host_id=' . $host['id']);?>'>Create Graphs for this Device</a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('data_sources.php?host_id=' . $host['id'] . '&ds_rows=30&filter=&template_id=-1&method_id=-1&page=1');?>'>Data Source List</a><br>
					<span class='linkMarker'>*</span><a class='hyperLink' href='<?php print htmlspecialchars('graphs.php?host_id=' . $host['id'] . '&graph_rows=30&filter=&template_id=-1&page=1');?>'>Graph List</a>
					<?php api_plugin_hook('device_edit_top_links'); ?>
				</td>
			</tr>
			<tr>
				<td style='vertical-align:top;' class='textHeader'>
					<div id='ping_results'>Contacting Device&nbsp;<i style='font-size:12px;' class='fa fa-spin fa-spinner'></i><br><br></div>
				</td>
			</tr>
		</table>
		<?php
	}

	form_start('host.php', 'host_form');

	html_start_box("Device $header_label", '100%', '', '3', 'center', '');

	/* preserve the host template id if passed in via a GET variable */
	if (!empty($_REQUEST['host_template_id'])) {
		$fields_host_edit['host_template_id']['value'] = $_REQUEST['host_template_id'];
	}

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_host_edit, (isset($host) ? $host : array()))
		));

	html_end_box();

	?>
	<script type="text/javascript">

	// default snmp information
	var snmp_community       = $('#snmp_community').val();
	var snmp_username        = $('#snmp_username').val();
	var snmp_password        = $('#snmp_password').val();
	var snmp_auth_protocol   = $('#snmp_auth_protocol').val();
	var snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
	var snmp_priv_protocol   = $('#snmp_priv_protocol').val();
	var snmp_context         = $('#snmp_context').val();
	var snmp_port            = $('#snmp_port').val();
	var snmp_timeout         = $('#snmp_timeout').val();
	var max_oids             = $('#max_oids').val();

	// default ping methods
	var ping_method    = $('#ping_method').val();
	var ping_port      = $('#ping_port').val();
	var ping_timeout   = $('#ping_timeout').val();
	var ping_retries   = $('#ping_retries').val();

	function setPing() {
		availability_method = $('#availability_method').val();
		ping_method         = $('#ping_method').val();

		switch(availability_method) {
		case '0': // none
			$('#row_ping_method').css('display', 'none');
			$('#row_ping_port').css('display', 'none');
			$('#row_ping_timeout').css('display', 'none');
			$('#row_ping_retries').css('display', 'none');

			break;
		case '2': // snmp
		case '5': // snmp sysDesc
		case '6': // snmp getNext
			$('#row_ping_method').css('display', 'none');
			$('#row_ping_port').css('display', 'none');
			$('#row_ping_timeout').css('display', '');
			$('#row_ping_retries').css('display', '');

			break;
		default: // ping ok
			switch(ping_method) {
			case '1': // ping icmp
				$('#row_ping_method').css('display', '');
				$('#row_ping_port').css('display', 'none');
				$('#row_ping_timeout').css('display', '');
				$('#row_ping_retries').css('display', '');

				break;
			case '2': // ping udp
			case '3': // ping tcp
				$('#row_ping_method').css('display', '');
				$('#row_ping_port').css('display', '');
				$('#row_ping_timeout').css('display', '');
				$('#row_ping_retries').css('display', '');

				break;
			}

			break;
		}
	}

	function setAvailability() {
		if ($('#snmp_version').val() == '0') {
			methods = [ 
				{ value: '0', text: 'None' }, 
				{ value: '3', text: 'Ping' }
			];

			if ($('#availability_method').val() != '3' && $('#availability_method').val() != '0') {
				$('#availability_method').val('3');
			}

			$('#availability_method').replaceOptions(methods, $('#availability_method').val());
		}else{
			methods = [
				{ value: '0', text: 'None' }, 
				{ value: '1', text: 'Ping and SNMP Uptime' }, 
				{ value: '2', text: 'SNMP Uptime' }, 
				{ value: '3', text: 'Ping' }, 
				{ value: '4', text: 'Ping or SNMP Uptime' }, 
				{ value: '5', text: 'SNMP Desc' }, 
				{ value: '6', text: 'SNMP GetNext' }
			];

			$('#availability_method').replaceOptions(methods, $('#availability_method').val());
		}

		switch($('#availibility_method').val()) {
			case '0': // availability none
				$('#row_ping_method').hide();
				$('#ping_method').val(0);
				$('#row_ping_timeout').hide();
				$('#row_ping_port').hide();
				$('#row_ping_timeout').hide();
				$('#row_ping_retrie').hide();

				break;
			case '1': // ping and snmp sysUptime
			case '3': // ping
			case '4': // ping or snmp sysUptime
				if (($('#row_ping_method').css('display', 'none')) ||
					($('#row_ping_method').css('display') == undefined)) {
					$('#ping_method').val(ping_method);
					$('#row_ping_method').css('display', '');
				}

				break;
			case '2': // snmp sysUptime
			case '5': // snmp sysDesc
			case '6': // snmp getNext
				$('#row_ping_method').css('display', 'none');
				$('#ping_method').val(0);

				break;
		}

		if ($('#availability_method-button').length) {
			$('#availability_method').selectmenu('refresh');
		}
	}

	function changeHostForm() {
		setSNMP();
		setAvailability();
		setPing();
	}

	function setSNMP() {
		snmp_version = $('#snmp_version').val();
		switch(snmp_version) {
		case '0': // No SNMP
			$('#row_snmp_username').hide();
			$('#row_snmp_password').hide();
			$('#row_snmp_community').hide();
			$('#row_snmp_auth_protocol').hide();
			$('#row_snmp_priv_passphrase').hide();
			$('#row_snmp_priv_protocol').hide();
			$('#row_snmp_context').hide();
			$('#row_snmp_port').hide();
			$('#row_snmp_timeout').hide();
			$('#row_max_oids').hide();
			break;
		case '1': // SNMP v1
		case '2': // SNMP v2c
			$('#row_snmp_username').hide();
			$('#row_snmp_password').hide();
			$('#row_snmp_community').show();
			$('#row_snmp_auth_protocol').hide();
			$('#row_snmp_priv_passphrase').hide();
			$('#row_snmp_priv_protocol').hide();
			$('#row_snmp_context').hide();
			$('#row_snmp_port').show();
			$('#row_snmp_timeout').show();
			$('#row_max_oids').show();
			break;
		case '3': // SNMP v3
			$('#row_snmp_username').show();
			$('#row_snmp_password').show();
			$('#row_snmp_community').hide();
			$('#row_snmp_auth_protocol').show();
			$('#row_snmp_priv_passphrase').show();
			$('#row_snmp_priv_protocol').show();
			$('#row_snmp_context').show();
			$('#row_snmp_port').show();
			$('#row_snmp_timeout').show();
			$('#row_max_oids').show();
			break;
		}
	}

	$(function() {
		$('[id^="reload"]').click(function(data) {
			$(this).removeClass('fa-circle-o').addClass('fa-circle-o-notch fa-spin');
			strURL = 'host.php?action=query_reload&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL);
		});

		$('[id^="verbose"]').click(function(data) {
			strURL = 'host.php?action=query_verbose&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL);
		});

		$('[id^="remove"]').click(function(data) {
			strURL = 'host.php?action=query_remove&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL);
		});

		$('[id^="gtremove"]').click(function(data) {
			strURL = 'host.php?action=gt_remove&id='+$(this).attr('data-id')+'&host_id='+$('#id').val();
			loadPageNoHeader(strURL);
		});

		$('#add_dq').click(function() {
			$.post('host.php?action=query_add', { host_id: $('#id').val(), snmp_query_id: $('#snmp_query_id').val(), reindex_method: $('#reindex_method').val(), __csrf_magic: csrfMagicToken }).done(function(data) {
				$('#main').html(data);
				applySkin();
			});
		});

		$('#add_gt').click(function() {
			$.post('host.php?action=gt_add', { host_id: $('#id').val(), graph_template_id: $('#graph_template_id').val(), __csrf_magic: csrfMagicToken }).done(function(data) {
				$('#main').html(data);
				applySkin();
			});
		});

		changeHostForm();
		$('#dbghide').unbind().click(function(data) {
			$('#dqdebug').fadeOut('fast');
		});

		$.get(urlPath+'host.php?action=ping_host&id='+$('#id').val(), function(data) {
			$('#ping_results').html(data);
		});
	});

	</script>
	<?php

	if ((isset($_REQUEST['display_dq_details'])) && (isset($_SESSION['debug_log']['data_query']))) {
		print "<table id='dqdebug' class='cactiDebugTable'><tr><td>\n";
		print "<table class='cactiTableTitle'>\n";
		print "<tr><td class='textHeaderDark'><a style='display:none;' name='dqdbg'></a><strong>Data Query Debug Information</strong></td><td class='textHeaderDark' align='right'><span id='dbghide' class='linkOverDark'>Hide</span></td></tr>\n";
		print "</table>\n";
		print "<table class='cactiTable'>\n";
		print "<tr><td class='debug'><span>" . debug_log_return('data_query') . "</span></td></tr>";
		print "</table>\n";
		print "</table>\n";
	}

	if (!empty($host['id'])) {
		html_start_box('Associated Graph Templates', '100%', '', '3', 'center', '');

		html_header(array('Graph Template Name', 'Status'), 2);

		$selected_graph_templates = db_fetch_assoc_prepared('SELECT
			graph_templates.id,
			graph_templates.name
			FROM (graph_templates, host_graph)
			WHERE graph_templates.id = host_graph.graph_template_id
			AND host_graph.host_id = ?
			ORDER BY graph_templates.name', array($_REQUEST['id']));

		$available_graph_templates = db_fetch_assoc_prepared('SELECT
			graph_templates.id, graph_templates.name
			FROM snmp_query_graph 
			RIGHT JOIN graph_templates
			ON snmp_query_graph.graph_template_id = graph_templates.id
			WHERE snmp_query_graph.name IS NULL 
			AND graph_templates.id NOT IN (SELECT graph_template_id FROM host_graph WHERE host_id = ?) 
			ORDER BY graph_templates.name', array($_REQUEST['id']));

		$i = 0;
		if (sizeof($selected_graph_templates)) {
			foreach ($selected_graph_templates as $item) {
				$i++;

				form_alternate_row("gt$i", true);

				/* get status information for this graph template */
				$is_being_graphed = (sizeof(db_fetch_assoc_prepared('SELECT id FROM graph_local WHERE graph_template_id = ? AND host_id = ?', array($item['id'], $_REQUEST['id']))) > 0) ? true : false;

				?>
					<td style="padding: 4px;">
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td>
						<?php print (($is_being_graphed == true) ? "<span class='beingGraphed'>Is Being Graphed</span> (<a class='linkEditMain' href='" . htmlspecialchars('graphs.php?action=graph_edit&id=' . db_fetch_cell_prepared('SELECT id FROM graph_local WHERE graph_template_id = ? AND host_id = ? LIMIT 0,1', array($item['id'], $_REQUEST['id']))) . "'>Edit</a>)" : "<span class='notBeingGraphed'>Not Being Graphed</span>");?>
					</td>
					<td class='nowrap right'>
						<span title='Delete Graph Template Association' class='deletequery fa fa-remove' id='gtremove<?php print $item['id'];?>' data-id='<?php print $item['id'];?>'></span>
					</td>
				<?php

				form_end_row();
			}
		}else{ 
			print "<tr class='tableRow'><td colspan='3'><em>No associated graph templates.</em></td></tr>"; 
		}

		?>
		<tr class='odd'>
			<td class='saveRow' colspan='3'>
				<table style='width:20%;'>
					<tr style='line-height:10px;'>
						<td class='nowrap' style='padding-right:15px;'>
							Add Graph Template
						</td>
						<td>
							<?php form_dropdown('graph_template_id',$available_graph_templates,'name','id','','','');?>
						</td>
						<td>
							<input type='button' value='Add' id='add_gt' title='Add Graph Template to Device'>
						</td>
					</tr>
				</table>
			</td>
		</tr>

		<?php
		html_end_box();

		html_start_box('Associated Data Queries', '100%', '', '3', 'center', '');

		html_header(array('Data Query Name', 'Debugging', 'Re-Index Method', 'Status'), 2);

		$selected_data_queries = db_fetch_assoc_prepared('SELECT
			snmp_query.id,
			snmp_query.name,
			host_snmp_query.reindex_method
			FROM (snmp_query, host_snmp_query)
			WHERE snmp_query.id = host_snmp_query.snmp_query_id
			AND host_snmp_query.host_id = ?
			ORDER BY snmp_query.name', array($_REQUEST['id']));

		$available_data_queries = db_fetch_assoc('SELECT
			snmp_query.id,
			snmp_query.name
			FROM snmp_query
			ORDER BY snmp_query.name');

		$keeper = array();
		if (sizeof($available_data_queries)) {
			foreach ($available_data_queries as $item) {
				if (sizeof(db_fetch_assoc_prepared('SELECT snmp_query_id FROM host_snmp_query WHERE host_id = ? AND snmp_query_id = ?', array($_REQUEST['id'], $item['id']))) > 0) {
					/* do nothing */
				} else {
					array_push($keeper, $item);
				}
			}
		}

		$available_data_queries = $keeper;

		$i = 0;
		if (sizeof($selected_data_queries)) {
			foreach ($selected_data_queries as $item) {
				$i++;

				form_alternate_row("dg$i", true);

				/* get status information for this data query */
				$num_dq_items = sizeof(db_fetch_assoc_prepared('SELECT snmp_index FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ?', array($_REQUEST['id'], $item['id'])));
				$num_dq_rows  = sizeof(db_fetch_assoc_prepared('SELECT snmp_index FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? GROUP BY snmp_index', array($_REQUEST['id'], $item['id'])));

				$status = 'success';

				?>
					<td style='padding: 4px;'>
						<strong><?php print $i;?>)</strong> <?php print htmlspecialchars($item['name']);?>
					</td>
					<td>
						(<span id='verbose<?php print $item['id'];?>' class='linkEditMain' data-id='<?php print $item['id'];?>'>Verbose Query</span>)
					</td>
					<td>
					<?php print $reindex_types{$item['reindex_method']};?>
					</td>
					<td>
						<?php print (($status == 'success') ? "<span class='success'>Success</span>" : "<span class='failed'>Fail</span>");?> [<?php print $num_dq_items;?> Item<?php print ($num_dq_items == 1 ? '' : 's');?>, <?php print $num_dq_rows;?> Row<?php print ($num_dq_rows == 1 ? '' : 's');?>]
					</td>
					<td class='nowrap right' style='vertical-align:middle;'>
						<span class='reloadquery fa fa-circle-o' id='reload<?php print $item['id'];?>' data-id='<?php print $item['id'];?>'></span>
						<span class='deletequery fa fa-remove' id='remove<?php print $item['id'];?>' data-id='<?php print $item['id'];?>'></span>
					</td>
				<?php
				form_end_row();
			}
		}else{ 
			print "<tr class='tableRow'><td colspan='4'><em>No associated data queries.</em></td></tr>"; 
		}

		?>
		<tr class='odd'>
			<td class='saveRow' colspan='5'>
				<table style='width:20%'>
					<tr style='line-height:10px;'>
						<td class='nowrap' style='padding-right:15px;'>
							Add Data Query
						</td>
						<td>
							<?php form_dropdown('snmp_query_id',$available_data_queries,'name','id','','','');?>
						</td>
						<td class='nowrap' style='padding-right:15px;'>
							Re-Index Method
						</td>
						<td>
							<?php form_dropdown('reindex_method',$reindex_types,'','',read_config_option('reindex_method'),'','');?>
						</td>
						<td>
							<input type='button' value='Add' id='add_dq' title='Add Data Query to Device'>
						</td>
					</tr>
				</table>
				<a style='display:none;' name='dqtop'></a>
			</td>
		</tr>

		<?php
		html_end_box();
	}

	form_save_button('host.php', 'return');

	api_plugin_hook('host_edit_bottom');
}

function host() {
	global $device_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('host_template_id'));
	input_validate_input_number(get_request_var('page'));
	input_validate_input_number(get_request_var('host_status'));
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

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_device_current_page');
		kill_session_var('sess_device_filter');
		kill_session_var('sess_device_host_template_id');
		kill_session_var('sess_host_status');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_host_sort_column');
		kill_session_var('sess_host_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['host_template_id']);
		unset($_REQUEST['host_status']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('host_template_id', 'sess_device_host_template_id');
		$changed += check_changed('filter',           'sess_device_filter');
		$changed += check_changed('host_status',      'sess_host_status');
		$changed += check_changed('rows',             'sess_default_rows');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	if ((!empty($_SESSION['sess_host_status'])) && (!empty($_REQUEST['host_status']))) {
		if ($_SESSION['sess_host_status'] != $_REQUEST['host_status']) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_device_current_page', '1');
	load_current_session_value('filter', 'sess_device_filter', '');
	load_current_session_value('host_template_id', 'sess_device_host_template_id', '-1');
	load_current_session_value('host_status', 'sess_host_status', '-1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('sort_column', 'sess_host_sort_column', 'description');
	load_current_session_value('sort_direction', 'sess_host_sort_direction', 'ASC');

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST['rows'] == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'host.php?host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'host.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function(data) {
		$('#refresh').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_devices').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>
	<?php

	html_start_box('Devices', '100%', '', '3', 'center', 'host.php?action=edit&host_template_id=' . htmlspecialchars(get_request_var('host_template_id')) . '&host_status=' . htmlspecialchars(get_request_var('host_status')));

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_devices' name='form_devices' action='host.php'>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
					</td>
					<td>
						Template
					</td>
					<td>
						<select id='host_template_id' name='host_template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>>None</option>
							<?php
							$host_templates = db_fetch_assoc('SELECT id, name FROM host_template ORDER BY name');

							if (sizeof($host_templates)) {
								foreach ($host_templates as $host_template) {
									print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . htmlspecialchars($host_template['name']) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						Status
					</td>
					<td>
						<select id='host_status' name='host_status' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>>Any</option>
							<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>>Enabled</option>
							<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>>Disabled</option>
							<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>>Not Up</option>
							<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>>Up</option>
							<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>>Down</option>
							<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>>Recovering</option>
							<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>>Unknown</option>
						</select>
					</td>
					<td>
						Devices
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<?php
							if (sizeof($item_rows)) {
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
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = "where (host.hostname like '%%" . get_request_var('filter') . "%%' OR host.description like '%%" . get_request_var('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_status') == '-2') {
		$sql_where .= (strlen($sql_where) ? " AND host.disabled='on'" : " WHERE host.disabled='on'");
	}elseif (get_request_var('host_status') == '-3') {
		$sql_where .= (strlen($sql_where) ? " AND host.disabled=''" : " WHERE host.disabled=''");
	}elseif (get_request_var('host_status') == '-4') {
		$sql_where .= (strlen($sql_where) ? " AND (host.status!='3' OR host.disabled='on')" : " WHERE (host.status!='3' OR host.disabled='on')");
	}else {
		$sql_where .= (strlen($sql_where) ? ' AND (host.status=' . get_request_var('host_status') . " AND host.disabled = '')" : 'where (host.status=' . get_request_var('host_status') . " AND host.disabled = '')");
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= (strlen($sql_where) ? ' AND host.host_template_id=0' : ' WHERE host.host_template_id=0');
	}elseif (!empty($_REQUEST['host_template_id'])) {
		$sql_where .= (strlen($sql_where) ? ' AND host.host_template_id=' . get_request_var('host_template_id') : ' WHERE host.host_template_id=' . get_request_var('host_template_id'));
	}

	form_start('host.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$total_rows = db_fetch_cell("SELECT
		COUNT(host.id)
		FROM host
		$sql_where");

	$sortby = get_request_var('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$sql_query = "SELECT host.*, graphs, data_sources
		FROM host
		LEFT JOIN (SELECT host_id, COUNT(*) AS graphs FROM graph_local GROUP BY host_id) AS gl
		ON host.id=gl.host_id
		LEFT JOIN (SELECT host_id, COUNT(*) AS data_sources FROM data_local GROUP BY host_id) AS dl
		ON host.id=dl.host_id
		$sql_where
		GROUP BY host.id
		ORDER BY " . $sortby . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows');

	$hosts = db_fetch_assoc($sql_query);

	$nav = html_nav_bar('host.php?filter=' . get_request_var('filter') . '&host_template_id=' . get_request_var('host_template_id') . '&host_status=' . get_request_var('host_status'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 13, 'Devices', 'page', 'main');

	print $nav;

	$display_text = array(
		'description' => array('display' => 'Device Description', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name by which this Device will be referred to.'),
		'hostname' => array('display' => 'Hostname', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'Either an IP address, or hostname.  If a hostname, it must be resolvable by either DNS, or from your hosts file.'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID for this Device.  Useful when performing automation or debugging.'),
		'graphs' => array('display' => 'Graphs', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The total number of Graphs generated from this Device.'),
		'data_sources' => array('display' => 'Data Sources', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The total number of Data Sources generated from this Device.'),
		'status' => array('display' => 'Status', 'align' => 'center', 'sort' => 'ASC', 'tip' => 'The monitoring status of the Device based upon ping results.  If this Device is a special type Device, by using the hostname "localhost", or due to the setting to not perform an Availability Check, it will always remain Up.  When using cmd.php data collector, a Device with no Graphs, is not pinged by the data collector and will remain in an "Unknown" state.'),
		'status_rec_date' => array('display' => 'In State', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The amount of time that this Device has been in its current state.'),
		'snmp_sysUpTimeInstance' => array('display' => 'Uptime', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The current amount of time that the host has been up.'),
		'polling_time' => array('display' => 'Poll Time', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The the amount of time it takes to collect data from this Device.'),
		'cur_time' => array('display' => 'Current (ms)', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The current ping time in milliseconds to reach the Device.'),
		'avg_time' => array('display' => 'Average (ms)', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The average ping time in milliseconds to reach the Device since the counters were cleared for this Device.'),
		'availability' => array('display' => 'Availability', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The availability percentage based upon ping results insce the counters were cleared for this Device.'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if ($host['disabled'] == '' && 
				($host['status'] == HOST_RECOVERING || $host['status'] == HOST_UP) &&
				($host['availability_method'] != AVAIL_NONE && $host['availability_method'] != AVAIL_PING)) { 
				$snmp_uptime = $host['snmp_sysUpTimeInstance'];
				$days      = intval($snmp_uptime / (60*60*24*100));
				$remainder = $snmp_uptime % (60*60*24*100);
				$hours     = intval($remainder / (60*60*100));
				$remainder = $remainder % (60*60*100);
				$minutes   = intval($remainder / (60*100));
				$uptime    = "$days:$hours:$minutes";
			}else{
				$uptime    = "N/A";
			}

			form_alternate_row('line' . $host['id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('host.php?action=edit&id=' . $host['id']) . "'>" .
				(strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['description'])) : htmlspecialchars($host['description'])) . '</a>', $host['id']);
			form_selectable_cell((strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($host['hostname'])) : htmlspecialchars($host['hostname'])), $host['id']);
			form_selectable_cell($host['id'], $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format($host['graphs']), $host['id'], '', 'text-align:right');
			form_selectable_cell(number_format($host['data_sources']), $host['id'], '', 'text-align:right');
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['id'], '', 'text-align:center');
			form_selectable_cell(get_timeinstate($host), $host['id'], '', 'text-align:right');
			form_selectable_cell($uptime, $host['id'], '', 'text-align:right');
			form_selectable_cell(round($host['polling_time'],2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round(($host['cur_time']), 2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round(($host['avg_time']), 2), $host['id'], '', 'text-align:right');
			form_selectable_cell(round($host['availability'], 2) . ' %', $host['id'], '', 'text-align:right');
			form_checkbox_cell($host['description'], $host['id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='11'><em>No Devices</em></td></tr>";
	}
	html_end_box(false);

	/* add a list of tree names to the actions dropdown */
	add_tree_names_to_actions_array();

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($device_actions);

	form_end();
}

function get_timeinstate($host) {
	$interval = read_config_option('poller_interval');
	if ($host['status_event_count'] > 0) {
		$time = $host['status_event_count'] * $interval;
	}elseif (strtotime($host['status_rec_date']) > 943916400) {
		$time = time() - strtotime($host['status_rec_date']);
	}else{
		return '-';
	}

	if ($time > 86400) {
		$days  = floor($time/86400);
		$time %= 86400;
	}else{
		$days  = 0;
	}

	if ($time > 3600) {
		$hours = floor($time/3600);
		$time  %= 3600;
	}else{
		$hours = 0;
	}

	$minutes = floor($time/60);

	return $days . ':' . $hours . ':' . $minutes;
}

