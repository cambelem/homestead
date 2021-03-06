<?php

namespace Homestead\Command;

use \Homestead\StudentFactory;
use \Homestead\Term;
use \Homestead\HMS_Assignment;
use \Homestead\AssignmentMoveConfirmationView;
use \Homestead\Exception\PermissionException;

class ShowAssignmentMoveConfirmationCommand extends Command {

    private $username;
    private $room;
    private $bed;
    private $assignmentType;
    private $notes;

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setRoom($room){
        $this->room = $room;
    }

    public function setBed($bed){
        $this->bed = $bed;
    }

    public function setAssignmentType($type){
        $this->assignmentType = $type;
    }

    public function setNotes($notes){
        $this->notes = $notes;
    }

    public function getRequestVars()
    {
        $vars = array('action'=>'ShowAssignmentMoveConfirmation');

        if(isset($this->username)){
            $vars['username'] = $this->username;
        }

        if(isset($this->room)){
            $vars['room'] = $this->room;
        }

        if(isset($this->bed)){
            $vars['bed'] = $this->bed;
        }

        if(isset($this->assignmentType)){
            $vars['assignment_type'] = $this->assignmentType;
        }

        if(isset($this->notes)){
            $vars['notes'] = $this->notes;
        }

        return $vars;
    }

    public function execute(CommandContext $context)
    {
        if(!\Current_User::allow('hms', 'assignment_maintenance')){
            throw new PermissionException('You do not have permission to assign students.');
        }

        $student = StudentFactory::getStudentByUsername($context->get('username'), Term::getSelectedTerm());
        $assignment = HMS_Assignment::getAssignment($student->getUsername(), Term::getSelectedTerm());

        $moveConfirmView = new AssignmentMoveConfirmationView($student,
                $assignment,
                $context->get('residence_hall'),
                $context->get('room'),
                $context->get('bed'),
                $context->get('assignment_type'),
                $context->get('notes'));

        $context->setContent($moveConfirmView->show());
    }
}
