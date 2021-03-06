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

define('MAX_DISPLAY_PAGES', 21);

$di_actions = array(1 => 'Delete');

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = ''; }

switch ($_REQUEST['action']) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'field_remove':
		field_remove();

		header('Location: data_input.php?header=false&action=edit&id=' . $_REQUEST['data_input_id']);
		break;
	case 'field_edit':
		top_header();

		field_edit();

		bottom_footer();
		break;
	case 'edit':
		top_header();

		data_edit();

		bottom_footer();
		break;
	default:
		top_header();

		data();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	global $registered_cacti_names;

	if (isset($_POST['save_component_data_input'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		/* ==================================================== */

		$save['id']           = $_POST['id'];
		$save['hash']         = get_hash_data_input($_POST['id']);
		$save['name']         = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['input_string'] = form_input_validate($_POST['input_string'], 'input_string', '', true, 3);
		$save['type_id']      = form_input_validate($_POST['type_id'], 'type_id', '^[0-9]+$', true, 3);

		if (!is_error_message()) {
			$data_input_id = sql_save($save, 'data_input');

			if ($data_input_id) {
				raise_message(1);

				/* get a list of each field so we can note their sequence of occurance in the database */
				if (!empty($_POST['id'])) {
					db_execute_prepared('UPDATE data_input_fields SET sequence = 0 WHERE data_input_id = ?', array(get_request_var_post('id')));

					generate_data_input_field_sequences($_POST['input_string'], $_POST['id']);
				}

				push_out_data_input_method($data_input_id);
			}else{
				raise_message(2);
			}
		}

		header('Location: data_input.php?header=false&action=edit&id=' . (empty($data_input_id) ? $_POST['id'] : $data_input_id));
	}elseif (isset($_POST['save_component_field'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('data_input_id'));
		input_validate_input_number(get_request_var_post('sequence'));
		input_validate_input_regex(get_request_var_post('input_output'), '^(in|out)$');
		/* ==================================================== */

		$save['id']            = $_POST['id'];
		$save['hash']          = get_hash_data_input($_POST['id'], 'data_input_field');
		$save['data_input_id'] = $_POST['data_input_id'];
		$save['name']          = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['data_name']     = form_input_validate($_POST['data_name'], 'data_name', '', false, 3);
		$save['input_output']  = $_POST['input_output'];
		$save['update_rra']    = form_input_validate((isset($_POST['update_rra']) ? $_POST['update_rra'] : ''), 'update_rra', '', true, 3);
		$save['sequence']      = $_POST['sequence'];
		$save['type_code']     = form_input_validate((isset($_POST['type_code']) ? $_POST['type_code'] : ''), 'type_code', '', true, 3);
		$save['regexp_match']  = form_input_validate((isset($_POST['regexp_match']) ? $_POST['regexp_match'] : ''), 'regexp_match', '', true, 3);
		$save['allow_nulls']   = form_input_validate((isset($_POST['allow_nulls']) ? $_POST['allow_nulls'] : ''), 'allow_nulls', '', true, 3);

		if (!is_error_message()) {
			$data_input_field_id = sql_save($save, 'data_input_fields');

			if ($data_input_field_id) {
				raise_message(1);

				if ((!empty($data_input_field_id)) && ($_POST['input_output'] == 'in')) {
					generate_data_input_field_sequences(db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(get_request_var_post('data_input_id'))), get_request_var_post('data_input_id'));
				}
			}else{
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: data_input.php?header=false&action=field_edit&data_input_id=' . $_POST['data_input_id'] . '&id=' . (empty($data_input_field_id) ? $_POST['id'] : $data_input_field_id) . (!empty($_POST['input_output']) ? '&type=' . $_POST['input_output'] : ''));
		}else{
			header('Location: data_input.php?header=false&action=edit&id=' . $_POST['data_input_id']);
		}
	}
}

function form_actions() {
	global $di_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '1') { /* delete */
				for ($i=0;($i<count($selected_items));$i++) {
					data_remove($selected_items[$i]);
				}
			}
		}

		header('Location: data_input.php?header=false');
		exit;
	}

	/* setup some variables */
	$di_list = ''; $i = 0;

	/* loop through each of the data queries and process them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$di_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_input WHERE id = ?', array($matches[1]))) . '</li>';
			$di_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_input.php');

	html_start_box($di_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

	if (isset($di_array) && sizeof($di_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			$graphs = array();

			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to delete the following Data Input Method(s).</p>
					<p><ul>$di_list</ul></p>
				</td>
			</tr>\n";
		}

		$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Input Method(s)'>";
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one data input method.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($di_array) ? serialize($di_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
    CDEF Item Functions
   -------------------------- */

