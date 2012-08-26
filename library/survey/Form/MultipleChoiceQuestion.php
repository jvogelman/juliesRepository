<?php
class Survey_Form_MultipleChoiceQuestion extends Survey_Form_QuestionEdit
{

	private $currentSelection = 'a';
	private $currentIndex = '1';

	private $otherField;
	private $otherFieldSingleLine;

	public function init()
	{
		parent::init();
	}

	public function addQuestionCategorySpecificElements(){

		$subForm = new Zend_Form_SubForm();

		$this->otherField = new Zend_Form_Element_Checkbox('otherField');
		$this->otherField->setLabel('Add "Other" field to selections above')
			->setDecorators($this->checkboxDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
		
		$this->otherFieldSingleLine = new Zend_Form_Element_Select('otherFieldSize');
		$this->otherFieldSingleLine->setLabel('Size of Field')
			->setDecorators($this->elementDecorators)
			->setMultiOptions(array(
				'0' => 'Text area',
				'1' => 'Single Line of Text'))
			->setOptions(array(
				'class' => 'zendFormElement'));
		
		$this->addSubForm($subForm, 'selection')
			->addElement($this->otherField)
			->addElement($this->otherFieldSingleLine);
		
		$this->addDisplayGroup(array('otherField', 'otherFieldSize'), 'otherFieldGroup');

		$this->currentIndex = '1';
		for ($i = 0; $i<4; $i++){
			$this->addSelection('');
		}
	}

	
	function setOtherField($otherField, $singleLine) {
		$this->otherField->setValue($otherField);
		$this->otherFieldSingleLine->setValue($singleLine);
	}
	
	function addSelection($text)
	{
		
		$selection = new Zend_Form_Element_Textarea(strval($this->currentIndex));// this adds array elements
		$selection->setLabel($this->currentSelection . '.')
			->setOptions(array(
				'rows' => 3,
				'cols' => 70,
				'class' => 'selection zendFormElement'))
			->setRequired(false)
			//->addFilter('HtmlEntities')
			->setDecorators($this->elementDecorators)
			->setValue($text);
			

		$this->getSubForm('selection')->addElement($selection);


		$this->currentSelection++;
		$this->currentIndex++;
	}
	
	function setSelections($selections) {
		$this->getSubForm('selection')->clearElements();
		$this->currentSelection = 'a';
		$this->currentIndex = '1';
		
		foreach ($selections as $s) {
			$this->addSelection($s);
		}
	}
	
	
}