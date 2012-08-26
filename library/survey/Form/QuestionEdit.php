<?php
abstract class Survey_Form_QuestionEdit extends Zend_Form
{
	public $formDecorators = array(
			array('FormElements'),
			array('Form'));

	public $elementDecorators = array(
			array('ViewHelper'),
			array('Label', array('class' => 'formLabel')),
			array('Errors'),
			array('HtmlTag', array('tag' => 'p')));

	public $checkboxDecorators = array(
			array('ViewHelper'),
			array('Label', array('class' => 'formLabel', 'placement' => 'APPEND')),
			array('Errors'),
			array('HtmlTag', array('tag' => 'p')));

	public $buttonDecorators = array(
			array('ViewHelper'));

	private $questionId;
	private $question;
	private $questionType;
	private $requireAnswer;

	public function init()
	{
		// initialize form for "Save"
		$this->setAction('owner/question/save')
			->setMethod('post')
			->setDecorators($this->formDecorators);

		$this->question = new Zend_Form_Element_Textarea('question');

		$this->question->setLabel('Question Description:')
			->setOptions(array(
				'id' => 'question',
				'class' => 'zendFormElement',
				'rows' => 3,
				'cols' => 50))
				->setRequired(true)
				//->addFilter('HtmlEntities')
				->setDecorators($this->elementDecorators);
			
			
		$this->questionType = new Zend_Form_Element_Select('questionType');
		$this->questionType->setLabel('Question Type:')
			->setMultiOptions(array(
				'1' => 'Multiple Choice - one answer',
				'2' => 'Multiple Choice - multiple answers',
				'3'	=> 'Comment/Essay Box',
				'4' => 'Descriptive Text',
				'5' => 'Matrix of Choices'
			))
			->setOptions(array(
				'class' => 'zendFormElement'))
			->setValue('Multiple Choice - one answer')
			->setDecorators($this->elementDecorators);

		
		$this->requireAnswer = new Zend_Form_Element_Checkbox('requireAnswer');
		$this->requireAnswer->setLabel('Require an answer to this question')
		->setDecorators($this->checkboxDecorators)
		->setOptions(array(
				'class' => 'zendFormElement'));


		$save = new Zend_Form_Element_Submit('save');
		$save->setLabel('Save')
		->setDecorators($this->buttonDecorators);

		$cancel = new Zend_Form_Element_Button('cancel');
		$cancel->setLabel('Cancel')
		->setDecorators($this->buttonDecorators)
		->setOptions(array('onclick' => 'editQuestionDialog.dialog("close");'));


		$this->addElement($this->question)
			->addElement($this->questionType)
			->addElement($this->requireAnswer);
		
		$this->addQuestionCategorySpecificElements();
		
		$this->addElement($save)
			->addElement($cancel);
		
	}
	
	abstract function addQuestionCategorySpecificElements();
	
	function setQuestionId($questionId) {
		$this->setAction('owner/question/save/' . $questionId);
		$this->questionId = $questionId;
	}
	
	function setQuestionDescription($desc) {
		$this->question->setValue(html_entity_decode($desc));
	}
	
	function setQuestionType($type) {
		$this->questionType->setValue($type);
	}
	
	function setRequireAnswer($requireAnswer) {
		$this->requireAnswer->setValue($requireAnswer);
	}

}