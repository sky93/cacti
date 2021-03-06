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

include ('./include/auth.php');
include_once('./lib/utility.php');
include_once('./lib/api_graph.php');
include_once('./lib/api_data_source.php');
include_once('./lib/template.php');
include_once('./lib/html_form_template.php');
include_once('./lib/rrd.php');
include_once('./lib/data_query.php');

define('MAX_DISPLAY_PAGES', 21);

$ds_actions = array(
	1 => 'Delete',
	2 => 'Change Data Template',
	3 => 'Change Device',
	8 => 'Reapply Suggested Names',
	4 => 'Duplicate',
	5 => 'Convert to Data Template',
	6 => 'Enable',
	7 => 'Disable'
);

$ds_actions = api_plugin_hook_function('data_source_action_array', $ds_actions);

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'rrd_add':
		ds_rrd_add();

		break;
	case 'rrd_remove':
		ds_rrd_remove();

		break;
	case 'data_edit':
		top_header();

		data_edit();

		bottom_footer();
		break;
	case 'ds_remove':
		ds_remove();

		header ('Location: data_sources.php');
		break;
	case 'ds_edit':
		ds_edit();

		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts();

		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(false);

		break;
	default:
		top_header();

		ds();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if ((isset($_POST['save_component_data_source_new'])) && (!empty($_POST['data_template_id']))) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('host_id'));
		input_validate_input_number(get_request_var_post('local_data_id'));
		input_validate_input_number(get_request_var_post('data_template_id'));
		/* ==================================================== */

		$save['id'] = $_POST['local_data_id'];
		$save['data_template_id'] = $_POST['data_template_id'];
		$save['host_id'] = $_POST['host_id'];

		$local_data_id = sql_save($save, 'data_local');

		change_data_template($local_data_id, $_POST['data_template_id']);

		/* update the title cache */
		update_data_source_title_cache($local_data_id);

		/* update host data */
		if (!empty($_POST['host_id'])) {
			push_out_host($_POST['host_id'], $local_data_id);
		}
	}

	if ((isset($_POST['save_component_data'])) && (!is_error_message())) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('data_template_data_id'));
		/* ==================================================== */

		/* ok, first pull out all 'input' values so we know how much to save */
		$input_fields = db_fetch_assoc_prepared("SELECT
			data_template_data.data_input_id,
			data_local.host_id,
			data_input_fields.id,
			data_input_fields.input_output,
			data_input_fields.data_name,
			data_input_fields.regexp_match,
			data_input_fields.allow_nulls,
			data_input_fields.type_code
			FROM data_template_data
			LEFT JOIN data_input_fields ON (data_input_fields.data_input_id = data_template_data.data_input_id)
			LEFT JOIN data_local ON (data_template_data.local_data_id = data_local.id)
			WHERE data_template_data.id = ?
			AND data_input_fields.input_output='in'", array($_POST['data_template_data_id']));

		if (sizeof($input_fields) > 0) {
		foreach ($input_fields as $input_field) {
			if (isset($_POST{'value_' . $input_field['id']})) {
				/* save the data into the 'data_input_data' table */
				$form_value = $_POST{'value_' . $input_field['id']};

				/* we shouldn't enforce rules on fields the user cannot see (ie. templated ones) */
				$is_templated = db_fetch_cell('SELECT t_value FROM data_input_data WHERE data_input_field_id=' . $input_field['id'] . ' and data_template_data_id=' . db_fetch_cell_prepared('SELECT local_data_template_data_id FROM data_template_data WHERE id = ?', array($_POST['data_template_data_id'])));

				if ($is_templated == '') {
					$allow_nulls = true;
				}elseif ($input_field['allow_nulls'] == 'on') {
					$allow_nulls = true;
				}elseif (empty($input_field['allow_nulls'])) {
					$allow_nulls = false;
				}

				/* run regexp match on input string */
				$form_value = form_input_validate($form_value, 'value_' . $input_field['id'], $input_field['regexp_match'], $allow_nulls, 3);

				if (!is_error_message()) {
					db_execute_prepared("REPLACE INTO data_input_data (data_input_field_id, data_template_data_id, t_value, value) VALUES (?, ?, '', ?)", 
						array($input_field['id'], $_POST['data_template_data_id'], $form_value));
				}
			}
		}
		}
	}

	if ((isset($_POST['save_component_data_source'])) && (!is_error_message())) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('local_data_id'));
		input_validate_input_number(get_request_var_post('data_template_id'));
		input_validate_input_number(get_request_var_post('host_id'));
		input_validate_input_number(get_request_var_post('data_template_data_id'));
		input_validate_input_number(get_request_var_post('local_data_template_data_id'));
		input_validate_input_number(get_request_var_post('current_rrd'));

		/* ==================================================== */

		$save1['id'] = $_POST['local_data_id'];
		$save1['data_template_id'] = $_POST['data_template_id'];
		$save1['host_id'] = $_POST['host_id'];

		$save2['id'] = $_POST['data_template_data_id'];
		$save2['local_data_template_data_id'] = $_POST['local_data_template_data_id'];
		$save2['data_template_id'] = $_POST['data_template_id'];
		$save2['data_input_id'] = form_input_validate($_POST['data_input_id'], 'data_input_id', '^[0-9]+$', true, 3);
		$save2['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save2['data_source_path'] = form_input_validate($_POST['data_source_path'], 'data_source_path', '', true, 3);
		$save2['active'] = form_input_validate((isset($_POST['active']) ? $_POST['active'] : ''), 'active', '', true, 3);
		$save2['rrd_step'] = form_input_validate($_POST['rrd_step'], 'rrd_step', '^[0-9]+$', false, 3);

		if (!is_error_message()) {
			$local_data_id = sql_save($save1, 'data_local');

			$save2['local_data_id'] = $local_data_id;
			$data_template_data_id = sql_save($save2, 'data_template_data');

			if ($data_template_data_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		if (!is_error_message()) {
			/* if this is a new data source and a template has been selected, skip item creation this time
			otherwise it throws off the templatate creation because of the NULL data */
			if ((!empty($_POST['local_data_id'])) || (empty($_POST['data_template_id']))) {
				/* if no template was set before the save, there will be only one data source item to save;
				otherwise there might be >1 */
				if (empty($_POST['_data_template_id'])) {
					$rrds[0]['id'] = $_POST['current_rrd'];
				}else{
					$rrds = db_fetch_assoc_prepared('SELECT id FROM data_template_rrd WHERE local_data_id = ?', array($_POST['local_data_id']));
				}

				if (sizeof($rrds) > 0) {
				foreach ($rrds as $rrd) {
					if (empty($_POST['_data_template_id'])) {
						$name_modifier = '';
					}else{
						$name_modifier = '_' . $rrd['id'];
					}

					$save3['id'] = $rrd['id'];
					$save3['local_data_id'] = $local_data_id;
					$save3['local_data_template_rrd_id'] = db_fetch_cell_prepared('SELECT local_data_template_rrd_id FROM data_template_rrd WHERE id = ?', array($rrd['id']));
					$save3['data_template_id'] = $_POST['data_template_id'];
					$save3['rrd_maximum'] = form_input_validate($_POST["rrd_maximum$name_modifier"], "rrd_maximum$name_modifier", "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$", false, 3);
					$save3['rrd_minimum'] = form_input_validate($_POST["rrd_minimum$name_modifier"], "rrd_minimum$name_modifier", "^(-?([0-9]+(\.[0-9]*)?|[0-9]*\.[0-9]+)([eE][+\-]?[0-9]+)?)|U$", false, 3);
					$save3['rrd_heartbeat'] = form_input_validate($_POST["rrd_heartbeat$name_modifier"], "rrd_heartbeat$name_modifier", '^[0-9]+$', false, 3);
					$save3['data_source_type_id'] = form_input_validate($_POST["data_source_type_id$name_modifier"], "data_source_type_id$name_modifier", '^[0-9]+$', false, 3);
					$save3['data_source_name'] = form_input_validate($_POST["data_source_name$name_modifier"], "data_source_name$name_modifier", '^[a-zA-Z0-9_-]{1,19}$', false, 3);
					$save3['data_input_field_id'] = form_input_validate((isset($_POST["data_input_field_id$name_modifier"]) ? $_POST["data_input_field_id$name_modifier"] : '0'), "data_input_field_id$name_modifier", '', true, 3);

					$data_template_rrd_id = sql_save($save3, 'data_template_rrd');

					if ($data_template_rrd_id) {
						raise_message(1);
					}else{
						raise_message(2);
					}
				}
				}
			}
		}

		if (!is_error_message()) {
			if (!empty($_POST['rra_id'])) {
				/* save entries in 'selected rras' field */
				db_execute_prepared('DELETE FROM data_template_data_rra WHERE data_template_data_id = ?', array($data_template_data_id));

				for ($i=0; ($i < count($_POST['rra_id'])); $i++) {
					/* ================= input validation ================= */
					input_validate_input_number($_POST['rra_id'][$i]);
					/* ==================================================== */

					db_execute_prepared('INSERT INTO data_template_data_rra (rra_id, data_template_data_id)
						VALUES (?, ?)', array($_POST['rra_id'][$i], $data_template_data_id));
				}
			}

			if ($_POST['data_template_id'] != $_POST['_data_template_id']) {
				/* update all necessary template information */
				change_data_template($local_data_id, $_POST['data_template_id']);
			}elseif (!empty($_POST['data_template_id'])) {
				update_data_source_data_query_cache($local_data_id);
			}

			if ($_POST['host_id'] != $_POST['_host_id']) {
				/* push out all necessary host information */
				push_out_host($_POST['host_id'], $local_data_id);

				/* reset current host for display purposes */
				$_SESSION['sess_data_source_current_host_id'] = $_POST['host_id'];
			}

			/* if no data source path has been entered, generate one */
			if (empty($_POST['data_source_path'])) {
				generate_data_source_path($local_data_id);
			}

			/* update the title cache */
			update_data_source_title_cache($local_data_id);
		}
	}

	/* update the poller cache last to make sure everything is fresh */
	if ((!is_error_message()) && (!empty($local_data_id))) {
		update_poller_cache($local_data_id, true);
	}

	if ((isset($_POST['save_component_data_source_new'])) && (empty($_POST['data_template_id']))) {
		header('Location: data_sources.php?header=false&action=ds_edit&host_id=' . $_POST['host_id'] . '&new=1');
	}elseif ((is_error_message()) || ($_POST['data_template_id'] != $_POST['_data_template_id']) || ($_POST['data_input_id'] != $_POST['_data_input_id']) || ($_POST['host_id'] != $_POST['_host_id'])) {
		header('Location: data_sources.php?header=false&action=ds_edit&id=' . (empty($local_data_id) ? $_POST['local_data_id'] : $local_data_id) . '&host_id=' . $_POST['host_id'] . '&view_rrd=' . (isset($_POST['current_rrd']) ? $_POST['current_rrd'] : '0'));
	}else{
		header('Location: data_sources.php?header=false');
	}
}

/* ------------------------
    The "actions" function
   ------------------------ */

function form_actions() {
	global $ds_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '1') { /* delete */
				if (!isset($_POST['delete_type'])) { $_POST['delete_type'] = 1; }

				switch ($_POST['delete_type']) {
					case '2': /* delete all graph items tied to this data source */
						$data_template_rrds = array_rekey(db_fetch_assoc('SELECT id FROM data_template_rrd WHERE ' . array_to_sql_or($selected_items, 'local_data_id')), 'id', 'id');

						/* loop through each data source item */
						if (sizeof($data_template_rrds) > 0) {
							db_execute('DELETE FROM graph_templates_item WHERE task_item_id IN (' . implode(',', $data_template_rrds) . ') and local_graph_id > 0');
						}

						api_plugin_hook_function('graph_items_remove', $data_template_rrds);

						break;
					case '3': /* delete all graphs tied to this data source */
						$graphs = array_rekey(db_fetch_assoc('SELECT
							graph_templates_graph.local_graph_id
							FROM (data_template_rrd,graph_templates_item,graph_templates_graph)
							WHERE graph_templates_item.task_item_id=data_template_rrd.id
							AND graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
							AND ' . array_to_sql_or($selected_items, 'data_template_rrd.local_data_id') . '
							AND graph_templates_graph.local_graph_id > 0
							GROUP BY graph_templates_graph.local_graph_id'), 'local_graph_id', 'local_graph_id');

						if (sizeof($graphs) > 0) {
							api_graph_remove_multi($graphs);
						}

						api_plugin_hook_function('graphs_remove', $graphs);

						break;
				}

				api_data_source_remove_multi($selected_items);

				api_plugin_hook_function('data_source_remove', $selected_items);
			}elseif ($_POST['drp_action'] == '2') { /* change graph template */
				input_validate_input_number(get_request_var_post('data_template_id'));

				for ($i=0;($i<count($selected_items));$i++) {
					change_data_template($selected_items[$i], $_POST['data_template_id']);
				}
			}elseif ($_POST['drp_action'] == '3') { /* change host */
				input_validate_input_number(get_request_var_post('host_id'));

				for ($i=0;($i<count($selected_items));$i++) {
					db_execute_prepared('UPDATE data_local SET host_id = ? WHERE id = ?', array($_POST['host_id'], $selected_items[$i]));
					push_out_host($_POST['host_id'], $selected_items[$i]);
					update_data_source_title_cache($selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == '4') { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					duplicate_data_source($selected_items[$i], 0, $_POST['title_format']);
				}
			}elseif ($_POST['drp_action'] == '5') { /* data source -> data template */
				for ($i=0;($i<count($selected_items));$i++) {
					data_source_to_data_template($selected_items[$i], $_POST['title_format']);
				}
			}elseif ($_POST['drp_action'] == '6') { /* data source enable */
				for ($i=0;($i<count($selected_items));$i++) {
					api_data_source_enable($selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == '7') { /* data source disable */
				for ($i=0;($i<count($selected_items));$i++) {
					api_data_source_disable($selected_items[$i]);
				}
			}elseif ($_POST['drp_action'] == '8') { /* reapply suggested data source naming */
				for ($i=0;($i<count($selected_items));$i++) {
					api_reapply_suggested_data_source_title($selected_items[$i]);
					update_data_source_title_cache($selected_items[$i]);
				}
			} else {
				api_plugin_hook_function('data_source_action_execute', $_POST['drp_action']);
			}
		}

		/* update snmpcache */
		snmpagent_data_source_action_bottom(array($_POST['drp_action'], $selected_items));

		api_plugin_hook_function('data_source_action_bottom', array($_POST['drp_action'], $selected_items));

		header('Location: data_sources.php?header=false');
		exit;
	}

	/* setup some variables */
	$ds_list = ''; $i = 0;

	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ds_list .= '<li>' . htmlspecialchars(get_data_source_title($matches[1])) . '</li>';
			$ds_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_sources.php');

	html_start_box($ds_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

	if (isset($ds_array) && sizeof($ds_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			$graphs = array();

			/* find out which (if any) graphs are using this data source, so we can tell the user */
			if (isset($ds_array)) {
				$graphs = db_fetch_assoc('SELECT
					graph_templates_graph.local_graph_id,
					graph_templates_graph.title_cache
					FROM (data_template_rrd,graph_templates_item,graph_templates_graph)
					WHERE graph_templates_item.task_item_id=data_template_rrd.id
					AND graph_templates_item.local_graph_id=graph_templates_graph.local_graph_id
					AND ' . array_to_sql_or($ds_array, 'data_template_rrd.local_data_id') . '
					AND graph_templates_graph.local_graph_id > 0
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache');
			}

			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to delete the following Data Source(s).</p>
					<p><ul>$ds_list</ul></p>";

			if (sizeof($graphs)) {
				print "<tr><td class='textArea'><p class='textArea'>The following graphs are using these data sources:</p>\n";

				print '<p><ul>';
				foreach ($graphs as $graph) {
					print '<li>' . $graph['title_cache'] . "</li>\n";
				}
				print '</ul></p>';
				print '<br>';

				form_radio_button('delete_type', '3', '1', 'Leave the Graph(s) untouched.', '1'); print '<br>';
				form_radio_button('delete_type', '3', '2', 'Delete all <strong>Graph Item(s)</strong> that reference these Data Source(s).', '1'); print '<br>';
				form_radio_button('delete_type', '3', '3', 'Delete all <strong>Graph(s)</strong> that reference these Data Source(s).', '1'); print '<br>';
				print '</td></tr>';
			}

			print "</td>
				</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Source(s)'>";
		}elseif ($_POST['drp_action'] == '2') { /* change graph template */
			print "<tr>
				<td class='textArea'>
					<p>Choose a Data Template and click \"Continue\" to change the Data Template for
					the following Data Source(s). Be aware that all warnings will be suppressed during the
					conversion, so graph data loss is possible.</p>
					<p><ul>$ds_list</ul></p>
					<p>New Data Template:<br>"; form_dropdown('data_template_id',db_fetch_assoc('SELECT data_template.id,data_template.name FROM data_template ORDER BY data_template.name'),'name','id','','','0'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Change Graph Template for Data Source(s)'>";
		}elseif ($_POST['drp_action'] == '3') { /* change host */
			print "<tr>
				<td class='textArea'>
					<p>Choose a new Device for these Data Source(s) and click 'Continue'.</p>
					<p><ul>$ds_list</ul></p>
					<p>New Device:<br>"; form_dropdown('host_id',db_fetch_assoc("SELECT id, CONCAT_WS('',description,' (',hostname,')') AS name FROM host ORDER BY description, hostname"),'name','id','','','0'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Change Device'>";
		}elseif ($_POST['drp_action'] == '4') { /* duplicate */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to duplicate the following Data Source(s). You can
					optionally change the title format for the new Data Source(s).</p>
					<p><ul>$ds_list</ul></p>
					<p>Title Format:<br>"; form_text_box('title_format', '<ds_title> (1)', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Duplicate Data Source(s)'>";
		}elseif ($_POST['drp_action'] == '5') { /* data source -> data template */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to convert the following Data Source(s) into Data Template(s).
					You can optionally change the title format for the new Data Template(s).</p>
					<p><ul>$ds_list</ul></p>
					<p>Title Format:<br>"; form_text_box('title_format', '<ds_title> Template', '', '255', '30', 'text'); print "</p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Convert Data Source(s) to Data Template(s)'>";
		}elseif ($_POST['drp_action'] == '6') { /* data source enable */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to enable the following Data Source(s).</p>
					<p><ul>$ds_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Enable Data Source(s)'>";
		}elseif ($_POST['drp_action'] == '7') { /* data source disable */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to disable the following Data Source(s).</p>
					<p><ul>$ds_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Disable Data Source(s)'>";
		}elseif ($_POST['drp_action'] == '8') { /* reapply suggested data source naming */
			print "<tr>
				<td class='textArea'>
					<p>Click 'Continue' to re-apply the suggested names to the following Data Source(s).</p>
					<p><ul>$ds_list</ul></p>
				</td>
			</tr>\n";

			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Reapply Suggested Naming to Data Source(s)'>";
		}else{
			$save['drp_action'] = $_POST['drp_action'];
			$save['ds_list'] = $ds_list;
			$save['ds_array'] = (isset($ds_array)? $ds_array : array());
			api_plugin_hook_function('data_source_action_prepare', $save);
			$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue'>";
		}
	}else{
		print "<tr><td class='even'><span class='textError'>You must select at least one data source.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ds_array) ? serialize($ds_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
    data - Custom Data
   ---------------------------- */

function data_edit() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	global $config;

	if (!empty($_REQUEST['id'])) {
		$data = db_fetch_row_prepared('SELECT id, data_input_id, data_template_id, name, local_data_id FROM data_template_data WHERE local_data_id = ?', array($_REQUEST['id']));
		$template_data = db_fetch_row_prepared('SELECT id, data_input_id FROM data_template_data WHERE data_template_id = ? AND local_data_id = 0', array($data['data_template_id']));

		$host = db_fetch_row_prepared('SELECT host.id, host.hostname FROM (data_local, host) WHERE data_local.host_id = host.id and data_local.id = ?', array($_REQUEST['id']));

		$header_label = '[edit: ' . htmlspecialchars($data['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('data_sources.php');

	$i = 0;
	if (!empty($data['data_input_id'])) {
		/* get each INPUT field for this data input source */
		$fields = db_fetch_assoc_prepared("SELECT * FROM data_input_fields WHERE data_input_id = ? AND input_output = 'in' ORDER BY name", array($data['data_input_id']));

		html_start_box("Custom Data [data input: " . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', array($data['data_input_id']))) . ']', '100%', '', '3', 'center', '');

		/* loop through each field found */
		if (sizeof($fields) > 0) {
		foreach ($fields as $field) {
			$data_input_data = db_fetch_row_prepared('SELECT * FROM data_input_data WHERE data_template_data_id = ? AND data_input_field_id = ?', array($data['id'], $field['id']));

			if (sizeof($data_input_data) > 0) {
				$old_value = $data_input_data['value'];
			}else{
				$old_value = '';
			}

			/* if data template then get t_value FROM template, else always allow user input */
			if (empty($data['data_template_id'])) {
				$can_template = 'on';
			}else{
				$can_template = db_fetch_cell_prepared('SELECT t_value FROM data_input_data WHERE data_template_data_id = ? AND data_input_field_id = ?', array($template_data['id'], $field['id']));
			}

			form_alternate_row();

			if ((!empty($host['id'])) && (preg_match('/^' . VALID_HOST_FIELDS . '$/i', $field['type_code']))) {
				print "<td style='width:50%;'><strong>" . $field['name'] . '</strong> (From Device: ' . $host['hostname'] . ")</td>\n";
				print "<td><em>$old_value</em></td>\n";
			}elseif (empty($can_template)) {
				print "<td style='width:50%;'><strong>" . $field['name'] . "</strong> (From Data Template)</td>\n";
				print '<td><em>' . (empty($old_value) ? 'Nothing Entered' : $old_value) . "</em></td>\n";
			}else{
				print "<td style='width:50%;'><strong>" . $field['name'] . "</strong></td>\n";
				print '<td>';

				draw_custom_data_row('value_' . $field['id'], $field['id'], $data['id'], $old_value);

				print '</td>';
			}

			print "</tr>\n";

			$i++;
		}
		}else{
			print '<tr><td><em>No Input Fields for the Selected Data Input Source</em></td></tr>';
		}

		html_end_box();
	}

	form_hidden_box('local_data_id', (isset($data) ? $data['local_data_id'] : '0'), '');
	form_hidden_box('data_template_data_id', (isset($data) ? $data['id'] : '0'), '');
	form_hidden_box('save_component_data', '1', '');
}

/* ------------------------
    Data Source Functions
   ------------------------ */

function ds_rrd_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM data_template_rrd WHERE id = ?', array($_REQUEST['id']));
	db_execute_prepared('UPDATE graph_templates_item SET task_item_id = 0 WHERE task_item_id = ?', array($_REQUEST['id']));

	header('Location: data_sources.php?header=false&action=ds_edit&id=' . $_REQUEST['local_data_id']);
}

function ds_rrd_add() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	db_execute_prepared("INSERT INTO data_template_rrd (local_data_id, rrd_maximum, rrd_minimum, rrd_heartbeat, data_source_type_id, data_source_name) VALUES (?, 100, 0, 600, 1, 'ds')", array($_REQUEST['id']));
	$data_template_rrd_id = db_fetch_insert_id();

	header('Location: data_sources.php?header=false&action=ds_edit&id=' . $_REQUEST['id'] . "&view_rrd=$data_template_rrd_id");
}

function ds_edit() {
	global $struct_data_source, $struct_data_source_item, $data_source_types;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('host_id'));
	/* ==================================================== */

	api_plugin_hook('data_source_edit_top');

	$use_data_template = true;
	$host_id = 0;

	if (!empty($_REQUEST['id'])) {
		$data_local = db_fetch_row_prepared('SELECT host_id, data_template_id FROM data_local WHERE id = ?', array($_REQUEST['id']));
		$data       = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE local_data_id = ?', array($_REQUEST['id']));

		if (isset($data_local['data_template_id']) && $data_local['data_template_id'] >= 0) {
			$data_template      = db_fetch_row_prepared('SELECT id, name FROM data_template WHERE id = ?', array($data_local['data_template_id']));
			$data_template_data = db_fetch_row_prepared('SELECT * FROM data_template_data WHERE data_template_id = ? AND local_data_id = 0', array($data_local['data_template_id']));
		} else {
			$_SESSION['sess_messages'] = 'Data Source "' . $_REQUEST['id'] . '" does not exist.';
			header ('Location: data_sources.php');
			exit;
		}

		$header_label = '[edit: ' . htmlspecialchars(get_data_source_title($_REQUEST['id'])) . ']';

		if (empty($data_local['data_template_id'])) {
			$use_data_template = false;
		}
	}else{
		$header_label = '[new]';

		$use_data_template = false;
	}

	/* handle debug mode */
	if (isset($_REQUEST['debug'])) {
		if ($_REQUEST['debug'] == '0') {
			kill_session_var('ds_debug_mode');
		}elseif ($_REQUEST['debug'] == '1') {
			$_SESSION['ds_debug_mode'] = true;
		}
	}

	top_header();

	if (!empty($_REQUEST['id'])) {
		?>
		<table style='width:100%'>
			<tr>
				<td class='textInfo left' colspan='2' valign='top'>
					<?php print htmlspecialchars(get_data_source_title($_REQUEST['id']));?>
				</td>
				<td class='textInfo right' valign='top'>
					<span class='linkMarker'>*<a class='hyperLink' href='<?php print htmlspecialchars('data_sources.php?action=ds_edit&id=' . (isset($_REQUEST['id']) ? $_REQUEST['id'] : '0'));?>&debug=<?php print (isset($_SESSION['ds_debug_mode']) ? '0' : '1');?>'>Turn <?php print (isset($_SESSION['ds_debug_mode']) ? 'Off' : 'On');?> Data Source Debug Mode.</a><br>
					<?php
						if (!empty($data_template['id'])) {
							?><span class='linkMarker'>*<a class='hyperLink' href='<?php print htmlspecialchars('data_templates.php?action=template_edit&id=' . (isset($data_template['id']) ? $data_template['id'] : '0'));?>'>Edit Data Template.</a><br><?php
						}
						if (!empty($_REQUEST['host_id']) || !empty($data_local['host_id'])) {
							?><span class='linkMarker'>*<a class='hyperLink' href='<?php print htmlspecialchars('host.php?action=edit&id=' . (isset($_REQUEST['host_id']) ? $_REQUEST['host_id'] : $data_local['host_id']));?>'>Edit Device.</a><br><?php
						}
					?>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

	form_start('data_sources.php', 'data_source');

	html_start_box("Data Template Selection $header_label", '100%', '', '3', 'center', '');

	$form_array = array(
		'data_template_id' => array(
			'method' => 'drop_sql',
			'friendly_name' => 'Selected Data Template',
			'description' => 'The name given to this data template.',
			'value' => (isset($data_template) ? $data_template['id'] : '0'),
			'none_value' => 'None',
			'sql' => 'SELECT id,name FROM data_template order by name'
			),
		'host_id' => array(
			'method' => 'drop_callback',
			'friendly_name' => 'Device',
			'description' => 'Choose the Device that this Data Source belongs to.',
			'none_value' => 'None',
			'sql' => 'SELECT id, description as name FROM host ORDER BY name',
			'action' => 'ajax_hosts_noany',
			'id' => (isset($_REQUEST['host_id']) ? $_REQUEST['host_id'] : $data_local['host_id']),
			'value' => db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', (isset($_REQUEST['host_id']) ? array($_REQUEST['host_id']) : array($data_local['host_id']))),
			),
		'_data_template_id' => array(
			'method' => 'hidden',
			'value' => (isset($data_template) ? $data_template['id'] : '0')
			),
		'_host_id' => array(
			'method' => 'hidden',
			'value' => (empty($data_local['host_id']) ? (isset($_REQUEST['host_id']) ? $_REQUEST['host_id'] : '0') : $data_local['host_id'])
			),
		'_data_input_id' => array(
			'method' => 'hidden',
			'value' => (isset($data['data_input_id']) ? $data['data_input_id'] : '0')
			),
		'data_template_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data) ? $data['id'] : '0')
			),
		'local_data_template_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data) ? $data['local_data_template_data_id'] : '0')
			),
		'local_data_id' => array(
			'method' => 'hidden',
			'value' => (isset($data) ? $data['local_data_id'] : '0')
			),
		);

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => $form_array
		)
	);

	html_end_box();

	/* only display the "inputs" area if we are using a data template for this data source */
	if (!empty($data['data_template_id'])) {
		$template_data_rrds = db_fetch_assoc_prepared('SELECT * FROM data_template_rrd WHERE local_data_id = ? ORDER BY data_source_name', array($_REQUEST['id']));

		html_start_box('Supplemental Data Template Data', '100%', '', '3', 'center', '');

		draw_nontemplated_fields_data_source($data['data_template_id'], $data['local_data_id'], $data, '|field|', 'Data Source Fields', true, true, 0);
		draw_nontemplated_fields_data_source_item($data['data_template_id'], $template_data_rrds, '|field|_|id|', 'Data Source Item Fields', true, true, true, 0);
		draw_nontemplated_fields_custom_data($data['id'], 'value_|id|', 'Custom Data', true, true, 0);

		form_hidden_box('save_component_data','1','');

		html_end_box();
	}

	if (((isset($_REQUEST['id'])) || (isset($_REQUEST['new']))) && (empty($data['data_template_id']))) {
		html_start_box('Data Source', '100%', '', '3', 'center', '');

		$form_array = array();

		while (list($field_name, $field_array) = each($struct_data_source)) {
			$form_array += array($field_name => $struct_data_source[$field_name]);

			if (!(($use_data_template == false) || (!empty($data_template_data{'t_' . $field_name})) || ($field_array['flags'] == 'NOTEMPLATE'))) {
				$form_array[$field_name]['description'] = '';
			}

			$form_array[$field_name]['value'] = (isset($data[$field_name]) ? $data[$field_name] : '');
			$form_array[$field_name]['form_id'] = (empty($data['id']) ? '0' : $data['id']);

			if (!(($use_data_template == false) || (!empty($data_template_data{'t_' . $field_name})) || ($field_array['flags'] == 'NOTEMPLATE'))) {
				$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
			}
		}

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($data) ? $data : array()))
			)
		);

		html_end_box();

		/* fetch ALL rrd's for this data source */
		if (!empty($_REQUEST['id'])) {
			$template_data_rrds = db_fetch_assoc_prepared('SELECT id, data_source_name FROM data_template_rrd WHERE local_data_id = ? ORDER BY data_source_name', array($_REQUEST['id']));
		}

		/* select the first "rrd" of this data source by default */
		if (empty($_REQUEST['view_rrd'])) {
			$_REQUEST['view_rrd'] = (isset($template_data_rrds[0]['id']) ? $template_data_rrds[0]['id'] : '0');
		}

		/* get more information about the rrd we chose */
		if (!empty($_REQUEST['view_rrd'])) {
			$local_data_template_rrd_id = db_fetch_cell_prepared('SELECT local_data_template_rrd_id FROM data_template_rrd WHERE id = ?', array($_REQUEST['view_rrd']));

			$rrd = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', array($_REQUEST['view_rrd']));
			$rrd_template = db_fetch_row_prepared('SELECT * FROM data_template_rrd WHERE id = ?', array($local_data_template_rrd_id));

			$header_label = '[edit: ' . $rrd['data_source_name'] . ']';
		}else{
			$header_label = '';
		}

		$i = 0;
		if (isset($template_data_rrds)) {
			if (sizeof($template_data_rrds) > 1) {
				/* draw the data source tabs on the top of the page */
				print "	<table class='tabs'><tr>\n";

				foreach ($template_data_rrds as $template_data_rrd) {
					$i++;
					print "	<td " . (($template_data_rrd['id'] == $_REQUEST['view_rrd']) ? "class='even'" : "class='odd'") . " style='width:" . ((strlen($template_data_rrd['data_source_name']) * 9) + 50) . ";text-align:center;' class='tab'>
						<span class='textHeader'><a href='" . htmlspecialchars('data_sources.php?action=ds_edit&id=' . $_REQUEST['id'] . '&view_rrd=' . $template_data_rrd['id']) . "'>$i: " . htmlspecialchars($template_data_rrd['data_source_name']) . '</a>' . (($use_data_template == false) ? " <a class='pic deleteMarker fa fa-remove' href='" . htmlspecialchars('data_sources.php?action=rrd_remove&id=' . $template_data_rrd['id'] . '&local_data_id=' . $_REQUEST['id']) . "' title='Delete'></a>" : '') . "</span>
						</td>\n";
					print "<td style='width:1px;'></td>\n";
				}

				print "<td></td></tr></table>\n";
			}elseif (sizeof($template_data_rrds) == 1) {
				$_REQUEST['view_rrd'] = $template_data_rrds[0]['id'];
			}
		}

		html_start_box('', '100%', '', '3', 'center', '');

		print "<tr>
			<td class='textHeaderDark left'>
				Data Source Item $header_label
			</td>
			<td class='textHeaderDark right'>
				" . ((!empty($_REQUEST['id']) && (empty($data_template['id']))) ? "<a class='linkOverDark' href='" . htmlspecialchars('data_sources.php?action=rrd_add&id=' . $_REQUEST['id']) . "'>New</a>&nbsp;" : '') . "
			</td>
		</tr>\n";

		/* data input fields list */
		if ((empty($data['data_input_id'])) || (db_fetch_cell_prepared('SELECT type_id FROM data_input WHERE id = ?', array($data['data_input_id'])) > '1')) {
			unset($struct_data_source_item['data_input_field_id']);
		}else{
			$struct_data_source_item['data_input_field_id']['sql'] = "SELECT id,CONCAT(data_name,' - ',name) as name FROM data_input_fields WHERE data_input_id=" . $data['data_input_id'] . " and input_output='out' and update_rra='on' order by data_name,name";
		}

		$form_array = array();

		while (list($field_name, $field_array) = each($struct_data_source_item)) {
			$form_array += array($field_name => $struct_data_source_item[$field_name]);

			if (!(($use_data_template == false) || ($rrd_template{'t_' . $field_name} == 'on'))) {
				$form_array[$field_name]['description'] = '';
			}

			$form_array[$field_name]['value'] = (isset($rrd) ? $rrd[$field_name] : '');

			if (!(($use_data_template == false) || ($rrd_template{'t_' . $field_name} == 'on'))) {
				$form_array[$field_name]['method'] = 'template_' . $form_array[$field_name]['method'];
			}
		}

		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => array(
				'data_template_rrd_id' => array(
					'method' => 'hidden',
					'value' => (isset($rrd) ? $rrd['id'] : '0')
				),
				'local_data_template_rrd_id' => array(
					'method' => 'hidden',
					'value' => (isset($rrd) ? $rrd['local_data_template_rrd_id'] : '0')
				)
			) + $form_array
			)
		);

		html_end_box();

		/* data source data goes here */
		data_edit();

		form_hidden_box('current_rrd', $_REQUEST['view_rrd'], '0');
	}

	/* display the debug mode box if the user wants it */
	if ((isset($_SESSION['ds_debug_mode'])) && (isset($_REQUEST['id']))) {
		?>
		<table style='width:100%'>
			<tr>
				<td>
					<span class='textInfo'>Data Source Debug</span><br>
					<pre><?php print @rrdtool_function_create($_REQUEST['id'], true);?></pre>
				</td>
			</tr>
		</table>
		<?php
	}

	if ((isset($_REQUEST['id'])) || (isset($_REQUEST['new']))) {
		form_hidden_box('save_component_data_source','1','');
	}else{
		form_hidden_box('save_component_data_source_new','1','');
	}

	form_save_button('data_sources.php');

	api_plugin_hook('data_source_edit_bottom');

	bottom_footer();
}

