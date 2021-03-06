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

/* inject_form_variables - replaces all variables contained in $form_array with
     their actual values
   @arg $form_array - an array that contains all of the information needed to draw
     the html form. see the arrays contained in include/global_settings.php
     for the extact syntax of this array
   @arg $arg1 - an array that represents the |arg1:| variable (see
     include/global_form.php for more details)
   @arg $arg2 - an array that represents the |arg2:| variable (see
     include/global_form.php for more details)
   @arg $arg3 - an array that represents the |arg3:| variable (see
     include/global_form.php for more details)
   @arg $arg4 - an array that represents the |arg4:| variable (see
     include/global_form.php for more details)
   @returns - $form_array with all available variables substituted with their
     proper values */
function inject_form_variables(&$form_array, $arg1 = array(), $arg2 = array(), $arg3 = array(), $arg4 = array()) {
	$check_fields = array('value', 'array', 'friendly_name', 'description', 'sql', 'sql_print', 'form_id', 'items', 'tree_id');

	/* loop through each available field */
	if (sizeof($form_array)) {
	while (list($field_name, $field_array) = each($form_array)) {
		/* loop through each sub-field that we are going to check for variables */
		foreach ($check_fields as $field_to_check) {
			if (isset($field_array[$field_to_check]) && (is_array($form_array[$field_name][$field_to_check]))) {
				/* if the field/sub-field combination is an array, resolve it recursively */
				$form_array[$field_name][$field_to_check] = inject_form_variables($form_array[$field_name][$field_to_check], $arg1);
			}elseif (isset($field_array[$field_to_check]) && (!is_array($field_array[$field_to_check])) && (preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $field_array[$field_to_check], $matches))) {
				$string = $field_array[$field_to_check];
				while ( 1 ) {
					/* an empty field name in the variable means don't treat this as an array */
					if ($matches[2] == '') {
						if (is_array(${$matches[1]})) {
							/* the existing value is already an array, leave it alone */
							$form_array[$field_name][$field_to_check] = ${$matches[1]};
							break;
						}else{
							/* the existing value is probably a single variable */
							$form_array[$field_name][$field_to_check] = str_replace($matches[0], ${$matches[1]}, $field_array[$field_to_check]);
							break;
						}
					}else{
						/* copy the value down from the array/key specified in the variable */
						$string = str_replace($matches[0], ((isset(${$matches[1]}{$matches[2]})) ? ${$matches[1]}{$matches[2]} : ''), $string);

						$matches = array();
						preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $string, $matches);
						if (!sizeof($matches)) {
							$form_array[$field_name][$field_to_check] = $string;
							break;
						}
					}
				}
			}
		}
	}
	}

	return $form_array;
}

/* form_alternate_row_color - starts an HTML row with an alternating color scheme
   @arg $row_color1 - the first color to use
   @arg $row_color2 - the second color to use
   @arg $row_value - the value of the row which will be used to evaluate which color
     to display for this particular row. must be an integer
   @arg $row_id - used to allow js and ajax actions on this object
   @returns - the background color used for this particular row */
function form_alternate_row_color($row_color1, $row_color2, $row_value, $row_id = '') {
	if (($row_value % 2) == 1) {
			$class='odd';
			$current_color = $row_color1;
	}else{
		if ($row_color2 == '' || $row_color2 == 'E5E5E5') {
			$class = 'even';
		}else{
			$class = 'even-alternate';
		}
		$current_color = $row_color1;
	}

	if (strlen($row_id)) {
		print "<tr class='$class selectable' id='$row_id'>\n";
	}else{
		print "<tr class='$class'>\n";
	}

	return $current_color;
}

/* form_alternate_row - starts an HTML row with an alternating color scheme
   @arg $light - Alternate odd style
   @arg $row_id - The id of the row
   @arg $reset - Reset to top of table */
function form_alternate_row($row_id = '', $light = false, $disabled = false) {
	static $i = 1;

	if (($i % 2) == 1) {
		$class = 'odd';
	}else{
		if ($light) {
			$class = 'even-alternate';
		}else{
			$class = 'even';
		}
	}

	$i++;

	if (strlen($row_id) && substr($row_id,0,4) != 'row_' && !$disabled) {
		print "<tr class='$class selectable' id='$row_id'>\n";
	}elseif (substr($row_id,0,4) == 'row_') {
		print "<tr class='$class' id='$row_id'>\n";
	}else{
		print "<tr class='$class'>\n";
	}
}