function field_remove() {
	global $registered_cacti_names;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('data_input_id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_REQUEST['confirm']))) {
		top_header();

		form_confirm('Are You Sure?', "Are you sure you want to delete the field '" . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM data_input_fields WHERE id = ?', array(get_request_var('id'))), ENT_QUOTES) . "'?", htmlspecialchars('data_input.php?action=edit&id=' . $_REQUEST['data_input_id']), htmlspecialchars('data_input.php?action=field_remove&id=' . $_REQUEST['id'] . '&data_input_id=' . $_REQUEST['data_input_id']));

		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_REQUEST['confirm']))) {
		/* get information about the field we're going to delete so we can re-order the seqs */
		$field = db_fetch_row_prepared('SELECT input_output,data_input_id FROM data_input_fields WHERE id = ?', array(get_request_var('id')));

		db_execute_prepared('DELETE FROM data_input_fields WHERE id = ?', array(get_request_var('id')));
		db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', array(get_request_var('id')));

		/* when a field is deleted; we need to re-order the field sequences */
		if (($field['input_output'] == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array($field['data_input_id'])), $matches))) {
			$j = 0;
			for ($i=0; ($i < count($matches[1])); $i++) {
				if (in_array($matches[1][$i], $registered_cacti_names) == false) {
					$j++;
					db_execute_prepared("UPDATE data_input_fields SET sequence = ? WHERE data_input_id = ? AND input_output = 'in' AND data_name = ?", array($j, $field['data_input_id'], $matches[1][$i]));
				}
			}
		}
	}
}

function field_edit() {
	global $registered_cacti_names, $fields_data_input_field_edit_1, $fields_data_input_field_edit_2, $fields_data_input_field_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('data_input_id'));
	input_validate_input_regex(get_request_var('type'), '^(in|out)$');
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$field = db_fetch_row_prepared('SELECT * FROM data_input_fields WHERE id = ?', array(get_request_var('id')));
	}

	if (!empty($_REQUEST['type'])) {
		$current_field_type = $_REQUEST['type'];
	}else{
		$current_field_type = $field['input_output'];
	}

	if ($current_field_type == 'out') {
		$header_name = 'Output';
	}elseif ($current_field_type == 'in') {
		$header_name = 'Input';
	}

	$data_input = db_fetch_row_prepared('SELECT type_id, name FROM data_input WHERE id = ?', array(get_request_var('data_input_id')));

	/* obtain a list of available fields for this given field type (input/output) */
	if (($current_field_type == 'in') && (preg_match_all('/<([_a-zA-Z0-9]+)>/', db_fetch_cell_prepared('SELECT input_string FROM data_input WHERE id = ?', array(($_REQUEST['data_input_id'] ? $_REQUEST['data_input_id'] : $field['data_input_id']))), $matches))) {
		for ($i=0; ($i < count($matches[1])); $i++) {
			if (in_array($matches[1][$i], $registered_cacti_names) == false) {
				$current_field_name = $matches[1][$i];
				$array_field_names[$current_field_name] = $current_field_name;
			}
		}
	}

	/* if there are no input fields to choose from, complain */
	if ((!isset($array_field_names)) && (isset($_REQUEST['type']) ? $_REQUEST['type'] == 'in' : false) && ($data_input['type_id'] == '1')) {
		display_custom_error_message('This script appears to have no input values, therefore there is nothing to add.');
		return;
	}

	form_start('data_input.php', 'data_input');

	html_start_box("$header_name Fields [edit: " . htmlspecialchars($data_input['name']) . ']', '100%', '', '3', 'center', '');

	$form_array = array();

	/* field name */
	if ((($data_input['type_id'] == '1') || ($data_input['type_id'] == '5')) && ($current_field_type == 'in')) { /* script */
		$form_array = inject_form_variables($fields_data_input_field_edit_1, $header_name, $array_field_names, (isset($field) ? $field : array()));
	}elseif (($data_input['type_id'] == '2') ||
			($data_input['type_id'] == '3') ||
			($data_input['type_id'] == '4') ||
			($data_input['type_id'] == '6') ||
			($data_input['type_id'] == '7') ||
			($data_input['type_id'] == '8') ||
			($current_field_type == 'out')) { /* snmp */
		$form_array = inject_form_variables($fields_data_input_field_edit_2, $header_name, (isset($field) ? $field : array()));
	}

	/* ONLY if the field is an input */
	if ($current_field_type == 'in') {
		unset($fields_data_input_field_edit['update_rra']);
	}elseif ($current_field_type == 'out') {
		unset($fields_data_input_field_edit['regexp_match']);
		unset($fields_data_input_field_edit['allow_nulls']);
		unset($fields_data_input_field_edit['type_code']);
	}

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => $form_array + inject_form_variables($fields_data_input_field_edit, (isset($field) ? $field : array()), $current_field_type, $_REQUEST)
		));

	html_end_box();

	form_save_button('data_input.php?action=edit&id=' . $_REQUEST['data_input_id']);
}

