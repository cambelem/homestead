<?php
/**
  * Wrapper class so the pager works on this table.
  *
  * @author     Daniel West <dwest at tux dot appstate dot edu>
  * @package    modules
  * @subpackage hms
  */

class HMS_Application_Features {

    function main()
    {
        switch($_REQUEST['op'])
        {
            case 'show_edit_features':
                PHPWS_Core::initModClass('hms', 'UI/Application_UI.php');
                return Application_UI::show_feature_interface();
                break;
            case 'edit_features':
                return HMS_Application_Features::handle_features_submit();
                break;
        }
    }

    function handle_features_submit()
    {
        # Check permissions
        if( !Current_User::allow('hms', 'edit_features') ){
            $tpl = array();
            return PHPWS_Template::process($tpl, 'hms', 'admin/permission_denied.tpl');
        }

        PHPWS_Core::initModClass('hms', 'UI/Application_UI.php');

        $result = HMS_Application_Features::save($_REQUEST);

        if($result){
            echo "yes";
            return Application_UI::show_feature_interface('Feature set updated successfully.');
        }else{
            echo "no";
            return Application_UI::show_feature_interface(NULL, 'Error: There was a problem working with the database.');
        }
    }

    function save($request)
    {
        $features = array(APPLICATION_RLC_APP          => 'RLC Applications',
                          APPLICATION_ROOMMATE_PROFILE => 'Roommate Profile Searching',
                          APPLICATION_SELECT_ROOMMATE  => 'Selecting Roommates');

        for($i = 0; $i < sizeof($features); $i++){
            $db = &new PHPWS_DB('hms_application_features');
            $db->addWhere('term', $request['term']);
            $db->addWhere('feature', $i);
            $result = $db->select();
            $exists = (sizeof($result) > 0 ? true : false);
            unset($result);
            
            $db->reset();
            if(isset($request['feature'][$i])){
                $db->addValue('enabled', 1);
                if($exists){
                    $db->addWhere('term', $request['term']);
                    $db->addWhere('feature', $i);
                    $result = $db->update();
                } else {
                    $db->addValue('term', $request['term']);
                    $db->addValue('feature', $i);
                    $result = $db->insert();
                }
            } else {
                $db->addValue('enabled', 0);
                if($exists){
                    $db->addWhere('term', $request['term']);
                    $db->addWhere('feature', $i);
                    $result = $db->update();
                } else {
                    $db->addValue('term', $request['term']);
                    $db->addValue('feature', $i);
                    $result = $db->insert();
                }
            }
            if(PHPWS_Error::logIfError($result))
                return false;
        }
        return true;
    }
}
?>