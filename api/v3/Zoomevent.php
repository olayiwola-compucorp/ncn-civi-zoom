<?php
use CRM_Ncnciviapi_ExtensionUtil as E;

use Firebase\JWT\JWT;
use Zttp\Zttp;


/**
 * Participant.GenerateWebinarAttendance specification
 *
 * Makes sure that the verification token is provided as a parameter
 * in the request to make sure that request is from a reliable source.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_zoomevent_generatezoomattendance_spec(&$spec) {
	$spec['days'] = [
    'title' => 'Select Events ended in past x Days',
    'description' => 'Events ended how many days before you need to select?',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
}

/**
 * Participant.GenerateWebinarAttendance API
 *
 * Designed to be called by a Zoom Event Subscription (event: webinar.ended).
 * Once invoked, it gets the absent registrants from the webinar that just ended.
 *
 * Then, it gets the event associated with the webinar, as well as, the
 * registered participants of the event.
 *
 * Absent registrants are then subtracted from registered participants and,
 * the remaining participants' statuses are set to Attended.
 *
 * @param array $params
 *
 * @return array
 *   Array containing data of found or newly created contact.
 *
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_zoomevent_generatezoomattendance($params) {
	$allAttendees = [];
	$days = $params['days'];
	$pastDateTimeFull = new DateTime();
	$pastDateTimeFull = $pastDateTimeFull->modify("-".$days." days");
	$pastDate = $pastDateTimeFull->format('Y-m-d');
	$currentDate = date('Y-m-d');

//CRM_Core_Error::debug_var('pastDate', $pastDate);
//CRM_Core_Error::debug_var('currentDate', $currentDate);

// When $pastDate == $currentDate nothing is returned by api call
// I would expect today's events to be returned but then they might not be finished
  $apiResult = civicrm_api3('Event', 'get', [
    'sequential' => 1,
		'limit' => 0,
    'end_date' => ['BETWEEN' => [$pastDate, $currentDate]],
  ]);
	$allEvents = $apiResult['values'];
	$eventIds = [];
	foreach ($allEvents as $key => $value) {
		$eventIds[] = $value['id'];
	}
	foreach ($eventIds as $eventId) {
		CRM_Core_Error::debug_var('eventId', $eventId);
		$list = CRM_CivirulesActions_Participant_AddToZoom::getZoomAttendeeOrAbsenteesList($eventId);
		if(empty($list)){
			continue;
		}
		$webinarId = getWebinarID($eventId);
		$meetingId = getMeetingID($eventId);
		if(!empty($webinarId)){
			$attendees = selectAttendees($list, $eventId, "Webinar");
		}elseif(!empty($meetingId)){
			$attendees = selectAttendees($list, $eventId, "Meeting");
		}
		updateAttendeesStatus($attendees, $eventId);
		$allAttendees[$eventId] = $attendees;
	}
	$return['allAttendees'] = $allAttendees;

	return civicrm_api3_create_success($return, $params, 'Event');
}

/**
 * Queries for the registered participants that weren't absent
 * during the webinar.
 * @param  array $absenteesEmails emails of registrants absent from the webinar
 * @param  int $event the id of the webinar's associated event
 * @return array participants (email, participant_id, contact_id) who weren't absent
 */
function selectAttendees($emails, $event, $entity = "Webinar") {
	// Preparing the query params
	$selectEmailString = '';
	$qParams = $selectEmails = array();
	$i = 1;
	foreach ($emails as $email) {
		if(!empty($email)){
			$qParams[$i] = array($email, 'String');
			$selectEmails[] = '%'.$i;
			$i++;
		}
	}
	$selectEmailString = join(', ', $selectEmails);

	if($entity == "Webinar"){
		// $absenteesEmails = join("','",$emails);

		$selectAttendees = "
			SELECT
				e.email,
				p.contact_id,
				p.id AS participant_id
			FROM civicrm_participant p
			LEFT JOIN civicrm_email e ON p.contact_id = e.contact_id
			WHERE
				e.email NOT IN ($selectEmailString) AND
				p.event_id = {$event}";
	}elseif($entity == "Meeting"){
		// $attendeesEmails = join("','",$emails);

		$selectAttendees = "
			SELECT
				e.email,
				p.contact_id,
				p.id AS participant_id
			FROM civicrm_participant p
			LEFT JOIN civicrm_email e ON p.contact_id = e.contact_id
			WHERE
				e.email IN ($selectEmailString) AND
				p.event_id = {$event}";
	}
	// Run query
	$query = CRM_Core_DAO::executeQuery($selectAttendees, $qParams);

	$attendees = [];

	while($query->fetch()) {
		array_push($attendees, [
			'email' => $query->email,
			'contact_id' => $query->contact_id,
			'participant_id' => $query->participant_id
		]);
	}

	return $attendees;
}

