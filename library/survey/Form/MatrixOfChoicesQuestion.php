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
		
		$updateChoicesRow = new Zend_Form_Element_Submit('updateChoicesRow');
		$updateChoicesRow->setLabel('Update Choices')
			->setDecorators($this->buttonDecorators);
		
		$this->columnChoices = new Zend_Form_Element_Select('columnChoices');
		$this->columnChoices->setLabel('Column Choices:')
			->setDecorators($this->elementDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
		

		$editColumn = new Zend_Form_Element_Submit('editColumn');
		$editColumn->setLabel('Edit Choice')
			->setDecorators($this->buttonDecorators);
		
		$updateChoicesColumn = new Zend_Form_Element_Submit('updateChoicesColumn');
		$updateChoicesColumn->setLabel('Update Choices')
			->setDecorators($this->buttonDecorators);
		
		$this->randomize = new Zend_Form_Element_Checkbox('randomize');
		$this->randomize->setLabel('Randomize answers')
			->setDecorators($this->checkboxDecorators)
			->setOptions(array(
				'class' => 'zendFormElement'));
			
		$this->addElement($this->rowChoices)
			//->addElement($editRow)
			->addElement($updateChoicesRow)
			->addElement($this->columnChoices)
			//->addElement($editColumn)
			->addElement($updateChoicesColumn);

		$this->addDisplayGroup(array('rowChoices', 'editRow', 'updateChoicesRow'), 'rowChoicesGroup');
		$this->addDisplayGroup(array('columnChoices', 'editColumn', 'updateChoicesColumn'), 'columnChoicesGroup');
			
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