/* -----------------------
    Data Input Functions
   ----------------------- */

function data_remove($id) {
	$data_input_fields = db_fetch_assoc_prepared('SELECT id FROM data_input_fields WHERE data_input_id = ?', array($id));

	if (is_array($data_input_fields)) {
		foreach ($data_input_fields as $data_input_field) {
			db_execute_prepared('DELETE FROM data_input_data WHERE data_input_field_id = ?', array($data_input_field['id']));
		}
	}

	db_execute_prepared('DELETE FROM data_input WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM data_input_fields WHERE data_input_id = ?', array($id));
}

function data_edit() {
	global $fields_data_input_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$data_input = db_fetch_row_prepared('SELECT * FROM data_input WHERE id = ?', array(get_request_var('id')));
		$header_label = '[edit: ' . htmlspecialchars($data_input['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('data_input.php', 'data_input');

	html_start_box("Data Input Methods $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_data_input_edit, (isset($data_input) ? $data_input : array()))
		));

	html_end_box();

	if (!empty($_REQUEST['id'])) {
		html_start_box('Input Fields', '100%', '', '3', 'center', 'data_input.php?action=field_edit&type=in&data_input_id=' . htmlspecialchars(get_request_var('id')));
		print "<tr class='tableHeader'>";
			DrawMatrixHeaderItem('Name','',1);
			DrawMatrixHeaderItem('Field Order','',1);
			DrawMatrixHeaderItem('Friendly Name','',2);
		print '</tr>';

		$fields = db_fetch_assoc_prepared("SELECT id, data_name, name, sequence FROM data_input_fields WHERE data_input_id = ? AND input_output = 'in' ORDER BY sequence, data_name", array(get_request_var('id')));

		$i = 0;
		if (sizeof($fields) > 0) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
					?>
					<td>
						<a class="linkEditMain" href="<?php print htmlspecialchars('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . $_REQUEST['id']);?>"><?php print htmlspecialchars($field['data_name']);?></a>
					</td>
					<td>
						<?php print $field['sequence']; if ($field['sequence'] == '0') { print ' (Not In Use)'; }?>
					</td>
					<td>
						<?php print htmlspecialchars($field['name']);?>
					</td>
					<td align="right">
						<a class='pic deleteMarker fa fa-remove' href='<?php print htmlspecialchars('data_input.php?action=field_remove&id=' . $field['id'] . '&data_input_id=' . $_REQUEST['id']);?>' title='Delete'></a>
					</td>
					<?php
				form_end_row();
			}
		}else{
			print '<tr><td><em>No Input Fields</em></td></tr>';
		}
		html_end_box();

		html_start_box('Output Fields', '100%', '', '3', 'center', 'data_input.php?action=field_edit&type=out&data_input_id=' . $_REQUEST['id']);
		print "<tr class='tableHeader'>";
			DrawMatrixHeaderItem('Name','',1);
			DrawMatrixHeaderItem('Field Order','',1);
			DrawMatrixHeaderItem('Friendly Name','',1);
			DrawMatrixHeaderItem('Update RRA','',2);
		print '</tr>';

		$fields = db_fetch_assoc_prepared("SELECT id, name, data_name, update_rra, sequence FROM data_input_fields WHERE data_input_id = ? and input_output = 'out' ORDER BY sequence, data_name", array(get_request_var('id')));

		$i = 0;
		if (sizeof($fields) > 0) {
			foreach ($fields as $field) {
				form_alternate_row('', true);
				?>
					<td>
						<a class="linkEditMain" href="<?php print htmlspecialchars('data_input.php?action=field_edit&id=' . $field['id'] . '&data_input_id=' . $_REQUEST['id']);?>"><?php print htmlspecialchars($field['data_name']);?></a>
					</td>
					<td>
						<?php print $field['sequence']; if ($field['sequence'] == '0') { print ' (Not In Use)'; }?>
					</td>
					<td>
						<?php print htmlspecialchars($field['name']);?>
					</td>
					<td>
						<?php print html_boolean_friendly($field['update_rra']);?>
					</td>
					<td align="right">
						<a class='pic deleteMarker fa fa-remove' href='<?php print htmlspecialchars('data_input.php?action=field_remove&id=' . $field['id'] . '&data_input_id=' . $_REQUEST['id']);?>' title='Delete'></a>
					</td>
				<?php
				form_end_row();
			}
		}else{
			print '<tr><td><em>No Output Fields</em></td></tr>';
		}

		html_end_box();
	}

	form_save_button('data_input.php', 'return');
}

