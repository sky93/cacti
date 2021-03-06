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
include_once('./lib/data_query.php');

define('MAX_DISPLAY_PAGES', 21);

$dq_actions = array(
	1 => 'Delete'
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
	case 'item_moveup_dssv':
		data_query_item_moveup_dssv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_movedown_dssv':
		data_query_item_movedown_dssv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_remove_dssv':
		data_query_item_remove_dssv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_moveup_gsv':
		data_query_item_moveup_gsv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_movedown_gsv':
		data_query_item_movedown_gsv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_remove_gsv':
		data_query_item_remove_gsv();

		header('Location: data_queries.php?header=false&action=item_edit&id=' . $_REQUEST['snmp_query_graph_id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_remove':
		data_query_item_remove();

		header('Location: data_queries.php?header=false&action=edit&id=' . $_REQUEST['snmp_query_id']);
		break;
	case 'item_edit':
		top_header();

		data_query_item_edit();

		bottom_footer();
		break;
	case 'remove':
		data_query_remove();

		header ('Location: data_queries.php');
		break;
	case 'edit':
		top_header();

		data_query_edit();

		bottom_footer();
		break;
	default:
		top_header();

		data_query();

		bottom_footer();
		break;
}

/* --------------------------
    The Save Function
   -------------------------- */

function form_save() {
	if (isset($_POST['save_component_snmp_query'])) {
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('data_input_id'));

		$save['id'] = $_POST['id'];
		$save['hash'] = get_hash_data_query($_POST['id']);
		$save['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['description'] = form_input_validate($_POST['description'], 'description', '', true, 3);
		$save['xml_path'] = form_input_validate($_POST['xml_path'], 'xml_path', '', false, 3);
		$save['data_input_id'] = $_POST['data_input_id'];

		if (!is_error_message()) {
			$snmp_query_id = sql_save($save, 'snmp_query');

			if ($snmp_query_id) {
				raise_message(1);
			}else{
				raise_message(2);
			}
		}

		header('Location: data_queries.php?header=false&action=edit&id=' . (empty($snmp_query_id) ? $_POST['id'] : $snmp_query_id));
	}elseif (isset($_POST['save_component_snmp_query_item'])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post('id'));
		input_validate_input_number(get_request_var_post('snmp_query_id'));
		input_validate_input_number(get_request_var_post('graph_template_id'));
		/* ==================================================== */

		$redirect_back = false;

		$save['id'] = $_POST['id'];
		$save['hash'] = get_hash_data_query($_POST['id'], 'data_query_graph');
		$save['snmp_query_id'] = $_POST['snmp_query_id'];
		$save['name'] = form_input_validate($_POST['name'], 'name', '', false, 3);
		$save['graph_template_id'] = $_POST['graph_template_id'];

		if (!is_error_message()) {
			$snmp_query_graph_id = sql_save($save, 'snmp_query_graph');

			if ($snmp_query_graph_id) {
				raise_message(1);

				/* if the user changed the graph template, go through and delete everything that
				was associated with the old graph template */
				if ($_POST['graph_template_id'] != $_POST['_graph_template_id']) {
					db_execute_prepared('DELETE FROM snmp_query_graph_rrd_sv WHERE snmp_query_graph_id = ?', array($snmp_query_graph_id));
					db_execute_prepared('DELETE FROM snmp_query_graph_sv WHERE snmp_query_graph_id = ?', array($snmp_query_graph_id));
					$redirect_back = true;
				}

				db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id = ?', array($snmp_query_graph_id));

				while (list($var, $val) = each($_POST)) {
					if (preg_match('/^dsdt_([0-9]+)_([0-9]+)_check/i', $var)) {
						$data_template_id = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', "\\1", $var);
						$data_template_rrd_id = preg_replace('/^dsdt_([0-9]+)_([0-9]+).+/', "\\2", $var);
						/* ================= input validation ================= */
						input_validate_input_number($data_template_id);
						input_validate_input_number($data_template_rrd_id);
						/* ==================================================== */

						db_execute_prepared('REPLACE INTO snmp_query_graph_rrd (snmp_query_graph_id, data_template_id, data_template_rrd_id, snmp_field_name) VALUES (?, ?, ?, ?)', array($snmp_query_graph_id, $data_template_id, $data_template_rrd_id, $_POST{'dsdt_' . $data_template_id . '_' . $data_template_rrd_id . '_snmp_field_output'}));
					}elseif ((preg_match('/^svds_([0-9]+)_x/i', $var, $matches)) && (!empty($_POST{'svds_' . $matches[1] . '_text'})) && (!empty($_POST{'svds_' . $matches[1] . '_field'}))) {
						/* suggested values -- data templates */

						/* ================= input validation ================= */
						input_validate_input_number($matches[1]);
						/* ==================================================== */

						$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_rrd_sv', 'snmp_query_graph_id=' . $_POST['id']  . ' AND data_template_id=' . $matches[1] . " AND field_name='" . $_POST{'svds_' . $matches[1] . '_field'} . "'");
						$hash = get_hash_data_query(0, 'data_query_sv_data_source');
						db_execute_prepared('INSERT INTO snmp_query_graph_rrd_sv (hash, snmp_query_graph_id, data_template_id, sequence, field_name, text) VALUES (?, ?, ?, ?, ?, ?)', array($hash, $_POST['id'], $matches[1], $sequence, $_POST{'svds_' . $matches[1] . '_field'}, $_POST{'svds_' . $matches[1] . '_text'}));

						$redirect_back = true;
						clear_messages();
					}elseif ((preg_match('/^svg_x/i', $var)) && (!empty($_POST{'svg_text'})) && (!empty($_POST{'svg_field'}))) {
						/* suggested values -- graph templates */
						$sequence = get_sequence(0, 'sequence', 'snmp_query_graph_sv', 'snmp_query_graph_id=' . $_POST['id'] . " AND field_name='" . $_POST{'svg_field'} . "'");
						$hash = get_hash_data_query(0, 'data_query_sv_graph');
						db_execute_prepared('INSERT INTO snmp_query_graph_sv (hash, snmp_query_graph_id, sequence, field_name, text) VALUES (?, ?, ?, ?, ?)', array($hash, $_POST['id'], $sequence, $_POST{'svg_field'}, $_POST{'svg_text'}));

						$redirect_back = true;
						clear_messages();
					}
				}

				if (isset($_POST['header']) && $_POST['header'] == 'false') {
					$header = '&header=false';
				}else{
					$header = '';
				}
			}else{
				raise_message(2);
				$header = '';
			}
		}

		header('Location: data_queries.php?header=false&action=item_edit' . $header . '&id=' . (empty($snmp_query_graph_id) ? $_POST['id'] : $snmp_query_graph_id) . '&snmp_query_id=' . $_POST['snmp_query_id']);
	}
}

function form_actions() {
	global $dq_actions;

	/* ================= input validation ================= */
	input_validate_input_regex(get_request_var_post('drp_action'), '^([a-zA-Z0-9_]+)$');
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset($_POST['selected_items'])) {
		$selected_items = sanitize_unserialize_selected_items($_POST['selected_items']);

		if ($selected_items != false) {
			if ($_POST['drp_action'] == '1') { /* delete */
				for ($i=0;($i<count($selected_items));$i++) {
					 data_query_remove($selected_items[$i]);
				}
			}
		}

		header('Location: data_queries.php?header=false');
		exit;
	}

	/* setup some variables */
	$dq_list = ''; $i = 0;

	/* loop through each of the data queries and process them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$dq_list .= '<li>' . htmlspecialchars(db_fetch_cell_prepared('SELECT snmp_query.name FROM snmp_query WHERE id = ?', array($matches[1]))) . '</li>';
			$dq_array[$i] = $matches[1];

			$i++;
		}
	}

	top_header();

	form_start('data_queries.php');

	html_start_box($dq_actions{$_POST['drp_action']}, '60%', '', '3', 'center', '');

	if (isset($dq_array) && sizeof($dq_array)) {
		if ($_POST['drp_action'] == '1') { /* delete */
			$graphs = array();

			print "<tr>
				<td class='textArea' class='odd'>
					<p>Click 'Continue' to delete the following Data Querie(s).</p>
					<p><ul>$dq_list</ul></p>
				</td>
			</tr>\n";
		}

		$save_html = "<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='Delete Data Querie(s)'>";
	}else{
		print "<tr><td class='odd'><span class='textError'>You must select at least one data query.</span></td></tr>\n";
		$save_html = "<input type='button' value='Return' onClick='cactiReturnTo()'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($dq_array) ? serialize($dq_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST['drp_action'] . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* ----------------------------
    Data Query Graph Functions
   ---------------------------- */

function data_query_item_movedown_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('snmp_query_graph_id'));
	/* ==================================================== */

	move_item_down('snmp_query_graph_sv', $_REQUEST['id'], 'snmp_query_graph_id=' . $_REQUEST['snmp_query_graph_id'] . " AND field_name='" . $_REQUEST['field_name'] . "'");
}

