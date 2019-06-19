<?php

use srag\DIC\OpenCast\DICTrait;

/**
 * Class xoctEventFormGUI
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
class xoctEventFormGUI extends ilPropertyFormGUI {


	use DICTrait;
	const PLUGIN_CLASS_NAME = ilOpenCastPlugin::class;

	const F_TITLE = 'title';
	const F_DESCRIPTION = 'description';
	const F_FILE_PRESENTER = 'file_presenter';
	const F_FILE_PRESENTATION = 'file_presenter';
	const F_IDENTIFIER = 'identifier';
	const F_CREATOR = 'creator';
	const F_DURATION = 'duration';
	const F_PROCESSING_STATE = 'processing_state';
	const F_START_TIME = 'start_time';
	const F_PRESENTERS = 'presenters';
	const F_START = 'start';
	const F_END = 'end';
	const F_LOCATION = 'location';
	const F_SOURCE = 'source';
	const F_WORKFLOW_PARAMETER = 'workflow_parameter';
	const F_ONLINE = 'online';
	const F_MULTIPLE = 'multiple';
	const F_MULTIPLE_START = 'multiple_start';
	const F_MULTIPLE_START_TIME = 'multiple_start_time';
	const F_MULTIPLE_END = 'multiple_end';
	const F_MULTIPLE_END_TIME = 'multiple_end_time';
	const F_MULTIPLE_WEEKDAYS = 'multiple_weekdays';

	/**
	 * @var  xoctEvent
	 */
	protected $object;
	/**
	 * @var xoctEventGUI
	 */
	protected $parent_gui;
	/**
	 * @var bool
	 */
	protected $external = true;
	/**
	 * @var bool
	 */
	protected $schedule;


	/**
	 * @param              $parent_gui
	 * @param xoctEvent $object
	 * @param xoctOpenCast $xoctOpenCast
	 * @param bool|false $view
	 * @param bool|false $infopage
	 * @param bool|true $external
	 */
	public function __construct($parent_gui, xoctEvent $object, xoctOpenCast $xoctOpenCast, $schedule = false,$view = false, $infopage = false, $external = true) {
		parent::__construct();
		$this->object = $object;
		$this->xoctOpenCast = $xoctOpenCast;
		$this->parent_gui = $parent_gui;
		self::dic()->ctrl()->saveParameter($parent_gui, xoctEventGUI::IDENTIFIER);
		$this->is_new = ($this->object->getIdentifier() == '');
		$this->schedule = $schedule;
		$this->view = $view;
		$this->infopage = $infopage;
		$this->external = $external;
		self::dic()->language()->loadLanguageModule('form');
		$this->setId('xoct_event');

		if ($view) {
			$this->initView();
		} else {
			$this->initForm();
		}
	}


	public function setValuesByPost() {
		/**
		 * @var $item ilTextInputGUI
		 */
		foreach ($this->getItems() as $item) {
			if ($item->getPostVar() != self::F_START) {
				$item->setValueByArray($_POST);
			}
		}
	}


	protected function initForm() {
		$this->setTarget('_top');
		$this->setFormAction(self::dic()->ctrl()->getFormAction($this->parent_gui));
		$this->initButtons();

		$te = new ilTextInputGUI($this->txt(self::F_TITLE), self::F_TITLE);
		$te->setRequired(!$this->is_new || $this->schedule);
		$this->addItem($te);

		if ($this->is_new && !$this->schedule) {
			$allow_audio = xoctConf::getConfig(xoctConf::F_AUDIO_ALLOWED);

			$te = new xoctFileUploadInputGUI($this, xoctEventGUI::CMD_CREATE, $this->txt(self::F_FILE_PRESENTER . ($allow_audio ? '_w_audio' : '')), self::F_FILE_PRESENTER);
			$te->setUrl(self::dic()->ctrl()->getLinkTarget($this->parent_gui, xoctEventGUI::CMD_UPLOAD_CHUNKS));
			$te->setSuffixes($allow_audio ? array(
				'mov',
				'mp4',
				'm4v',
				'flv',
				'mpeg',
				'avi',
				'mp4',
				'mp3',
				'm4a',
				'wma',
				'aac',
				'ogg',
				'flac',
				'aiff',
				'wav'
			) : array(
				'mov',
				'mp4',
				'm4v',
				'flv',
				'mpeg',
				'avi',
				'mp4',
			));
			$te->setMimeTypes($allow_audio ? array(
				'video/avi',
				'video/quicktime',
				'video/mpeg',
				'video/mp4',
				'video/ogg',
				'video/webm',
				'video/x-ms-wmv',
				'video/x-flv',
				'video/x-matroska',
				'video/x-msvideo',
				'video/x-dv',
				'audio/mp4',
				'audio/x-m4a',
				'audio/ogg',
				'audio/mpeg',
				'audio/mp3',
				'audio/x-aiff',
				'audio/aiff',
				'audio/x-wav',
				'audio/wav',
				'audio/aac',
				'audio/flac',
				'audio/x-ms-wma',
				'audio/basic'
			) : array(
				'video/avi',
				'video/quicktime',
				'video/mpeg',
				'video/mp4',
				'video/ogg',
				'video/webm',
				'video/x-ms-wmv',
				'video/x-flv',
				'video/x-matroska',
				'video/x-msvideo',
				'video/x-dv',
			));
			$te->setRequired(true);
			$this->addItem($te);
		}

		$te = new ilTextAreaInputGUI($this->txt(self::F_DESCRIPTION), self::F_DESCRIPTION);
		$this->addItem($te);

		$te = new ilTextInputGUI($this->txt(self::F_PRESENTERS), self::F_PRESENTERS);
		$te->setRequired(true);
		$this->addItem($te);


		// show location and start date for scheduled events only if configured
        $date_and_location_disabled = $this->object->isScheduled() && xoctConf::getConfig(xoctConf::F_SCHEDULED_METADATA_EDITABLE) == xoctConf::METADATA_EXCEPT_DATE_PLACE;

		if (xoct::isApiVersionGreaterThan('v1.1.0') && ($this->schedule || $this->object->isScheduled())) {
			$input = new ilSelectInputGUI($this->txt(self::F_LOCATION), self::F_LOCATION);
			$options = array();
			/** @var xoctAgent $agent */
			foreach (xoctAgent::getAllAgents() as $agent) {
				$options[$agent->getAgentId()] = $agent->getAgentId();
			}
			$input->setOptions($options);
		} else {
			$input = new ilTextInputGUI($this->txt(self::F_LOCATION), self::F_LOCATION);
		}
		$input->setDisabled($date_and_location_disabled);
		$this->addItem($input);

		if (!$this->schedule) {
			$date = new ilDateTimeInputGUI($this->txt(self::F_START), self::F_START);
			if (!xoct::isIlias52()) {
				$date->setMode(ilDateTimeInputGUI::MODE_INPUT);
			}
			$date->setShowTime(true);
			$date->setShowSeconds(false);
			$date->setMinuteStepSize(1);
			$date->setDisabled($date_and_location_disabled);
			$date->setRequired(true);
			$this->addItem($date);
		}

		if ($this->object->isScheduled() && !$this->schedule) {
			$date = new ilDateTimeInputGUI($this->txt(self::F_END), self::F_END);
			if (!xoct::isIlias52()) {
				$date->setMode(ilDateTimeInputGUI::MODE_INPUT);
			}
			$date->setShowTime(true);
			$date->setShowSeconds(false);
			$date->setMinuteStepSize(1);
			$date->setDisabled($date_and_location_disabled);
            $date->setRequired(true);
            $this->addItem($date);
		}

		if ($this->schedule) {
			$radio = new ilRadioGroupInputGUI($this->txt(self::F_MULTIPLE), self::F_MULTIPLE);

			// SINGLE EVENT
			$opt = new ilRadioOption(self::dic()->language()->txt('no'), 0);

			$date = new ilDateTimeInputGUI($this->txt(self::F_START), self::F_START);
			$date->setShowTime(true);
			$date->setShowSeconds(false);
			$date->setMinuteStepSize(1);
            $date->setDate(new ilDateTime(time(), IL_CAL_UNIX), IL_CAL_DATETIME);
            $date->setRequired(true);
            $opt->addSubItem($date);

			$date = new ilDateTimeInputGUI($this->txt(self::F_END), self::F_END);
			$date->setShowTime(true);
			$date->setShowSeconds(false);
			$date->setMinuteStepSize(1);
			$date->setDate(new ilDateTime(time(), IL_CAL_UTC));
            $date->setRequired(true);
			$opt->addSubItem($date);

			$radio->addOption($opt);

			// MULTIPLE EVENTS
			$opt = new ilRadioOption(self::dic()->language()->txt('yes'), 1);

			$subinput = new ilDateTimeInputGUI($this->txt(self::F_MULTIPLE_START), self::F_MULTIPLE_START);
			$subinput->setRequired(true);
			$opt->addSubItem($subinput);

			$subinput = new ilDateTimeInputGUI($this->txt(self::F_MULTIPLE_END), self::F_MULTIPLE_END);
			$subinput->setRequired(true);
			$opt->addSubItem($subinput);

			$subinput = new srWeekdayInputGUI($this->txt(self::F_MULTIPLE_WEEKDAYS), self::F_MULTIPLE_WEEKDAYS);
			$subinput->setRequired(true);
			$opt->addSubItem($subinput);

			$subinput = new ilInteractiveVideoTimePicker($this->txt(self::F_MULTIPLE_START_TIME), self::F_MULTIPLE_START_TIME);
			$subinput->setRequired(true);
			$opt->addSubItem($subinput);

			$subinput = new ilInteractiveVideoTimePicker($this->txt(self::F_MULTIPLE_END_TIME), self::F_MULTIPLE_END_TIME);
			$subinput->setRequired(true);
			$opt->addSubItem($subinput);

			$radio->addOption($opt);

			$this->addItem($radio);
		}

		if ($this->is_new) {
			foreach (xoctSeriesWorkflowParameterRepository::getInstance()
				         ->getFormItemsForObjId($this->xoctOpenCast->getObjId(), ilObjOpenCastAccess::hasPermission('edit_videos')) as $item) {
				$this->addItem($item);
			}
		}
	}


	public function fillForm() {
		$startDateTime = $this->object->getStart();
		$endDateTime = $this->object->getEnd();
		if (xoct::isIlias52()) {
			$start = $startDateTime->format('Y-m-d H:i:s');
			$end = $endDateTime ? $endDateTime->format('Y-m-d H:i:s') : '';
		} else {
			$start = array(
				'date' => $startDateTime->format('Y-m-d'),
				'time' => $startDateTime->format('H:i:s'),
			);
			$end = $endDateTime ? array(
				'date' => $endDateTime->format('Y-m-d'),
				'time' => $endDateTime->format('H:i:s'),
			) : array();
		}

		$array = array(
			self::F_TITLE            => $this->object->getTitle(),
			self::F_DESCRIPTION      => $this->object->getDescription(),
			self::F_IDENTIFIER       => $this->object->getIdentifier(),
			self::F_CREATOR          => $this->object->getCreator(),
			self::F_DURATION         => $this->object->getDurationArrayForInput(),
			self::F_PROCESSING_STATE => $this->object->getProcessingState(),
			self::F_PRESENTERS       => $this->object->getPresenter(),
			self::F_LOCATION         => $this->object->getLocation(),
			self::F_SOURCE           => $this->object->getSource(),
			self::F_START          => $start,
			self::F_END          => $end,
		);

		if( empty($array[self::F_PRESENTERS]) )
		{
			global $DIC;
			$ilUser = $DIC['ilUser'];
			$xoctUser = xoctUser::getInstance($ilUser);
			$array[self::F_PRESENTERS] = $xoctUser->getFirstName() . " " . $xoctUser->getLastName();
		}

		$this->setValuesByArray($array);
	}


    /**
     * @return bool
     * @throws ilTimeZoneException
     */
	public function fillObject() {
	    $check_input = $this->checkInput();
	    $check_date = $this->checkDates();
		if (!$check_input || !$check_date) {
			return false;
		}


		$presenter = xoctUploadFile::getInstanceFromFileArray('file_presenter');
		$title = $this->getInput(self::F_TITLE);

		$this->object->setTitle($title ? $title : $presenter->getTitle());
		$this->object->setDescription($this->getInput(self::F_DESCRIPTION));
		$this->object->setLocation($this->getInput(self::F_LOCATION));
		$this->object->setPresenter($this->getInput(self::F_PRESENTERS));
		$this->object->setWorkflowParametersForObjId((array) $this->getInput(self::F_WORKFLOW_PARAMETER), $this->parent_gui->getObjId(), ilObjOpenCastAccess::hasPermission('edit_videos'));

        $date_and_location_disabled = $this->object->isScheduled() && xoctConf::getConfig(xoctConf::F_SCHEDULED_METADATA_EDITABLE) == xoctConf::METADATA_EXCEPT_DATE_PLACE;

        if ($this->getInput(self::F_MULTIPLE)) {
			$start_date = $this->getInput(self::F_MULTIPLE_START);
			$start_time = $this->getInput(self::F_MULTIPLE_START_TIME);
			$start = $start_date . ' ' . floor($start_time/3600) . ':' . floor($start_time/60%60) . ':' . $start_time%60;
			$this->object->setStart($start);

			// the start date is used for end date, since the enddate defines the end of the recurrence, not of the actual event
            $end_date = $this->getInput(self::F_MULTIPLE_END);
            $end_time = $this->getInput(self::F_MULTIPLE_END_TIME);
			$end = $end_date . ' ' . floor($end_time/3600) . ':' . floor($end_time/60%60) . ':' . ($end_time%60);
			$this->object->setEnd($end);

			$duration = ($end_time - $start_time) * 1000;
			$this->object->setDuration($duration);
		} else if (!$date_and_location_disabled) {
            /**
			 * @var $start            ilDateTime
			 * @var $ilDateTimeInputGUI ilDateTimeInputGUI
			 */
			$ilDateTimeInputGUI = $this->getItemByPostVar(self::F_START);
			$start = $ilDateTimeInputGUI->getDate();
			$default_datetime = $this->object->getDefaultDateTimeObject($start->get(IL_CAL_ISO_8601, '', ilTimeZone::_getInstance()->getIdentifier()));
			$this->object->setStart($default_datetime);

			if ($this->object->isScheduled() || $this->schedule) {
				/**
				 * @var $start            ilDateTime
				 * @var $ilDateTimeInputGUI ilDateTimeInputGUI
				 */
				$ilDateTimeInputGUI = $this->getItemByPostVar(self::F_END);
				$end = $ilDateTimeInputGUI->getDate();
				$default_datetime = $this->object->getDefaultDateTimeObject($end->get(IL_CAL_ISO_8601, '', ilTimeZone::_getInstance()->getIdentifier()));
				$this->object->setEnd($default_datetime);
			}
		}

		return true;
	}

    /**
     * @return bool
     */
    protected function checkDates() {
        $date_and_location_disabled = xoctConf::getConfig(xoctConf::F_SCHEDULED_METADATA_EDITABLE) == xoctConf::METADATA_EXCEPT_DATE_PLACE;;
        if (($this->object->isScheduled() && !$date_and_location_disabled) || $this->schedule) {
            if ($this->getInput(self::F_MULTIPLE)) {
                $start_date = $this->getInput(self::F_MULTIPLE_START);
                $start_time = $this->getInput(self::F_MULTIPLE_START_TIME);
                $start = $start_date . ' ' . floor($start_time/3600) . ':' . floor($start_time/60%60) . ':' . $start_time%60;

                $end_date = $this->getInput(self::F_MULTIPLE_END);
                $end_time = $this->getInput(self::F_MULTIPLE_END_TIME);
                $end = $end_date . ' ' . floor($end_time/3600) . ':' . floor($end_time/60%60) . ':' . ($end_time%60);
            } else {
                $start = $this->getInput(self::F_START);
                $end = $this->getInput(self::F_END);
            }

            if ($end && ($end < $start)) {
                ilUtil::sendFailure(self::plugin()->translate('event_msg_end_before_start'), true);
                return false;
            }

            $now = date('Y-m-d H:i:s');
            if (($start && ($start < $now)) || ($end && ($end < $now))) {
                ilUtil::sendFailure(self::plugin()->translate('event_msg_scheduled_in_past'), true);
                return false;
            }
        }

        return true;
	}

	/**
	 * @param $key
	 *
	 * @return string
	 */
	protected function txt($key) {
		return $this->parent_gui->txt($key);
	}


	/**
	 * @param $key
	 *
	 * @return string
	 */
	protected function infoTxt($key) {
		return self::plugin()->translate($key . '_info', 'event');
	}


    /**
     * @return bool|string
     * @throws xoctException
     */
	public function saveObject() {
		if (!$this->fillObject()) {
			return false;
		}
		if ($this->object->getIdentifier()) {
			try {
				$this->object->update();
			} catch (Exception $e) {
				return $this->checkAndShowConflictMessage($e);
			}
			$this->object->getXoctEventAdditions()->update();
		} else {
			$this->object->setSeriesIdentifier($this->xoctOpenCast->getSeriesIdentifier());
            if ($this->schedule) {
                try {
                    $this->object->schedule($this->buildRRule());
                } catch (Exception $e) {
                    return $this->checkAndShowConflictMessage($e);
                }
            } else {
                $this->object->create();
			}
		}

		return $this->object->getIdentifier();
	}

	protected function buildRRule() {
		if ($this->getInput(self::F_MULTIPLE)) {
			$start_time = $this->getInput(self::F_MULTIPLE_START_TIME);
			$byhour = floor($start_time / 3600);
			$byminute = floor($start_time / 60) % 60;

			$weekdays = $this->getInput(self::F_MULTIPLE_WEEKDAYS);
			$byday = implode(',', $weekdays);
			$rrule = "FREQ=WEEKLY;BYDAY=$byday;BYHOUR=$byhour;BYMINUTE=$byminute;";
			return $rrule;
		}
		return false;
	}

	protected function initButtons() {
		switch (true) {
			case  $this->is_new AND !$this->view AND !$this->schedule:
				$this->setTitle($this->txt('create'));
				$this->addCommandButton(xoctEventGUI::CMD_CREATE, $this->txt(xoctEventGUI::CMD_CREATE));
				$this->addCommandButton(xoctEventGUI::CMD_CANCEL, $this->txt(xoctEventGUI::CMD_CANCEL));
				break;
			case $this->is_new AND $this->schedule:
				$this->setTitle($this->txt('schedule_new'));
				$this->addCommandButton(xoctEventGUI::CMD_CREATE_SCHEDULED, $this->txt(xoctEventGUI::CMD_CREATE_SCHEDULED));
				$this->addCommandButton(xoctEventGUI::CMD_CANCEL, $this->txt(xoctEventGUI::CMD_CANCEL));
				break;
			case  !$this->is_new AND !$this->view:
				if (ilObjOpenCast::DEV) {
					$this->addCommandButton('saveAndStay', 'Save and Stay');
				}
				$this->setTitle($this->txt('edit'));
				$this->addCommandButton(xoctEventGUI::CMD_UPDATE, $this->txt(xoctEventGUI::CMD_UPDATE));
				$this->addCommandButton(xoctEventGUI::CMD_CANCEL, $this->txt(xoctEventGUI::CMD_CANCEL));
				break;
			case $this->view:
				$this->setTitle($this->txt('view'));
				$this->addCommandButton(xoctEventGUI::CMD_CANCEL, $this->txt(xoctEventGUI::CMD_CANCEL));
				break;
		}
	}


	protected function initView() {
		$this->initForm();

		$te = new ilNonEditableValueGUI($this->txt(self::F_IDENTIFIER), self::F_IDENTIFIER);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_CREATOR), self::F_CREATOR);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_START), self::F_START);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_DURATION), self::F_DURATION);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_PROCESSING_STATE), self::F_PROCESSING_STATE);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_START_TIME), self::F_START_TIME);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_LOCATION), self::F_LOCATION);
		$this->addItem($te);

		$te = new ilNonEditableValueGUI($this->txt(self::F_PRESENTERS), self::F_PRESENTERS);
		$this->addItem($te);

		/**
		 * @var $item ilNonEditableValueGUI
		 */
		foreach ($this->getItems() as $item) {
			$te = new ilNonEditableValueGUI($this->txt($item->getPostVar()), $item->getPostVar());
			$this->removeItemByPostVar($item->getPostVar());
			$this->addItem($te);
		}
		$te = new ilCustomInputGUI('detail', 'detail');
		$te->setHtml('<table><tr><td>' . $this->object->__toCsv("</td><td>", "</td></tr><tr><td>") . '</td></tr></table>');
		$this->addItem($te);

		foreach ($this->object->getPublications() as $pub) {
			$h = new ilFormSectionHeaderGUI();
			$h->setTitle($pub->getChannel());
			$this->addItem($h);

			$te = new ilCustomInputGUI('Publication ' . $pub->getChannel(), 'pub_' . $pub->getChannel());
			$te->setHtml('<table><tr><td>' . $pub->__toCsv("</td><td>", "</td></tr><tr><td>") . '</td></tr></table>');
			$this->addItem($te);

			foreach ($pub->getMedia() as $med) {
				$te = new ilCustomInputGUI($med->getId(), $med->getId());
				$te->setHtml('<table><tr><td>' . $med->__toCsv("</td><td>", "</td></tr><tr><td>") . '</td></tr></table>');
				$this->addItem($te);
			}
		}

		$h = new ilFormSectionHeaderGUI();
		$h->setTitle('ACL');
		$this->addItem($h);

		foreach ($this->object->getAcl() as $acl) {
			$te = new ilCustomInputGUI($acl->getRole(), $acl->getRole());
			$te->setHtml('<table><tr><td>' . $acl->__toCsv("</td><td>", "</td></tr><tr><td>") . '</td></tr></table>');
			$this->addItem($te);
		}
	}


	/**
	 * @return xoctEvent
	 */
	public function getObject() {
		return $this->object;
	}


	/**
	 * @param xoctEvent $object
	 */
	public function setObject($object) {
		$this->object = $object;
	}


	/**
	 * @param $e
	 *
	 * @return bool
	 */
	protected function checkAndShowConflictMessage($e) {
		if ($e->getCode() == xoctException::API_CALL_STATUS_409) {
			$conflicts = json_decode(substr($e->getMessage(), 10), true);
			$message = $this->txt('msg_scheduling_conflict') . '<br>';
			foreach ($conflicts as $conflict) {
				$message .= '<br>' . $conflict['title'] . '<br>' . date('Y.m.d H:i:s', strtotime($conflict['start'])) . ' - '
					. date('Y.m.d H:i:s', strtotime($conflict['end'])) . '<br>';
			}
			ilUtil::sendFailure($message);

			return false;
		}
		throw $e;
	}
}


