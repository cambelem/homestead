<?php

namespace Homestead\Command;

use \Homestead\HMS_Lottery;
use \Homestead\HousingApplicationFactory;
use \Homestead\UserStatus;
use \Homestead\RlcMembershipFactory;
use \Homestead\LotteryRoommateRequestView;
use \Homestead\StudentFactory;

class LotteryShowRoommateRequestCommand extends Command {

    private $requestId;

    public function setRequestId($id){
        $this->requestId = $id;
    }

    public function getRequestVars(){
        $vars = array('action'=>'LotteryShowRoommateRequest');

        $vars['requestId'] = $this->requestId;

        return $vars;
    }

    public function execute(CommandContext $context)
    {
        $request = HMS_Lottery::get_lottery_roommate_invite_by_id($context->get('requestId'));
        $term = \PHPWS_Settings::get('hms', 'lottery_term');

        $student = StudentFactory::getStudentByUsername(UserStatus::getUsername(), $term);
        $housingApp = HousingApplicationFactory::getAppByStudent($student, $term);

        // Check for a self-select RLC membership for the logged-in student
        $rlcAssign = RlcMembershipFactory::getMembership($student, $term);

        if($rlcAssign == false) {
        	$rlcAssign = null;
        }

        $view = new LotteryRoommateRequestView($request, $term, $housingApp, $rlcAssign);
        $context->setContent($view->show());
    }
}
