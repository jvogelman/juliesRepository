<?php
class Survey_Form_CommentEssayBoxQuestion extends Survey_Form_QuestionEdit
{	

	public function init()
	{
		parent::init();
	}
	
	public function addQuestionCategorySpecificElements(){

		$this->textBoxSize = new Zend_Form_Element_Select('textBoxSize');
		$this->textBoxSize->setLabel('Text Box Size:')
			->setMultiOptions(array(
				'1' => 'One line',
				'0' => 'Multiple lines'
			))
			->setOptions(array(
				'class' => 'zendFormElement'))
				->setDecorators($this->elementDecorators);
		

		$this->addElement($this->textBoxSize);
	}

	public function setTextBoxSize($textBoxSize) {
		$this->textBoxSize->setValue($textBoxSize);
	}
}


