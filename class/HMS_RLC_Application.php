<?php

namespace Homestead;

use \Homestead\Exception\StudentNotFoundException;
use \Homestead\Exception\DatabaseException;
use \PHPWS_Error;
use \PHPWS_DB;

/**
 * The HMS_RLC_Application class
 * Implements the RLC_Application object and methods to load/save
 * learning community applications from the database.
 *
 * @package HMS
 * @author Jeremy Booker
 */

// RLC application types
define('RLC_APP_FRESHMEN', 'freshmen');
define('RLC_APP_RETURNING', 'returning');

class HMS_RLC_Application extends HMS_Item
{
    /**
     * @deprecated
     */
    const RLC_RESPONSE_LIMIT = 4096; // max number of characters allowed in the text areas on the RLC application

    /**
     * Word limit for RLC question responses.
     *
     * @var integer
     */
    const RLC_RESPONSE_WORD_LIMIT = 500;

    public $id;

    public $username;
    public $date_submitted;

    public $rlc_first_choice_id;
    public $rlc_second_choice_id;
    public $rlc_third_choice_id;

    public $why_specific_communities;
    public $strengths_weaknesses;

    public $rlc_question_0;
    public $rlc_question_1;
    public $rlc_question_2;

    public $term = null;

    public $denied = 0;
    public $denied_email_sent = 0;

    public $application_type;

    /**
     * Constructor
     * Set $username equal to the ASU email of the student you want
     * to create/load a application for. Otherwise, the student currently
     * logged in (session) is used.
     */
    public function __construct($id = 0)
    {
        parent::__construct($id);
    }

    public function getDb()
    {
        return new PHPWS_DB('hms_learning_community_applications');
    }

    /**
     * Returns true if this RLC application has been flagged as denied, false otherwise.
     *
     * @return boolean
     */
    public function isDenied()
    {
        if($this->denied == 1){
            return true;
        }

        return false;
    }

    public function setDenied($status)
    {
        $this->denied = $status;
    }

    public function getAdminPagerTags()
    {
        $student = StudentFactory::getStudentByUsername($this->username, Term::getCurrentTerm());

        $rlc_list = HMS_Learning_Community::getRlcList();

        $tags = array();

        $tags['NAME']           = $student->getProfileLink();

        $rlcCmd = CommandFactory::getCommand('ShowRlcApplicationReView');
        $rlcCmd->setAppId($this->getId());

        $tags['1ST_CHOICE']     = $rlcCmd->getLink($rlc_list[$this->getFirstChoice()], '_blank');
        if(isset($rlc_list[$this->getSecondChoice()]))
        $tags['2ND_CHOICE'] = $rlc_list[$this->getSecondChoice()];
        if(isset($rlc_list[$this->getThirdChoice()]))
        $tags['3RD_CHOICE'] = $rlc_list[$this->getThirdChoice()];
        $tags['FINAL_RLC']      = HMS_RLC_Application::generateRLCDropDown($rlc_list, $this->getID());
        $tags['CLASS']          = $student->getClass();
        //        $tags['SPECIAL_POP']    = ;
        //        $tags['MAJOR']          = ;
        //        $tags['HS_GPA']         = ;
        $tags['GENDER']         = $student->getPrintableGender();
        $tags['DATE_SUBMITTED'] = date('d-M-y', $this->getDateSubmitted());

        $denyCmd = CommandFactory::getCommand('DenyRlcApplication');
        $denyCmd->setApplicationId($this->getID());

        $tags['DENY']           = $denyCmd->getLink('Deny');

        return $tags;
    }

