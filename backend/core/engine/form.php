<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * This is our extended version of SpoonForm
 *
 * @author Davy Hellemans <davy.hellemans@netlash.com>
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class BackendForm extends SpoonForm
{
	/**
	 * The header instance
	 *
	 * @var	BackendHeader
	 */
	private $header;

	/**
	 * The URL instance
	 *
	 * @var	BackendURL
	 */
	private $URL;

	/**
	 * Show the global error
	 *
	 * @var	bool
	 */
	private $useGlobalError = true;

	/**
	 * @param string[optional] $name Name of the form.
	 * @param string[optional] $action The action (URL) whereto the form will be submitted, if not provided it will be autogenerated.
	 * @param string[optional] $method The method to use when submitting the form, default is POST.
	 * @param bool[optional] $useToken Should we automagically add a formtoken?
	 * @param bool[optional] $useGlobalError Should we automagically show a global error?
	 */
	public function __construct($name = null, $action = null, $method = 'post', $useToken = true, $useGlobalError = true)
	{
		if(BackendModel::getContainer()->has('url')) $this->URL = BackendModel::getContainer()->get('url');
		if(BackendModel::getContainer()->has('header')) $this->header = BackendModel::getContainer()->get('header');
		$this->useGlobalError = (bool) $useGlobalError;

		// build a name if there wasn't one provided
		$name = ($name === null) ? SpoonFilter::toCamelCase($this->URL->getModule() . '_' . $this->URL->getAction(), '_', true) : (string) $name;

		// build the action if it wasn't provided
		$action = ($action === null) ? '/' . $this->URL->getQueryString() : (string) $action;

		// call the real form-class
		parent::__construct($name, $action, $method, $useToken);

		// add default classes
		$this->setParameter('id', $name);
		$this->setParameter('class', 'forkForms submitWithLink');
	}

	/**
	 * Adds a button to the form
	 *
	 * @param string $name Name of the button.
	 * @param string $value The value (or label) that will be printed.
	 * @param string[optional] $type The type of the button (submit is default).
	 * @param string[optional] $class Class(es) that will be applied on the button.
	 * @return SpoonFormButton
	 */
	public function addButton($name, $value, $type = 'submit', $class = null)
	{
		$name = (string) $name;
		$value = (string) $value;
		$type = (string) $type;
		$class = ($class !== null) ? (string) $class : 'inputButton';

		// do a check
		if($type == 'submit' && $name == 'submit') throw new BackendException('You can\'t add buttons with the name submit. JS freaks out when we replace the buttons with a link and use that link to submit the form.');

		// create and return a button
		return parent::addButton($name, $value, $type, $class);
	}

	/**
	 * Adds a single checkbox.
	 *
	 * @param string $name The name of the element.
	 * @param bool[optional] $checked Should the checkbox be checked?
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormCheckbox
	 */
	public function addCheckbox($name, $checked = false, $class = null, $classError = null)
	{
		$name = (string) $name;
		$checked = (bool) $checked;
		$class = ($class !== null) ? (string) $class : 'inputCheckbox';
		$classError = ($classError !== null) ? (string) $classError : 'inputCheckboxError';

		// create and return a checkbox
		return parent::addCheckbox($name, $checked, $class, $classError);
	}

	/**
	 * Adds a datefield to the form
	 *
	 * @param string $name Name of the element.
	 * @param mixed[optional] $value The value for the element.
	 * @param string[optional] $type The type (from, till, range) of the datepicker.
	 * @param int[optional] $date The date to use.
	 * @param int[optional] $date2 The second date for a rangepicker.
	 * @param string[optional] $class Class(es) that have to be applied on the element.
	 * @param string[optional] $classError Class(es) that have to be applied when an error occurs on the element.
	 * @return BackendFormDate
	 */
	public function addDate($name, $value = null, $type = null, $date = null, $date2 = null, $class = null, $classError = null)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (($value !== '') ? (int) $value : '') : null;
		$type = SpoonFilter::getValue($type, array('from', 'till', 'range'), 'none');
		$date = ($date !== null) ? (int) $date : null;
		$date2 = ($date2 !== null) ? (int) $date2 : null;
		$class = ($class !== null) ? (string) $class : 'inputText inputDate';
		$classError = ($classError !== null) ? (string) $classError : 'inputTextError inputDateError';

		// validate
		if($type == 'from' && ($date == 0 || $date == null)) throw new BackendException('A datefield with type "from" should have a valid date-parameter.');
		if($type == 'till' && ($date == 0 || $date == null)) throw new BackendException('A datefield with type "till" should have a valid date-parameter.');
		if($type == 'range' && ($date == 0 || $date2 == 0 || $date == null || $date2 == null)) throw new BackendException('A datefield with type "range" should have 2 valid date-parameters.');

		// @later	get preferred mask & first day
		$mask = 'd/m/Y';
		$firstday = 1;

		// build attributes
		$attributes['data-mask'] = str_replace(array('d', 'm', 'Y', 'j', 'n'), array('dd', 'mm', 'yy', 'd', 'm'), $mask);
		$attributes['data-firstday'] = $firstday;

		// add extra classes based on type
		switch($type)
		{
			// start date
			case 'from':
				$class .= ' inputDatefieldFrom inputText';
				$classError .= ' inputDatefieldFrom';
				$attributes['data-startdate'] = date('Y-m-d', $date);
			 break;

			// end date
			case 'till':
				$class .= ' inputDatefieldTill inputText';
				$classError .= ' inputDatefieldTill';
				$attributes['data-enddate'] = date('Y-m-d', $date);
			 break;

			// date range
			case 'range':
				$class .= ' inputDatefieldRange inputText';
				$classError .= ' inputDatefieldRange';
				$attributes['data-startdate'] = date('Y-m-d', $date);
				$attributes['data-enddate'] = date('Y-m-d', $date2);
			 break;

			// normal date field
			default:
				$class .= ' inputDatefieldNormal inputText';
				$classError .= ' inputDatefieldNormal';
			 break;
		}

		// create a datefield
		$this->add(new BackendFormDate($name, $value, $mask, $class, $classError));

		// set attributes
		parent::getField($name)->setAttributes($attributes);

		// return datefield
		return parent::getField($name);
	}

	/**
	 * Adds a single dropdown.
	 *
	 * @param string $name Name of the element.
	 * @param array[optional] $values Values for the dropdown.
	 * @param string[optional] $selected The selected elements.
	 * @param bool[optional] $multipleSelection Is it possible to select multiple items?
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormDropdown
	 */
	public function addDropdown($name, array $values = null, $selected = null, $multipleSelection = false, $class = null, $classError = null)
	{
		$name = (string) $name;
		$values = (array) $values;
		$selected = ($selected !== null) ? $selected : null;
		$multipleSelection = (bool) $multipleSelection;
		$class = ($class !== null) ? (string) $class : 'select';
		$classError = ($classError !== null) ? (string) $classError : 'selectError';

		// special classes for multiple
		if($multipleSelection)
		{
			$class .= ' selectMultiple';
			$classError .= ' selectMultipleError';
		}

		// create and return a dropdown
		return parent::addDropdown($name, $values, $selected, $multipleSelection, $class, $classError);
	}

	/**
	 * Add an editor field
	 *
	 * @param string $name The name of the element.
	 * @param string[optional] $value The value inside the element.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @param bool[optional] $HTML Will the field contain HTML?
	 * @return SpoonFormTextarea
	 */
	public function addEditor($name, $value = null, $class = null, $classError = null, $HTML = true)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (string) $value : null;
		$class = 'inputEditor ' . (string) $class;
		$classError = 'inputEditorError ' . (string) $classError;
		$HTML = (bool) $HTML;

		// we add JS because we need CKEditor
		$this->header->addJS('ckeditor/ckeditor.js', 'core', false);
		$this->header->addJS('ckeditor/adapters/jquery.js', 'core', false);
		$this->header->addJS('ckfinder/ckfinder.js', 'core', false);

		// add the internal link lists-file
		if(is_file(FRONTEND_CACHE_PATH . '/navigation/editor_link_list_' . BL::getWorkingLanguage() . '.js'))
		{
			$timestamp = @filemtime(FRONTEND_CACHE_PATH . '/navigation/editor_link_list_' . BL::getWorkingLanguage() . '.js');
			$this->header->addJS('/frontend/cache/navigation/editor_link_list_' . BL::getWorkingLanguage() . '.js?m=' . $timestamp, null, false, true, false);
		}

		// create and return a textarea for the editor
		return $this->addTextArea($name, $value, $class, $classError, $HTML);
	}

	/**
	 * Adds a single file field.
	 *
	 * @param string $name Name of the element.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormFile
	 */
	public function addFile($name, $class = null, $classError = null)
	{
		$name = (string) $name;
		$class = ($class !== null) ? (string) $class : 'inputFile';
		$classError = ($classError !== null) ? (string) $classError : 'inputFileError';

		// add element
		$this->add(new BackendFormFile($name, $class, $classError));
		return $this->getField($name);
	}

	/**
	 * Adds a single image field.
	 *
	 * @param string $name The name of the element.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormImage
	 */
	public function addImage($name, $class = null, $classError = null)
	{
		$name = (string) $name;
		$class = ($class !== null) ? (string) $class : 'inputFile inputImage';
		$classError = ($classError !== null) ? (string) $classError : 'inputFileError inputImageError';

		// add element
		$this->add(new BackendFormImage($name, $class, $classError));
		return $this->getField($name);
	}

	/**
	 * Adds a multiple checkbox.
	 *
	 * @param string $name The name of the element.
	 * @param array $values The values for the checkboxes.
	 * @param mixed[optional] $checked Should the checkboxes be checked?
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormMultiCheckbox
	 */
	public function addMultiCheckbox($name, array $values, $checked = null, $class = null, $classError = null)
	{
		$name = (string) $name;
		$values = (array) $values;
		$checked = ($checked !== null) ? (array) $checked : null;
		$class = ($class !== null) ? (string) $class : 'inputCheckbox';
		$classError = ($classError !== null) ? (string) $classError : 'inputCheckboxError';

		// create and return a multi checkbox
		return parent::addMultiCheckbox($name, $values, $checked, $class, $classError);
	}

	/**
	 * Adds a single password field.
	 *
	 * @param string $name The name of the field.
	 * @param string[optional] $value The value for the field.
	 * @param int[optional] $maxlength The maximum length for the field.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @param bool[optional] $HTML Will the field contain HTML?
	 * @return SpoonFormPassword
	 */
	public function addPassword($name, $value = null, $maxlength = null, $class = null, $classError = null, $HTML = false)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (string) $value : null;
		$maxlength = ($maxlength !== null) ? (int) $maxlength : null;
		$class = ($class !== null) ? (string) $class : 'inputText inputPassword';
		$classError = ($classError !== null) ? (string) $classError : 'inputTextError inputPasswordError';
		$HTML = (bool) $HTML;

		// create and return a password field
		return parent::addPassword($name, $value, $maxlength, $class, $classError, $HTML);
	}

	/**
	 * Adds a single radiobutton.
	 *
	 * @param string $name The name of the element.
	 * @param array $values The possible values for the radiobutton.
	 * @param string[optional] $checked Should the element be checked?
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormRadiobutton
	 */
	public function addRadiobutton($name, array $values, $checked = null, $class = null, $classError = null)
	{
		$name = (string) $name;
		$values = (array) $values;
		$checked = ($checked !== null) ? (string) $checked : null;
		$class = ($class !== null) ? (string) $class : 'inputRadio';
		$classError = ($classError !== null) ? (string) $classError : 'inputRadioError';

		// create and return a radio button
		return parent::addRadiobutton($name, $values, $checked, $class, $classError);
	}

	/**
	 * Adds a single textfield.
	 *
	 * @param string $name The name of the element.
	 * @param string[optional] $value The value inside the element.
	 * @param int[optional] $maxlength The maximum length for the value.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @param bool[optional] $HTML Will this element contain HTML?
	 * @return SpoonFormText
	 */
	public function addText($name, $value = null, $maxlength = 255, $class = null, $classError = null, $HTML = false)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (string) $value : null;
		$maxlength = ($maxlength !== null) ? (int) $maxlength : null;
		$class = ($class !== null) ? (string) $class : 'inputText';
		$classError = ($classError !== null) ? (string) $classError : 'inputTextError';
		$HTML = (bool) $HTML;

		// create and return a textfield
		return parent::addText($name, $value, $maxlength, $class, $classError, $HTML);
	}

	/**
	 * Adds a single textarea.
	 *
	 * @param string $name The name of the element.
	 * @param string[optional] $value The value inside the element.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @param bool[optional] $HTML Will the element contain HTML?
	 * @return SpoonFormTextarea
	 */
	public function addTextarea($name, $value = null, $class = null, $classError = null, $HTML = false)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (string) $value : null;
		$class = ($class !== null) ? (string) $class : 'textarea';
		$classError = ($classError !== null) ? (string) $classError : 'textareaError';
		$HTML = (bool) $HTML;

		// create and return a textarea
		return parent::addTextarea($name, $value, $class, $classError, $HTML);
	}

	/**
	 * Adds a single timefield.
	 *
	 * @param string $name The name of the element.
	 * @param string[optional] $value The value inside the element.
	 * @param string[optional] $class Class(es) that will be applied on the element.
	 * @param string[optional] $classError Class(es) that will be applied on the element when an error occurs.
	 * @return SpoonFormTime
	 */
	public function addTime($name, $value = null, $class = null, $classError = null)
	{
		$name = (string) $name;
		$value = ($value !== null) ? (string) $value : null;
		$class = ($class !== null) ? (string) $class : 'inputText inputTime';
		$classError = ($classError !== null) ? (string) $classError : 'inputTextError inputTimeError';

		// create and return a timefield
		return parent::addTime($name, $value, $class, $classError);
	}

	/**
	 * Fetches all the values for this form as key/value pairs
	 *
	 * @param mixed[optional] $excluded Which elements should be excluded?
	 * @return array
	 */
	public function getValues($excluded = array('form', 'save', 'form_token', '_utf8'))
	{
		return parent::getValues($excluded);
	}

	/**
	 * Checks to see if this form has been correctly submitted. Will revalidate by default.
	 *
	 * @param bool[optional] $revalidate Do we need to enforce validation again, even if it might already been done before?
	 * @return bool
	 */
	public function isCorrect($revalidate = true)
	{
		return parent::isCorrect($revalidate);
	}

	/**
	 * Parse the form
	 *
	 * @param SpoonTemplate $tpl The template instance wherein the form will be parsed.
	 */
	public function parse(SpoonTemplate $tpl)
	{
		parent::parse($tpl);
		$this->validate();

		// if the form is submitted but there was an error, assign a general error
		if($this->useGlobalError && $this->isSubmitted() && !$this->isCorrect())
		{
			$tpl->assign('formError', true);
		}
	}
}

