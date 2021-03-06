<?php

namespace Homestead\Command;

use \Homestead\CommandFactory;
use \Homestead\LotteryProcess;

class SendLotteryInvitesCommand extends Command {

    public function getRequestVars()
    {
        return array('action'=>'SendLotteryInvites');
    }

    public function execute(CommandContext $context)
    {

        $reminders = $context->get('send_reminders');
        $sendReminders = isset($reminders) ? true : false;

        $magic = $context->get('magic_flag');
        $sendMagic = isset($magic) ? true : false;

        $srMale   = $context->get('sr_male');
        $srFemale = $context->get('sr_female');

        $jrMale   = $context->get('jr_male');
        $jrFemale = $context->get('jr_female');

        $sophMale   = $context->get('soph_male');
        $sophFemale = $context->get('soph_female');

        $inviteCounts = array();

        $inviteCounts[CLASS_SENIOR][MALE]      = $srMale;
        $inviteCounts[CLASS_SENIOR][FEMALE]    = $srFemale;

        $inviteCounts[CLASS_JUNIOR][MALE]      = $jrMale;
        $inviteCounts[CLASS_JUNIOR][FEMALE]    = $jrFemale;

        $inviteCounts[CLASS_SOPHOMORE][MALE]   = $sophMale;
        $inviteCounts[CLASS_SOPHOMORE][FEMALE] = $sophFemale;

        $lottery = new LotteryProcess($sendMagic, $sendReminders, $inviteCounts);
        $lottery->sendInvites();

        $_SESSION['LOTTERY_OUTPUT'] = $lottery->getOutput();

        $viewCmd = CommandFactory::getCommand('ShowLotteryInviteResults');
        $viewCmd->redirect();
    }
}