function data_query_item_moveup_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('snmp_query_graph_id'));
	/* ==================================================== */

	move_item_up('snmp_query_graph_sv', $_REQUEST['id'], 'snmp_query_graph_id=' . $_REQUEST['snmp_query_graph_id'] . " AND field_name='" . $_REQUEST['field_name'] . "'");
}

function data_query_item_remove_gsv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM snmp_query_graph_sv WHERE id = ?', array($_REQUEST['id']));
}

function data_query_item_movedown_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('data_template_id'));
	input_validate_input_number(get_request_var('snmp_query_graph_id'));
	/* ==================================================== */

	move_item_down('snmp_query_graph_rrd_sv', $_REQUEST['id'], 'data_template_id=' . $_REQUEST['data_template_id'] . ' AND snmp_query_graph_id=' . $_REQUEST['snmp_query_graph_id'] . " AND field_name='" . $_REQUEST['field_name'] . "'");
}

function data_query_item_moveup_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('data_template_id'));
	input_validate_input_number(get_request_var('snmp_query_graph_id'));
	/* ==================================================== */

	move_item_up('snmp_query_graph_rrd_sv', $_REQUEST['id'], 'data_template_id=' . $_REQUEST['data_template_id'] . ' AND snmp_query_graph_id=' . $_REQUEST['snmp_query_graph_id'] . " AND field_name='" . $_REQUEST['field_name'] . "'");
}

