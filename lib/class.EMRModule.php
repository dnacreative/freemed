<?php
 // $Id$
 // desc: module prototype
 // lic : GPL, v2

LoadObjectDependency('_FreeMED.BaseModule');

// Class: FreeMED.EMRModule
//
//	Electronic Medical Record module superclass. It is descended from
//	<BaseModule>.
//
class EMRModule extends BaseModule {

	// override variables
	var $CATEGORY_NAME = "Electronic Medical Record";
	var $CATEGORY_VERSION = "0.4";

	// Variable: $this->widget_hash
	//
	//	Specifies the format of the <widget> method. This is
	//	formatted by having SQL field names surrounded by '##'s.
	//
	// Example:
	//
	//	$this->widget_hash = '##phylname##, ##phyfname##';
	//	
	var $widget_hash;

	// vars to be passed from child modules
	var $order_fields;
	var $form_vars;
	var $table_name;

	// Variable: disable_patient_box
	//
	//	Whether or not to disable the patient box display at
	//	the top of the screen. Defaults to false.
	//
	var $disable_patient_box = false;

	// Variable: patient_field
	//
	//	Field name that describes the patient. This is used by
	//	FreeMED's module handler to determine whether a record
	//	should be displayed in the EMR summary screen.
	//
	// Example:
	//
	//	$this->patient_field = 'eocpatient';
	//
	var $patient_field; // the field that links to the patient ID

	// Variable: display_format
	//
	//	Hash describing the format which is used to display the
	//	current record by default. It needs to be overridden by
	//	child classes. It uses '##' seperated values to signify
	//	variables.
	//
	// Example:
	//
	//	$this->display_format = '##phylname##, ##phyfname##';
	//
	var $display_format;

	// Variable: summary_conditional
	//
	//	An SQL logical phrase which is used to pare down the
	//	results from a summary view query.
	//
	// Example:
	//
	//	$this->summary_conditional = "ptsex = 'm'";
	var $summary_conditional = '';

	// Variable: summary_order_by
	//
	//	The order in which the EMR summary items are displayed.
	//	This is passed verbatim to an SQL "ORDER BY" clause, so
	//	DESC can be used. Defaults to 'id' if nothing else is
	//	specified.
	//
	// Example:
	//
	//	$this->summary_order_by = 'eocdtlastsimilar, eocstate DESC';
	//
	// See Also:
	//	<summary>
	//
	var $summary_order_by = 'id';

	// contructor method
	function EMRModule () {
		// Check for patient, if so, then set _ref appropriately
		if ($GLOBALS['patient'] > 0) {
			$GLOBALS['_ref'] = "manage.php?id=".urlencode($GLOBALS['patient']);
		}

		// Add meta information for patient_field, if it exists
		if (isset($this->patient_field)) {
			$this->_SetMetaInformation('patient_field', $this->patient_field);
		}
		if (isset($this->table_name)) {
			$this->_SetMetaInformation('table_name', $this->table_name);
		}
		if (!empty($this->widget_hash)) {
			$this->_SetMetaInformation('widget_hash', $this->widget_hash);
		}

		// Call parent constructor
		$this->BaseModule();
	} // end function EMRModule

	// override check_vars method
	function check_vars ($nullvar = "") {
		global $module, $patient;
		if (!isset($module)) {
			trigger_error("No Module Defined", E_ERROR);
		}
		if ($patient < 1) {
			trigger_error( "No Patient Defined", E_ERROR);
		}
		// check access to patient
		if (!freemed::check_access_for_patient($patient)) {
			trigger_error("User not Authorized for this function", E_USER_ERROR);
		}
		return true;

	} // end function check_vars