/* form_selectable_cell - format's a table row such that it can be highlighted using cacti's js actions
   @arg $contents - the readable portion of the
   @arg $id - the id of the object that will be highlighted
   @arg $width - the width of the table element
   @arg $style - the style to apply to the table element */
function form_selectable_cell($contents, $id, $width='', $style='') {
	print "\t<td " . ($width != '' || $style != "" ? "style='" . ($width != '' ? "width:$width;":"") . ($style != '' ? "$style;'":"'"):"") . ">" . $contents . "</td>\n";
}

/* form_checkbox_cell - format's a tables checkbox form element so that the cacti js actions work on it
   @arg $title - the text that will be displayed if your hover over the checkbox */
function form_checkbox_cell($title, $id, $disabled = false) {
	print "\t<td style='width:1%;'>\n";
	print "\t\t<input type='checkbox' class='checkbox" . ($disabled ? ' disabled':'') . "' " . ($disabled ? "disabled='disabled'":'') . " id='chk_" . $id . "' name='chk_" . $id . "'>\n";
	print "\t</td>\n";
}

/* form_end_row - ends a table row that is started with form_alternate_row */
function form_end_row() {
	print "</tr>\n";
}

/* form_confirm_buttons - provides confirm buttons in the gui
   @arg $message - the value of the HTML checkbox */
