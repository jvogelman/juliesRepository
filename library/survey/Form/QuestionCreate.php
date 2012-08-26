<?php
class Survey_Form_QuestionCreate extends Zend_Form
{
	
	private $currentSelection = 'a';
	
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
	
	private $_surveyId;
	
	public function init()
	{
		// initialize form for "Save"
		$this->setAction('owner/survey/show/')
			->setMethod('post')
			->setDecorators($this->formDecorators);
	
		$question = new Zend_Form_Element_Textarea('question');
		
		$question->setLabel('#.')	// fill this in later
			->setOptions(array(
					'id' => 'question',
					'rows' => 4,
					'columns' => 40))
			->setRequired(true)
			->addFilter('HtmlEntities')
			->setDecorators($this->elementDecorators);
			
					
		$questionType = new Zend_Form_Element_Select('questionType');
		$questionType->setLabel('Question Type:')
			->setMultiOptions(array(
					'SingleSelectRadio' => 'Radio Button: Single Selection',
					'MultiSelectRadio' => 'Radio Button: Multiple Selections'))
			->setDecorators($this->elementDecorators);
		
			

		$subForm = new Zend_Form_SubForm();
			
		
		$save = new Zend_Form_Element_Submit('save');
		$save->setLabel('Save')
			->setDecorators($this->buttonDecorators);
		
				
		$this->addElement($question)
			->addElement($questionType)			
			->addSubForm($subForm, 'selection')		
			->addElement($save)	;
		
		for ($i = 0; $i<6; $i++){
			$this->addSelection();
		}
		
		//$selection2 = new Zend_Form_Element_Text('b');
		//$subForm->addElement($selection1)
		//	->addElement($selection2);
		//$subForm->addElement(new Zend_Form_Element_Text('a'))
		//->addElement(new Zend_Form_Element_Text(b'));
		
		// Additional forms would include a "Delete" button and a "Revert" button
	}
	
	/*function setQuestionNum($numQuestion){
		$this->setAction('owner/question/save/' . $numQuestion);
		$this->getElement('question')->setLabel($numQuestion . '.');
	}*/
	
	
	function setSurveyId($surveyId) {
		$this->setAction('owner/survey/show/' . $surveyId);
		$this->_surveyId = $surveyId;
	}
	
	function addSelection()
	{
		//if ($this->currentSelection == null)
		//	$currentSelection = "NoName";
		
		$selection = new Zend_Form_Element_Textarea($this->currentSelection);
		$selection->setLabel($this->currentSelection . '.')
			->setOptions(array(
				'rows' => 2,
				'columns' => 40))
				->setRequired(true)
				->addFilter('HtmlEntities')
				->setDecorators($this->elementDecorators);
			
		
		$this->getSubForm('selection')->addElement($selection);
		
		
		$this->currentSelection++;
	}
}