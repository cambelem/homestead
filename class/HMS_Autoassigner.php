<?php

/**
 * Auto Assigner
 * @author Jeff Tickle <jtickle at tux dot appstate dot edu>
 */

class HMS_Autoassigner
{
    function auto_assign($test = 0)
    {
        PHPWS_Core::initModClass('hms', 'HMS_Room.php');
        PHPWS_Core::initModClass('hms', 'HMS_Application.php');
        PHPWS_Core::initModClass('hms', 'HMS_Roommate.php');
        PHPWS_Core::initModClass('hms', 'HMS_Term.php');
        PHPWS_Core::initModClass('hms', 'HMS_Assignment.php');
        PHPWS_Core::initModClass('hms', 'HMS_SOAP.php');
        PHPWS_Core::initModClass('hms', 'HMS_Activity_Log.php');
        PHPWS_Core::initModClass('hms', 'HMS_RLC_Assignment.php');

        $term = HMS_Term::get_selected_term();

        // In both cases: Random, and include Banner info
        $f_rooms = HMS_Room::get_all_free_rooms($term, FEMALE, TRUE);
        $m_rooms = HMS_Room::get_all_free_rooms($term, MALE,   TRUE);
        $roommates = HMS_Roommate::get_all_confirmed_roommates($term, TRUE);
        $applicants = HMS_Application::get_all_applicants(
            'gender', 'student_status', 'lifestyle_option',
            'preferred_bedtime', 'room_condition', 'random');

        $problems = array();
        $rlcs = array();
        $assigns = array();
        $notices = array();
        $successes = array();
        $assigned = array();

        reset($f_rooms);
        reset($m_rooms);

        $i_f_count = count($f_rooms);
        $i_m_count = count($m_rooms);

        // TODO: IMMEDIATELY Add check for RLCs
        // TODO: IMMEDIATELY clean up the output

        // Assign Roommates
        reset($roommates);
        foreach($roommates as $pair) {
            $a = new HMS_Application($pair['requestor'], $term);

            if(in_array($a->hms_student_id, $assigned)) {
                $notices[] = "<strong>{$a->hms_student_id}</strong> already scheduled for assignment.";
                continue;
            }

            $rlc = HMS_RLC_Assignment::check_for_assignment($a->hms_student_id, $term);
            if($rlc !== FALSE) {
                $rlcs[] = "Skipping <strong>{$a->hms_student_id}</strong>; assigned to an RLC.";
                continue;
            }
            
            $b = new HMS_Application($pair['requestee'], $term);

            if(in_array($b->hms_student_id, $assigned)) {
                $notices[] = "<strong>{$b->hms_student_id}</strong> already scheduled for assignment.";
                continue;
            }

            $rlc = HMS_RLC_Assignment::check_for_assignment($b->hms_student_id, $term);
            if($rlc !== FALSE) {
                $rlcs[] = "Skipping <strong>{$b->hms_student_id}</strong>; assigned to an RLC.";
                continue;
            }

            if(is_null($a->id)) {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong> with roommate <strong>{$b->hms_student_id}</strong>; {$a->hms_student_id} does not have an application.";
                continue;
            }

            if(is_null($b->id)) {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong> with roommate <strong>{$b->hms_student_id}</strong>; {$b->hms_student_id} does not have an application.";
                continue;
            }

            if($a->gender != $b->gender) {
                $problems[] = "Epic FAIL... <strong>{$a->hms_student_id}</strong> and <strong>{$b->hms_student_id}</strong> are not the same gender.";
                continue;
            }

            $ass = HMS_Assignment::get_assignment($a->hms_student_id, $term);
            if(is_a($ass,'HMS_Assignment')) {
                $bbc = $ass->get_banner_building_code();
                $bed = $ass->get_banner_bed_id();
                $assigns[] = "Could not assign <strong>{$a->hms_student_id}</strong>; already assigned to <strong>$bbc $bed</strong>";
                continue;
            }

            $ass = HMS_Assignment::get_assignment($b->hms_student_id, $term);
            if(is_a($ass,'HMS_Assignment')) {
                $bbc = $ass->get_banner_building_code();
                $bed = $ass->get_banner_bed_id();
                $assigns[] = "Could not assign <strong>{$b->hms_student_id}</strong>; already assigned to <strong>$bbc $bed</strong>";
                continue;
            }

            $room = ($a->gender == FEMALE ? array_shift($f_rooms) :
                   ($a->gender == MALE   ? array_shift($m_rooms) :
                   'badgender'));

            if(is_null($room)) {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong>; out of empty ".($a->gender?'male':'female').' rooms.';
                continue;
            } else if($room === 'badgender') {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong>; {$a->gender} is not a valid gender.";
                continue;
            }

            // Prepare for assignment
            $room = &new HMS_Room($room);
            $room->loadBeds();

            $bed_a_text = $room->_beds[0]->get_banner_building_code() . ' ' . $room->_beds[0]->banner_id;
            $bed_b_text = $room->_beds[1]->get_banner_building_code() . ' ' . $room->_beds[1]->banner_id;

            $successes[] = HMS_Autoassigner::record_success('Requested', $a, $b, $bed_a_text);
            $successes[] = HMS_Autoassigner::record_success('Requested', $b, $a, $bed_b_text);

            $assigned[] = $a->hms_student_id;
            if(!is_null($b->id))
                $assigned[] = $b->hms_student_id;
        }

        reset($applicants);
        while(count($applicants) > 0) {
            $a = array_shift($applicants);
            if($a === FALSE) continue;
            if(!isset($a)) continue;

            if(in_array($a->hms_student_id, $assigned)) {
                $notices[] = "<strong>{$a->hms_student_id}</strong> already scheduled for assignment.";
                continue;
            }

            $rlc = HMS_RLC_Assignment::check_for_assignment($a->hms_student_id, $term);
            if($rlc !== FALSE) {
                $rlcs[] = "Skipping <strong>{$a->hms_student_id}</strong>; assigned to an RLC.";
                continue;
            }

            $b = array_shift($applicants);

            if(in_array($b->hms_student_id, $assigned)) {
                $notices[] = "<strong>{$b->hms_student_id}</strong> already scheduled for assignment.";
                array_unshift($applicants, $a);
                continue;
            }

            $rlc = HMS_RLC_Assignment::check_for_assignment($b->hms_student_id, $term);
            if($rlc !== FALSE) {
                $rlcs[] = "Skipping <strong>{$b->hms_student_id}</strong>; assigned to an RLC.";
                array_unshift($applicants, $a);
                continue;
            }

            if($a->gender != $b->gender) {
                array_unshift($applicants, $b);
                $b = NULL;
            }

            $ass = HMS_Assignment::get_assignment($a->hms_student_id, $term);
            if(is_a($ass, 'HMS_Assignment')) {
                $bbc = $ass->get_banner_building_code();
                $bed = $ass->get_banner_bed_id();
                $assigns[] = "Could not assign <strong>{$a->hms_student_id}</strong>; already assigned to <strong>$bbc $bed</strong>";
                array_unshift($applicants, $b);
                continue;
            }

            $ass = HMS_Assignment::get_assignment($b->hms_student_id, $term);
            if(is_a($ass, 'HMS_Assignment')) {
                $bbc = $ass->get_banner_building_code();
                $bed = $ass->get_banner_bed_id();
                $assigns[] = "Could not assign <strong>{$b->hms_student_id}</strong>; already assigned to <strong>$bbc $bed</strong>";
                array_unshift($applicants, $a);
                continue;
            }

            // Determine Room Gender
            $room = ($a->gender == FEMALE ? array_shift($f_rooms) :
                   ($a->gender == MALE   ? array_shift($m_rooms) :
                   'badgender'));

            // We could be out of rooms or have database corruption
            if(is_null($room)) {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong>; out of ".($a->gender?'male':'female').' rooms.';
                continue;
            } else if($room === 'badgender') {
                $problems[] = "Could not assign <strong>{$a->hms_student_id}</strong>; {$a->gender} is not a valid gender.";
                continue;
            }

            // Prepare for assignment
            $room = &new HMS_Room($room);
            $room->loadBeds();

            $bed_a_text = $room->_beds[0]->get_banner_building_code() . ' ' . $room->_beds[0]->banner_id;
            $bed_b_text = $room->_beds[1]->get_banner_building_code() . ' ' . $room->_beds[1]->banner_id;

            $successes[] = HMS_Autoassigner::record_success('Auto', $a, $b, $bed_a_text);
            $successes[] = HMS_Autoassigner::record_success('Auto', $b, $a, $bed_b_text);

            $assigned[] = $a->hms_student_id;
            if(!is_null($b->id))
                $assigned[] = $b->hms_student_id;
        }

            // Assign
/*            if(!$test) {
                // Get Meal Plan
                $meal_plan = HMS_SOAP::get_plan_meal_codes($applicant->hms_student_id, $bed['hall'], $applicant->meal_option);

                $banner_success = HMS_Banner_Queue::queue_create_assignment(
                    $applicant->hms_student_id,
                    HMS_Term::get_selected_term(),
                    $bed['banner_building_code'],
                    $bed['banner_id'],
                    $meal_plan['plan'],
                    $meal_plan['meal']
                );

                if($banner_success) {
                    $problems[] = "Skipped bed {$bed['bed']['id']} username {$applicant->hms_student_id} due to banner error code $banner_success";
                    continue;
                }

                $assignment = new HMS_Assignment();

                $assignment->asu_username = $applicant->hms_student_id;
                $assignment->bed_id       = $bed['bed']['id'];
                $assignment->term         = HMS_Term::get_selected_term();
                $assignment->meal_option  = $applicant->meal_option;

                $result = $assignment->save();

                HMS_Activity_Log::log_activity($applicant->hms_student_id, ACTIVITY_AUTO_ASSIGNED, Current_User::getUsername(), HMS_Term::get_selected_term() . ' ' . $bed['banner_building_code'] . ' ' . $bed['banner_id']);

                $successes[] = "Assigned <strong>{$applicant->hms_student_id}</strong> to <strong>{$bed['banner_building_code']} {$bed['banner_id']}</strong>";
            } else {
                $successes[] = "Would have assigned {$applicant->hms_student_id} to bed{$bed['id']}";
            }
        }*/

        $f_f_count = count($f_rooms);
        $f_m_count = count($m_rooms);

        usort($successes, array('HMS_Autoassigner', 'sort_successes'));

        $content  = '<h1>Autoassigner Results - ' . date('Y-m-d') . '</h1>';
        $content .= '<h2>Total Assignments: ' . count($assigned) . '</h2>';
        $content .= "<p>Began with $i_f_count female rooms and $i_m_count male rooms</p>";
        $content .= "<p>Ended with $f_f_count female rooms and $f_m_count male rooms</p>";
        $content .= '<h2>Assignment Report (' . count($successes) . ')</h2>';
        $content .= '<table><tr>';
        $content .= '<th>Type</th><th>Bed A</th><th>Code A</th><th>Bed B</th><th>Code B</th><th>Room</th>';
        $content .= '</tr>';

        foreach($successes as $success) {
            $content .= '<tr>';
            $content .= '<td>' . $success['type'] . '</td>';
            $content .= '<td>' . $success['a'] . '</td>';
            $content .= '<td>' . $success['a_code'] . '</td>';
            $content .= '<td>' . $success['room'] . '</td>';
            $content .= '<td>' . $success['b'] . '</td>';
            $content .= '<td>' . $success['b_code'] . '</td>';
            $content .= "</tr>\n";
        }

        $content .= '</tr></table>';

        sort($problems);
        $content .= '<h2>Problems ('.count($problems).')</h2>';
        $content .= implode("<br />\n", $problems);

        sort($rlcs);
        $content .= '<h2>Skipped for RLC ('.count($rlcs).')</h2>';
        $content .= implode("<br />\n", $rlcs);

        sort($assigns);
        $content .= '<h2>Skipped, already assigned ('.count($assigns).')</h2>';
        $content .= implode("<br />\n", $assigns);

        sort($notices);
        $content .= '<h2>Notices ('.count($notices).')</h2>';
        $content .= implode("<br />\n", $notices);

        Layout::nakedDisplay($content, NULL, TRUE);
    }

