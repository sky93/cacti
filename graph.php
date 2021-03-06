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

/* set default action */
if (!isset($_REQUEST['action'])) { $_REQUEST['action'] = 'view'; }
if (!isset($_REQUEST['view_type'])) { $_REQUEST['view_type'] = ''; }

$guest_account = true;
include('./include/auth.php');
include_once('./lib/rrd.php');

/* ================= input validation ================= */
input_validate_input_regex(get_request_var('rra_id'), '^([0-9]+|all)$');
input_validate_input_number(get_request_var('local_graph_id'));
input_validate_input_number(get_request_var('graph_end'));
input_validate_input_number(get_request_var('graph_start'));
input_validate_input_regex(get_request_var('view_type'), '^([a-zA-Z0-9]+)$');
/* ==================================================== */

api_plugin_hook_function('graph');

include_once('./lib/html_tree.php');
top_graph_header();

if (!isset($_REQUEST['rra_id'])) {
	$_REQUEST['rra_id'] = 'all';
}

if ($_REQUEST['rra_id'] == 'all') {
	$sql_where = ' WHERE id IS NOT NULL';
}else{
	$sql_where = ' WHERE id=' . $_REQUEST['rra_id'];
}

/* make sure the graph requested exists (sanity) */
if (!(db_fetch_cell_prepared('SELECT local_graph_id FROM graph_templates_graph WHERE local_graph_id = ?', array(get_request_var('local_graph_id'))))) {
	print "<strong><font class='txtErrorTextBox'>GRAPH DOES NOT EXIST</font></strong>"; 
	exit;
}

/* take graph permissions into account here */
if (!is_graph_allowed($_REQUEST['local_graph_id'])) {
	header('Location: permission_denied.php');
	exit;
}

$graph_title = get_graph_title($_REQUEST['local_graph_id']);

if ($_REQUEST['action'] != 'properties') {
	print "<table width='100%' class='cactiTable'>";
}

$rras = get_associated_rras($_REQUEST['local_graph_id']);