function get_poller_interval($seconds) {
	if ($seconds == 0) {
		return '<em>External</em>';
	}else if ($seconds < 60) {
		return '<em>' . $seconds . ' Seconds</em>';
	}else if ($seconds == 60) {
		return '1 Minute';
	}else{
		return '<em>' . ($seconds / 60) . ' Minutes</em>';
	}
}

function ds() {
	global $ds_actions, $item_rows;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('rows'));
	input_validate_input_number(get_request_var('host_id'));
	input_validate_input_number(get_request_var('template_id'));
	input_validate_input_number(get_request_var('method_id'));
	input_validate_input_number(get_request_var('page'));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST['filter'])) {
		$_REQUEST['filter'] = sanitize_search_string(get_request_var('filter'));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST['sort_column'])) {
		$_REQUEST['sort_column'] = sanitize_search_string(get_request_var('sort_column'));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_ds_current_page');
		kill_session_var('sess_ds_filter');
		kill_session_var('sess_ds_sort_column');
		kill_session_var('sess_ds_sort_direction');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_ds_host_id');
		kill_session_var('sess_ds_template_id');
		kill_session_var('sess_ds_method_id');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['host_id']);
		unset($_REQUEST['template_id']);
		unset($_REQUEST['method_id']);
	}else{
		$changed = 0;
		$changed += check_changed('filter',      'sess_ds_filter');
		$changed += check_changed('host_id',     'sess_ds_host_id');
		$changed += check_changed('template_id', 'sess_ds_template_id');
		$changed += check_changed('method_id',   'sess_ds_method_id');
		$changed += check_changed('rows',        'sess_default_rows');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('page', 'sess_ds_current_page', '1');
	load_current_session_value('filter', 'sess_ds_filter', '');
	load_current_session_value('sort_column', 'sess_ds_sort_column', 'name_cache');
	load_current_session_value('sort_direction', 'sess_ds_sort_direction', 'ASC');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));
	load_current_session_value('host_id', 'sess_ds_host_id', '-1');
	load_current_session_value('template_id', 'sess_ds_template_id', '-1');
	load_current_session_value('method_id', 'sess_ds_method_id', '-1');

	$host = db_fetch_row_prepared('SELECT hostname FROM host WHERE id = ?', array(get_request_var('host_id')));

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$_REQUEST['rows'] = read_config_option('num_rows_table');
	}

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'data_sources.php?host_id=' + $('#host_id').val();
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		strURL += '&template_id=' + $('#template_id').val();
		strURL += '&method_id=' + $('#method_id').val();
		strURL += '&page=' + $('#page').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'data_sources.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyFilter()
		});

		$('#clear').click(function() {
			clearFilter()
		});

		$('#form_data_sources').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	if (read_config_option('grds_creation_method') == 1 ) {
		$add_url = htmlspecialchars('data_sources.php?action=ds_edit&host_id=' . get_request_var('host_id'));
	}else{
		$add_url = '';
	}

	html_start_box('Data Sources [host: ' . (empty($host['hostname']) ? 'No Device' : htmlspecialchars($host['hostname'])) . ']', '100%', '', '3', 'center', $add_url);

	?>
	<tr class='even noprint'>
		<td>
		<form id='form_data_sources' name='form_data_sources' action='data_sources.php'>
			<table class='filterTable'>
				<tr>
					<?php print html_host_filter($_REQUEST['host_id']);?>
					<td>
						Template
					</td>
					<td>
						<select id='template_id' name='template_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('template_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('template_id') == '0') {?> selected<?php }?>>None</option>
							<?php

							$templates = db_fetch_assoc('SELECT DISTINCT data_template.id, data_template.name
								FROM data_template
								INNER JOIN data_template_data
								ON data_template.id = data_template_data.data_template_id
								WHERE data_template_data.local_data_id > 0
								ORDER BY data_template.name');

							if (sizeof($templates) > 0) {
								foreach ($templates as $template) {
									print "<option value='" . $template['id'] . "'"; if (get_request_var('template_id') == $template['id']) { print ' selected'; } print '>' . title_trim(htmlspecialchars($template['name']), 40) . "</option>\n";
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
				<tr>
					<td>
						Method
					</td>
					<td>
						<select id='method_id' name='method_id' onChange='applyFilter()'>
							<option value='-1'<?php if (get_request_var('method_id') == '-1') {?> selected<?php }?>>Any</option>
							<option value='0'<?php if (get_request_var('method_id') == '0') {?> selected<?php }?>>None</option>
							<?php

							$methods = db_fetch_assoc('SELECT DISTINCT data_input.id, data_input.name
								FROM data_input
								INNER JOIN data_template_data
								ON data_input.id = data_template_data.data_input_id
								WHERE data_template_data.local_data_id > 0
								ORDER BY data_input.name');

							if (sizeof($methods) > 0) {
								foreach ($methods as $method) {
									print "<option value='" . $method['id'] . "'"; if (get_request_var('method_id') == $method['id']) { print ' selected'; } print '>' . title_trim(htmlspecialchars($method['name']), 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td class='nowrap'>
						Data Sources
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
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>' onChange='applyFilter()'>
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
		$sql_where1 = "AND (data_template_data.name_cache like '%" . get_request_var('filter') . "%'" .
			" OR data_template_data.local_data_id like '%" . get_request_var('filter') . "%'" .
			" OR data_template.name like '%" . get_request_var('filter') . "%'" .
			" OR data_input.name like '%" . get_request_var('filter') . "%')";

		$sql_where2 = "AND (data_template_data.name_cache like '%" . get_request_var('filter') . "%'" .
			" OR data_template.name like '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where1 = '';
		$sql_where2 = '';
	}

	if (get_request_var('host_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('host_id') == '0') {
		$sql_where1 .= ' AND data_local.host_id=0';
		$sql_where2 .= ' AND data_local.host_id=0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where1 .= ' AND data_local.host_id=' . get_request_var('host_id');
		$sql_where2 .= ' AND data_local.host_id=' . get_request_var('host_id');
	}

	if (get_request_var('template_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('template_id') == '0') {
		$sql_where1 .= ' AND data_template_data.data_template_id=0';
		$sql_where2 .= ' AND data_template_data.data_template_id=0';
	}elseif (!empty($_REQUEST['host_id'])) {
		$sql_where1 .= ' AND data_template_data.data_template_id=' . get_request_var('template_id');
		$sql_where2 .= ' AND data_template_data.data_template_id=' . get_request_var('template_id');
	}

	if (get_request_var('method_id') == '-1') {
		/* Show all items */
	}elseif (get_request_var('method_id') == '0') {
		$sql_where1 .= ' AND data_template_data.data_input_id=0';
		$sql_where2 .= ' AND data_template_data.data_input_id=0';
	}elseif (!empty($_REQUEST['method_id'])) {
		$sql_where1 .= ' AND data_template_data.data_input_id=' . get_request_var('method_id');
		$sql_where2 .= ' AND data_template_data.data_input_id=' . get_request_var('method_id');
	}

	$total_rows = sizeof(db_fetch_assoc("SELECT
		data_local.id
		FROM (data_local,data_template_data)
		LEFT JOIN data_input
		ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template
		ON (data_local.data_template_id=data_template.id)
		WHERE data_local.id=data_template_data.local_data_id
		$sql_where1"));

	$poller_intervals = array_rekey(db_fetch_assoc("SELECT data_template_data.local_data_id AS id,
		Min(data_template_data.rrd_step*rra.steps) AS poller_interval
		FROM data_template
		INNER JOIN (data_local
		INNER JOIN ((data_template_data_rra
		INNER JOIN data_template_data ON data_template_data_rra.data_template_data_id=data_template_data.id)
		INNER JOIN rra ON data_template_data_rra.rra_id = rra.id) ON data_local.id = data_template_data.local_data_id) ON data_template.id = data_template_data.data_template_id
		$sql_where2
		GROUP BY data_template_data.local_data_id"), 'id', 'poller_interval');

	$data_sources = db_fetch_assoc("SELECT
		data_template_data.local_data_id,
		data_template_data.name_cache,
		data_template_data.active,
		data_input.name as data_input_name,
		data_template.name as data_template_name,
		data_local.host_id
		FROM (data_local,data_template_data)
		LEFT JOIN data_input
		ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template
		ON (data_local.data_template_id=data_template.id)
		WHERE data_local.id=data_template_data.local_data_id
		$sql_where1
		ORDER BY ". get_request_var('sort_column') . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	form_start('data_sources.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	$nav = html_nav_bar('data_sources.php?filter=' . get_request_var('filter') . '&host_id=' . get_request_var('host_id'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 7, 'Data Sources', 'page', 'main');

	print $nav;

	$display_text = array(
		'name_cache' => array('display' => 'Data Source Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Data Source.  Generally programtically generated from the Data Template definition.'),
		'local_data_id' => array('display' => 'ID','align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal database ID for this Data Source.  Useful when performing automation or debugging.'),
		'data_input_name' => array('display' => 'Data Input Method', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The method by which data is gathered for this Data Source.'),
		'nosort' => array('display' => 'Poller Interval', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The frequency that data is collected for this Data Source.'),
		'active' => array('display' => 'Active', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'Whether or not data will be collected for this Data Source.  Controlled at the Data Template level.'),
		'data_template_name' => array('display' => 'Template Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The Data Template that this Data Source was based upon.'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($data_sources) > 0) {
		foreach ($data_sources as $data_source) {
			$data_source['data_template_name'] = htmlspecialchars($data_source['data_template_name']);
			$data_name_cache = title_trim(htmlspecialchars($data_source['name_cache']), read_config_option('max_title_length'));

			if (trim(get_request_var('filter') != '')) {
				$data_name_cache = preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", ($data_name_cache));
			}

			/* keep copy of data source for comparison */
			$data_source_orig = $data_source;
			$data_source = api_plugin_hook_function('data_sources_table', $data_source);
			/* we're escaping strings here, so no need to escape them on form_selectable_cell */
			if (empty($data_source['data_template_name'])) {
				$data_template_name = '<em>None</em>';
			} elseif ($data_source_orig['data_template_name'] != $data_source['data_template_name']) {
				/* was changed by plugin, plugin has to take care for html-escaping */
				$data_template_name = ((empty($data_source['data_template_name'])) ? '<em>None</em>' : $data_source['data_template_name']);
			} elseif (trim(get_request_var('filter') != '')) {
				/* we take care of html-escaping */
				$data_template_name = preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($data_source['data_template_name']));
			} else {
				$data_template_name = htmlspecialchars($data_source['data_template_name']);
			}

			if (empty($data_source['data_input_name'])) {
				$data_input_name = '<em>None</em>';
			} elseif ($data_source_orig['data_input_name'] != $data_source['data_input_name']) {
				/* was changed by plugin, plugin has to take care for html-escaping */
				$data_input_name = ((empty($data_source['data_input_name'])) ? '<em>None</em>' : $data_source['data_input_name']);
			} elseif (trim(get_request_var('filter') != '')) {
				/* we take care of html-escaping */
				$data_input_name = preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($data_source['data_input_name']));
			} else {
				$data_input_name = htmlspecialchars($data_source['data_input_name']);
			}

			$poller_interval    = ((isset($poller_intervals[$data_source['local_data_id']])) ? $poller_intervals[$data_source['local_data_id']] : 0);

			form_alternate_row('line' . $data_source['local_data_id'], true);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_sources.php?action=ds_edit&id=' . $data_source['local_data_id']) . "'>" . ((get_request_var('filter') != '') ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", title_trim(htmlspecialchars($data_source['name_cache']), read_config_option('max_title_length'))) : title_trim(htmlspecialchars($data_source['name_cache']), read_config_option('max_title_length'))) . '</a>', $data_source['local_data_id']);
			form_selectable_cell($data_source['local_data_id'], $data_source['local_data_id'], '', 'text-align:right');
			form_selectable_cell($data_input_name, $data_source['local_data_id']);
			form_selectable_cell(get_poller_interval($poller_interval), $data_source['local_data_id']);
			form_selectable_cell(($data_source['active'] == 'on' ? 'Yes' : 'No'), $data_source['local_data_id']);
			form_selectable_cell($data_template_name, $data_source['local_data_id']);
			form_checkbox_cell($data_source['name_cache'], $data_source['local_data_id']);
			form_end_row();
		}

		/* put the nav bar on the bottom as well */
		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='7'><em>No Data Sources</em></td></tr>";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($ds_actions);

	form_end();
}