/**
 * This is our extended version of SpoonFormDate
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class BackendFormDate extends SpoonFormDate
{
	/**
	 * Checks if this field is correctly submitted.
	 *
	 * @param string[optional] $error The errormessage to set.
	 * @return bool
	 */
	public function isValid($error = null)
	{
		// call parent (let them do the hard word)
		$return = parent::isValid($error);

		// already errors detect, no more further testing is needed
		if($return === false) return false;

		// define long mask
		$longMask = str_replace(array('d', 'm', 'y', 'Y'), array('dd', 'mm', 'yy', 'yyyy'), $this->mask);

		// post/get data
		$data = $this->getMethod(true);

		// init some vars
		$year = (strpos($longMask, 'yyyy') !== false) ? substr($data[$this->attributes['name']], strpos($longMask, 'yyyy'), 4) : substr($data[$this->attributes['name']], strpos($longMask, 'yy'), 2);
		$month = substr($data[$this->attributes['name']], strpos($longMask, 'mm'), 2);
		$day = substr($data[$this->attributes['name']], strpos($longMask, 'dd'), 2);

		// validate datefields that have a from-date set
		if(strpos($this->attributes['class'], 'inputDatefieldFrom') !== false)
		{
			// process from date
			$fromDateChunks = explode('-', $this->attributes['data-startdate']);
			$fromDateTimestamp = mktime(12, 00, 00, $fromDateChunks[1], $fromDateChunks[2], $fromDateChunks[0]);

			// process given date
			$givenDateTimestamp = mktime(12, 00, 00, $month, $day, $year);

			// compare dates
			if($givenDateTimestamp < $fromDateTimestamp)
			{
				if($error !== null) $this->setError($error);
				return false;
			}
		}

		// validate datefield that have a till-date set
		elseif(strpos($this->attributes['class'], 'inputDatefieldTill') !== false)
		{
			// process till date
			$tillDateChunks = explode('-', $this->attributes['data-enddate']);
			$tillDateTimestamp = mktime(12, 00, 00, $tillDateChunks[1], $tillDateChunks[2], $tillDateChunks[0]);

			// process given date
			$givenDateTimestamp = mktime(12, 00, 00, $month, $day, $year);

			// compare dates
			if($givenDateTimestamp > $tillDateTimestamp)
			{
				if($error !== null) $this->setError($error);
				return false;
			}
		}

		// validate datefield that have a from and till-date set
		elseif(strpos($this->attributes['class'], 'inputDatefieldRange') !== false)
		{
			// process from date
			$fromDateChunks = explode('-', $this->attributes['data-startdate']);
			$fromDateTimestamp = mktime(12, 00, 00, $fromDateChunks[1], $fromDateChunks[2], $fromDateChunks[0]);

			// process till date
			$tillDateChunks = explode('-', $this->attributes['data-enddate']);
			$tillDateTimestamp = mktime(12, 00, 00, $tillDateChunks[1], $tillDateChunks[2], $tillDateChunks[0]);

			// process given date
			$givenDateTimestamp = mktime(12, 00, 00, $month, $day, $year);

			// compare dates
			if($givenDateTimestamp < $fromDateTimestamp || $givenDateTimestamp > $tillDateTimestamp)
			{
				if($error !== null) $this->setError($error);
				return false;
			}
		}

		/**
		 * When the code reaches the point, it means no errors have occurred
		 * and truth will out!
		 */
		return true;
	}
}