switch ($_REQUEST['action']) {
case 'view':
	api_plugin_hook_function('page_buttons',
		array('lgid' => $_REQUEST['local_graph_id'],
			'leafid' => '',//$leaf_id,
			'mode' => 'mrtg',
			'rraid' => $_REQUEST['rra_id'])
		);
	?>
	<tr class='tableHeader'>
		<td colspan='3' class='textHeaderDark'>
			<strong>Viewing Graph</strong> '<?php print htmlspecialchars($graph_title, ENT_QUOTES);?>'
		<script type="text/javascript" >
		$(function() { 
			$('#navigation').show();
			$('#navigation_right').show();
		});
		</script>
		</td>
	</tr>
	<?php

	$graph = db_fetch_row("SELECT * FROM graph_templates_graph WHERE local_graph_id=" . $_REQUEST['local_graph_id']);

	$i = 0;
	if (sizeof($rras) > 0) {
		$graph_end   = time();
		foreach ($rras as $rra) {
			$graph_start = $graph_end - db_fetch_cell_prepared('SELECT timespan FROM rra WHERE id = ?', array($rra['id']));
			$aggregate_url = aggregate_build_children_url($_REQUEST['local_graph_id'], $graph_start, $graph_end, $rra['id']);
			?>
			<tr>
				<td align='center'>
					<table>
						<tr>
							<td>
								<div id='wrapper_<?php print $graph['local_graph_id'] . '_' . $rra['id'];?>' graph_id='<?php print $graph['local_graph_id'];?>' rra_id='<?php print $rra['id'];?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print ((read_graph_config_option("custom_fonts") == "on") ? read_graph_config_option("title_size") : read_config_option("title_size"));?>' style="min-height: <?php echo (1.2 * $graph["height"]) . "px"?>;"></div>
							</td>
							<td valign='top' style='padding: 3px;' class='noprint'>
								<span class='hyperLink utils' graph_start='<?php print $graph_start;?>' graph_end='<?php print $graph_end;?>' rra_id='<?php print $rra['id'];?>' id='graph_<?php print $_REQUEST['local_graph_id'];?>_util'><img class='drillDown' src='<?php print $config['url_path'];?>images/cog.png' alt='' title='Graph Details, Zooming and Debugging Utilities'></span><br>
								<a href='<?php print htmlspecialchars($config['url_path'] . 'graph_xport.php?local_graph_id=' . $_REQUEST['local_graph_id'] . '&rra_id=' . $rra['id'] . '&view_type=' . $_REQUEST['view_type'] .  '&graph_start=' . $graph_start . '&graph_end=' . $graph_end);?>'><img src='<?php print $config['url_path'] . "images/table_go.png";?>' alt='CSV Export' title='CSV Export'></a><br>
								<?php if (read_config_option('realtime_enabled') == 'on') print "<a href='#' onclick=\"window.open('".$config['url_path']."graph_realtime.php?top=0&left=0&local_graph_id=" . $_REQUEST['local_graph_id'] . "', 'popup_" . $_REQUEST['local_graph_id'] . "', 'toolbar=no,menubar=no,resizable=yes,location=no,scrollbars=no,status=no,titlebar=no,width=650,height=300')\"><img src='" . $config['url_path'] . "images/chart_curve_go.png' alt='Realtime' title='Realtime'></a><br/>\n";?>
								<?php print ($aggregate_url != '' ? $aggregate_url:'')?>
								<?php api_plugin_hook('graph_buttons', array('hook' => 'view', 'local_graph_id' => $_REQUEST['local_graph_id'], 'rra' => $rra['id'], 'view_type' => $_REQUEST['view_type'])); ?>
							</td>
						</tr>
						<tr>
							<td colspan='2' align='center'>
								<strong><?php print htmlspecialchars($rra['name'], ENT_QUOTES);?></strong>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?php
			$i++;
		}
		api_plugin_hook_function('tree_view_page_end');
	}
	?>
	<script type='text/javascript'>
	/* turn off the page refresh */
	refreshMSeconds=9999999;

	function initializeGraph() {
		$('div[id^="wrapper_"]').each(function() {
			graph_id=$(this).attr('graph_id');
			rra_id=$(this).attr('rra_id');
			graph_height=$(this).attr('graph_height');
			graph_width=$(this).attr('graph_width');

			$.getJSON(urlPath+'graph_json.php?'+
				'local_graph_id='+graph_id+
				'&graph_height='+graph_height+
				'&rra_id='+rra_id+
				'&graph_width='+graph_width+
				'&disable_cache=true'+
				<?php print (isset($_REQUEST['thumbnails']) && $_REQUEST['thumbnails'] == 'true' ? "'&graph_nolegend=true'":"''");?>,
				function(data) {
					$('#wrapper_'+data.local_graph_id+'_'+data.rra_id).html("<img class='graphimage' id='graph_"+data.local_graph_id+"' src='data:image/"+data.type+";base64,"+data.image+"' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");
				});
		});

		$('span[id$="_util"]').unbind('click').click(function() {
			graph_id=$(this).attr('id').replace('graph_','').replace('_util','');
			rra_id=$(this).attr('rra_id');
			graph_start=$(this).attr('graph_start');
			graph_end=$(this).attr('graph_end');
			$.get('graph.php?action=zoom&header=false&local_graph_id='+graph_id+'&rra_id='+rra_id+'&graph_start='+graph_start+'&graph_end='+graph_end, function(data) {
				$('#main').html(data);
				$('#breadcrumbs').append('<li><a id="nav_util" href="#">Utility View</a></li>');
				applySkin();
			});
		});
	}

	$(function() { 
		initializeGraph();
		$('#navigation').show();
		$('#navigation_right').show();
	});
	</script>
	<?php

	break;
case 'zoom':
	/* find the maximum time span a graph can show */
	$max_timespan=1;
	if (sizeof($rras) > 0) {
		foreach ($rras as $rra) {
			if ($rra['steps'] * $rra['rows'] * $rra['rrd_step'] > $max_timespan) {
				$max_timespan = $rra['steps'] * $rra['rows'] * $rra['rrd_step'];
			}
		}
	}

	/* fetch information for the current RRA */
	if (isset($_REQUEST['rra_id']) && $_REQUEST['rra_id'] > 0) {
		$rra = db_fetch_row_prepared('SELECT id, timespan, steps, name FROM rra WHERE id = ?', array($_REQUEST['rra_id']));
	}else{
		$rra = db_fetch_row_prepared('SELECT id, timespan, steps, name FROM rra WHERE id = ?', array($rras[0]['id']));
	}

	/* define the time span, which decides which rra to use */
	$timespan = -($rra['timespan']);

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell_prepared('SELECT
		data_template_data.rrd_step
		FROM (data_template_data, data_template_rrd, graph_templates_item)
		WHERE graph_templates_item.task_item_id = data_template_rrd.id
		AND data_template_rrd.local_data_id = data_template_data.local_data_id
		AND graph_templates_item.local_graph_id = ?
		LIMIT 0,1', array(get_request_var('local_graph_id')));
	$ds_step = empty($ds_step) ? 300 : $ds_step;
	$seconds_between_graph_updates = ($ds_step * $rra['steps']);

	$now = time();

	if (isset($_REQUEST['graph_end']) && ($_REQUEST['graph_end'] <= $now - $seconds_between_graph_updates)) {
		$graph_end = $_REQUEST['graph_end'];
	}else{
		$graph_end = $now - $seconds_between_graph_updates;
	}

	if (isset($_REQUEST['graph_start'])) {
		if (($graph_end - $_REQUEST['graph_start'])>$max_timespan) {
			$graph_start = $now - $max_timespan;
		}else {
			$graph_start = $_REQUEST['graph_start'];
		}
	}else{
		$graph_start = $now + $timespan;
	}

	/* required for zoom out function */
	if ($graph_start == $graph_end) {
		$graph_start--;
	}

	$graph = db_fetch_row_prepared('SELECT * FROM graph_templates_graph WHERE local_graph_id = ?', array(get_request_var('local_graph_id')));

	$graph_height = $graph['height'];
	$graph_width  = $graph['width'];

	if (read_graph_config_option('custom_fonts') == 'on' & read_graph_config_option('title_size') != '') {
		$title_font_size = read_graph_config_option('title_size');
	}elseif (read_config_option('title_size') != '') {
		$title_font_size = read_config_option('title_size');
	}else {
	 	$title_font_size = 10;
	}

	?>
	<tr class='tableHeader'>
		<td colspan='3' class='textHeaderDark'>
			<strong>Graph Utility View</strong> '<?php print htmlspecialchars($graph_title);?>'
		</td>
	</tr>
	<tr class='even'>
		<td align='center'>
			<table>
				<tr>
					<td align='center'>
						<div id='wrapper_<?php print $graph['local_graph_id']?>' graph_width='<?php print $graph['width'];?>' graph_height='<?php print $graph['height'];?>' title_font_size='<?php print ((read_graph_config_option("custom_fonts") == "on") ? read_graph_config_option("title_size") : read_config_option("title_size"));?>' style="min-height: <?php echo (1.2 * $graph["height"]) . "px"?>;"></div>
                            <?php print (read_graph_config_option("show_graph_title") == "on" ? "<span align='center'><strong>" . htmlspecialchars($graph["title_cache"]) . "</strong></span>" : "");?>
					</td>
					<td valign='top' style='align:left;padding-top: 3px;' class='noprint'>
						<span id='graph_<?php print $graph['local_graph_id'];?>_properties' class='hyperLink properties'> 
							<img class='drillDown' src='<?php print $config['url_path'] . "images/graph_properties.gif";?>' alt='' title='Graph Source/Properties'>
						</span>
						<br>
						<span id='graph_<?php print $graph['local_graph_id'];?>_csv' class='hyperLink properties'> 
							<img class='drillDown' src='<?php print $config['url_path'] . "images/table_go.png";?>' alt='' title='Graph Data'>
						</span>
						<br>
						<?php api_plugin_hook('graph_buttons', array('hook' => 'zoom', 'local_graph_id' => $_REQUEST['local_graph_id'], 'rra' =>  $_REQUEST['rra_id'], 'view_type' => $_REQUEST['view_type'])); ?>
					</td>
				</tr>
				<tr>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td style='display:none;'>
			<input type='button' name='button_refresh_x' value='Refresh' onClick='refreshGraph()'>
			<input type='textbox' id='date1' value=''>
			<input type='textbox' id='date2' value=''>
			<input type='textbox' id='graph_start' value='<?php print $graph_start;?>'>
			<input type='textbox' id='graph_end' value='<?php print $graph_end;?>'>
		</td>
	</tr>
	<tr class='odd'>
		<td id='data'></td>
	</tr>
	<script type="text/javascript" >
	var graph_id=<?php print $_REQUEST['local_graph_id'] . ";\n";?>
	var props_on=false;
	var graph_data_on=true;

	/* turn off the page refresh */
	refreshMSeconds=9999999;

	function refreshGraph() {
		$('#graph_start').val(getTimestampFromDate($('#date1').val()));
		$('#graph_end').val(getTimestampFromDate($('#date2').val()));
		now = Math.floor($.now()/1000);
		if ($('#graph_end').val() > now) {
			$('#graph_end').val(now);
		}
		
		initializeGraph();
	}

	function graphProperties() {
		$.get('graph.php?action=properties&header=false&local_graph_id='+graph_id+'&rra_id=<?php print $_REQUEST['rra_id'];?>&view_type=<?php print (isset($_REQUEST['view_type']) ? $_REQUEST['view_type']:'');?>&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val(), function(data) {
			$('#data').html(data);
		});
		props_on = true;
		graph_data_on = false;
	}

	function graphXport() {
		$.get(urlPath+'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&format=table&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val(), function(data) {
			$('#data').html(data);

			$('.download').click(function() {
				graph_id=$(this).attr('id').replace('graph_','');
				document.location = urlPath+'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&view_type=tree&graph_start='+$('#graph_start').val()+'&graph_end='+$('#graph_end').val();
			});
		});
		props_on = false;
		graph_data_on = true;
	}

	function initializeGraph() {
		$('div[id^="wrapper_"]').each(function() {
			graph_id=$(this).attr('id').replace('wrapper_','');
			graph_height=$(this).attr('graph_height');
			graph_width=$(this).attr('graph_width');

			$.getJSON(urlPath+'graph_json.php?rra_id=0'+
				'&local_graph_id='+graph_id+
				'&graph_start='+$('#graph_start').val()+
				'&graph_end='+$('#graph_end').val()+
				'&graph_height='+graph_height+
				'&graph_width='+graph_width+
				'&disable_cache=true'+
				<?php print (isset($_REQUEST['thumbnails']) && $_REQUEST['thumbnails'] == 'true' ? "'&graph_nolegend=true'":"''");?>,
				function(data) {
					$('#wrapper_'+data.local_graph_id).html("<img class='graphimage' id='graph_"+data.local_graph_id+"' src='data:image/"+data.type+";base64,"+data.image+"' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");

					$('#graph_start').val(data.graph_start);
					$('#graph_end').val(data.graph_end);

					$("#graph_"+data.local_graph_id).zoom({
						inputfieldStartTime : 'date1',
						inputfieldEndTime : 'date2',
						serverTimeOffset : <?php print date('Z') . "\n";?>
					});

					if (graph_data_on) {
						graphXport();
					}else if (props_on) {
						graphProperties();
					}
				});
		});

		$('span[id$="_properties"]').unbind('click').click(function() {
			graphProperties();
		});

		$('span[id$="_csv"]').unbind('click').click(function() {
			graphXport();
		});
	}

	$(function() { 
		initializeGraph();
		$('#navigation').show();
		$('#navigation_right').show();
	});
	</script>
	<?php

	break;
case 'properties':
	$graph_data_array['print_source'] = true;

	/* override: graph start time (unix time) */
	if (!empty($_REQUEST['graph_start'])) {
		$graph_data_array['graph_start'] = get_request_var('graph_start');
	}

	/* override: graph end time (unix time) */
	if (!empty($_REQUEST['graph_end'])) {
		$graph_data_array['graph_end'] = get_request_var('graph_end');
	}

	print "<table align='center' width='100%' class='cactiTable'<tr><td>\n";
	print "<table class='cactiTable' width='100%'>\n";
	print "<tr class='tableHeader'><td colspan='3' class='linkOverDark' style='font-weight:bold;'>RRDtool Graph Syntax</td></tr>\n";
	print "<tr><td><pre>\n";
	print trim(@rrdtool_function_graph(get_request_var('local_graph_id'), get_request_var('rra_id'), $graph_data_array));
	print "</pre></td></tr>\n";
	print "</table></td></tr></table>\n";

	exit;
	break;
}

print '</table>';

bottom_footer();