function data() {
	global $input_types, $di_actions, $item_rows;

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

	/* clean up search string */
	if (isset($_REQUEST['sort_direction'])) {
		$_REQUEST['sort_direction'] = sanitize_search_string(get_request_var('sort_direction'));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST['clear'])) {
		kill_session_var('sess_data_input_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_data_input_sort_column');
		kill_session_var('sess_data_input_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('filter', 'sess_data_input_filter');
		$changed += check_changed('rows',   'sess_default_rows');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('filter', 'sess_data_input_filter', '');
	load_current_session_value('sort_column', 'sess_data_input_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_data_input_sort_direction', 'ASC');
	load_current_session_value('page', 'sess_data_input_current_page', '1');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('Data Input Methods', '100%', '', '3', 'center', 'data_input.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_data_input' method='get' action='data_input.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td class='nowrap'>
						Input Methods
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
						<input type="submit" id='refresh' value="Go" title="Set/Refresh Filters">
					</td>
					<td>
						<input type="button" id='clear' value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'data_input.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'data_input.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_data_input').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='data_input.php'>\n";

	html_start_box('', '100%', '', '3', 'center', '');

	/* form the 'where' clause for our main sql query */
	if ($_REQUEST['filter'] != '') {
		$sql_where = "WHERE (di.name like '%" . get_request_var('filter') . "%')";
	}else{
		$sql_where = '';
	}

	$sql_where .= (strlen($sql_where) ? ' AND':'WHERE') . " (di.name!='Get Script Data (Indexed)'
		AND di.name!='Get Script Server Data (Indexed)'
		AND di.name!='Get SNMP Data'
		AND di.name!='Get SNMP Data (Indexed)')";

	$total_rows = db_fetch_cell("SELECT
		count(*)
		FROM data_input AS di
		$sql_where");

	$data_inputs = db_fetch_assoc("SELECT di.*,
		SUM(CASE WHEN dtd.local_data_id=0 THEN 1 ELSE 0 END) AS templates,
		SUM(CASE WHEN dtd.local_data_id>0 THEN 1 ELSE 0 END) AS data_sources
		FROM data_input AS di
		LEFT JOIN data_template_data AS dtd
		ON di.id=dtd.data_template_id
		$sql_where
		GROUP BY di.id
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('data_input.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 6, 'Input Methods', 'page', 'main');

	print $nav;

	$display_text = array(
		'name' => array('display' => 'Data Input Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Data Input Method.'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Data Inputs that are in use can not be Deleted.  In use is defined as being referenced either by a Data Source or a Data Template.'), 
		'data_sources' => array('display' => 'Data Sources Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Sources that use this Data Input Method.'),
		'templates' => array('display' => 'Templates Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Data Templates that use this Data Input Method.'),
		'type_id' => array('display' => 'Data Input Method', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The method used to gather information for this Data Input Method.'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($data_inputs) > 0) {
		foreach ($data_inputs as $data_input) {
			/* hide system types */
			if ($data_input['templates'] > 0 || $data_input['data_sources'] > 0) {
				$disabled = true;
			}else{
				$disabled = false;
			}
			form_alternate_row('line' . $data_input['id'], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_input.php?action=edit&id=' . $data_input['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($data_input['name'])) : htmlspecialchars($data_input['name'])) . '</a>', $data_input['id']);
			form_selectable_cell($disabled ? 'No':'Yes', $data_input['id'],'', 'text-align:right');
			form_selectable_cell(number_format($data_input['data_sources']), $data_input['id'],'', 'text-align:right');
			form_selectable_cell(number_format($data_input['templates']), $data_input['id'],'', 'text-align:right');
			form_selectable_cell($input_types{$data_input['type_id']}, $data_input['id']);
			form_checkbox_cell($data_input['name'], $data_input['id'], $disabled);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr><td colspan='5'><em>No Data Input Methods</em></td></tr>";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($di_actions);

	form_end();
}