    public function applicantsReport()
    {
        $term = Term::getSelectedTerm();

        $rlc_list = HMS_Learning_Community::getRlcList();

        $student = StudentFactory::getStudentByUsername($this->username, $this->term);

        $application_date = isset($this->date_submitted) ? HMS_Util::get_long_date($this->date_submitted) : 'Error with the submission date';

        $roomie = null;
        if (HMS_Roommate::has_confirmed_roommate($this->username, $term)) {
            $roomie = HMS_Roommate::get_Confirmed_roommate($this->username, $term);
        } elseif(HMS_Roommate::has_roommate_request($this->username, $term)) {
            $roomie = HMS_Roommate::get_unconfirmed_roommate($this->username, $term) . ' *pending* ';
        }

        $row = array();

        $row['last_name']           = $student->getLastName();
        $row['first_name']          = $student->getFirstName();
        $row['middle_name']         = $student->getMiddleName();
        $row['gender']              = $student->getPrintableGender();

        if ($roomie instanceof Student) {
            $row['roommate']        = $roomie->getUsername();
        } else {
            $row['roommate']        = '';
        }

        $row['email']               = $student->getUsername() . '@appstate.edu';
        $row['first_chocie']        = $rlc_list[$this->getFirstChoice()];

        $secondChoice = $this->getSecondChoice();
        if(is_null($secondChoice) || $secondChoice == '') {
            $row['second_choice'] = '';
        } else {
            $row['second_choice'] = $rlc_list[$this->getSecondChoice()];
        }

        $thirdChoice = $this->getThirdChoice();
        if(is_null($thirdChoice) || $thirdChoice == '') {
            $row['third_choice'] = '';
        } else {
            $row['third_choice'] = $rlc_list[$this->getThirdChoice()];
        }

        $row['application_date']    = $application_date;
        $row['denied']              = (isset($this->denied) && $this->denied == 0) ? 'no' : 'yes';

        return $row;
    }

    public function getDeniedPagerTags()
    {
        $student = StudentFactory::getStudentByUsername($this->username, $this->term);

        $tags = array();
        $rlc_list = HMS_Learning_Community::getRlcList();

        $tags['NAME']           = $student->getProfileLink();

        $rlcCmd = CommandFactory::getCommand('ShowRlcApplicationReView');
        $rlcCmd->setAppId($this->getId());

        $tags['1ST_CHOICE']     = $rlcCmd->getLink($rlc_list[$this->getFirstChoice()], '_blank');

        if(isset($rlc_list[$this->getSecondChoice()]))
        $tags['2ND_CHOICE'] = $rlc_list[$this->getSecondChoice()];
        if(isset($rlc_list[$this->getThirdChoice()]))
        $tags['3RD_CHOICE'] = $rlc_list[$this->getThirdChoice()];
        $tags['CLASS']          = $student->getClass();
        $tags['GENDER']         = $student->getGender();
        $tags['DATE_SUBMITTED'] = date('d-M-y',$this->getDateSubmitted());

        $unDenyCmd = CommandFactory::getCommand('UnDenyRlcApplication');
        $unDenyCmd->setApplicationId($this->id);

        $tags['ACTION']         = $unDenyCmd->getLink('Un-Deny');

        return $tags;
    }

    /**
     * Returns this rlc application (and assignment) as array of fields for CSV export
     *
     * @return Array
     */
    public function viewByRLCExportFields()
    {
        $row = array();

        // Get the Student object
        try{
            $student = StudentFactory::getStudentByUsername($this->username, Term::getSelectedTerm());
        }catch(StudentNotFoundException $e){
            // Catch the StudentNotFound exception in the odd case that someone doesn't exist.
            // Show a warning message and skip the rest of the method
            \NQ::simple('hms', NotificationView::WARNING, "No student found with username: {$this->username}.");
            $row['username'] = $this->username;
            $row['name'] = 'UNKNOWN - INVALID';
            return $tags;
        }

        $row['name']            = $student->getFullName();
        $row['gender']          = $student->getPrintableGender();
        $row['student_type']    = $student->getPrintableType();
        $row['username']        = $student->getUsername();
        $row['banner_id']       = $student->getBannerId();

        /*** Assignment Status/State ***/
        // Lookup the assignmnet (used later as well)
        $assign = HMS_RLC_Assignment::getAssignmentByUsername($this->username, $this->term);
        $state = $assign->getStateName();
        if($state == 'confirmed'){
            $row['state'] = 'confirmed';
        }else if($state == 'declined'){
            $row['state'] = 'declined';
        }else if($state == 'new'){
            $row['state'] = 'not invited';
        }else if($state == 'invited'){
            $row['state'] = 'pending';
        }else{
            $row['state'] = '';
        }


        // Check for/display room assignment
        $roomAssign = HMS_Assignment::getAssignmentByBannerId($student->getBannerId(), Term::getSelectedTerm());

        if(isset($roomAssign)){
            $row['room_assignment'] = $roomAssign->where_am_i();
        }else{
            $row['room_assignment'] = 'n/a';
        }

        /*** Roommates ***/
        // Show all possible roommates for this application
        $allRoommates = HMS_Roommate::get_all_roommates($this->username, $this->term);
        $row['roommates'] = 'N/A'; // Default text

        if(sizeof($allRoommates) > 1) {
            // Don't show all the roommates
            $row['roommates'] = "Multiple Requests";
        }
        elseif(sizeof($allRoommates) == 1) {
            // Get other roommate
            $otherGuy = StudentFactory::getStudentByUsername($allRoommates[0]->get_other_guy($this->username), $this->term);
            $row['roommates'] = $otherGuy->getFullName();
            // If roommate is pending then show little status message
            if(!$allRoommates[0]->confirmed) {
                $row['roommates'] .= " (Pending)";
            }
        }

        return $row;
    }