/**
 * Set the status of the registrants who weren't absent to Attended.
 * @param  array $attendees registrants who weren't absent
 * @param  int $event the event associated with the webinar
 *
 */
function updateAttendeesStatus($attendees, $event) {
	foreach($attendees as $attendee) {
		$rr = civicrm_api3('Participant', 'create', [
		  'event_id' => $event,
		  'id' => $attendee['participant_id'],
		  'status_id' => "Attended",
		]);
	}
}


/**
 * Get an event's webinar id
 * @param  int $event The event's id
 * @return string The event's webinar id
 */
function getWebinarID($eventId) {
	$result;
	$customField = CRM_NcnCiviZoom_Utils::getWebinarCustomField();
	try {
		$apiResult = civicrm_api3('Event', 'get', [
		  'sequential' => 1,
		  'return' => [$customField],
		  'id' => $eventId,
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
function getMeetingID($eventId) {
	$result;
	$customField = CRM_NcnCiviZoom_Utils::getMeetingCustomField();
	try {
		$apiResult = civicrm_api3('Event', 'get', [
		  'sequential' => 1,
		  'return' => [$customField],
		  'id' => $eventId,
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
 * Get Recent Zoom registrants specs
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_zoomevent_getrecentzoomregistrants_spec(&$spec) {
	$spec['mins'] = [
    'title' => 'How many minutes before?',
    'description' => 'Enter the minutes, as you want the notification of the zoom registrants. By default it will be 60 minutes.',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 0,
  ];

	$spec['to_emails'] = [
    'title' => 'Email address',
    'description' => 'Enter the Email addresses(seperated by comma) to which you want the regitrants list to be sent.',
    'type' => CRM_Utils_Type::T_TEXT,
    'api.required' => 0,
  ];
}



/**
 * Get Recent Zoom registrants
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function civicrm_api3_zoomevent_getrecentzoomregistrants($params) {
	if(empty($params['mins'])){
		$params['mins'] = 60;
	}

	$result = [];

	$events = CRM_NcnCiviZoom_Utils::getUpcomingEventsList();
	foreach ($events as $key => $event) {
		$registrantsList = CRM_CivirulesActions_Participant_AddToZoom::getZoomRegistrants($event['id']);
		if(!empty($registrantsList)){
			CRM_NcnCiviZoom_Utils::insertZoomRegistrantsInToCivi($event['id'], $registrantsList);
			CRM_NcnCiviZoom_Utils::updateMissingZoomRegistrantsStatusToCancelledThroughZoom($event['id'], $registrantsList);
			$registrantsEmailList = $registrantsListConsolidated = array();
			foreach ($registrantsList as $registrant) {
				$registrantsListConsolidated[$registrant['email']] = $registrant;
				$registrantsEmailList[]	= $registrant['email'];
			}
			$participantDetails = selectZoomParticipants($registrantsEmailList, $event['id']);
			// Updating the zoom participant join link
			foreach ($participantDetails as $participantDetail) {
				CRM_NcnCiviZoom_Utils::updateZoomParticipantJoinLink($participantDetail['participant_id'], $registrantsListConsolidated[$participantDetail['email']]['join_url']);
			}
			$recentRegistrants = CRM_NcnCiviZoom_Utils::filterZoomRegistrantsByTime($registrantsList, $params['mins']);
			//CRM_Core_Error::debug_var('recentRegistrants', $recentRegistrants);
			if(!empty($recentRegistrants)){
				$notesUpdateMessage = CRM_NcnCiviZoom_Utils::updateZoomRegistrantsToNotes($event['id'], $registrantsList);
				$result[$event['id']]['Notes Update Message'] = $notesUpdateMessage;
				$recentRegistrantsForEmail = CRM_NcnCiviZoom_Utils::filterRegistrantsIfEmailed($event['id'], $recentRegistrants);
				if(!empty($params['to_emails']) && !empty($recentRegistrantsForEmail)){
					$emailSentDetails = CRM_NcnCiviZoom_Utils::sendZoomRegistrantsToEmail($params['to_emails'], $recentRegistrantsForEmail, $event);
					if($emailSentDetails['status']){
						CRM_NcnCiviZoom_Utils::setZoomRegistrantAsEmailed($event['id'], $recentRegistrants);
					}
					$result[$event['id']]['Email sent Update'] = $emailSentDetails;
				}
			}else{
				$result[$event['id']]['Notes Update Message'] = 'No recent registrants to update.';
			}
		}else{
			$result[$event['id']]['Message'] = 'No Registrants to Update';
		}
	}

	return civicrm_api3_create_success($result, $params, 'Event');
}


/**
 * Participant.Sync Zoom Data specification
 *
 * Makes sure that the verification token is provided as a parameter
 * in the request to make sure that request is from a reliable source.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_zoomevent_synczoomdata_spec(&$spec) {
	$spec['days'] = [
    'title' => 'Select Events ended in past x Days',
    'description' => 'Events ended how many days before you need to select?',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];
}


/**
 * Sync Zoom Webinar Participants Data
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function civicrm_api3_zoomevent_synczoomdata($params) {
	$allAttendees = [];
	$days = $params['days'];
	$pastDateTimeFull = new DateTime();
	$pastDateTimeFull = $pastDateTimeFull->modify("-".$days." days");
	$pastDate = $pastDateTimeFull->format('Y-m-d');
	$currentDate = date('Y-m-d');

  $apiResult = civicrm_api3('Event', 'get', [
    'sequential' => 1,
    'end_date' => ['BETWEEN' => [$pastDate, $currentDate]],
  ]);
	$allEvents = $apiResult['values'];
	$eventIds = [];
	foreach ($allEvents as $key => $value) {
		$eventIds[] = $value['id'];
	}
	$allUpdatedParticpants = [];
	foreach ($eventIds as $eventId) {
		$updatedParticpants = [];
		$list = CRM_CivirulesActions_Participant_AddToZoom::getZoomParticipantsData($eventId);
		if(empty($list)){
			continue;
		}

		$consolidatedList = [];
		foreach ($list as $email => $participant) {
			$participantDetails = $participant[0];
			// Picking the first entry time
			$firstEntry = key(array_slice($participant, 0, 1, true));
			// Picking the last last leaving time
			$lastEntry = key(array_slice($participant, -1, 1, true));
			$participantDetails['join_time'] = $participant[$firstEntry]['join_time'];
			$participantDetails['leave_time'] = $participant[$lastEntry]['leave_time'];
			$totalDuration = 0;
			foreach ($participant as $key => $eachJoin) {
				$totalDuration += $eachJoin['duration'];
				$participantDetails['duration_'.($key+1)] = $eachJoin['duration'];
			}
			$participantDetails['duration'] = $totalDuration;
			$consolidatedList[$email] = $participantDetails;
			$consolidatedList[$email]['has_civi_record'] = FALSE;
		}

		$emails = [];
		foreach ($consolidatedList as $key => $value) {
			$emails[] = $key;
		}
		$webinarId = getWebinarID($eventId);
		$meetingId = getMeetingID($eventId);
		if(!empty($webinarId)){
			$attendees = selectZoomParticipants($emails, $eventId);
		}elseif(!empty($meetingId)){
			$attendees = selectZoomParticipants($emails, $eventId);
		}
		foreach ($attendees as $attendee) {
			$updatedParticpants[$attendee['participant_id']] = CRM_NcnCiviZoom_Utils::updateZoomParticipantData($attendee['participant_id'], $consolidatedList[$attendee['email']]);
			$consolidatedList[$attendee['email']]['has_civi_record'] = TRUE;
		}
		$allUpdatedParticpants[$eventId] = $updatedParticpants;
		// Collecting the unmatched participants as exceptions
		$exceptionsArray = array();
		foreach ($consolidatedList as $value) {
			if(!$value['has_civi_record']){
				$exceptionsArray[] = $value;
			}
		}
		if(!empty($exceptionsArray)){
			$return['exception_notes'][$eventId] = CRM_NcnCiviZoom_Utils::updateUnmatchedZoomParticipantsToNotes($eventId, $exceptionsArray);
		}
	}

	$return['all_updated_participants'] = $allUpdatedParticpants;

	return civicrm_api3_create_success($return, $params, 'Event');
}


/**
 * Selects the zoom participants for for the event(webinar/meeting) using the given array of emails
 *
 * @param  array emails of registrants from the webinar/meeting
 * @param  int $event the id of the webinar's/meeting's associated event
 * @return array of zoom webinar/meeting registrants in the civi (email, participant_id, contact_id)
 */
function selectZoomParticipants($emails, $event) {
	// Preparing the query params
	$selectEmailString = '';
	$qParams = $selectEmails = array();
	$i = 1;
	foreach ($emails as $email) {
		if(!empty($email)){
			$qParams[$i] = array($email, 'String');
			$selectEmails[] = '%'.$i;
			$i++;
		}
	}
	$selectEmailString = join(', ', $selectEmails);

	$selectAttendees = "
		SELECT
			e.email,
			p.contact_id,
			p.id AS participant_id
		FROM civicrm_participant p
		LEFT JOIN civicrm_email e ON p.contact_id = e.contact_id
		WHERE
			e.email IN ($selectEmailString) AND
			p.event_id = {$event}";

	// Run query
	$query = CRM_Core_DAO::executeQuery($selectAttendees, $qParams);

	$attendees = [];

	while($query->fetch()) {
		array_push($attendees, [
			'email' => $query->email,
			'contact_id' => $query->contact_id,
			'participant_id' => $query->participant_id
		]);
	}

	return $attendees;
}
