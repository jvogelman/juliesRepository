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
				'-1' => '',
				enums_QuestionCategory::MultipleChoiceOneAnswer => 'Multiple Choice - one answer',
				enums_QuestionCategory::MultipleChoiceMultipleAnswers => 'Multiple Choice - multiple answers',
				enums_QuestionCategory::CommentEssayBox	=> 'Comment/Essay Box',
				enums_QuestionCategory::DescriptiveText => 'Descriptive Text',
				enums_QuestionCategory::MatrixOfChoices => 'Matrix of Choices'
			))
			->setOptions(array(
				'class' => 'zendFormElement'))
			->setValue('-1')
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
		->setDecorators($this->buttonDecorators);
		//->setOptions(array('onclick' => 'editQuestionDialog.dialog("close");'));
		
		$this->hiddenQuestionId = new Zend_Form_Element_Hidden('hiddenQuestionId');
		$this->hiddenQuestionId->setOptions(array(
				'class' => 'hidden'))
				->setDecorators(array(
						array('ViewHelper')));


		$this->addElement($this->question)
			->addElement($this->questionType)
			->addElement($this->requireAnswer);
		
		$this->addQuestionCategorySpecificElements();
		
		$this->addElement($save)
			->addElement($cancel)
			->addElement($this->hiddenQuestionId);
		
	}
	
	abstract function addQuestionCategorySpecificElements();
	
	function setQuestionId($questionId) {
		$this->setAction('owner/question/save/' . $questionId);
		$this->questionId = $questionId;
		$this->hiddenQuestionId->setValue($questionId);
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