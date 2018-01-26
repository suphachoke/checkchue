<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints a particular instance of checkchue
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_checkchue
 * @copyright  2016 Your Name <your@email.address>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace checkchue with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... checkchue instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('checkchue', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $checkchue  = $DB->get_record('checkchue', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $checkchue  = $DB->get_record('checkchue', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $checkchue->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('checkchue', $checkchue->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

$event = \mod_checkchue\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $checkchue);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/checkchue/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($checkchue->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('checkchue-'.$somevar);
 */

// Output starts here.
echo $OUTPUT->header();

// Conditions to show the intro can change to look for own settings or whatever.
if ($checkchue->intro) {
    echo $OUTPUT->box(format_module_intro('checkchue', $checkchue, $cm->id), 'generalbox mod_introbox', 'checkchueintro');
}

// Replace the following lines with you own code.
echo "<style type='text/css'>".
		"table{border-top:1px solid gray;border-bottom:1px solid gray;}".
		"table tr th{border-bottom:1px solid gray;}".
		"table tr td,table tr th{padding:10px;}".
	"</style>";

$rw1 = $DB->get_record('context',array('contextlevel'=>'50','instanceid'=>$course->id),"*",MUST_EXIST);
$rw2 = $DB->get_record('role_assignments',array('contextid'=>$rw1->id,'userid'=>$USER->id),"*",MUST_EXIST);
//print_r($rw1);
//echo"<br/>";
//print_r($rw2);

if((isset($_REQUEST['act'])&&$_REQUEST['act']=="report")||$rw2->roleid>4){
	$editbtn = ($rw2->roleid<=4)?'&nbsp;<a href=\'javascript:window.location.href="?id='.$_REQUEST['id'].'";\'><img src=\'pix/edit.png\'/></a>':'';
	echo $OUTPUT->heading('รายงานการเข้าชั้นเรียน'.$editbtn);
	
	$rw0 = $DB->get_records_sql('select distinct date from {checkchue_assign} where courseid=? and activityid=? '.$gp.' order by date',array($course->id,$checkchue->id));
	$tmpdt = "";
	$cc = 0;
	foreach($rw0 as $dd){
		$tmpdt .= "<th>".$dd->date."</th>";
		$cc++;
	}
	
	$stdrow = "";
	$sc = 1;
	$rw3 = ($rw2->roleid<=4)?$DB->get_records('role_assignments',array('contextid'=>$rw1->id,'roleid'=>'5')):
		$DB->get_records('role_assignments',array('contextid'=>$rw1->id,'roleid'=>'5','userid'=>$rw2->userid));
	$rwchk = $DB->get_records('groupings_groups',array('groupingid'=>$checkchue->grouping));
	foreach($rw3 as $r){
		$chk = false;
		foreach($rwchk as $rc){
			$chk = ($DB->record_exists('groups_members',array('groupid'=>$rc->groupid,'userid'=>$r->userid)))?true:$chk;
		}
		if(empty($checkchue->grouping))$chk=true;
		if(!$chk)continue;

		$ddcol = "";
		$rw4 = $DB->get_record('user',array('id'=>$r->userid),"*",MUST_EXIST);
		foreach($rw0 as $dd){
			$rw5 = $DB->get_record('checkchue_assign',array('courseid'=>$course->id,'activityid'=>$checkchue->id,'userid'=>$r->userid,'date'=>$dd->date),"*",IF_EXIST);
			$rmk = ($rw5->remark<>"")?" (".$rw5->remark.")":"";
			$tmm = ($rw5->time<>"")?$rw5->time:"<font color='red'>ขาด</font>";
			$ddcol .= "<td>".$tmm.$rmk."</td>";
		}
		$pix = "<img src='../../user/pix.php/".$rw4->id."/f1.jpg' style='width:70px;'/>";
		$stdrow .= "<tr><td>".$sc."</td><td>".$rw4->username."</td><td>".$rw4->firstname." ".$rw4->lastname."</td><td>".$pix."</td>".$ddcol."</tr>";
		$sc++;
	}

	echo "<table><tr><th rowspan='2'>ที่</th><th rowspan='2'>รหัสนักศึกษา</th><th rowspan='2'>ชื่อ - สกุล</th><th rowspan='2'>รูป</th><th colspan='".$cc."'>วันที่</th></tr><tr>".$tmpdt."</tr>";
	echo $stdrow;
	echo "</table>";

}else{

	if(!empty($_POST)){
		//print_r($_POST);
		foreach($_POST['userid'] as $uid){
			if($DB->record_exists('checkchue_assign',array('courseid'=>$course->id,'activityid'=>$checkchue->id,'userid'=>$uid,'date'=>$_POST['date']))){
				$DB->execute("update {checkchue_assign} set time=?,remark=? where courseid=? and activityid=? and userid=? and date=?",array($_POST[$uid.'_time'],$_POST[$uid.'_remark'],$_POST['courseid'],$_POST['activityid'],$uid,$_POST['date']));
			}else{
				$rec = new stdClass();
				$rec->courseid = $_POST['courseid'];
				$rec->activityid = $_POST['activityid'];
				$rec->userid = $uid;
				$rec->date = $_POST['date'];
				$rec->time = $_POST[$uid.'_time'];
				$rec->remark = $_POST[$uid.'_remark'];
				$DB->insert_record('checkchue_assign',$rec,false);
			}
		}
	}

	$cdate = date("Y-m-d",mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y")));
	$rw0 = $DB->get_records_sql('select distinct date from {checkchue_assign} where courseid=? and activityid=?',array($course->id,$checkchue->id));
	//print_r($rw0);
	$tmpdt = "";
	foreach($rw0 as $dd){
		if($dd->date<>$cdate){
			$tmpdtsel = (isset($_REQUEST['date'])&&$_REQUEST['date']==$dd->date)?"selected":"";
			$tmpdt .= "<option value='".$dd->date."' ".$tmpdtsel.">".$dd->date."</option>";
		}
	}
	$seldate = "<select name='date' id='checkchuedate'><option value='".$cdate."'>".$cdate."</option>".$tmpdt."</select>";
	$cdate = (isset($_REQUEST['date']))?$_REQUEST['date']:$cdate;
	echo "<form action='' method='post'>".
		"<input type='hidden' name='courseid' value='".$course->id."'/>".
		"<input type='hidden' name='activityid' value='".$checkchue->id."'/>".
		"<input type='hidden' name='date' value='".$cdate."'/>";
	echo $OUTPUT->heading('การเข้าชั้นเรียนประจำวันที่ '.$seldate.'&nbsp;<a href=\'javascript:window.location.href="?id='.$_REQUEST['id'].'&act=report";\'><img src=\'pix/report.png\'/></a>');


	//If a teacher
	if($rw2->roleid<=4){
		$rw3 = $DB->get_records('role_assignments',array('contextid'=>$rw1->id,'roleid'=>'5'));
		//echo"<br/>";
		//print_r($rw3);
		echo"<table><tr><th>ที่</th><th>รหัสนักศึกษา</th><th>ชื่อ - สกุล</th><th>รูป</th><th>เข้าเรียน</th><th>เวลา</th><th>หมายเหตุ</th></tr>";
		$i = 1;
		
		$rwchk = $DB->get_records('groupings_groups',array('groupingid'=>$checkchue->grouping));
		foreach($rw3 as $r){
			$chk = false;
			foreach($rwchk as $rc){
				$chk = ($DB->record_exists('groups_members',array('groupid'=>$rc->groupid,'userid'=>$r->userid)))?true:$chk;
			}
			if(empty($checkchue->grouping))$chk=true;
			if(!$chk)continue;

			$rw4 = $DB->get_record('user',array('id'=>$r->userid),"*",MUST_EXIST);
			$rw5 = $DB->get_record('checkchue_assign',array('courseid'=>$course->id,'activityid'=>$checkchue->id,'userid'=>$r->userid,'date'=>$cdate),"*",IF_EXIST);
			$ifchk = (!empty($rw5->time))?"checked":"";
			//echo"<br/>";
			//print_r($rw4);
			$pix = "<img src='../../user/pix.php/".$rw4->id."/f1.jpg' style='width:70px;'/>";
			echo "<tr><td>".$i."<input type='hidden' name='userid[]' value='".$rw4->id."'/></td><td>".$rw4->username."</td><td>".$rw4->firstname." ".$rw4->lastname."</td><td>".$pix."</td><td><input type='checkbox' class='stdcheck' id='".$rw4->id."_check' name='".$rw4->id."_check' value='true' ".$ifchk."/></td><td><input size='10' type='text' name='".$rw4->id."_time' value='".$rw5->time."'/></td><td><textarea name='".$rw4->id."_remark'>".$rw5->remark."</textarea></td></tr>";
			$i++;
		}
		echo"</table>";
	}

	echo "<br/><button type='button' id='savebtn'>บันทึก</button></form>";
}

echo "<script type='text/javascript' src='../../lib/jquery/jquery-3.1.0.min.js'></script>";
echo "<script type='text/javascript'>". 
		"\$(\".stdcheck\").click(function(){var dt = new Date();var hh = dt.getHours();var mm = dt.getMinutes();if(hh<10){hh='0'+hh;} if(mm<10){mm='0'+mm;}var tm = hh+'.'+mm; if(\$(this).prop('checked')==true){\$(this).parent().parent().find(\"input[name*='_time']\").val(tm);}else{\$(this).parent().parent().find(\"input[name*='_time']\").val('');}});".
		"\$(\"#savebtn\").click(function(){\$(this).parent().submit();});".
		"\$('#checkchuedate').change(function(){window.location.href='?id=".$_REQUEST['id']."&date='+\$(this).val();});".
		"</script>";

// Finish the page.
echo $OUTPUT->footer();
