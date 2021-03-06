<?php

namespace Homestead\ApplicationFeature;

use \Homestead\ApplicationFeatureRegistration;
use \Homestead\Student;
use \Homestead\Term;

class RoommateSelectionRegistration extends ApplicationFeatureRegistration {
    public function __construct()
    {
        $this->name = 'RoommateSelection';
        $this->description = 'Roommate Selection';
        $this->startDateRequired = true;
        $this->editDateRequired = true;
        $this->endDateRequired = true;
        $this->priority = 5;
    }

    public function showForStudent(Student $student, $term)
    {
        // Freshmen only
        if($student->getApplicationTerm() > Term::getCurrentTerm())
        {
            return true;
        }

        // Possibly available for continuing students in the summer terms (this is sort of a hack)
        //TODO: find a better way to implement this
        $termSem = Term::getTermSem($term);
        if($student->getApplicationTerm() <= Term::getCurrentTerm() && ($termSem == TERM_SUMMER1 || $termSem == TERM_SUMMER2)){
            return true;
        }

        return false;
    }
}
