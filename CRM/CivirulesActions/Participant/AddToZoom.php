<?php

use CRM_NcnCiviZoom_ExtensionUtil as E;
use CRM_NcnCiviZoom_Utils as CiviZoomUtils;

class CRM_CivirulesActions_Participant_AddToZoom extends CRM_Civirules_Action{

  /**
   * Method processAction to execute the action
   *
   * @param CRM_Civirules_TriggerData_TriggerData $triggerData
   * @access public
   *
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $contactId = $triggerData->getContactId();
    $event = $triggerData->getEntityData('Event');
    $webinar = $this->getWebinarID($event['id']);
    $participant = $this->getContactData($contactId);
    $meeting = $this->getMeetingID($event['id']);
    if (!empty($meeting)) {
      $this->addParticipant($participant, $meeting, $triggerData, 'Meeting');
    }
    elseif (!empty($webinar)) {
      $this->addParticipant($participant, $webinar, $triggerData, 'Webinar');
    }
  }

  /**
   * Get an event's webinar id
   * @param  int $event The event's id
   * @return string The event's webinar id
   */
  private function getWebinarID($event) {
    $result = null;
    $customField = CiviZoomUtils::getWebinarCustomField();
    try {
      $apiResult = civicrm_api3('Event', 'get', [
        'sequential' => 1,
        'return' => [$customField],
        'id' => $event,
      ]);
      $result = null;
      if(!empty($apiResult['values'][0][$customField])){
        // Remove any empty spaces
        $result = trim($apiResult['values'][0][$customField]);
        $result = str_replace(' ', '', $result);
      }
    } catch (Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
   * Get an event's Meeting id
   * @param  int $event The event's id
   * @return string The event's Meeting id
   */
  private function getMeetingID($event) {
    $result;
    $customField = CiviZoomUtils::getMeetingCustomField();
    try {
      $apiResult = civicrm_api3('Event', 'get', [
        'sequential' => 1,
        'return' => [$customField],
        'id' => $event,
      ]);
      $result = null;
      if(!empty($apiResult['values'][0][$customField])){
        // Remove any empty spaces
        $result = trim($apiResult['values'][0][$customField]);
        $result = str_replace(' ', '', $result);
      }
    } catch (Exception $e) {
      throw $e;
    }

    return $result;
  }

  /**
   * Get given contact's email, first_name, last_name,
   * city, state/province, country, post code
   *
   * @param int $id An existing CiviCRM contact id
   *
   * @return array Retrieved contact info
   */
  private function getContactData($id) {
    $result = [];

    try {
      $result = civicrm_api3('Contact', 'get', [
        'sequential' => 1,
        'return' => ["email", "first_name", "last_name", "street_address", "city", "state_province_name", "country", "postal_code"],
        'id' => $id,
      ])['values'][0];
    } catch (Exception $e) {
      watchdog(
        'NCN-Civi-Zoom CiviRules Action (AddToZoom)',
        'Something went wrong with getting contact data.',
        array(),
        WATCHDOG_INFO
      );
    }

    return $result;
  }

  /**
   * Add's the given participant data as a single participant
   * to a Zoom Webinar/Meeting with the given id.
   *
   * @param array $participant participant data where email, first_name, and last_name are required
   * @param int $entityID id of an existing Zoom webinar/meeting
   * @param string $entity 'Meeting' or 'Webinar'
   */
  private function addParticipant($participant, $entityID, $triggerData, $entity) {
    $event = $triggerData->getEntityData('Event');
    $accountId = CiviZoomUtils::getZoomAccountIdByEventId($event['id']);
    $settings = CiviZoomUtils::getZoomSettings();
    $url = CiviZoomUtils::convertEntityToURL($entity) . "/$entityID/registrants";

    [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $url, $participant, 'post');

    if(!empty($result['join_url'])){
      $participantId = $triggerData->getEntityData('Participant')['participant_id'];
      CiviZoomUtils::updateZoomParticipantJoinLink($participantId, $result['join_url']);
    }
    if(!empty($result['registrant_id'])){
      $participantId = $triggerData->getEntityData('Participant')['participant_id'];
      CiviZoomUtils::updateZoomParticipantRegistrantId($participantId, $result['registrant_id']);
    }

    // Added the registrant_id and event_id to the log
    $msg = "Event Id is ".$event['id']. ". ";
    if(!empty($result['registrant_id'])){
      $msg .= "Registrant Id is ".$result['registrant_id']. ". ";
    }
    // Alert to user on success.
    if ($isResponseOK) {
      $firstName = $participant['first_name'];
      $lastName = $participant['last_name'];
      $msg .= 'Participant Added to Zoom. $entity ID: '.$entityID;
      $this->logAction($msg, $triggerData, \PSR\Log\LogLevel::INFO);

      CRM_Core_Session::setStatus(
        "$firstName $lastName was added to Zoom $entity $entityID.",
        E::ts('Participant added!'),
        'success'
      );
    } else {
      $msg .= $result['message'].' $entity ID: '.$entityID;
      $this->logAction($msg, $triggerData, \PSR\Log\LogLevel::ALERT);
    }
  }

  public static function getJoinUrl($object){
    $eventId = $object->event_id;
    $accountId = CiviZoomUtils::getZoomAccountIdByEventId($eventId);
    $settings = CiviZoomUtils::getZoomSettings();
    $webinar = $object->getWebinarID($eventId);
    $meeting = $object->getMeetingID($eventId);
    $url = '';
    $eventType = '';
    if(!empty($meeting)){
      $url = "meetings/$meeting";
      $eventType = 'Meeting';
    } elseif (!empty($webinar)) {
      $url = "webinars/$webinar";
      $eventType = 'Webinar';
    } else {
      return [null, null, null];
    }

    [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $url);

    if (empty($result['join_url'])) {
      Civi::log()->warning("ncn-civi-zoom: AddToZoom: join_url for $url was empty");
      return [null, null, null];
    }

    $joinUrl = $result['join_url'];
    $registrationUrl = $result['registration_url'];
    $password = isset($result['password'])? $result['password'] : '';
    return [$joinUrl, $password, $eventType, $registrationUrl];
  }

  /**
   *
   */
  public static function checkEventWithZoom($params) {
    if(empty($params) || empty($params["account_id"])
      || empty($params["entityID"])
      || empty($params["entity"])){
      return ['status' => null , 'message' => "Parameters missing"];
    }

    $object = new CRM_CivirulesActions_Participant_AddToZoom;
    $url = CiviZoomUtils::convertEntityToURL($params['entity']) . '/' . $params['entityID'];

    // Additional Check by user_id if configured in settings
    $userID = CRM_Utils_Array::value('user_id', $settings);
    if (!empty($userID)) {
      // Does Meeting/Webinar id belong to the given user? If not return validation error.
      $userParams = $params;
      $userParams['user_id'] = $userID;
      $userDetails = CiviZoomUtils::validateMeetingWebinarByUserId($userParams);

      // If we cannot find user details then return error
      if (empty($userDetails)) {
        return ["status" => 0, "message" => "Please verify the User ID"];
      }
      // else if user id exists and meeting/webinar not belong to this user then return.
      elseif (!empty($userDetails['message'])) {
        return ["status" => 0, "message" => $userDetails['message']];
      }
    }

    [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($params['account_id'], $url);

    if ($isResponseOK) {
      if(!empty($result['registration_url'])){
        $return = array("status" => 1, "message" => $params["entity"]." has been verified");
      }else{
        $return = array("status" => 0, "message" => "Please enable the Registration as required for the Zoom ".$params["entity"].": ".$params["entityID"]);
      }
    }
    else {
      $return = array("status" => 0, "message" => $params["entity"]." does not belong to the ".$settings['name']);
    }

    // Check for additional fields enabled
    if ($return['status']) {
      $url = CiviZoomUtils::convertEntityToURL($params['entity']) . '/registrants/questions';
      [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($params['account_id'], $url);

      if ($isResponseOK) {
        // Checking for fields other than last_name
        foreach ($result['questions'] as $question) {
          if($question['field_name'] != 'last_name' && $question['required']){
            $return['status'] = -1;
            $return['message'] = $params["entity"]." has been verified. But participants may not be added to zoom as additional fields are marked as required in zoom.";
          }
        }
        // Checking for custom fields
        foreach ($result['custom_questions'] as $custom_question) {
          if($custom_question['required']){
            $return['status'] = -1;
            $return['message'] = $params["entity"]." has been verified. But participants may not be added to zoom as custom questions are marked as required in zoom.";
          }
        }
      }
    }

    return $return;
  }

  public static function getZoomRegistrants($eventId, $pageSize = 150){
    if(empty($eventId)){
      return [];
    }
    $object = new CRM_CivirulesActions_Participant_AddToZoom;
    $webinarId = $object->getWebinarID($eventId);
    $meetingId = $object->getMeetingID($eventId);
    $zoomRegistrantsList = [];
    if(empty($webinarId) && empty($meetingId)){
      return $zoomRegistrantsList;
    }
    $url = '';
    $accountId = CiviZoomUtils::getZoomAccountIdByEventId($eventId);
    $settings = CiviZoomUtils::getZoomSettings();
    CiviZoomUtils::checkPageSize($pageSize);
    if(!empty($meetingId)){
      $url = "meetings/$meetingId/registrants?page_size=$pageSize";
    } elseif (!empty($webinarId)) {
      $url = "webinars/$webinarId/registrants?page_size=$pageSize";
    }

    $result = [];
    $page = 1;
    $next_page_token = null;

    do {
      $fetchUrl = $url . $next_page_token;
      [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $fetchUrl);

      if (!empty($result['registrants'])) {
        $zoomRegistrantsList = array_merge($zoomRegistrantsList, $result['registrants']);
      }
      $next_page_token = '&next_page_token='.$result['next_page_token'];
    } while ($result['next_page_token']);

    return $zoomRegistrantsList;
  }

  public static function getZoomAttendeeOrAbsenteesList($eventId, $pageSize = 300){
    if (empty($eventId)) {
      return [];
    }
    $object = new CRM_CivirulesActions_Participant_AddToZoom;
    $webinarId = $object->getWebinarID($eventId);
    $meetingId = $object->getMeetingID($eventId);
    $returnZoomList = [];
    if(empty($webinarId) && empty($meetingId)) {
      return $returnZoomList;
    }
    $url = $array_name = $key_name = '';
    $urls = [];
    $accountId = CiviZoomUtils::getZoomAccountIdByEventId($eventId);
    $settings = CiviZoomUtils::getZoomSettings();
    CiviZoomUtils::checkPageSize($pageSize);

    if (!empty($meetingId)) {
      $array_name = 'participants';
      $key_name = 'user_email';

      // Get meeting instances
      [$isResponseOK, $instances_result] = CiviZoomUtils::zoomApiRequest($accountId, "past_meetings/$meetingId/instances");

      foreach ($instances_result['meetings'] as $key => $instance) {
        $urls[] = "past_meetings/" . urlencode(urlencode($instance['uuid'])) . "/participants?&page_size=".$pageSize;
      }
   }
   elseif (!empty($webinarId)) {
     $url = "past_webinars/$webinarId/absentees?&page_size=$pageSize";
     $urls = [$url];
     $array_name = 'absentees';
     $key_name = 'email';
   }

   foreach ($urls as $key => $url) {
     $result = [];
     $next_page_token = null;
     do {
       $fetchUrl = $url . $next_page_token;
       [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $fetchUrl);

       // Zoom Webinars returns registrants so test for absentees and then registrants
       if (!isset($result[$array_name]) && isset($result['registrants'])) {
         $array_name = 'registrants';
       }

       if (!empty($result[$array_name])) {
         $list = $result[$array_name];
         foreach ($list as $item) {
           $returnZoomList[] = $item[$key_name];
         }
       }
       $next_page_token = '&next_page_token='.$result['next_page_token'];
     } while ($result['next_page_token']);
   }

   return $returnZoomList;
  }

  /**
   *
   * @param $eventId type-integer
   *
   * @return $returnZoomList type-array of zoom participants data
   */
  public static function getZoomParticipantsData($eventId, $pageSize = 150){
    if(empty($eventId)){
      return [];
    }
    $object = new CRM_CivirulesActions_Participant_AddToZoom;
    $webinarId = $object->getWebinarID($eventId);
    $meetingId = $object->getMeetingID($eventId);
    $returnZoomList = [];
    if (empty($webinarId) && empty($meetingId)){
      return $returnZoomList;
    }

    $url = $array_name = $key_name = '';
    $accountId = CiviZoomUtils::getZoomAccountIdByEventId($eventId);
    $settings = CiviZoomUtils::getZoomSettings();
    CiviZoomUtils::checkPageSize($pageSize);
    if (!empty($meetingId)){
      // Calling Meeting participants report api
      $url = "report/meetings/$meetingId/participants?&page_size=$pageSize";
      $array_name = 'participants';
      $key_name = 'user_email';
    } elseif (!empty($webinarId)) {
     // Calling Webinar absentees api
     $url = "past_webinars/$webinarId/absentees?&page_size=$pageSize";
     $array_name = 'absentees';
     $key_name = 'email';
   }

   $result = [];
   $next_page_token = null;

   do {
     $fetchUrl = $url.$next_page_token;
     [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $fetchUrl);

     if (!empty($result[$array_name])) {
       $list = $result[$array_name];
       foreach ($list as $item) {
         $returnZoomList[$item[$key_name]][] = $item;
       }
     }
     $next_page_token = '&next_page_token='.$result['next_page_token'];
   } while ($result['next_page_token']);

   if (!empty($webinarId)) {
     // Calling Webinar participants report api also
     $url = "report/webinars/$webinarId/participants?&page_size=$pageSize";
     $array_name = 'participants';
     $key_name = 'user_email';
     $result = [];
     $next_page_token = null;

     do {
       $fetchUrl = $url . $next_page_token;
       [$isResponseOK, $result] = CiviZoomUtils::zoomApiRequest($accountId, $fetchUrl);

       // Switch over to registrants b/c zoom is returning that as the array key
       if (empty($result[$array_name]) && !empty($result['registrants'])) {
         $array_name = 'registrants';
       }

       if (!empty($result[$array_name])) {
         $list = $result[$array_name];
         foreach ($list as $item) {
           // Webinars seem to be returning email key not user_email
           if (!empty($item['user_email'])) {
             $returnZoomList[$item['user_email']][] = $item;
           } elseif (!empty($item['email'])) {
             $returnZoomList[$item['name']][] = $item;
           }
         }
       }
       $next_page_token = '&next_page_token='.$result['next_page_token'];
     } while ($result['next_page_token']);
   }

    return $returnZoomList;
  }

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   * @access public
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return FALSE;
  }

}
