<?php 

class CheckinStartView extends View {
	
	private $halls;
	private $term;
	
	public function __construct(Array $halls, $term)
	{
		$this->halls = $halls;
		$this->term = $term;
	}
	
	public function show()
	{
		javascript('jquery');
		javascriptMod('hms', 'jqueryCookie');
		javascriptMod('hms', 'checkinStart');
		
		$tpl = array();
		
		$form = new PHPWS_Form('checkin_form');
		
		$submitCmd = CommandFactory::getCommand('StartCheckinSubmit');
		$submitCmd->initForm($form);
		
		if(count($this->halls) > 1){
			$form->addDropbox('residence_hall', array(0 => "Select a hall..") + $this->halls);
			$form->setLabel('residence_hall', 'Residence Hall');
			$form->addHidden('residence_hall_hidden');
		}else{
			$keys = array_keys($this->halls);
			$form->addHidden('residence_hall_hidden', $keys[0]);
		}
		
		$form->addText('banner_id');
		$form->setLabel('banner_id', 'Resident');
		$form->setExtra('banner_id', 'placeholder = "Swipe AppCard or type Name/Email/Banner ID"');
		$form->setClass('banner_id', 'checkin-search-box');
		
		$form->addSubmit('Begin Check-in');
		$form->setClass('submit', 'btn btn-primary');
		
		$form->mergeTemplate($tpl);
		$tpl = $form->getTemplate();
		
		return PHPWS_Template::process($tpl, 'hms', 'admin/checkinStart.tpl');
	}
}

?>