/**
 * This is our extended version of SpoonFormFile
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Jelmer Snoeck <jelmer@siphoc.com>
 * @author Annelies Van Extergem <annelies.vanextergem@netlash.com>
 */
class BackendFormImage extends SpoonFormImage
{
	/**
	 * Should the helpTxt span be hidden when parsing the field?
	 *
	 * @var	bool
	 */
	private $hideHelpTxt = false;

	/**
	 * Generate thumbnails based on the folders in the path
	 * Use
	 *  - 128x128 as foldername to generate an image that where the width will be 128px and the height will be 128px
	 *  - 128x as foldername to generate an image that where the width will be 128px, the height will be calculated based on the aspect ratio.
	 *  - x128 as foldername to generate an image that where the width will be 128px, the height will be calculated based on the aspect ratio.
	 *
	 * @param string $path
	 * @param string $filename
	 */
	public function generateThumbnails($path, $filename)
	{
		$fs = new Filesystem();
		if(!$fs->exists($path . '/source')) $fs->mkdir($path . '/source');
		$this->moveFile($path . '/source/' . $filename);

		// generate the thumbnails
		BackendModel::generateThumbnails($path, $path . '/source/' . $filename);
	}

	/**
	 * This function will return the errors. It is extended so we can do image checks automatically.
	 *
	 * @return string
	 */
	public function getErrors()
	{
		// do an image validation
		if($this->isFilled())
		{
			$this->isAllowedExtension(array('jpg', 'jpeg', 'gif', 'png'), BL::err('JPGGIFAndPNGOnly'));
			$this->isAllowedMimeType(array('image/jpeg', 'image/gif', 'image/png'), BL::err('JPGGIFAndPNGOnly'));
		}

		return $this->errors;
	}

