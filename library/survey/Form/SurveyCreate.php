<?php

class Survey_Form_SurveyCreate extends Zend_Form
{
	public $formDecorators = array(
			array('FormElements'),
			array('Form'));
	
	public $elementDecorators = array(
			array('ViewHelper'),
			array('Label'),
			array('Errors'),
			array('HtmlTag', array('tag' => 'p')));
	
	public $buttonDecorators = array(
			array('ViewHelper'),
			array('HtmlTag', array('tag' => 'p')));
	
	public function init()
	{
		$this->setAction('owner/surveylist/create')
			->setMethod('post');
			//->setDecorators($this->formDecorators);
		
		$surveyName = new Zend_Form_Element_Text('Name');
		$surveyName->setLabel('Survey Name:')
			->setOptions(array(
					'id' => 'Name'))
			->setRequired(true)
			->addFilter('HtmlEntities');
			//->setDecorators($this->elementDecorators);
			
		$description = new Zend_Form_Element_Textarea('Description');
		$description->setLabel('Description (optional):')
			->setOptions(array(
					'id' => 'Description',
					'rows' => 4,
					'columns' => 40
					))
			->setRequired(false)
			->addFilter('HtmlEntities');
			//->setDecorators($this->elementDecorators);
			
		$submit = new Zend_Form_Element_Submit('Submit');
		$submit->setLabel("Continue");
		
		$cancel = new Zend_Form_Element_Button('Cancel');
		$cancel->setLabel("Cancel")
			->setOptions(array(
					'id' => 'Description',
					'onclick' => 'location.assign(\'/owner/surveylist/index\')'));
		
		$this->addElement($surveyName)
			->addElement($description)
			->addElement($cancel)
			->addElement($submit);
			
	}
}
	