	// Method: locked
	//
	// 	Determines if record id is locked or not.
	//
	// Parameters:
	//
	//	$id - Record id to be checked
	//
	//	$quiet - (optional) Boolean. If set to true, this value
	//	will cause a denial screen to be displayed. Defaults to
	//	false.
	//
	// Returns:
	//
	//	Boolean, whether the record is locked or not.
	//
	// Example:
	//
	//	if ($this->locked($id)) return false;
	//
	function locked ($id, $quiet = false) {
		global $sql, $display_buffer;
		static $locked;

		// If there is no table_name, we can skip this altogether
		if (empty($this->table_name)) { return false; }

		if (!isset($locked)) {
			$query = "SELECT * FROM ".$this->table_name." WHERE ".
				"id='".addslashes($id)."' AND (locked > 0)";
			$result = $sql->query($query);
			$locked = $sql->results($result);
		}

		if ($locked) {
			if (!$quiet) 
			$display_buffer .= "
			<div ALIGN=\"CENTER\">

			</div>

			<p/>

			<div ALIGN=\"CENTER\">
			".(
				($GLOBALS['return'] == "manage") ?
				"<a href=\"manage.php?id=".urlencode($GLOBALS['patient']).
					"\">".__("Manage Patient")."</a>" :
				"<a href=\"module_loader.php?module=".
					get_class($this)."\">".__("back")."</a>"
			)."
			</div>
			";
			return true;
		} else {
			return false;
		}
	} // end function locked

	// function main
	// - generic main function
	function main ($nullvar = "") {
		global $display_buffer;
		global $action, $patient, $__submit, $return;

		if ($action=='print') {
			$this->disable_patient_box = true;
		}

		// Pull current patient from session if needed
		if (!isset($patient)) {
			$patient = $_SESSION['current_patient'];
		}

		if (!isset($this->this_patient))
			$this->this_patient = CreateObject('FreeMED.Patient', $patient);
		if (!isset($this->this_user))
			$this->this_user    = CreateObject('FreeMED.User');

		// display universal patient box
		if (!$this->disable_patient_box) {
		$display_buffer .= freemed::patient_box($this->this_patient)."<p/>\n";
		}

		// Kludge for older "submit" actions
		if (!isset($__submit)) { $__submit = $GLOBALS['submit']; }

		// Handle cancel action from __submit
		if ($__submit==__("Cancel")) {
			if ($return=="manage") {
			Header("Location: manage.php?".
				"id=".urlencode($patient));
			} else {
			Header("Location: ".$this->page_name.
				"?module=".urlencode($this->MODULE_CLASS).
				"&patient=".urlencode($patient));
			}
			die("");
		}

		switch ($action) {
			case "add":
				if (!freemed::acl_patient('emr', 'add', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->add();
				break;

			case "addform":
				if (!freemed::acl_patient('emr', 'add', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->addform();
				break;

			case "del":
			case "delete":
				if (!freemed::acl_patient('emr', 'delete', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->del();
				break;

			case "lock":
				if (!freemed::acl_patient('emr', 'lock', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->lock();
				break;

			case "mod":
			case "modify":
				if (!freemed::acl_patient('emr', 'modify', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->mod();
				break;

			case "modform":
				if (!freemed::acl_patient('emr', 'modify', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->modform();
				break;

			case "print":
				$this->printaction();
				break;

			case "display";
				if (!freemed::acl_patient('emr', 'view', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->display();
				break;

			case "view":
			default:
				if (!freemed::acl_patient('emr', 'view', $patient)) {
					trigger_error(__("You do not have access to do that."), E_USER_ERROR);
				}
				$this->view();
				break;
		} // end switch action
	} // end function main

	// Method: additional_move
	//
	//	Stub function. Define additional EMR movement functionality
	//	per module. Note that this function does *not* perform the
	//	actual move, but instead moves support files, et cetera.
	//
	// Parameters:
	//
	//	$id - Id of the record in question
	//
	//	$from - Original patient
	//
	//	$to - Destination patient
	//
	function additional_move ($id, $from, $to) { }

	function display_message () {
		global $display_buffer;
		if (isset($this->message)) {
			$display_buffer .= "
			<p/>
			<div ALIGN=\"CENTER\">
			<b>".prepare($this->message)."</b>
			</div>
			";
		}
	} // end function display_message

	// Method: form_table
	//
	//	Builds the table used by the add/mod form methods, and
	//	returns it as an associative array which is passed to
	//	<html_form::form_table>. By default this returns NULL
	//	and needs to be overridden by child classes. It is only
	//	used if the default <form> method is used.
	//
	// Returns:
	//
	//	Associative array describing form.
	//
	// See Also:
	//	<form>
	//
	function form_table () {
		return NULL;
	} // end function form_table

	// ********************** MODULE SPECIFIC ACTIONS *********************

	// function add
	// - addition routine
	function add () { $this->_add(); }
	function _add ($_param = NULL) {
		global $display_buffer;
		foreach ($GLOBALS as $k => $v) global ${$k};

		if (is_array($_param)) {
			foreach ($_param AS $k => $v) {
				global ${$k};
				${$k} = $v;
			}
		}

		$result = $sql->query (
			$sql->insert_query (
				$this->table_name,
				$this->variables
			)
		);

		if ($result) {
			$this->message = __("Record added successfully.");
			if (is_array($_param)) { return true; }
		} else {
			$this->message = __("Record addition failed.");
			if (is_array($_param)) { return false; }
		}
		$this->view(); $this->display_message();

		// Check for return to management screen
		if ($GLOBALS['return'] == 'manage') {
			global $refresh, $patient;
			$refresh = "manage.php?id=".urlencode($patient);
		}
	} // end function _add

	// function del
	// - delete function
	function del () { $this->_del(); }
	function _del ($_id = -1) {
		global $display_buffer;
		global $id, $sql;

		// Pull from parameter, if given
		if ($_id > 0) { $id = $_id; }

		// Check for record locking

		// If there is an override ...
		if (!freemed::lock_override()) {
			if ($this->locked($id)) return false;
		}

		$query = "DELETE FROM $this->table_name ".
			"WHERE id = '".prepare($id)."'";
		$result = $sql->query ($query);
		if ($result) {
			$this->message = __("Record deleted successfully.");
			if ($_id > 0) { return true; }
		} else {
			$this->message = __("Record deletion failed.");
			if ($_id > 0) { return false; }
		}
		$this->view(); $this->display_message();

		// Check for return to management screen
		if ($GLOBALS['return'] == 'manage') {
			global $refresh, $patient;
			$refresh = "manage.php?id=".urlencode($patient);
		}
	} // end function _del

	// function mod
	// - modification function
	function mod () { $this->_mod(); }
	function _mod ($_param = NULL) {
		global $display_buffer;
		foreach ($GLOBALS as $k => $v) global $$k;

		// Check for record locking
		if (!freemed::lock_override()) {
			if ($this->locked($id)) return false;
		}

		if (is_array($_param)) {
			foreach ($_param AS $k => $v) {
				global ${$k};
				${$k} = $v;
			}
		}

		$result = $sql->query (
			$sql->update_query (
				$this->table_name,
				$this->variables,
				array (
					"id" => $id
				)
			)
		);
		if ($result) {
			$this->message = __("Record modified successfully.");
			if (is_array($_param)) { return true; }
		} else {
			$this->message = __("Record modification failed.");
			if (is_array($_param)) { return false; }
		}
		$this->view(); $this->display_message();

		// Check for return to management screen
		if ($GLOBALS['return'] == 'manage') {
			global $refresh, $patient;
			$refresh = "manage.php?id=".urlencode($patient);
		}
	} // end function _mod

	// function add/modform
	// - wrappers for form
	function addform () { $this->form(); }
	function modform () { $this->form(); }

	// function display
	// by default, a wrapper for view
	function display () { $this->view(); }

	// function form
	// - add/mod form stub
	function form () {
		global $display_buffer, $module, $action, $id, $sql, $patient;

		if (is_array($this->form_vars)) {
			reset ($this->form_vars);
			while (list ($k, $v) = each ($this->form_vars)) global ${$v};
		} // end if is array

		// Handle additional hidden variables
		$form_hidden = '';
		if (is_array($this->form_hidden)) {
			foreach ($this->form_hidden AS $k => $v) {
				if ( (($k+0)>0) or empty($k)) {
					$k = $v;
					$v = $_REQUEST[$v];
				}
				// TODO: should handle arrays, etc
				$form_hidden .= "<input type=\"hidden\" ".
					"name=\"".prepare($k)."\" ".
					"value=\"".prepare($v)."\" />\n";
			}
		}

		switch ($action) {
			case "addform":
				break;

			case "modform":
				if ($this->table_name) {
					$r = freemed::get_link_rec($id, $this->table_name);
					foreach ($r as $k => $v) {
						global ${$k};
						${$k} = $v;
					}
				} // end checking for table name

				// Check for record locking
				if ($this->locked($id)) return false;

				break;
		} // end of switch action
		
		$display_buffer .= "
		<div align=\"center\">
		<form action=\"".$this->page_name."\" method=\"post\">
		<input type=\"hidden\" name=\"module\" value=\"".
			prepare($module)."\"/>
		<input type=\"hidden\" name=\"return\" value=\"".
			prepare($GLOBALS['return'])."\"/>
		<input type=\"hidden\" name=\"action\" value=\"".
			( $action=="addform" ? "add" : "mod" )."\"/>
		<input type=\"hidden\" name=\"patient\" value=\"".
			prepare($patient)."\"/>
		<input type=\"hidden\" name=\"id\" value=\"".
			prepare($id)."\"/>
		".$form_hidden."
		".html_form::form_table($this->form_table())."
		<p/>
		<input type=\"submit\" name=\"__submit\" value=\"".
			 ( ($action=="addform") ? __("Add") : __("Modify") )."\" ".
			 "class=\"button\" />
		<input type=\"submit\" name=\"__submit\" value=\"".
			__("Cancel")."\" class=\"button\" />
		</form>
		</div>
		";
	} // end function form

	// function lock
	// - locking function
	function lock () { $this->_lock(); }
	function _lock () {
		global $display_buffer;
		foreach ($GLOBALS as $k => $v) global ${$k};

		// Check for record locking
		if ($this->locked($id)) return false;

		$result = $sql->query (
			$sql->update_query (
				$this->table_name,
				array (
					"locked" => $_SESSION['authdata']['user']
				),
				array (
					"id" => $id
				)
			)
		);
		if ($result) $this->message = __("Record locked successfully.");
			else $this->message = __("Record locking failed.");
		$this->view(); $this->display_message();

		// Check for return to management screen
		if ($GLOBALS['return'] == 'manage') {
			global $refresh, $patient;
			$refresh = "manage.php?id=".urlencode($patient);
		}
	} // end function _mod

	// function summary
	// - show summary view of last few items
	function summary ($patient, $items) {
		global $sql, $display_buffer, $patient;

		// get last $items results
		$query = "SELECT *".
			( (count($this->summary_query)>0) ? 
			",".join(",", $this->summary_query)." " : " " ).
			"FROM ".$this->table_name." ".
			"WHERE ".$this->patient_field."='".addslashes($patient)."' ".
			($this->summary_conditional ? 'AND '.$this->summary_conditional.' ' : '' ).
			"ORDER BY ".$this->summary_order_by." DESC LIMIT ".addslashes($items);
		$result = $sql->query($query);

		// Check to see if there *are* any...
		if ($sql->num_rows($result) < 1) {
			// If not, let the world know
			$buffer .= "<b>".__("NONE")."</b>\n";
		} else { // checking for results
			// Or loop and display
			$buffer .= "
			<table WIDTH=\"100%\" CELLSPACING=\"0\"
			 CELLPADDING=\"2\" BORDER=\"0\">
			<TR>
			";

			foreach ($this->summary_vars AS $k => $v) {
				$buffer .= "
				<td VALIGN=\"MIDDLE\" CLASS=\"menubar_info\">
				<b>".prepare($k)."</b>
				</td>
				";
			} // end foreach summary_vars
			$buffer .= "
				<td VALIGN=\"MIDDLE\" CLASS=\"menubar_info\">
				<b>".__("Action")."</b>
				</td>
			</tr>
			";
			while ($r = $sql->fetch_array($result)) {
				// Pull out all variables
				extract ($r);

				// Check for annotations
				if ($_anno = module_function('Annotations', 'getAnnotations', array (get_class($this), $id))) {
					$use_anno = true;
					$_anno = module_function('Annotations', 'outputAnnotations', array ($_anno));
				} else {
					$use_anno = false;
				}

				// Use $this->summary_vars
				$buffer .= "
				<tr VALIGN=\"MIDDLE\">
				";
				$first = true;
				foreach ($this->summary_vars AS $k => $v) {
					if (!(strpos($v, ":")===false)) {
						// Split it up
						list ($p1, $p2, $p3) = explode(":", $v);
					
						switch ($p2) {
						case "phy":
						case "physician":
						$p = CreateObject('FreeMED.Physician', ${$p1});
						${$v} = $p->fullName();
						break;

						default:
						// use fields ...
						${$v} = freemed::get_link_field(${$p1}, $p2, $p3);
						break;
						}
					}
					$buffer .= "
					<td VALIGN=\"MIDDLE\">
					<small>".
					( ($use_anno and $first) ?
						"<span style=\"text-decoration: underline;\" ".
						"onMouseOver=\"tooltip('".str_replace("\n", '<br/>\n', addslashes($_anno))."');\" ".
						"onMouseOut=\"hidetooltip();\">" : "" ).
					prepare(${$v}).
					( ($use_anno and $first) ? "</span>" : "" ).
					"</small>
					</td>
					";
					$first = false;
				} // end looping through summary vars
				$buffer .= "
				<td VALIGN=\"MIDDLE\">
				".( ((!$r['locked'] > 0) or freemed::lock_override()) ?
				template::summary_modify_link($this,
				"module_loader.php?module=".
				get_class($this)."&patient=$patient&".
				"action=modform&id=".$r['id']."&return=manage") : "" ).
				// Delete option
				( (((!$r['locked'] > 0) or freemed::lock_override()) and ($this->summary_options & SUMMARY_DELETE)) ?
				template::summary_delete_link($this,
				"module_loader.php?module=".
				get_class($this)."&patient=$patient&".
				"action=del&id=".$r['id']."&return=manage") : "" ).
				"\n".( ($this->summary_options & SUMMARY_VIEW) ?
				template::summary_view_link($this,
				"module_loader.php?module=".
				get_class($this)."&patient=$patient&".
				"action=display&id=".$r['id']."&return=manage",
				($this->summary_options & SUMMARY_VIEW_NEWWINDOW)) : "" ).

				// "Lock" link for quick locking from the menu
				
				"\n".( (($this->summary_options & SUMMARY_LOCK) and
				!($r['locked'] > 0)) ?
				template::summary_lock_link($this,
				"module_loader.php?module=".
				get_class($this)."&patient=$patient&".
				"action=lock&id=".$r['id']."&return=manage") : "" ).

				// Process a "locked" link, which does nothing other
				// than display that the record is locked
				
				"\n".( (($this->summary_options & SUMMARY_LOCK) and
				($r['locked'] > 0)) ?
				template::summary_locked_link($this) : "" ).

				// Printing stuff
				"\n".( ($this->summary_options & SUMMARY_PRINT) ?
				template::summary_print_link($this,
				"module_loader.php?module=".
				get_class($this)."&patient=$patient&".
				"action=print&id=".$r['id']) : "" ).

				// Annotations
				( !($this->summary_options & SUMMARY_NOANNOTATE) ?
				template::summary_annotate_link($this,
				"module_loader.php?module=annotations&".
				"atable=".$this->table_name."&".
				"amodule=".get_class($this)."&".
				"patient=$patient&action=addform&".
				"aid=".$r['id']."&return=manage") : "" ).
				"</td>
				</tr>
				";
			} // end of loop and display
			$buffer .= "</table>\n";
		} // checking if there are any results

		// Send back the buffer
		return $buffer;
	} // end function summary

	// Method: summary_bar
	//
	//	Produces the text for the EMR summary bar menu. By
	//	default it produces View/Manage and Add links. Override
	//	this function to change the basic EMR summary bar menu.
	//
	// Parameters:
	//
	//	$patient - Id of current patient
	//
	// Returns:
	//
	//	XHTML formatted text links.
	//
	function summary_bar ($patient) {
		return "
		<a HREF=\"module_loader.php?module=".
		get_class($this)."&patient=".urlencode($patient).
		"&return=manage\">".__("View/Manage")."</a> |
		<a HREF=\"module_loader.php?module=".
		get_class($this)."&patient=".urlencode($patient).
		"&action=addform&return=manage\">".__("Add")."</a>
		";
	} // end function summary_bar

	// function view
	// - view stub
	function view () {
		global $display_buffer;
		global $sql;
		$result = $sql->query ("SELECT ".$this->order_fields." FROM ".
			$this->table_name." ORDER BY ".$this->order_fields);
		$display_buffer .= freemed_display_itemlist (
			$result,
			"module_loader.php",
			$this->form_vars,
			array ("", __("NO DESCRIPTION")),
			"",
			"t_page"
		);
	} // end function view

	// override _setup with create_table
	function _setup () {
		if (!$this->create_table()) return false;
		return freemed_import_stock_data ($this->table_name);
	} // end function _setup

	// function create_table
	// - used to initally create SQL table
	function create_table () {
		global $sql;

		if (!isset($this->table_definition)) return false;
		$query = $sql->create_table_query(
			$this->table_name,
			$this->table_definition,
			( is_array($this->table_keys) ?
				array_merge(array("id"), $this->table_keys) :
				array("id")
			)
		);
		$result = $sql->query($query);
		return !empty($query);
	} // end function create_table

	// this function exports XML for the entire patient record
	function xml_export () {
		global $display_buffer;
		global $patient;

		if (!isset($this->this_patient))
			$this->this_patient = CreateObject('FreeMED.Patient', $patient);

		return $this->xml_generate($this->this_patient);
	} // end function EMRModule->xml_export

	function xml_generate ($patient) { return ""; } // stub 

	// ----- XML-RPC Functions -----------------------------------------
	function picklist ($patient) {
		global $sql;

		// Check for access violation from user
		$user_id = $GLOBALS['__freemed']['basic_auth_id'];

		if (!freemed::check_for_access($patient, $user_id)) {
			// TODO: Set to return XML-RPC error
			return false;
		}

		$result = $sql->query(
			"SELECT * FROM ".$this->table_name." ".
			"WHERE ".$this->patient_field."='".
				addslashes($patient)."' ".
			"ORDER BY ".$this->order_fields
		);

		while ($r = $sql->fetch_array($result)) {
			if (!(strpos($this->display_format, '##') === false)) {
				$displayed = '';
				$split = explode('##', $this->display_format);
				foreach ($split as $_k => $_v) {
					if (!($_k & 1)) {
						$displayed .= $_v;
					} else {
						$displayed .= prepare($r[$_v]);
					}
				}
			} else {
				// Assume single field if no '##'s
				$displayed = stripslashes($r[$this->display_format]);
			}

			// Add to the hash
			$results["$displayed"] = $r['id'];
		}
		
		return $results;
	} // end method EMRModule->picklist

	// Method: widget
	//
	//	Generic widget code to allow a picklist-based widget for
	//	simple modules. Should be overridden for more complex tasks.
	//
	//	This function uses $this->widget_hash, which contains field
	//	names surrounded by '##'s.
	//
	// Parameters:
	//
	//	$varname - Name of the variable that the widget's data is
	//	passed in.
	//
	//	$patient - Id of patient this deals with.
	//
	//	$conditions - (optional) Additional clauses for SQL WHERE.
	//	defaults to none.
	//
	// Returns:
	//
	//	XHTML-compliant picklist widget.
	//
	function widget ( $varname, $patient, $conditions = false ) {
		$query = "SELECT * FROM ".$this->table_name." WHERE ".
			"( ".$this->patient_field.
				" = '".addslashes($patient)."') ".
			( $conditions ? "AND ( ".$conditions." ) " : "" ).
			( $this->order_field ? "ORDER BY ".$this->order_field : "" );
		$result = $GLOBALS['sql']->query($query);
		$return[__("NONE SELECTED")] = "";
		while ($r = $GLOBALS['sql']->fetch_array($result)) {
			if (!(strpos($this->widget_hash, "##") === false)) {
				$key = '';
				$hash_split = explode('##', $this->widget_hash);
				foreach ($hash_split AS $_k => $_v) {
					if (!($_k & 1)) {
						$key .= prepare($_v);
					} else {
						$key .= prepare($r[$_v]);
					}
				}
			} else {
				$key = $this->widget_hash;
			}
			$return[$key] = $r['id'];
		}
		return html_form::select_widget($varname, $return);
	} // end method widget

	//------ Internal Printing ----------------------------------------

	// Method: _RenderTeX
	//
	//	Internal TeX renderer for the record. By default this
	//	uses the <print_format> class variable to determine the
	//	proper format. If another format is to be used, override
	//	this class.
	//
	// Parameters:
	//
	//	$TeX - <FreeMED.TeX> object reference. Must be prefixed by
	//	an amphersand, otherwise changes will be lost!
	//
	//	$id - Record id to be printed
	//
	// Example:
	//
	//	$this->_RenderTeX ( &$TeX, $id );
	//
	// See Also:
	//	<_RenderField>
	//
	function _RenderTeX ( $TeX, $id ) {
		if (is_array($id)) {
			foreach ($id AS $k => $v) {
				$buffer .= _RenderTeX ( &$TeX, $v );
			}
			return $buffer;
		} else {
			if (!$id) return false;

			// Handle templating elsewhere
			if (isset($this->print_template)) {
				return $TeX->RenderFromTemplate(
					$this->print_template,
					$this->_print_mapping($TeX, $id)
				);
			}

			// Get record from ID
			$r = freemed::get_link_rec($id, $this->table_name);

			// Loop through parts
			foreach ($this->print_format AS $garbage => $f) {
				$print = true;
				if (isset($f['condition'])) {
					switch ($f['condition']) {
						case 'isset':
						if (!$r[$f['trigger']]) {
							$print = false;
						}
						break;
					}
				}

				if ($print) {
					if (!(strpos($f['content'], '##') === false)) {
						$content = $f['content'];
					} else {
						$content = $r[$f['content']];
					}
					switch ($f['type']) {
						case 'short':
						$TeX->AddShortItems(array(
							$f['title'] => $this->_RenderField($content)
						));
						break;
					
						case 'long':
						$TeX->AddLongItems(array(
							$f['title'] => $this->_RenderField($content)
						));
						break;
					} // end switch by type
				} // end if print
			}
		}
	} // end method _RenderTeX

	// Method: _RenderField
	//
	//	Render out ##a:b@c## or ##a## type fields. "a" stands for
	//	the record, "b" stands for the target field, and "c" stands
	//	for the target table.
	//
	// Parameters:
	//
	//	$arg - Formatted field
	//
	//	$r - Associative array containing record
	//
	// Returns:
	//
	//	Rendered field
	//
	// Example:
	//
	//	$return = $this->_RenderField ( '##eocpatient:ptlname@patient##' );
	//
	function _RenderField ( $arg, $r = NULL ) {
		if (!(strpos($arg, '##') === false)) {
			// We need to deal with the content
			$displayed = '';
			$f_split = explode ('##', $arg);
			foreach ($f_split AS $f_k => $f_v) {
				if (!($f_k & 1)) {
					// Outside of '##'s
					$displayed .= $f_v;
				} else {
					// Inside ... process
					if (!(strpos($f_v, ':') === false)) {
						// Process as ##a:b@c##
						list ($a, $_b) = explode (':', $f_v);
						list ($b, $c) = explode ('@', $_b);
						$f_q = $GLOBALS['sql']->query(
							'SELECT '.$c.'.'.$b.' AS result '.
							'FROM '.$c.', '.$this->table_name.' '.
							'WHERE '.$this->table_name.'.'.$a.' = '.$c.'.id AND '.
							$this->table_name.'.id = '.$_REQUEST['id']
						);
						$f_r = $GLOBALS['sql']->fetch_array($f_q);
						$displayed .= $f_r['result'];
					} else {
						// Simple field replacement
						$displayed .= $r[$f_v];
					}
				}
			}
			return $displayed;
		} else {
			// No processing required. Return as is.
			return $arg;
		}
	} // end method _RenderField

	// Method: _TeX_Information
	//
	//	Callback to provide information to the TeX renderer about
	//	formatting.
	//
	// Returns:
	//
	//	Array ( title, heading, physician )
	//
	function _TeX_Information ( ) {
		// abstract
		$rec = freemed::get_link_rec($_REQUEST['id'], $this->table_name);
		$patient = CreateObject('FreeMED.Patient', $_REQUEST['patient']);
		$user = CreateObject('FreeMED.User');
		if ($user->isPhysician()) {
			$phy = $user->getPhysician();
		} else {
			$phy = $patient->local_record['patphy'];
		}
		$physician_object = CreateObject('FreeMED.Physician', $phy);
		$title = __($this->record_name);
		$heading = $patient->fullName().' ('.$patient->local_record['ptid'].')';
		$physician = $physician_object->fullName();
		return array ($title, $heading, $physician);
		return array ($title, $heading, $physician);
	} // end method _TeX_Information

} // end class EMRModule

?>
