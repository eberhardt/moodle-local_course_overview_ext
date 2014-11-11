<?php

/**
 * Bis wohin soll das log in der Kursübersicht durchgesucht werden?
 *
 * @param int $courseid
 *
 * @see block_recent_activity::get_timestart()
 * @package block_course_overview
 * @author Jan Eberhardt <eberhardt@math.tu-berlin.de>
 */
function course_overview_ext_get_timestart($courseid = SITEID)
{
	global $USER, $DB;

	switch (get_user_preferences("course_overview_timestart_for_log", 0)) {
	case 1:
		$timestart = strtotime("now -1 day");
		break;
	case 2:
		$timestart = strtotime("now -1 week");
		break;
	case 3:
		$timestart = strtotime("now -1 month");
		break;
	case 4:
		$timestart = $USER->lastlogin;
		break;

	default:
		$timestart = round(time() - COURSE_MAX_RECENT_PERIOD, -2);
		if (!isguestuser() && !empty($USER->lastcourseaccess[$courseid])) {
			$timestart = $DB->get_field("logstore_standard_log",
			            	"MAX(timecreated)",
			            	array("userid" => $USER->id,
			            		"contextinstanceid" => (int)$courseid,
			            		"contextlevel" => CONTEXT_COURSE)); //durch Verwendung des Kontextes nutzen wir einen Index aus
			if ($USER->lastcourseaccess[$courseid] > $timestart) {
				$timestart = $USER->lastcourseaccess[$courseid];
			}
		}
	}

	return $timestart;
}

/**
 * Findet die neuesten Aktivitäten / Aktivitätsupdates eines Kurses
 *
 * @param stdClass $course
 * @param array<string> $htmlarray Änderungen werden dort abgelegt
 *
 * @package block_course_overview
 * @see block_recent_activity::get_structural_changes()
 * @author Jan Eberhardt <eberhardt@math.tu-berlin.de>
 */
function course_overview_ext_structural_changes($course, &$htmlarray)
{
	global $USER, $DB;

	$context = context_course::instance($course->id);
	$canviewdeleted = has_capability('block/recent_activity:viewdeletemodule', $context);
	$canviewupdated = has_capability('block/recent_activity:viewaddupdatemodule', $context);
	if (!$canviewdeleted && !$canviewupdated) {
		return;
	}

	$sql = "SELECT
                    cmid, MIN(action) AS minaction, MAX(action) AS maxaction, MAX(modname) AS modname, MAX(timecreated) AS timemodified
                FROM {block_recent_activity}
                WHERE timecreated > :tc AND courseid = :cid
                GROUP BY cmid
                ORDER BY timemodified ASC";
	$params = array("tc" => isis_course_overview_get_timestart($course->id), "cid" => $course->id);
	$logs = $DB->get_records_sql($sql, $params);

	if (isset($logs[0])) {
		// If special record for this course and cmid=0 is present, migrate logs.
		self::migrate_logs($course);
		$logs = $DB->get_records_sql($sql, $params);
	}

	if ($logs) {
		$changelist = array();
		$modinfo = get_fast_modinfo($course);
		$modnames = get_module_types_names();
		foreach ($logs as $log) {
			// We used aggregate functions since constants CM_CREATED, CM_UPDATED and CM_DELETED have ascending order (0,1,2).
			$wasdeleted = ($log->maxaction == block_recent_activity_observer::CM_DELETED);
			$wascreated = ($log->minaction == block_recent_activity_observer::CM_CREATED);


			if ($wasdeleted && $wascreated) {
				// Activity was created and deleted within this interval. Do not show it.
				continue;
			} else if ($wasdeleted && $canviewdeleted) {
				if (plugin_supports('mod', $log->modname, FEATURE_NO_VIEW_LINK, false)) {
					// Better to call cm_info::has_view() because it can be dynamic.
					// But there is no instance of cm_info now.
					continue;
				}
				// Unfortunately we do not know if the mod was visible.
				$info = html_writer::div(get_string("deleted") . ": " . userdate($log->timemodified), "details");
				$changelist[$log->cmid] = array(
							'info' => array(
										"action" => "deletedactivity",
										"infotext" => html_writer::div($info, $log->modname . " overview")),
							'module' => $log->modname
						); // Die Struktur von $changelist wurde gegenüber der Vorlage geändert.

			} else if (!$wasdeleted && isset($modinfo->cms[$log->cmid]) && $canviewupdated) {
				// Module was either added or updated during this interval and it currently exists.
				// If module was both added and updated show only "add" action.
				$cm = $modinfo->cms[$log->cmid];
				$info = html_writer::div(get_string("name") . ": " . html_writer::link($cm->url, $cm->name), "name")
				      . html_writer::div(get_string("modified") . ": " . userdate($log->timemodified), "info");
				if ($cm->has_view() && $cm->uservisible) {
					$changelist[$log->cmid] = array(
							"info" => array(
										"action" => $wascreated ? "added" : "updated",
										"infotext" => html_writer::div($info, $cm->modname . " overview")),
							"module" => $cm->modname
					);
				}
			}
		}

		if (!empty($changelist)) {
			if (!isset($htmlarray[$course->id]))
				$htmlarray[$course->id] = array();
			foreach ($changelist as $change) {
				$module = $change["module"];
				$action = $change["info"]["action"];
				if (isset($htmlarray[$course->id][$module])) {
					$htmlarray[$course->id][$module] = html_writer::div(html_writer::div(get_string($action . "_course_overview_info", "local_isis"), "info"), $module . " overview")
					                                 . $htmlarray[$course->id][$module]; // add/update VOR der "normalen" Nachricht anzeigen
				} else {
					$htmlarray[$course->id]["isis2".$module] = $change["info"];
					//dieser String wird woanders aufgebaut
					//@see block_course_overview_renderer::activity_display
				}
			}
		}
	}
}

/**
 * Startzeit-Selektor für Kursübersicht
 *
 * @param moodle_url $url URL der Weiterleitung, nach dem Einstellen der Option
 * @param string $name Name des Objektes
 * @return single_select
 *
 * @see block_course_overview_renderer::editing_bar_head
 * @package block_course_overview
 * @author Jan Eberhardt <eberhardt@math.tu-berlin.de>
 */
function course_overview_ext_timestart_select(moodle_url $url, $name = "mytimestart")
{
	$modes = array(
				"0" => get_string("lastcourseaccess", "local_isis"),
				"1" => get_string("lastday", "local_isis"),
				"2" => get_string("lastweek", "local_isis"),
				"3" => get_string("lastmonth", "local_isis"),
				"4" => get_string("lastlogin", "local_isis")
			);
	$select = new single_select(new moodle_url("/my/index.php"), $name, $modes, get_user_preferences("course_overview_timestart_for_log", "0"));
	$select->set_label(get_string("co_timestart_label", "local_isis"));

	return $select;
}

?>
