<?php

namespace Homestead;

use \Homestead\Command\Command;

class SelectRoomView extends View{

    private $title;
    private $term;
    private $onSelectCmd;
    private $halls;

    public function __construct(Command $onSelectCmd, $halls, $title, $term)
    {
        $this->onSelectCmd	= $onSelectCmd;
        $this->title		= $title;
        $this->term			= $term;
        $this->halls		= $halls;
    }

    public function show()
    {
        $tpl = array();

        if($this->halls == NULL){
            \NQ::simple('hms', NotificationView::ERROR, 'There are no halls available for the selected term.');
            $cmd = CommandFactory::getCommand('DashboardHome');
            $cmd->redirect();
        }

        $tpl['TITLE']   = $this->title;
        $tpl['TERM']    = Term::getPrintableSelectedTerm();

        javascript('jquery');
        javascript('modules/hms/select_room');

        # Setup the form
        $form = new \PHPWS_Form();
        $this->onSelectCmd->initForm($form);

        $form->setMethod('get');
        $form->addDropBox('residence_hall', $this->halls);
        $form->setLabel('residence_hall', 'Residence hall');
        $form->setMatch('residence_hall', 0);
        $form->addCssClass('residence_hall', 'form-control');

        $form->addDropBox('floor', array(0 => ''));
        $form->setLabel('floor', 'Floor');
        $form->addCssClass('floor', 'form-control');

        $form->addDropBox('room', array(0 => ''));
        $form->setLabel('room', 'Room');
        $form->addCssClass('room', 'form-control');

        $form->addSubmit('submit_button', 'Select');

        $form->mergeTemplate($tpl);
        $tpl = $form->getTemplate();

        \Layout::addPageTitle("Select Room");

        return \PHPWS_Template::process($tpl, 'hms', 'admin/select_room.tpl');
    }
}
