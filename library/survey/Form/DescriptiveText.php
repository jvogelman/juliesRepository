<?php
class Survey_Form_DescriptiveText extends Survey_Form_QuestionEdit
{

	public function init()
	{
		parent::init();
				
		// remove the requireResponse checkbox
		$this->removeElement('requireAnswer');
		
		
		// override default label and make text box bigger
		$this->question->setLabel('Text:')
			->setOptions(array(
				'rows' => 12,
				'cols' => 70));
	}
	
	public function addQuestionCategorySpecificElements(){
	}
}