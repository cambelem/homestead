<?php

namespace Homestead\Command;

use \Homestead\UserStatus;
use \Homestead\StudentFactory;
use \Homestead\CommandFactory;
use \Homestead\HousingApplicationFactory;
use \Homestead\HMS_Activity_Log;
use \Homestead\Term;
use \Homestead\FreshmenApplicationReview;
use \Homestead\NotificationView;

class ShowFreshmenApplicationReviewCommand extends Command {

    private $term;
    private $mealOption;
    private $lifestyleOption;
    private $preferredBedtime;
    private $smoking_preference;
    private $roomCondition;
    private $rlcInterest;


    public function setTerm($term)
    {
        $this->term = $term;
    }

    public function getRequestVars()
    {
        //$vars = $_REQUEST; // Carry forward the existing context

        // Overwrite the old action
        //unset($vars['module']);

        $vars = array();
        $vars['action'] = 'ShowFreshmenApplicationReview';
        $vars['term']	= $this->term;

        return $vars;
    }

    public function execute(CommandContext $context)
    {
        $term = $context->get('term');

        $student = StudentFactory::getStudentByUsername(UserStatus::getUsername(), $term);

        // If they haven't agreed, redirect to the agreement
        // TODO: actually check via docusign API
        $event = $context->get('event');
        if(is_null($event) || !isset($event) || ($event != 'signing_complete' && $event != 'viewing_complete'))
        {
            $returnCmd = CommandFactory::getCommand('ShowFreshmenApplicationReview');
            $returnCmd->setTerm($term);

            $agreementCmd = CommandFactory::getCommand('ShowTermsAgreement');
            $agreementCmd->setTerm($term);
            $agreementCmd->setAgreedCommand($returnCmd);
            $agreementCmd->redirect();
        } else if($event === 'signing_complete'){
            HMS_Activity_Log::log_activity($student->getUsername(), ACTIVITY_CONTRACT_STUDENT_SIGN_EMBEDDED, UserStatus::getUsername(), "Student signed contract for $term through the embedded signing process");
        }

        $errorCmd = CommandFactory::getCommand('ShowHousingApplicationForm');
        $errorCmd->setTerm($term);

        // Determine the application type, based on the term
        $sem = Term::getTermSem($term);

        switch ($sem){
            case TERM_FALL:
                $appType = 'fall';
                break;
            case TERM_SPRING:
                $appType = 'spring';
                break;
            case TERM_SUMMER1:
            case TERM_SUMMER2:
                $appType = 'summer';
                break;
        }

        try{

            $application = HousingApplicationFactory::getApplicationFromSession($_SESSION['application_data'], $term, $student, $appType);
        }catch(\Exception $e){
            \NQ::simple('hms', NotificationView::ERROR, $e->getMessage());
            $errorCmd->redirect();
        }

        $view = new FreshmenApplicationReview($student, $term, $application);
        $context->setContent($view->show());
    }
}
