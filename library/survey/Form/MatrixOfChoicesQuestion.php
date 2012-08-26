<?php
class Survey_Form_MatrixOfChoicesQuestion extends Survey_Form_QuestionEdit
{

	public function init()
	{
		parent::init();
	}

	public function addQuestionCategorySpecificElements(){
		
		$this->rowChoices = new Zend_Form_Element_Select('rowChoices');
		$this->rowChoices->setLabel('Row Choices:')
			->setDecorators($this->elementDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
		

		$editRow = new Zend_Form_Element_Submit('editRow');
		$editRow->setLabel('Edit Choice')
			->setDecorators($this->buttonDecorators);
		
		$addChoicesRow = new Zend_Form_Element_Submit('addChoicesRow');
		$addChoicesRow->setLabel('Add Choices')
			->setDecorators($this->buttonDecorators);
		
		$this->columnChoices = new Zend_Form_Element_Select('columnChoices');
		$this->columnChoices->setLabel('Column Choices:')
			->setDecorators($this->elementDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
		

		$editColumn = new Zend_Form_Element_Submit('editColumn');
		$editColumn->setLabel('Edit Choice')
			->setDecorators($this->buttonDecorators);
		
		$addChoicesColumn = new Zend_Form_Element_Submit('addChoicesColumn');
		$addChoicesColumn->setLabel('Add Choices')
			->setDecorators($this->buttonDecorators);
		
		$this->randomize = new Zend_Form_Element_Checkbox('randomize');
		$this->randomize->setLabel('Randomize answers')
			->setDecorators($this->checkboxDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
			
		$this->addElement($this->rowChoices)
			->addElement($editRow)
			->addElement($addChoicesRow)
			->addElement($this->columnChoices)
			->addElement($editColumn)
			->addElement($addChoicesColumn);

		$this->addDisplayGroup(array('rowChoices', 'editRow', 'addChoicesRow'), 'rowChoicesGroup');
		$this->addDisplayGroup(array('columnChoices', 'editColumn', 'addChoicesColumn'), 'columnChoicesGroup');
			
		$this->addElement($this->randomize);
				
	}
	
	public function setRows($rows) {
		$this->rowChoices->setMultiOptions($rows);
	}
	
	public function setColumns($columns) {
		$this->columnChoices->setMultiOptions($columns);
	}
	
	public function setRandomize($randomize) {
		$this->randomize->setValue($randomize);
		
		
	}
	
}