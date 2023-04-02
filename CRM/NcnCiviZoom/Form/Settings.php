<?php

use CRM_NcnCiviZoom_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_NcnCiviZoom_Form_Settings extends CRM_Core_Form {

  public $_id = NULL;
  public $_act = NULL;

  public function preProcess() {
    CRM_Utils_System::setTitle(ts("Zoom Account Settings"));
    $this->_id = CRM_Utils_Request::retrieve('id', 'String', $this);
    $this->_act = CRM_Utils_Request::retrieve('act', 'Positive', $this);
    //setting the user context to zoom accounts list page
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext(CRM_Utils_System::url('civicrm/Zoom/zoomaccounts',"reset=1"));
    parent::preProcess();
  }

  public function buildQuickForm() {
    $deleteAction = FALSE;
    if ($this->_act & CRM_Core_Action::DELETE) {
      $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => E::ts("Delete"),
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => E::ts('Cancel'),
        ),
      ));
      $deleteAccountDetails = CRM_NcnCiviZoom_Utils::getZoomAccountSettingsByIdOrName($this->_id);
      $this->zoomName = $deleteAccountDetails['name'];
      $this->assign('zoomName',$this->zoomName);
      $deleteAction = TRUE;
    }
    else {
      $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => E::ts('Save'),
          'isDefault' => TRUE,
        ),
        array(
          'type' => 'cancel',
          'name' => E::ts('Cancel'),
        ),
      ));
    }

    if ($this->_act == CRM_Core_Action::UPDATE || $this->_act == CRM_Core_Action::ADD) {
      if (($this->_act == CRM_Core_Action::UPDATE) && !empty($this->_id)) {
        $zoomSettingsToEdit = CRM_NcnCiviZoom_Utils::getZoomAccountSettingsByIdOrName($this->_id);
        $this->zoomName = $zoomSettingsToEdit['name'];
        $this->assign('zoomName', $this->zoomName);
      }
      else {
        $this->zoomName = E::ts('New Account');
        $this->assign('zoomName', $this->zoomName);
      }

      $this->add('hidden', 'id', $this->_id);
      $this->add('text', 'name', ts('Account Name'), array('size' => 48,), TRUE);
      $this->add('text', 'user_id', ts('User Email'), array(
        'size' => 48,
      ), FALSE);

      $oauth_clients = ['' => E::ts('- select -')] + \Civi\Api4\OAuthClient::get(FALSE)
        ->addWhere('provider', '=', 'zoom')
        ->execute()
        ->indexBy('id')
        ->column('guid');

      $this->add('select', 'oauth_client_id', E::ts('OAuth Client'), $oauth_clients, TRUE);
      $this->add('text', 'account_id', E::ts('Account ID'), ['size' => 48], TRUE);

      $testButton = array(
        'type' => 'upload',
        'name' => ts('Test Settings'),
        'subName' => 'done'
      );
      $saveButton = array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE
      );
      $cancelButton = array(
        'type' => 'cancel',
        'name' => E::ts('Cancel')
      );
      $buttons[] = $saveButton;
      if(($this->_act == 2)){
        $buttons[] = $testButton;
      }
      $buttons[] = $cancelButton;
      $this->addButtons($buttons);
    }

    // add form elements
    if(empty($this->_id) && empty($this->_act)){
      $this->add(
        'select',
        'custom_field_id_webinar',
        'Custom Field for Webinar',
        $this->getEventCustomFields(),
        TRUE,
        array('multiple' => FALSE)
      );
      $this->add(
        'select',
        'custom_field_id_meeting',
        'Custom Field For Meeting',
        $this->getEventCustomFields(),
        TRUE,
        array('multiple' => FALSE)
      );
      $this->add(
        'select',
        'custom_field_account_id',
        'Custom Field For Zoom Account Id',
        $this->getEventCustomFields(),
        TRUE,
        array('multiple' => FALSE)
      );
      // The Email location type for importing contacts
      $emailTypeProps = array(
        'entity' => 'Email',
        'field' => 'location_type_id',
        'label' => ts('Import Email Type'),
      );
      $this->addSelect('import_email_location_type', $emailTypeProps);

      $rows = CRM_NcnCiviZoom_Utils::getAllZoomAccountSettings();
      foreach ($rows as $Id => $values) {
        if(strlen($values['api_key']) > 4){
          $rows[$Id]['api_key'] = substr($values['api_key'], 0, 4).(str_repeat('*',strlen($values['api_key']) - 4));
        }
        if(strlen($values['secret_key']) > 4){
          $rows[$Id]['secret_key'] = substr($values['secret_key'], 0, 4).(str_repeat('*',strlen($values['secret_key']) - 4));
        }
        if (!$deleteAction) {
          $editURL = CRM_Utils_System::href('Edit', 'civicrm/Zoom/zoomaccounts', 'reset=1&act='.CRM_Core_Action::UPDATE.'&id='.$Id);
          $deleteURL = CRM_Utils_System::href('Delete', 'civicrm/Zoom/zoomaccounts', 'reset=1&act='.CRM_Core_Action::DELETE.'&id='.$Id);
          $rows[$Id]['action'] = sprintf("<span>%s &nbsp;/&nbsp; %s</span>", $editURL, $deleteURL, $Id);
          $testResult = self::testAPIConnectionSettings($Id);
          $ext = 'ncn-civi-zoom';
          if($testResult['type'] == 'success'){
            $file = 'images/connected.png';
            $imageUrl = CRM_Core_Resources::singleton()->getUrl($ext, $file);
          }else{
            $file = 'images/notConnected.png';
            $imageUrl = CRM_Core_Resources::singleton()->getUrl($ext, $file);
          }
          $rows[$Id]['connected'] = "<img height='16' width='16' src=".$imageUrl." />";
        }
      }
    }

    $headers = [ts('Id')
      , ts('Account Name')
      , ts('User Email')
      , ts('Api Key')
      , ts('Secret Key')
      , ts('Action')
      , ts('Connected')
    ];

    $columnNames = array('id', 'name', 'user_id', 'api_key', 'secret_key', 'action', 'connected');
    // export form elements
    $defaults = CRM_NcnCiviZoom_Utils::getZoomSettings($this->_id);
    $this->assign('act', $this->_act);
    $this->assign('id', $this->_id);
    $this->assign('deleteAction', $deleteAction);
    $this->assign('headers',$headers);
    if(!empty($rows)){$this->assign('rows',$rows);}
    $this->assign('columnNames',$columnNames);
    //Set default Values
    $this->setDefaults($defaults);

    $this->addFormRule(array('CRM_NcnCiviZoom_Form_Settings', 'formRule'));
    parent::buildQuickForm();
  }

  public function formRule($fields) {
    $errors = [];

    if (!empty($fields['user_id'])) {
      $tableName = CRM_NcnCiviZoom_Constants::ZOOM_ACCOUNT_SETTINGS;
      $zoomAccountByUser = CRM_Core_Dao::singleValueQuery("SELECT id FROM {$tableName} WHERE user_id = %1", array(1 => array($fields['user_id'], 'String')));
      $duplicateAccount = FALSE;

      //For update action
      if (!empty($zoomAccountByUser)
        && !empty($fields['id'])
        && $fields['id'] != $zoomAccountByUser ) {

        $duplicateAccount = TRUE;
      }

      // For create action
      if (!empty($zoomAccountByUser) && empty($fields['id'])) {
        $duplicateAccount = TRUE;
      }

      if ($duplicateAccount) {
        $userId = $fields['user_id'];
        $errors['user_id'] = ts("Duplicate User Email. User Email {$userId} already linked to other account. Please check the user email");
      }
    }
    return empty($errors) ? TRUE : $errors;
  }

  /**
   * @return array
   */
  public static function getEventCustomFields() {
    $cFields = array('' => '- select -');
    $cGroupResult = civicrm_api3('CustomGroup', 'get', array(
      'sequential' => 1,
      'extends' => "Event",
      'options' => array('limit' => 0),
    ));

    if (empty($cGroupResult['values'])) {
      return $cFields;
    }

    foreach ($cGroupResult['values'] as $cgKey => $cgValue) {
      $cFieldResult = civicrm_api3('CustomField', 'get', array(
        'sequential' => 1,
        'custom_group_id' => $cgValue['id'],
        'options' => array('limit' => 0),
      ));

      if (!empty($cFieldResult['values'])) {
        foreach ($cFieldResult['values'] as $cfKey => $cfValue) {
          $cFields[$cfValue['id']] = $cfValue['label'];
        }
      }
    }

    return $cFields;
  }

  public function postProcess() {
    $buttonName = $this->controller->getButtonName();
    $values = $this->exportValues();

    if ($buttonName == $this->getButtonName('upload', 'done')) {
      $result = self::testAPIConnectionSettings($this->_id);
      $redirectUrl = CRM_Utils_System::url('civicrm/Zoom/zoomaccounts', 'reset=1&act='.$this->_act."&id=".$this->_id);
    }
    else {
      $tableName = CRM_NcnCiviZoom_Constants::ZOOM_ACCOUNT_SETTINGS;

      if ($this->_act == CRM_Core_Action::UPDATE || $this->_act == CRM_Core_Action::ADD) {
        // Create or update the existing settings
        $oauth_client_id = $values['oauth_client_id'];
        $account_id = $values['account_id'];
        $zoom_name = $values['name'];
        $user_id = $values['user_id'];

        $queryParams = [
          1 => [$zoom_name, 'String'],
          2 => [$oauth_client_id, 'Positive'],
          3 => [$account_id, 'String'],
        ];

        if (($this->_act == CRM_Core_Action::UPDATE) && !empty($this->_id)) {
          $queryParams[4] = [$this->_id, 'Integer'];

          $extraParams = '';
          if (!empty($user_id)) {
            $extraParams = ", user_id = %5";
            $queryParams[5] = [$user_id, 'String'];
          }
          else {
            $extraParams = ", user_id = NULL";
          }

          $query = "UPDATE {$tableName} SET name = %1, oauth_client_id = %2, account_id = %3 {$extraParams} WHERE id = %4";
          CRM_Core_Dao::executeQuery($query, $queryParams);

          $result['message'] = ts('Zoom account settings have been updated');
          $result['type'] = 'success';
        }
        else {
          $extraColumn = $extraValue = '';
          if (!empty($user_id)) {
            $extraColumn = ", user_id";
            $extraValue = ", %4";
            $queryParams[4] = [$user_id, 'String'];
          }

          $query = "INSERT INTO {$tableName} (name, oauth_client_id, account_id {$extraColumn}) VALUES (%1, %2, %3 {$extraValue})";
          CRM_Core_Dao::executeQuery($query, $queryParams);
          $result['message'] = ts('Your new zoom account settings have been saved');
          $result['type'] = 'success';
        }
      }

      //Delete the zoom setting
      if(($this->_act == CRM_Core_Action::DELETE) && !empty($this->_id)){
        $queryParams = array(1 => array($this->_id, 'Integer'));
        $query = "DELETE FROM {$tableName} WHERE id=%1";
        CRM_Core_Dao::executeQuery($query, $queryParams);
        $result['message'] = ts($this->zoomName.' settings has been deleted');
        $result['type'] = 'success';
      }

      if(empty($this->_act) && empty($this->_id)){
        $zoomSettings = CRM_NcnCiviZoom_Utils::getZoomSettings();
        $zoomSettings['base_url']     = $values['base_url'];
        $zoomSettings['custom_field_id_webinar'] = $values['custom_field_id_webinar'];
        $zoomSettings['custom_field_id_meeting'] = $values['custom_field_id_meeting'];
        $zoomSettings['custom_field_account_id'] = $values['custom_field_account_id'];
        $zoomSettings['import_email_location_type'] = $values['import_email_location_type'];
        CRM_Core_BAO_Setting::setItem($zoomSettings, ZOOM_SETTINGS, 'zoom_settings');
        $result['message'] = ts('Your Settings have been saved');
        $result['type'] = 'success';
      }
      $redirectUrl    = CRM_Utils_System::url('civicrm/Zoom/zoomaccounts', 'reset=1');
    }

    CRM_Core_Session::setStatus($result['message'], ts('Zoom Account Settings'), $result['type']);
    CRM_Utils_System::redirect($redirectUrl);
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  public static function testAPIConnectionSettings($id = null) {
    if(empty($id)){
      $result = [
        'message' => 'Parameters missing',
        'type' => 'alert'
      ];
      return $result;
    }
    $settings = CRM_NcnCiviZoom_Utils::getZoomSettings($id);
    if(empty($settings['base_url'])){
      $result = [
        'message' => 'Base url is missing',
        'type' => 'alert'
      ];
      return $result;
    }

    $url = $settings['base_url'] . "/report/daily";
    $token = self::createOAuthToken($id);
    $params['year'] = date('Y');
    $params['month'] = date('m');
/* @todo Test fetching /account/me?
    $response = Zttp::withHeaders([
      'Content-Type' => 'application/json;charset=UTF-8',
      'Authorization' => "Bearer $token"
    ])->get($url, $params);
    // Alert to user on success.
    if ($response->isOk()) {
      $msg = "Connection settings are correct.";
      $type = 'success';
    } else {
      $result = $response->json();
      $msg = $result['message'];
      $type = 'alert';
    }
*/

    $status['message'] = $msg;
    $status['type'] = $type;
    return $status;
  }

  public static function createOAuthToken($id = null) {
    if(empty($id)){
      return null;
    }
    $settings = CRM_NcnCiviZoom_Utils::getZoomSettings($id);

    $client = \Civi\Api4\OAuthClient::get(FALSE)
      ->addWhere('id', '=', $settings['oauth_client_id'])
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->single();

    /** @var OAuthTokenFacade $tokenService */
    $tokenService = \Civi::service('oauth2.token');

    $tokenRecord = $tokenService->init(
      [
        'client' => $client,
        // 'scope' => 'foo',
        'tag' => NULL,
        'storage' => 'OAuthSysToken',
        'grant_type' => 'account_credentials',
        'cred' => ['account_id' => $settings['account_id']],
      ]
    );

    return $jwt;
  }

}