	/**
	 * Hides (or shows) the help text when parsing the field.
	 *
	 * @param bool[optional] $on
	 */
	public function hideHelpTxt($on = true)
	{
		$this->hideHelpTxt = $on;
	}

	/**
	 * Parses the html for this filefield.
	 *
	 * @param SpoonTemplate[optional] $template The template to parse the element in.
	 * @return string
	 */
	public function parse(SpoonTemplate $template = null)
	{
		// get upload_max_filesize
		$uploadMaxFilesize = ini_get('upload_max_filesize');
		if($uploadMaxFilesize === false) $uploadMaxFilesize = 0;

		// reformat if defined as an integer
		if(SpoonFilter::isInteger($uploadMaxFilesize)) $uploadMaxFilesize = $uploadMaxFilesize / 1024 . 'MB';

		// reformat if specified in kB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'K') $uploadMaxFilesize = substr($uploadMaxFilesize, 0, -1) . 'kB';

		// reformat if specified in MB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'M') $uploadMaxFilesize .= 'B';

		// reformat if specified in GB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'G') $uploadMaxFilesize .= 'B';

		// name is required
		if($this->attributes['name'] == '') throw new SpoonFormException('A name is required for a file field. Please provide a name.');

		// start html generation
		$output = '<input type="file"';

		// add attributes
		$output .= $this->getAttributesHTML(array('[id]' => $this->attributes['id'], '[name]' => $this->attributes['name'])) . ' />';

		// add help txt if needed
		if(!$this->hideHelpTxt)
		{
			$output .= '<span class="helpTxt">' . sprintf(BL::getMessage('HelpImageFieldWithMaxFileSize', 'core'), $uploadMaxFilesize) . '</span>';
		}

		// parse to template
		if($template !== null)
		{
			$template->assign('file' . SpoonFilter::toCamelCase($this->attributes['name']), $output);
			$template->assign('file' . SpoonFilter::toCamelCase($this->attributes['name']) . 'Error', ($this->errors != '') ? '<span class="formError">' . $this->errors . '</span>' : '');
		}

		return $output;
	}
}