    function record_success($type, $a, $b, $room)
    {
        $success = array();
        $success['type'] = $type;
        $success['a_code'] =
            ($a->gender == 0 ? 'F' :
                ($a->gender == 1 ? 'M' : 'U')) .
            ($a->student_status == 1 ? 'F' :
                ($a->student_status == 2 ? 'T' : 'U')) .
            ($a->lifestyle_option == 1 ? 'S' :
                ($a->lifestyle_option == 2 ? 'C' : 'U')) .
            ($a->preferred_bedtime == 1 ? 'E' :
                ($a->preferred_bedtime == 2 ? 'L' : 'U')) .
            ($a->room_condition == 1 ? 'C' :
                ($a->room_condition == 2 ? 'D' : 'U'));
        $success['b_code'] =
            ($b->gender == 0 ? 'F' :
                ($b->gender == 1 ? 'M' : 'U')) .
            ($b->student_status == 1 ? 'F' :
                ($b->student_status == 2 ? 'T' : 'U')) .
            ($b->lifestyle_option == 1 ? 'S' :
                ($b->lifestyle_option == 2 ? 'C' : 'U')) .
            ($b->preferred_bedtime == 1 ? 'E' :
                ($b->preferred_bedtime == 2 ? 'L' : 'U')) .
            ($b->room_condition == 1 ? 'C' :
                ($b->room_condition == 2 ? 'D' : 'U'));
        $success['a'] = $a->hms_student_id;
        $success['b'] = $b->hms_student_id;
        $success['room'] = $room;
        return $success;
    }

    function sort_successes($a, $b)
    {
        return strcmp($a['a'], $b['a']);
    }
}

?>