    /*****************
     * Static Methods *
     *****************/

    /**
     * Check to see if an application already exists for the specified user. Returns false if no application exists.
     * If an application does exist, an associative array containing that row is returned. In the case of a db error, a \PEAR
     * error object is returned.
     * @param include_denied Controls whether or not denied applications are returned
     * TODO: Deprecate this and/or move to RlcApplicationFactory
     * @see RlcApplicationFactory
     */
    public static function checkForApplication($username, $term, $include_denied = true)
    {
        $db = new PHPWS_DB('hms_learning_community_applications');

        $db->addWhere('username', $username, 'ILIKE');
        $db->addWhere('term', $term);

        if(!$include_denied) {
            $db->addWhere('denied', 0);
        }

        $result = $db->select('row');

        if(PHPWS_Error::logIfError($result)) {
            throw new DatabaseException($result->toString());
        }

        if(sizeof($result) > 1) {
            return $result;
        }else{
            return false;
        }
    }

    /**
     * TODO: Deprecate this and/or move to RlcApplicationFactory
     * @see RlcApplicationFactory
     * @throws DatabaseException
     * @return NULL|HMS_RLC_Application
     */
    public static function getApplicationByUsername($username, $term)
    {
        $app = new HMS_RLC_Application();

        $db = new PHPWS_DB('hms_learning_community_applications');

        $db->addWhere('username', $username, 'ILIKE');
        $db->addWhere('term', $term);

        $result = $db->loadObject($app);

        if(PHPWS_Error::logIfError($result)) {
            throw new DatabaseException($result->toString());
        }

        if($app->id == 0) {
            return null;
        }

        return $app;
    }

    /**
     * TODO: Deprecate this and/or move to RlcApplicationFactory
     * @see RlcApplicationFactory
     * @throws DatabaseException
     * @return HMS_RLC_Application
     */
    public static function getApplicationById($id)
    {

        $app = new HMS_RLC_Application();

        $db = new PHPWS_DB('hms_learning_community_applications');
        $db->addWhere('id', $id);
        $result = $db->loadObject($app);

        if(PHPWS_Error::logIfError($result)) {
            throw new DatabaseException($result->toString());
        }

        return $app;
    }

    /**
     * TODO: Deprecate this and/or move to RlcApplicationFactory
     * @see RlcApplicationFactory
     *
     * Get denied RLC applicants by term
     * @return Array of Student objects
     *
     */
    public static function getNonNotifiedDeniedApplicantsByTerm($term)
    {
        // query DB
        $db = new PHPWS_DB('hms_learning_community_applications');
        $db->addWhere('denied', 1);
        $db->addWhere('denied_email_sent', 0);
        $db->addWhere('term', $term);
        $result = $db->select();

        if(PHPWS_Error::logIfError($result)) {
            throw new DatabaseException($result->toString());
        }

        return $result;
    }