function form_confim_buttons($post_variable, $item_array, $save_message, $return = false) {
	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($item_array) ? serialize($item_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $post_variable . "'>" . ($return ? "
			<input type='button' value='Return' onClick='cactiReturnTo()'>
			":"
			<input type='button' value='Cancel' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='Continue' title='$message'>") . "
		</td>
	</tr>\n";
}

/* html_boolean - returns the boolean equivalent of an HTML checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - true or false based on the value of the HTML checkbox */
function html_boolean($html_boolean) {
	if ($html_boolean == "on") {
		return true;
	}else{
		return false;
	}
}

/* html_boolean_friendly - returns the natural language equivalent of an HTML
     checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - 'Selected' or 'Not Selected' based on the value of the HTML
     checkbox */
function html_boolean_friendly($html_boolean) {
	if ($html_boolean == "on") {
		return "Selected";
	}else{
		return "Not Selected";
	}
}

/* get_checkbox_style - finds the proper CSS padding to apply based on the
     current client browser in use
   @returns - a CSS style string which should be used with an HTML checkbox
     control */
function get_checkbox_style() {
	return "";
}

/* set_default_action - sets the required 'action' request variable
   @arg $default - The default action is not set
   @returns - null */
function set_default_action($default = '') {
	global $_CACTI_REQUEST;

	if (!isset($_REQUEST['action'])) { 
		$_REQUEST['action'] = $default;
		$_CACTI_REQUEST['action'] = $default;
	}else{
		$_CACTI_REQUEST['action'] = $_REQUEST['action'];
	}
}

/* unset_request_var - unsets the request variable
   @arg $variable - The variable to unset
   @returns - null */
function unset_request_var($variable) {
	global $_CACTI_REQUEST;

	unset($_CACTI_REQUEST[$variable]);
	unset($_REQUEST[$variable]);
}

/* isset_request_var - checks to see if the $_REQUEST variable
   is set.  Returns true or false.
   @arg $variable - The variable to check
   @returns - true or false */
function isset_request_var($variable) {
	if (isset($_REQUEST[$variable])) {
		return true;
	}else{
		return false;
	}
}

/* isempty_request_var - checks to see if the $_REQUEST variable
   is empty.  Returns true or false.
   @arg $variable - The variable to check
   @returns - true or false */
function isempty_request_var($variable) {
	if (isset_request_var($variable)) {
		$value = $_REQUEST[$variable];

		if (!empty($value)) {
			return false;
		}
	}

	return true;
}

/* set_request_var - sets a given $_REQUEST variable and Cacti global.
   @arg $variable - The variable to set
   @arg $value - The value to set the variable to
   @returns - null */
function set_request_var($variable, $value) {
	global $_CACTI_REQUEST;

	$_CACTI_REQUEST[$variable] = $value;
	$_REQUEST[$variable]       = $value;
}

/* get_request_var - returns the current value of a PHP $_REQUEST variable, optionally
     returning a default value if the request variable does not exist.  When Cacti
     is set to 'developer_mode', it will log all instances where a request variable
     has not first been filtered.
   @arg $name - the name of the request variable. this should be a valid key in the
     $_REQUEST array
   @arg $default - the value to return if the specified name does not exist in the
     $_REQUEST array
   @returns - the value of the request variable */
function get_request_var($name, $default = '') {
	global $_CACTI_REQUEST;

	$developer = read_config_option('developer_mode');

	if (isset($_CACTI_REQUEST[$name])) {
		return $_CACTI_REQUEST[$name];
	}elseif (isset_request_var($name)) {
		if ($developer == 'on') {
			html_log_input_error($name);
		}

		return $_REQUEST[$name];
	} else {
		return $default;
	}
}

/* get_request_var_request - deprecated - alias of get_request_var()
     returning a default value if the request variable does not exist
   @arg $name - the name of the request variable. this should be a valid key in the
     $_GET array
   @arg $default - the value to return if the specified name does not exist in the
     $_GET array
   @returns - the value of the request variable */
function get_request_var_request($name, $default = '') {
	return get_request_var($name, $default);
}

/* get_sanitize_request_var - returns the current value of a PHP $_REQUEST variable and also
     sanitizing the value using the filter. It will also optionally
     return a default value if the request variable does not exist
   @arg $name - the name of the request variable. this should be a valid key in the
     $_REQUEST array
   @arg $default - the value to return if the specified name does not exist in the
     $_REQUEST array
   @returns - the value of the request variable */
function get_sanitize_request_var($name, $filter, $options = array()) {
	if (isset_request_var($name)) {
		if (!sizeof($options)) {
			$value = filter_var($_REQUEST[$name], $filter);
		}else{
			$value = filter_var($_REQUEST[$name], $filter, $options);
		}

		if ($value === FALSE) {
			die_html_input_error();
		}else{
			set_request_var($name, $value);

			return $value;
		}
	}else{
		if (isset($options['default'])) {
			set_request_var($name, $options['default']);

			return $options['default'];
		}else{
			return;
		}
	}
}

/* get_request_var_post - returns the current value of a PHP $_POST variable, optionally
     returning a default value if the request variable does not exist
   @arg $name - the name of the request variable. this should be a valid key in the
     $_POST array
   @arg $default - the value to return if the specified name does not exist in the
     $_POST array
   @returns - the value of the request variable */
function get_request_var_post($name, $default = '') {
	if (isset($_POST[$name])) {
		if (isset($_GET[$name])) {
			unset($_GET[$name]);
			$_REQUEST[$name] = $_POST[$name];
		}

		return $_POST[$name];
	}else{
		return $default;
	}
}

/* validate_store_request_vars - validate, sanitize, and store
   request variables into the custom $_CACTI_REQUEST and desired
   session variables for Cacti filtering.


   @arg $filters - an array keyed with the filter methods.
   @arg $session_prefix - the prefix for the session variable

   Valid filter include those from PHP filter_var() function syntax.  
   The format of the array is:

     array(
       'varA' => array(
          'filter' => value, 
          'pageset' => true,      (optional)
          'session' => sess_name, (optional)
          'options' => mixed, 
          'default' => value),
       'varB' => array(
          'filter' => value, 
          'pageset' => true,      (optional)
          'session' => sess_name, (optional)
          'options' => mixed, 
          'default' => value),
       ...
     );

   The 'pageset' attribute is optional, and when set, any changes 
   between what the page returns and what is set in the session 
   result in the page number being returned to 1.

   The 'session' attribute is also optional, and when set, all
   changes will be stored to the session variable defined and
   not to session_prefix . '_' . $variable as the default.  This
   allows for the concept of global session variables such as
   'sess_default_rows'.

   Validateion 'filter' follow PHP conventions including:

     FILTER_VALIDATE_BOOLEAN - Validate that the variable is boolean
     FILTER_VALIDATE_EMAIL   - Validate that the variable is an email
     FILTER_VALIDATE_FLOAT   - Validate that the variable is a float
     FILTER_VALIDATE_INT     - Validate that the variable is an integer
     FILTER_VALIDATE_IP      - Validate that the variable is an IP address
     FILTER_VALIDATE_MAC     - Validate that the variable is a MAC Address
     FILTER_VALIDATE_REGEXP  - Validate against a REGEX
     FILTER_VALIDATE_URL     - Validate that the variable is a valid URL

   Sanitization 'filters' follow PHP conventions including:

     FILTER_SANITIZE_EMAIL              - Sanitize the email address
     FILTER_SANITIZE_ENCODED            - URL-encode string
     FILTER_SANITIZE_MAGIC_QUOTES       - Apply addslashes()
     FILTER_SANITIZE_NUMBER_FLOAT       - Remove all non float values
     FILTER_SANITIZE_NUMBER_INT         - Remove everything non int
     FILTER_SANITIZE_SPECIAL_CHARS      - Escape special chars
     FILTER_SANITIZE_FULL_SPECIAL_CHARS - Equivalent to htmlspecialchars adding ENT_QUOTES
     FILTER_SANITIZE_STRING             - Strip tags, optionally strip or encode special chars
     FILTER_SANITIZE_URL                - Remove all characters except letters, digits, etc.
     FILTER_UNSAFE_RAW                  - Nothing and optional strip or encode

   @returns - the $_REQUEST variable validated and sanitized. */
function validate_store_request_vars($filters, $sess_prefix) {
	global $_CACTI_REQUEST;

	$changed = 0;

	if (sizeof($filters)) {
		foreach($filters as $variable => $options) {
			// Establish the session variable first
			if (isset($options['session'])) {
				$session_variable = $options['session'];
			}elseif ($variable != 'rows') {
				$session_variable = $sess_prefix . '_' . $variable;
			}else{
				$session_variable = 'sess_default_rows';
			}

			// Check for special cases 'clear' and 'reset'
			if (isset_request_var('clear')) {
				kill_session_var($session_variable);
				unset_request_var($variable);
			}elseif (isset_request_var('reset')) {
				kill_session_var($session_variable);
			}elseif (isset($options['pageset'])) {
				$changed += check_changed($variable, $session_variable);
			}

			if (!isset_request_var($variable)) {
				if (isset($options['default'])) {
					set_request_var($variable, $options['default']);
				}else{
					cacti_log("Filter Variable: $variable, Must have a default and none is set", false);
					set_request_var($variable, '');
				}
			}else{
				if (!isset($options['options'])) {
					$value = filter_var($_REQUEST[$variable], $options['filter']);
				}else{
					$value = filter_var($_REQUEST[$variable], $options['filter'], $options['options']);
				}

				if ($value === FALSE) {
					die('Request variable:' . $variable . ' With value: ' . $_REQUEST[$variable] . ' Failed validation');
				}else{
					set_request_var($variable, $value);
				}
			}

			if (isset_request_var($variable)) {
				$_SESSION[$session_variable] = get_request_var($variable);
			}elseif (isset($_SESSION[$session_variable])) {
				set_request_var($variable, $_SESSION[$session_variable]);
			}
		}
	}

	if ($changed) {
		set_request_var('page', 1);
	}
}

/* load_current_session_value - finds the correct value of a variable that is being
     cached as a session variable on an HTML form
   @arg $request_var_name - the array index name for the request variable
   @arg $session_var_name - the array index name for the session variable
   @arg $default_value - the default value to use if values cannot be obtained using
     the session or request array */
function load_current_session_value($request_var_name, $session_var_name, $default_value) {
	if (isset($_REQUEST[$request_var_name])) {
		$_SESSION[$session_var_name] = $_REQUEST[$request_var_name];
	}elseif (isset($_SESSION[$session_var_name])) {
		$_REQUEST[$request_var_name] = $_SESSION[$session_var_name];
	}else{
		$_REQUEST[$request_var_name] = $default_value;
	}
}

/* get_colored_device_status - given a device's status, return the colored text in HTML
     format suitable for display
   @arg $disabled (bool) - true if the device is disabled, false is it is not
   @arg $status - the status type of the device as defined in global_constants.php
   @returns - a string containing html that represents the device's current status */
function get_colored_device_status($disabled, $status) {
	$status_colors = array(
		HOST_DOWN       => 'deviceDown',
		HOST_ERROR      => 'deviceError',
		HOST_RECOVERING => 'deviceRecovering',
		HOST_UP         => 'deviceUp'
	);

	if ($disabled) {
		return "<span class='deviceDisabled'>Disabled</span>";
	}else{
		switch ($status) {
			case HOST_DOWN:
				return "<span class='deviceDown'>Down</span>"; 
				break;
			case HOST_RECOVERING:
				return "<span class='deviceRecovering'>Recovering</span>";
				break;
			case HOST_UP:
				return "<span class='deviceUp'>Up</span>";
				break;
			case HOST_ERROR:
				return "<span class='deviceError'>Error</span>";
				break;
			default:
				return "<span class='deviceUnknown'>Unknown</span>";
				break;
		}
	}
}

/* get_current_graph_start - determine the correct graph start time selected using
     the timespan selector
   @returns - the number of seconds relative to now where the graph should begin */
function get_current_graph_start() {
	if (isset($_SESSION['sess_current_timespan_begin_now'])) {
		return $_SESSION['sess_current_timespan_begin_now'];
	}else{
		return '-' . DEFAULT_TIMESPAN;
	}
}

/* get_current_graph_end - determine the correct graph end time selected using
     the timespan selector
   @returns - the number of seconds relative to now where the graph should end */
function get_current_graph_end() {
	if (isset($_SESSION['sess_current_timespan_end_now'])) {
		return $_SESSION['sess_current_timespan_end_now'];
	}else{
		return '0';
	}
}

/* get_page_list - generates the html necessary to present the user with a list of pages limited
     in length and number of rows per page
   @arg $current_page - the current page number
   @arg $pages_per_screen - the maximum number of pages allowed on a single screen. odd numbered
     values for this argument are prefered for equality reasons
   @arg $current_page - the current page number
   @arg $total_rows - the total number of available rows
   @arg $url - the url string to prepend to each page click
   @returns - a string containing html that represents the a page list */
function get_page_list($current_page, $pages_per_screen, $rows_per_page, $total_rows, $url, $page_var = 'page', $return_to = '') {
	$url_page_select = '';

	if (strpos($url, '?') !== false) {
		$url . '&';
	}else{
		$url . '?';
	}

	if ($rows_per_page <= 0) {
		$total_pages = 0;
	}else{
		$total_pages = ceil($total_rows / $rows_per_page);
	}

	$start_page = max(1, ($current_page - floor(($pages_per_screen - 1) / 2)));
	$end_page = min($total_pages, ($current_page + floor(($pages_per_screen - 1) / 2)));

	/* adjust if we are close to the beginning of the page list */
	if ($current_page <= ceil(($pages_per_screen) / 2)) {
		$end_page += ($pages_per_screen - $end_page);
	}else{
		$url_page_select .= '...';
	}

	/* adjust if we are close to the end of the page list */
	if (($total_pages - $current_page) < ceil(($pages_per_screen) / 2)) {
		$start_page -= (($pages_per_screen - ($end_page - $start_page)) - 1);
	}

	/* stay within limits */
	$start_page = max(1, $start_page);
	$end_page = min($total_pages, $end_page);

	for ($page_number=0; (($page_number+$start_page) <= $end_page); $page_number++) {
		$page = $page_number + $start_page;
		if ($page_number < $pages_per_screen) {
			if ($current_page == $page) {
				$url_page_select .= "<strong><span class='linkOverDark' style='cursor:pointer;' onClick='goto$page_var($page)'>$page</span></strong>";
			}else{
				$url_page_select .= "<span class='linkOverDark' style='cursor:pointer;' onClick='goto$page_var($page)'>$page</span>";
			}
		}

		if (($page_number+$start_page) < $end_page) {
			$url_page_select .= ',';
		}
	}

	if (($total_pages - $current_page) >= ceil(($pages_per_screen) / 2)) {
		$url_page_select .= '...';
	}

	if ($return_to != '') {
		$url_page_select .= "<script type='text/javascript'>function goto$page_var(pageNo) { if (typeof url_graph === 'function') { var url_add=url_graph('') }else{ var url_add=''; }; $.get('${url}&header=false&$page_var='+pageNo+url_add,function(data) { $('#$return_to').html(data); applySkin(); if (typeof initializeGraphs == 'function') initializeGraphs();}); }</script>";
	}else{
		$url_page_select .= "<script type='text/javascript'>function goto${page_var}(pageNo) { if (typeof url_graph === 'function') { var url_add=url_graph('') }else{ var url_add=''; }; document.location='$url&$page_var='+pageNo+url_add }</script>";
	}

	return $url_page_select;
}

