<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 * $Id$
 *
 */

/**
 * This class provides the functionality to push event participants to zoom
 */
class CRM_NcnCiviZoom_Form_Task_AddToZoom extends CRM_Event_Form_Task {

  /**
   * Variable to store redirect path.
   * @var string
   */
  protected $_userContext;

  /**
   * Build all the data structures needed to build the form.
   *
   * @return void
   */
  public function preProcess() {
    // initialize the task and row fields
    parent::preProcess();

    $session = CRM_Core_Session::singleton();
    $this->_userContext = $session->readUserContext();
  }

  /**
   * Build the form object.
   *
   *
   * @return void
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Add Participant to Zoom'));
    $session = CRM_Core_Session::singleton();
    $this->addDefaultButtons(ts('Add to Zoom'), 'done');
    CRM_Core_Error::debug_var(__CLASS__.'::'.__FUNCTION__.' this->_participantIds', $this->_participantIds);
  }

  /**
   * Process the form after the input has been submitted and validated.
   *
   *
   * @return void
   */
  public function postProcess() {
    $params = $this->exportValues();
    $value = [];

    CRM_Core_Error::debug_var(__CLASS__.'::'.__FUNCTION__.' this->_participantIds', $this->_participantIds);
    foreach ($this->_participantIds as $participantId) {
      $status = CRM_NcnCiviZoom_Utils::pushToZoom($participantId);
    	CRM_Core_Error::debug_var(__CLASS__.'::'.__FUNCTION__.' status', $status);
    	if($status){
    		$alertType = 'success';
    		$message = 'Participant Added to Zoom.';
    	}else{
    		$alertType = 'alert';
    		$message = "Couln't be pushed to zoom.";
    	}
    	CRM_Core_Session::setStatus($message, ts('Push to Zoom'), $alertType);
    }
  }

}