    //TODO move this!!
    public static function denied_pager()
    {
        $pager = new \DBPager('hms_learning_community_applications', '\Homestead\HMS_RLC_Application');

        $pager->db->addWhere('term', Term::getSelectedTerm());
        $pager->db->addWhere('denied', 1); // show only denied applications

        $pager->db->addColumn('hms_learning_community_applications.*');
        $pager->db->addColumn('hms_learning_communities.abbreviation');
        $pager->db->addWhere('hms_learning_community_applications.rlc_first_choice_id',
                             'hms_learning_communities.id','=');

        $pager->setModule('hms');
        $pager->setTemplate('admin/denied_rlc_app_pager.tpl');
        $pager->setEmptyMessage("No denied RLC applications exist.");
        $pager->addRowTags('getDeniedPagerTags');

        return $pager->get();
    }

    /**
     * Generates a drop down menu using the RLC abbreviations
     * TODO: Deprecate this and/or move to RlcApplicationFactory
     * @see RlcApplicationFactory
     */
    public static function generateRLCDropDown($rlc_list,$application_id) {

        $output = "<select name=\"final_rlc[$application_id]\" class='form-control'>";

        $output .= '<option value="-1">None</option>';

        foreach ($rlc_list as $id => $rlc_name) {
            $output .= "<option value=\"$id\">$rlc_name</option>";
        }

        $output .= '</select>';

        return $output;
    }

    /****************************
     * Accessor & Mutator Methods
     ****************************/

    public function setID($id)
    {
        $this->id = $id;
    }

    public function getID()
    {
        return $this->id;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function setDateSubmitted($date = null)
    {
        if(!isset($date)){
            $this->date_submitted = time();
        }else{
            $this->date_submitted = $date;
        }
    }

    public function getDateSubmitted()
    {
        return $this->date_submitted;
    }

    public function setFirstChoice($choice)
    {
        $this->rlc_first_choice_id = $choice;
    }

    public function getFirstChoice()
    {
        return $this->rlc_first_choice_id;
    }

    public function setSecondChoice($choice)
    {
        $this->rlc_second_choice_id = $choice;
    }

    public function getSecondChoice()
    {
        return $this->rlc_second_choice_id;
    }

    public function setThirdChoice($choice)
    {
        $this->rlc_third_choice_id = $choice;
    }

    public function getThirdChoice()
    {
        return $this->rlc_third_choice_id;
    }

    public function setWhySpecificCommunities($why)
    {
        $this->why_specific_communities = $why;
    }

    public function getWhySpecificCommunities()
    {
        return $this->why_specific_communities;
    }

    public function setStrengthsWeaknesses($strenghts)
    {
        $this->strengths_weaknesses = $strenghts;
    }

    public function getStrengthsWeaknesses()
    {
        return $this->strengths_weaknesses;
    }

    public function setRLCQuestion0($question)
    {
        $this->rlc_question_0 = $question;
    }

    public function getRLCQuestion0()
    {
        return $this->rlc_question_0;
    }

    public function setRLCQuestion1($question)
    {
        $this->rlc_question_1 = $question;
    }

    public function getRLCQuestion1()
    {
        return $this->rlc_question_1;
    }

    public function setRLCQuestion2($question)
    {
        $this->rlc_question_2 = $question;
    }

    public function getRLCQuestion2()
    {
        return $this->rlc_question_2;
    }

    public function setAssignmentID($id)
    {
        $this->hms_assignment_id = $id;
    }

    public function getAssignmentID()
    {
        return $this->hms_assignment_id;
    }

    /**
     * @depreciated
     * Use 'getTerm' instead.
     */
    public function getEntryTerm()
    {
        return $this->term;
    }

    /**
     * @depreciated
     * Use 'setTerm' instead.
     */
    public function setEntryTerm($term)
    {
        $this->term = $term;
    }

    public function getTerm()
    {
        return $this->term;
    }

    public function setTerm($term)
    {
        $this->term = $term;
    }

    public function getApplicationType()
    {
        return $this->application_type;
    }

    public function setApplicationType($type)
    {
        $this->application_type = $type;
    }

    public function getDeniedEmailSent()
    {
        return $this->denied_email_sent;
    }

    public function setDeniedEmailSent($value)
    {
        $this->denied_email_sent = $value;
    }

}