/**
 * This is our extended version of SpoonFormFile
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Annelies Van Extergem <annelies.vanextergem@netlash.com>
 */
class BackendFormFile extends SpoonFormFile
{
	/**
	 * Should the helpTxt span be hidden when parsing the field?
	 *
	 * @var	bool
	 */
	private $hideHelpTxt = false;

	/**
	 * Hides (or shows) the help text when parsing the field.
	 *
	 * @param bool[optional] $on
	 */
	public function hideHelpTxt($on = true)
	{
		$this->hideHelpTxt = $on;
	}

	/**
	 * Parses the html for this filefield.
	 *
	 * @param SpoonTemplate[optional] $template The template to parse the element in.
	 * @return string
	 */
	public function parse(SpoonTemplate $template = null)
	{
		// get upload_max_filesize
		$uploadMaxFilesize = ini_get('upload_max_filesize');
		if($uploadMaxFilesize === false) $uploadMaxFilesize = 0;

		// reformat if defined as an integer
		if(SpoonFilter::isInteger($uploadMaxFilesize)) $uploadMaxFilesize = $uploadMaxFilesize / 1024 . 'MB';

		// reformat if specified in kB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'K') $uploadMaxFilesize = substr($uploadMaxFilesize, 0, -1) . 'kB';

		// reformat if specified in MB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'M') $uploadMaxFilesize .= 'B';

		// reformat if specified in GB
		if(strtoupper(substr($uploadMaxFilesize, -1, 1)) == 'G') $uploadMaxFilesize .= 'B';

		// name is required
		if($this->attributes['name'] == '') throw new SpoonFormException('A name is required for a file field. Please provide a name.');

		// start html generation
		$output = '<input type="file"';

		// add attributes
		$output .= $this->getAttributesHTML(array('[id]' => $this->attributes['id'], '[name]' => $this->attributes['name'])) . ' />';

		// add help txt if needed
		if(!$this->hideHelpTxt)
		{
			if(isset($this->attributes['extension'])) $output .= '<span class="helpTxt">' . sprintf(BL::getMessage('HelpFileFieldWithMaxFileSize', 'core'), $this->attributes['extension'], $uploadMaxFilesize) . '</span>';
			else $output .= '<span class="helpTxt">' . sprintf(BL::getMessage('HelpMaxFileSize'), $uploadMaxFilesize) . '</span>';
		}

		// parse to template
		if($template !== null)
		{
			$template->assign('file' . SpoonFilter::toCamelCase($this->attributes['name']), $output);
			$template->assign('file' . SpoonFilter::toCamelCase($this->attributes['name']) . 'Error', ($this->errors != '') ? '<span class="formError">' . $this->errors . '</span>' : '');
		}

		return $output;
	}
}
