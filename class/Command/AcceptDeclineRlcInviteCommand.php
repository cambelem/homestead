<?php

namespace Homestead\Command;

use \Homestead\CommandFactory;
use \Homestead\HMS_RLC_Assignment;
use \Homestead\RlcAssignmentConfirmedState;
use \Homestead\RlcAssignmentDeclinedState;
use \Homestead\HMS_Activity_Log;
use \Homestead\StudentFactory;
use \Homestead\UserStatus;
use \Homestead\NotificationView;

class AcceptDeclineRlcInviteCommand extends Command {

    public function getRequestVars(){
        return array('action'=>'AcceptDeclineRlcInvite');
    }

    public function execute(CommandContext $context)
    {
        $term = $context->get('term');
        if(!isset($term)){
            throw new \InvalidArgumentException('Missing term!');
        }

        $rlcAssignment = HMS_RLC_Assignment::getAssignmentByUsername(UserStatus::getUsername(), $term);
        $rlcApplication = $rlcAssignment->getApplication();
        $student = StudentFactory::getStudentByUsername($rlcApplication->getUsername(), $rlcApplication->getTerm());

        $acceptStatus = $context->get('acceptance');

        $termsCheck = $context->get('terms_cond');

        if($acceptStatus == 'accept' && !isset($termsCheck)){
            // Student accepted the invite, but didn't check the terms/conditions box
            $errorCmd = CommandFactory::getCommand('ShowAcceptRlcInvite');
            $errorCmd->setTerm($term);
            \NQ::simple('hms', NotificationView::ERROR, 'Please check the box indicating that you agree to the learning communitiy terms and conditions.');
            $errorCmd->redirect();
        }else if($acceptStatus == 'accept' && isset($termsCheck)){
            // Student accepted the invite and checked the terms/conditions box
            $rlcAssignment->changeState(new RlcAssignmentConfirmedState($rlcAssignment));

            \NQ::simple('hms', NotificationView::SUCCESS, 'You have <strong>accepted</strong> your Residential Learning Community invitation.');

            // Log this!
            HMS_Activity_Log::log_activity($student->getUsername(), ACTIVITY_ACCEPT_RLC_INVITE, UserStatus::getUsername(), $rlcAssignment->getRlcName());

            $successCmd = CommandFactory::getCommand('ShowStudentMenu');
            $successCmd->redirect();
        }else if($acceptStatus == 'decline'){
            // student declined
            $rlcAssignment->changeState(new RlcAssignmentDeclinedState($rlcAssignment));

            \NQ::simple('hms', NotificationView::SUCCESS, 'You have <strong>declined</strong> your Residential Learning Community invitation.');

            // Log this!
            HMS_Activity_Log::log_activity($student->getUsername(), ACTIVITY_DECLINE_RLC_INVITE, UserStatus::getUsername(), $rlcAssignment->getRlcName());

            $successCmd = CommandFactory::getCommand('ShowStudentMenu');
            $successCmd->redirect();
        }else{
            // Didn't choose
            $errorCmd = CommandFactory::getCommand('ShowAcceptRlcInvite');
            $errorCmd->setTerm($term);
            \NQ::simple('hms', NotificationView::ERROR, 'Please choose to either accept or decline your learning community invitation.');
            $errorCmd->redirect();
        }

        $context->setContent('confirmed or denied');
    }
}