function data_query_item_remove_dssv() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	db_execute_prepared('DELETE FROM snmp_query_graph_rrd_sv WHERE id = ?', array($_REQUEST['id']));
}

function data_query_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('snmp_query_id'));
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset($_REQUEST['confirm']))) {
		top_header();

		form_confirm('Are You Sure?', "Are you sure you want to delete the Data Query Graph '" . htmlspecialchars(db_fetch_cell_prepared('SELECT name FROM snmp_query_graph WHERE id = ?', array($_REQUEST['id'])), ENT_QUOTES) . "'?", htmlspecialchars('data_queries.php?action=edit&id=' . $_REQUEST['snmp_query_id']), htmlspecialchars('data_queries.php?action=item_remove&id=' . $_REQUEST['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']));

		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset($_REQUEST['confirm']))) {
		db_execute_prepared('DELETE FROM snmp_query_graph WHERE id = ?', array($_REQUEST['id']));
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id = ?', array($_REQUEST['id']));
		db_execute_prepared('DELETE FROM snmp_query_graph_rrd_sv WHERE snmp_query_graph_id = ?', array($_REQUEST['id']));
		db_execute_prepared('DELETE FROM snmp_query_graph_sv WHERE snmp_query_graph_id = ?', array($_REQUEST['id']));
	}
}

