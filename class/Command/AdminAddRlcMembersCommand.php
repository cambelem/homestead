<?php

namespace Homestead\Command;

use \Homestead\HMS_Learning_Community;
use \Homestead\HMS_RLC_Application;
use \Homestead\HMS_RLC_Assignment;
use \Homestead\HMS_Lottery;
use \Homestead\StudentFactory;
use \Homestead\HousingApplicationFactory;
use \Homestead\RlcMembershipFactory;
use \Homestead\RlcApplicationFactory;
use \Homestead\Term;
use \Homestead\CommandFactory;
use \Homestead\NotificationView;
use \Homestead\UserStatus;
use \Homestead\Exception\PermissionException;
use \Homestead\Exception\StudentNotFoundException;

class AdminAddRlcMembersCommand extends Command {

    private $community;

    public function getRequestVars()
    {
        return array(
                'action' => 'AdminAddRlcMembers',
                'communityId' => $this->community->getId()
        );
    }

    public function setCommunity($community)
    {
        $this->community = $community;
    }

    public function execute(CommandContext $context)
    {
        if (!\Current_User::allow('hms', 'add_rlc_members')) {
            throw new PermissionException('You do not have permission to view RLC members.');
        }

        // Get the selected term
        $term = Term::getSelectedTerm();

        // Get the request community
        $communityId = $context->get('communityId');

        if (!isset($communityId) || $communityId == '') {
            throw new \InvalidArgumentException('Missing community id.');
        }

        $community = new HMS_Learning_Community($communityId);

        // Get banner ID list and make sure it has content
        $bannerIds = $context->get('banner_id_list');

        if (!isset($bannerIds) || $bannerIds == '') {
            $errorCmd = CommandFactory::getCommand('ShowAdminAddRlcMember');
            $errorCmd->setCommunity($community);
            $errorCmd->redirect();
        }

        // Break up string into an array of individual ids
        $bannerIds = explode("\n", $bannerIds);

        foreach ($bannerIds as $banner) {
            // Clean up the banner id
            $banner = trim($banner);

            // Skip blank lines
            if($banner == '') {
                continue;
            }

            // Get the student
            try {
                $student = StudentFactory::getStudentByBannerId($banner, $term);
            } catch (StudentNotFoundException $e) {
                \NQ::simple('hms', NotificationView::ERROR, "Couldn't find a student with ID: {$e->getRequestedId()}");
                continue;
            } catch (\InvalidArgumentException $e) {
                \NQ::simple('hms', NotificationView::ERROR, "This doesn't look like a banner ID: $banner");
                continue;
            }

            // Check for an existing housing application
            $housingApp = HousingApplicationFactory::getAppByStudent($student, $term);

            // If no housing app, show a warning
            if (is_null($housingApp)) {
                \NQ::simple('hms', NotificationView::WARNING, "No housing application found for: {$student->getName()}({$student->getBannerID()})");
            }

            // Check for an existing learning community application
            $rlcApp = RlcApplicationFactory::getApplication($student, $term);

            if($rlcApp == null){
                // Create a new learning community application
                $rlcApp = new HMS_RLC_Application();
                $rlcApp->setUsername($student->getUsername());
                $rlcApp->setDateSubmitted(time());
                $rlcApp->setFirstChoice($community->getId());
                $rlcApp->setSecondChoice(null);
                $rlcApp->setThirdChoice(null);
                $rlcApp->setWhySpecificCommunities('Application created administratively.');
                $rlcApp->setStrengthsWeaknesses('');
                $rlcApp->setRLCQuestion0(null);
                $rlcApp->setRLCQuestion1(null);
                $rlcApp->setRLCQuestion2(null);
                $rlcApp->setEntryTerm($term);
                if($student->getType() == TYPE_CONTINUING){
                    $rlcApp->setApplicationType(RLC_APP_RETURNING);
                } else {
                    $rlcApp->setApplicationType(RLC_APP_FRESHMEN);
                }

                $rlcApp->save();

            } else {
                // Reset the application's denial flag, see #1026
                $rlcApp->setDenied(0);
                $rlcApp->save();

                // RLC application already exists
                \NQ::simple('hms', NotificationView::WARNING, "RLC application already exists for {$student->getName()}({$student->getBannerID()})");
            }

            // Check for RLC membership
            $membership = RlcMembershipFactory::getMembership($student, $term);

            if($membership !== false){
                \NQ::simple('hms', NotificationView::ERROR, "RLC membership already exists for {$student->getName()}({$student->getBannerID()})");
                continue;
            }

            // If not a freshmen then check Student's Eligibility
            if($student->getType() == TYPE_CONTINUING) {
                $eligibility = HMS_Lottery::determineEligibility($student->getUsername());
                if($eligibility === false){
                    \NQ::simple('hms', NotificationView::ERROR, "{$student->getName()} ({$student->getBannerID()}) is not currently eligible for housing");
                    continue;
                }
            }

            // Create RLC Membership
            $membership = new HMS_RLC_Assignment();
            $membership->rlc_id         = $community->getId();
            $membership->gender         = $student->getGender();
            $membership->assigned_by    = UserStatus::getUsername();
            $membership->application_id = $rlcApp->id;
            $membership->state          = 'new';

            $membership->save();
        }

        $successCmd = CommandFactory::getCommand('ShowViewByRlc');
        $successCmd->setRlcId($community->getId());
        $successCmd->redirect();
    }
}
