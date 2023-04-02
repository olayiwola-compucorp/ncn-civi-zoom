<?php
use CRM_NcnCiviZoom_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_NcnCiviZoom_Upgrader extends CRM_NcnCiviZoom_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  //Upgrade function to create a table to store multiple zoom accounts
  public function upgrade_1001(){
    $sql_file = 'sql/auto_install.sql';
    $this->executeSqlFile($sql_file);
    return TRUE;
  }

  //Upgrade function to add the existing  function to the table
  public function upgrade_1002(){
    $settings = CRM_NcnCiviZoom_Utils::getZoomSettings();
    if(!empty($settings['api_key'])
      && !empty($settings['secret_key'])){
      $tableName = CRM_NcnCiviZoom_Constants::ZOOM_ACCOUNT_SETTINGS;
      $query = "INSERT INTO {$tableName} (name, api_key, secret_key) VALUES (%1, %2 , %3)";
      $queryParams = array(
        1 => array('Existing account', 'String'),
        2 => array($settings['api_key'] , 'String'),
        3 => array($settings['secret_key'] , 'String')
      );
      CRM_Core_Dao::executeQuery($query, $queryParams);
      $settings['custom_field_id_webinar'] = $settings['custom_field_id'];
      unset($settings['custom_field_id']);
      unset($settings['api_key']);
      unset($settings['secret_key']);
      CRM_Core_BAO_Setting::setItem($settings, ZOOM_SETTINGS, 'zoom_settings');
    }
    return TRUE;
  }

  //Upgrade function to add the note type custom field to the events
  public function upgrade_1003(){
    CRM_NcnCiviZoom_Utils::forUpgrade1003();
    return TRUE;
  }

  //Upgrade function to add the email template id to zoom settings
  public function upgrade_1004(){
    CRM_NcnCiviZoom_Utils::forUpgrade1004();
    return TRUE;
  }

  //Upgrade function to add the email template id to zoom settings
  public function upgrade_1005(){
    $tableName = CRM_NcnCiviZoom_Constants::ZOOM_ACCOUNT_SETTINGS;
    if (!CRM_Core_DAO::checkFieldExists($tableName, 'user_id')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE {$tableName} ADD COLUMN user_id VARCHAR(128)");
    }
    return TRUE;
  }

  //Upgrade function to add the note type custom field to the events
  public function upgrade_1006(){
    CRM_NcnCiviZoom_Utils::forUpgrade1006();
    return TRUE;
  }

  //Upgrade function to add the custom field to store join_url
  public function upgrade_1007(){
    CRM_NcnCiviZoom_Utils::forUpgrade1007();
    return TRUE;
  }

  //Upgrade function to add the custom field to store zoom_participant_join_url
  public function upgrade_1008(){
    CRM_NcnCiviZoom_Utils::forUpgrade1008();
    return TRUE;
  }

  //Upgrade function to add create the zoom registrants table
  public function upgrade_1009(){
    CRM_NcnCiviZoom_Utils::forUpgrade1009();
    return TRUE;
  }

  //Upgrade function to add emailed column to the zoom registrants table
  public function upgrade_1010(){
    CRM_NcnCiviZoom_Utils::forUpgrade1010();
    return TRUE;
  }

  /**
   * Adds the new fields for OAuth configuration.
   * We are not deleting the JWT fields yet, in case someone wants to downgrade.
   */
  public function upgrade_1011() {
    CRM_Upgrade_Incremental_Base::addColumn($this->ctx, 'zoom_account_settings', 'oauth_client_id', 'int(11) default NULL');
    CRM_Upgrade_Incremental_Base::addColumn($this->ctx, 'zoom_account_settings', 'account_id', 'varchar(128)');
    return TRUE;
  }

  /**
   * Rename the zoom_account_settings accounts per conventions
   * (so that the table can have logging, if enabled)
   */
  public function upgrade_1012() {
    CRM_Core_DAO::executeQuery('RENAME TABLE zoom_account_settings TO civicrm_zoom_account');
    return TRUE;
  }

}