function data_query_item_edit() {
	global $fields_data_query_item_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	input_validate_input_number(get_request_var('snmp_query_id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$snmp_query_item = db_fetch_row_prepared('SELECT * FROM snmp_query_graph WHERE id = ?', array($_REQUEST['id']));
	}

	$snmp_query = db_fetch_row_prepared('SELECT name, xml_path FROM snmp_query WHERE id = ?', array($_REQUEST['snmp_query_id']));
	$header_label = '[edit: ' . htmlspecialchars($snmp_query['name']) . ']';

	form_start('data_queries.php', 'data_queries');

	html_start_box("Associated Graph/Data Templates $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_data_query_item_edit, (isset($snmp_query_item) ? $snmp_query_item : array()), $_REQUEST)
		)
	);

	html_end_box();

	?>
	<script type='text/javascript'>
	$('#graph_template_id').change(function() {
		$('#name').val($(this).children(':selected').text());
	});
	</script>
	<?php

	if (!empty($snmp_query_item['id'])) {
		html_start_box('Associated Data Templates', '100%', '', '3', 'center', '');

		$data_templates = db_fetch_assoc_prepared('SELECT
			data_template.id,
			data_template.name
			FROM (data_template, data_template_rrd, graph_templates_item)
			WHERE graph_templates_item.task_item_id = data_template_rrd.id
			AND data_template_rrd.data_template_id = data_template.id
			AND data_template_rrd.local_data_id = 0
			AND graph_templates_item.local_graph_id = 0
			AND graph_templates_item.graph_template_id = ?
			GROUP BY data_template.id
			ORDER BY data_template.name', array($snmp_query_item['graph_template_id']));

		$i = 0;
		if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			print "<tr class='tableHeader'>
					<th>Data Template - " . $data_template['name'] . '</th>
				</tr>';

			$data_template_rrds = db_fetch_assoc_prepared('SELECT
				data_template_rrd.id,
				data_template_rrd.data_source_name,
				snmp_query_graph_rrd.snmp_field_name,
				snmp_query_graph_rrd.snmp_query_graph_id
				FROM data_template_rrd
				LEFT JOIN snmp_query_graph_rrd on (snmp_query_graph_rrd.data_template_rrd_id = data_template_rrd.id AND snmp_query_graph_rrd.snmp_query_graph_id = ? AND snmp_query_graph_rrd.data_template_id = ?)
				WHERE data_template_rrd.data_template_id = ?
				AND data_template_rrd.local_data_id = 0
				ORDER BY data_template_rrd.data_source_name', array($_REQUEST['id'], $data_template['id'], $data_template['id']));

			$i = 0;
			if (sizeof($data_template_rrds) > 0) {
			foreach ($data_template_rrds as $data_template_rrd) {
				if (empty($data_template_rrd['snmp_query_graph_id'])) {
					$old_value = '';
				}else{
					$old_value = 'on';
				}

				form_alternate_row();
				?>
					<td>
						<table>
							<tr>
								<td style='width:200px;'>
									Data Source
								</td>
								<td style='width:200px;'>
									<?php print $data_template_rrd['data_source_name'];?>
								</td>
								<td>
									<?php
									$snmp_queries = get_data_query_array($_REQUEST['snmp_query_id']);
									$xml_outputs = array();

									while (list($field_name, $field_array) = each($snmp_queries['fields'])) {
										if ($field_array['direction'] == 'output') {
											$xml_outputs[$field_name] = $field_name . ' (' . $field_array['name'] . ')';
										}
									}

									form_dropdown('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_snmp_field_output',$xml_outputs,'','',$data_template_rrd['snmp_field_name'],'','');?>
								</td>
								<td align="right">
									<?php form_checkbox('dsdt_' . $data_template['id'] . '_' . $data_template_rrd['id'] . '_check', $old_value, '', '', '', $_REQUEST['id']); print '<br>';?>
								</td>
							</tr>
						</table>
					</td>
				<?php
				form_end_row();
			}
			}
		}
		}

		html_end_box();

		html_start_box('Suggested Values - Graph Names', '100%', '', '3', 'center', '');

		/* suggested values for graphs templates */
		$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, id
			FROM snmp_query_graph_sv
			WHERE snmp_query_graph_id = ?
			ORDER BY field_name, sequence', array($_REQUEST['id']));

		html_header(array('Name', '', 'Equation'), 2);

		$i = 0;
		$total_values = sizeof($suggested_values);
		if ($total_values) {
			foreach ($suggested_values as $suggested_value) {
				form_alternate_row();

				$show_up   = false;
				$show_down = false;

				// Handle up true
				if ($i != 0) {
					$show_up = true;
				}

				// Handle down true
				if ($total_values > 1 && $i < $total_values-1) {
					$show_down = true;
				}

				?>
				<td style='width;120;'>
					<?php print htmlspecialchars($suggested_value['field_name']);?>
				</td>
				<td style='width:40px;text-align:center;'>
					<?php if ($show_down) {?>
					<span class='remover fa fa-arrow-down moveArrow' title='Move Down' href='<?php print htmlspecialchars('data_queries.php?action=item_movedown_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&field_name=' . $suggested_value['field_name']);?>'></span>
					<?php }else{?>
					<span class='moveArrowNone'></span>
					<?php } ?>
					<?php if ($show_up) {?>
					<span class='remover fa fa-arrow-up moveArrow' title='Move Up' href='<?php print htmlspecialchars('data_queries.php?action=item_moveup_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&field_name=' . $suggested_value['field_name']);?>'></span>
					<?php }else{?>
					<span class='moveArrowNone'></span>
					<?php } ?>
				</td>
				<td>
					<?php print htmlspecialchars($suggested_value['text']);?>
				</td>
				<td align='right'>
					<span class='remover deleteMarker fa fa-remove' titel='Delete' href='<?php print htmlspecialchars('data_queries.php?action=item_remove_gsv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id']);?>'></span>
				</td>
				<?php

				form_end_row();

				$i++;
			}
		}

		form_alternate_row();
		?>
		<td colspan='4'>
			<table>
				<tr>
					<td class='nowrap'>
						Field Name
					</td>
					<td>
						<input type='text' id='svg_field' size='15'>
					</td>
					<td class='nowrap'>
						Suggested Value
					</td>
					<td>
						<input type='text' id='svg_text' size='60'>
					</td>
					<td>
						<input id='svg_x' type='button' name='svg_x' value='Add' title='Add Graph Title Suggested Name'>
					</td>
				</tr>
			</table>
		</td>
		<?php
		form_end_row();

		html_end_box();
		html_start_box('Suggested Values - Data Source Names', '100%', '', '3', 'center', '');

		reset($data_templates);

		/* suggested values for data templates */
		if (sizeof($data_templates)) {
		foreach ($data_templates as $data_template) {
			$suggested_values = db_fetch_assoc_prepared('SELECT text, field_name, id
				FROM snmp_query_graph_rrd_sv
				WHERE snmp_query_graph_id = ?
				AND data_template_id = ?
				ORDER BY field_name, sequence', array($_REQUEST['id'], $data_template['id']));

			html_header(array('Name', '', 'Equation'), 2);

			$i = 0;
			$total_values = sizeof($suggested_values);

			if ($total_values) {
				$prev_name = '';
				foreach ($suggested_values as $suggested_value) {
					form_alternate_row();

					$show_up   = false;
					$show_down = false;

					// Handle up true
					if ($i != 0) {
						$show_up = true;
					}

					// Handle down true
					if ($total_values > 1 && $i < $total_values-1) {
						$show_down = true;
					}

					?>
					<td style='width:120;'>
						<?php print htmlspecialchars($suggested_value['field_name']);?>
					</td>
					<td style='width:40;text-align:center;'>
						<?php if ($show_down) {?>
						<span class='remover fa fa-arrow-down moveArrow' title='Move Down' href='<?php print htmlspecialchars('data_queries.php?action=item_movedown_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id='. $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'></span>
						<?php }else{?>
						<span class='moveArrowNone'></span>
						<?php } ?>
						<?php if ($show_up) {?>
						<span class='remover fa fa-arrow-up moveArrow' title='Move Up' href='<?php print htmlspecialchars('data_queries.php?action=item_moveup_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id'] . '&field_name=' . $suggested_value['field_name']);?>'></span>
						<?php }else{?>
						<span class='moveArrowNone'></span>
						<?php } ?>
					</td>
					<td class='nowrap'>
						<?php print htmlspecialchars($suggested_value['text']);?>
					</td>
					<td align='right'>
						<span class='remover deleteMarker fa fa-remove' title='Delete' href='<?php print htmlspecialchars('data_queries.php?action=item_remove_dssv&snmp_query_graph_id=' . $_REQUEST['id'] . '&id=' . $suggested_value['id'] . '&snmp_query_id=' . $_REQUEST['snmp_query_id'] . '&data_template_id=' . $data_template['id']);?>'></span>
					</td>
					<?php

					form_end_row();

					$prev_name = $suggested_value['field_name'];
					$i++;
				}
			}

			form_alternate_row();
			?>
			<td colspan='4'>
				<table>
					<tr>
						<td class='nowrap'>
							Field Name
						</td>
						<td>
							<input id='svds_field' type='text' name='svds_<?php print $data_template['id'];?>_field' size='15'>
						</td>
						<td class='nowrap'>
							Suggested Value
						</td>
						<td>
							<input id='svds_text' type='text' name='svds_<?php print $data_template['id'];?>_text' size='60'>
						</td>
						<td>
							<input id='svds_x' type='button' name='svds_<?php print $data_template['id'];?>_x' value='Add' title='Add Data Source Name Suggested Name'>
						</td>
					</tr>
				</table>
				<script type='text/javascript'>
				$('.remover').click(function() {
					href=$(this).attr('href');
					$.get(href, function(data) {
						$('form[action="data_queries.php"]').unbind();
						$('#main').html(data);
						applySkin();
					});
				});

				$('input[id="svg_x"]').click(function() {
					$.post('data_queries.php', { 
						_graph_template_id:$('#_graph_template_id').val(), 
						action:'save',
						name:$('#name').val(),
						graph_template_id:$('#graph_template_id').val(), 
						id:$('#id').val(),
						header:'false',
						save_component_snmp_query_item:'1', 
						snmp_query_id:$('#snmp_query_id').val(), 
						svg_field:$('#svg_field').val(), 
						svg_text:$('#svg_text').val(), 
						svg_x:'Add',
						__csrf_magic: csrfMagicToken
					}).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				});

				$('input[id="svds_x"]').click(function() {
					var svds_text_name=$('#svds_text').attr('name');
					var svds_field_name=$('#svds_field').attr('name');
					var svds_x_name=$('#svds_x').attr('name');
					var jSON = $.parseJSON('{ ' + 
						'"_graph_template_id":"'+$('#_graph_template_id').val() + '", ' +
						'"action":"save", ' +
						'"name":"'+$('#name').val() + '", ' +
						'"graph_template_id":"'+$('#graph_template_id').val() + '", ' +
						'"id":"'+$('#id').val() + '", ' +
						'"header":"false", ' +
						'"__csrf_magic":"'+csrfMagicToken+'", ' +
						'"save_component_snmp_query_item":"1", ' +
						'"snmp_query_id":"'+$('#snmp_query_id').val() + '", ' +
						'"'+svds_field_name+'":"'+$('#svds_field').val() + '", ' +
						'"'+svds_text_name+'":"'+$('#svds_text').val() + '", ' +
						'"'+svds_x_name+'":"Add" }');

					$.post('data_queries.php', jSON).done(function(data) {
						$('#main').html(data);
						applySkin();
					});
				});
				</script>
			</td>
			<?php
			form_end_row();
		}
		}
		html_end_box();

	}

	form_save_button('data_queries.php?action=edit&id=' . $_REQUEST['snmp_query_id'], 'return');
}

/* ---------------------
    Data Query Functions
   --------------------- */

function data_query_remove($id) {
	$snmp_query_graph = db_fetch_assoc_prepared('SELECT id FROM snmp_query_graph WHERE snmp_query_id = ?', array($id));

	if (sizeof($snmp_query_graph) > 0) {
	foreach ($snmp_query_graph as $item) {
		db_execute('DELETE FROM snmp_query_graph_rrd WHERE snmp_query_graph_id=' . $item['id']);
	}
	}

	db_execute_prepared('DELETE FROM snmp_query WHERE id = ?', array($id));
	db_execute_prepared('DELETE FROM snmp_query_graph WHERE snmp_query_id = ?', array($id));
	db_execute_prepared('DELETE FROM host_template_snmp_query WHERE snmp_query_id = ?', array($id));
	db_execute_prepared('DELETE FROM host_snmp_query WHERE snmp_query_id = ?', array($id));
	db_execute_prepared('DELETE FROM host_snmp_cache WHERE snmp_query_id = ?', array($id));
}

function data_query_edit() {
	global $fields_data_query_edit, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var('id'));
	/* ==================================================== */

	if (!empty($_REQUEST['id'])) {
		$snmp_query = db_fetch_row_prepared('SELECT * FROM snmp_query WHERE id = ?', array($_REQUEST['id']));
		$header_label = '[edit: ' . htmlspecialchars($snmp_query['name']) . ']';
	}else{
		$header_label = '[new]';
	}

	form_start('data_queries.php', 'data_queries');

	html_start_box("Data Queries $header_label", '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($fields_data_query_edit, (isset($snmp_query) ? $snmp_query : array()))
		));

	html_end_box();

	if (!empty($snmp_query['id'])) {
		$xml_filename = str_replace('<path_cacti>', $config['base_path'], $snmp_query['xml_path']);

		if ((file_exists($xml_filename)) && (is_file($xml_filename))) {
			$text = "<font color='#0d7c09'>Successfully located XML file</font>";
			$xml_file_exists = true;
		}else{
			$text = "<font class='txtErrorText'>Could not locate XML file.</font>";
			$xml_file_exists = false;
		}

		html_start_box('', '100%', '', '3', 'center', '');
		print "<tr class='tableRow'><td>$text</td></tr>";
		html_end_box();

		if ($xml_file_exists == true) {
			html_start_box('Associated Graph Templates', '100%', '', '3', 'center', 'data_queries.php?action=item_edit&snmp_query_id=' . $snmp_query['id']);

			print "<tr class='tableHeader'>
					<th class='tableSubHeaderColumn'>Name</th>
					<th class='tableSubHeaderColumn'>Graph Template Name</th>
					<th class='tableSubHeaderColumn right'>Mapping ID</th>
					<th class='tableSubHeaderColumn right' style='width:60px;'>Action</td>
				</tr>";

			$snmp_query_graphs = db_fetch_assoc_prepared('SELECT sqg.id, gt.name AS graph_template_name, sqg.name
				FROM snmp_query_graph AS sqg
				LEFT JOIN graph_templates AS gt
				ON (sqg.graph_template_id = gt.id)
				WHERE sqg.snmp_query_id = ?
				ORDER BY sqg.name', array($snmp_query['id']));

			$i = 0;
			if (sizeof($snmp_query_graphs) > 0) {
				foreach ($snmp_query_graphs as $snmp_query_graph) {
					form_alternate_row();
					?>
						<td>
							<a class='linkEditMain' href="<?php print htmlspecialchars('data_queries.php?action=item_edit&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id']);?>"><?php print htmlspecialchars($snmp_query_graph['name']);?></a>
						</td>
						<td>
							<?php print htmlspecialchars($snmp_query_graph['graph_template_name']);?>
						</td>
						<td class='right'>
							<?php print $snmp_query_graph['id'];?>
						</td>
						<td class='right'>
							<a class='deleteMarker fa fa-remove' title='Delete' href='<?php print htmlspecialchars('data_queries.php?action=item_remove&id=' . $snmp_query_graph['id'] . '&snmp_query_id=' . $snmp_query['id']);?>'></a>
						</td>
					</tr>
					<?php
				}
			}else{
				print "<tr class='tableRow'><td><em>No Graph Templates Defined.</em></td></tr>";
			}

			html_end_box();
		}
	}

	form_save_button('data_queries.php', 'return');
}

function data_query() {
	global $dq_actions, $item_rows;

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
		kill_session_var('sess_data_queries_filter');
		kill_session_var('sess_default_rows');
		kill_session_var('sess_data_queries_sort_column');
		kill_session_var('sess_data_queries_sort_direction');

		unset($_REQUEST['page']);
		unset($_REQUEST['filter']);
		unset($_REQUEST['rows']);
		unset($_REQUEST['sort_column']);
		unset($_REQUEST['sort_direction']);
	}else{
		$changed = 0;
		$changed += check_changed('filter', 'sess_data_queries_filter');
		$changed += check_changed('rows',   'sess_default_rows');

		if ($changed) {
			$_REQUEST['page'] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value('sort_column', 'sess_data_queries_sort_column', 'name');
	load_current_session_value('sort_direction', 'sess_data_queries_sort_direction', 'ASC');
	load_current_session_value('page', 'sess_data_queries_current_page', '1');
	load_current_session_value('filter', 'sess_data_queries_filter', '');
	load_current_session_value('rows', 'sess_default_rows', read_config_option('num_rows_table'));

	html_start_box('Data Queries', '100%', '', '3', 'center', 'data_queries.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='form_data_queries' method='get' action='data_queries.php'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						Search
					</td>
					<td>
						<input id='filter' type='text' name='filter' size='25' value='<?php print htmlspecialchars(get_request_var('filter'));?>'>
					</td>
					<td class='nowrap'>
						Data Queries
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
						<input type='button' id='clear' name='clear' value='Clear' title='Clear Filters'>
					</td>
				</tr>
			</table>
			<input type='hidden' id='page' name='page' value='<?php print $_REQUEST['page'];?>'>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'data_queries.php?filter='+$('#filter').val()+'&rows='+$('#rows').val()+'&page='+$('#page').val()+'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'data_queries.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});
	
			$('#form_data_queries').submit(function(event) {
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
	form_start('data_queries.php', 'chk');

	html_start_box('', '100%', '', '3', 'center', '');

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var('filter'))) {
		$sql_where = "WHERE (sq.name like '%%" . get_request_var('filter') . "%%' OR di.name like '%%" . get_request_var('filter') . "%%')";
	}else{
		$sql_where = '';
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(*)
		FROM snmp_query AS sq
		INNER JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		$sql_where");

	$snmp_queries = db_fetch_assoc("SELECT sq.id, sq.name,
		di.name AS data_input_method, 
		COUNT(DISTINCT gl.id) AS graphs,
		COUNT(DISTINCT sqg.graph_template_id) AS templates
		FROM snmp_query AS sq
		LEFT JOIN snmp_query_graph AS sqg
		ON sq.id=sqg.snmp_query_id
		LEFT JOIN data_input AS di
		ON (sq.data_input_id=di.id)
		LEFT JOIN graph_local AS gl
		ON gl.snmp_query_id=sq.id
		$sql_where
		GROUP BY sq.id
		ORDER BY " . get_request_var('sort_column') . ' ' . get_request_var('sort_direction') . '
		LIMIT ' . (get_request_var('rows')*(get_request_var('page')-1)) . ',' . get_request_var('rows'));

	$nav = html_nav_bar('data_queries.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), get_request_var('rows'), $total_rows, 7, 'Data Queries', 'page', 'main');
	print $nav;

	$display_text = array(
		'name' => array('display' => 'Data Query Name', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The name of this Data Query.'),
		'nosort' => array('display' => 'Deletable', 'align' => 'right', 'tip' => 'Data Queries that are in use can not be Deleted.  In use is defined as being referenced by either a Graph or a Graph Template.'), 
		'graphs' => array('display' => 'Graphs Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graphs using this Data Query.'),
		'templates' => array('display' => 'Templates Using', 'align' => 'right', 'sort' => 'DESC', 'tip' => 'The number of Graphs Templates using this Data Query.'),
		'data_input_method' => array('display' => 'Data Input Method', 'align' => 'left', 'sort' => 'ASC', 'tip' => 'The Data Input Method used to collect data for Data Sources associated with this Data Query.'),
		'id' => array('display' => 'ID', 'align' => 'right', 'sort' => 'ASC', 'tip' => 'The internal ID for this Graph Template.  Useful when performing automation or debugging.'));

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i = 0;
	if (sizeof($snmp_queries) > 0) {
		foreach ($snmp_queries as $snmp_query) {
			if ($snmp_query['graphs'] == 0 && $snmp_query['templates'] == 0) {
				$disabled = false;
			}else{
				$disabled = true;
			}

			form_alternate_row('line' . $snmp_query['id'], true, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars('data_queries.php?action=edit&id=' . $snmp_query['id']) . "'>" . (strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($snmp_query['name'])) : htmlspecialchars($snmp_query['name'])) . '</a>', $snmp_query['id']);
			form_selectable_cell($disabled ? 'No':'Yes', $snmp_query['id'], '', 'text-align:right');
			form_selectable_cell(number_format($snmp_query['graphs']), $snmp_query['id'], '', 'text-align:right');
			form_selectable_cell(number_format($snmp_query['templates']), $snmp_query['id'], '', 'text-align:right');
			form_selectable_cell((strlen(get_request_var('filter')) ? preg_replace('/(' . preg_quote(get_request_var('filter'), '/') . ')/i', "<span class='filteredValue'>\\1</span>", htmlspecialchars($snmp_query['data_input_method'])) : htmlspecialchars($snmp_query['data_input_method'])), $snmp_query['id']);
			form_selectable_cell($snmp_query['id'], $snmp_query['id'], '', 'text-align:right;');
			form_checkbox_cell($snmp_query['name'], $snmp_query['id'], $disabled);
			form_end_row();
		}

		print $nav;
	}else{
		print "<tr class='tableRow'><td colspan='5'><em>No Data Queries</em></td></tr>";
	}

	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($dq_actions);
}

