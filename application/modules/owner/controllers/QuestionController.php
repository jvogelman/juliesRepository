<?php

require_once 'survey/Form/QuestionCreate.php';
require_once 'survey/Model/Question.php';
require_once 'enums.php';

class Owner_QuestionController extends Zend_Controller_Action
{
	public function init()
	{	
	}
	
	public function showeditAction(){
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			
			$userId = $this->getUserId();
			
			// #### we don't really need surveyId, do we?
			
			$validators = array(
					'surveyId' => array('NotEmpty', 'Int'),
					'questionId' => array('NotEmpty', 'Int')
			);
			
			$filters = array(
					'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim')
			);
			
			$input = new Zend_Filter_Input($filters, $validators);
			$input->setData($this->getRequest()->getParams());
					
		
			$response = "";
			$form;
			
			if ($input->isValid())
			{
				$q = Doctrine_Query::create()
					->select('q.*, m.AddOtherField as AddOtherField, m.SingleLine as mSingleLine, e.SingleLine as eSingleLine, 
							mat.RandomizeAnswers as RandomizeAnswers, c.Name as CategoryName, s.OwnerID')
					->from('Survey_Model_Question q')
					->leftJoin('q.Survey_Model_Questioncategory c')
					->leftJoin('q.Survey_Model_Survey s')
					->leftJoin('q.Survey_Model_Multiplechoicequestion m')
					->leftJoin('q.Survey_Model_Essayboxquestion e')
					->leftJoin('q.Survey_Model_Matrixofchoicesquestion mat')
					->where('q.SurveyID = ' . $input->surveyId)
					->addWhere('q.ID = ' . $input->questionId)
					->addWhere('s.OwnerID = ' . $userId);
				$question = $q->fetchArray();
				
				if (count($question) < 1) {
					$response = "ERROR:database returned no value";
				}
				else {
					
					switch ($question[0]['CategoryID']){
						case enums_QuestionCategory::Undefined:
							$form = new Survey_Form_UndefinedCategoryQuestion;
							$form->setQuestionId($question[0]['ID']);
							
							break;
						case enums_QuestionCategory::MultipleChoiceOneAnswer:
						case enums_QuestionCategory::MultipleChoiceMultipleAnswers:
							$form = new Survey_Form_MultipleChoiceQuestion;
							
							$form->setQuestionId($question[0]['ID']);
							
							// input all the values into the form

							$form->setQuestionType($question[0]['CategoryID']);
							$form->setQuestionDescription($question[0]['Text']);
							$form->setRequireAnswer($question[0]['RequireAnswer']);
							
							$form->setOtherField($question[0]['AddOtherField'], $question[0]['mSingleLine']);
							
							// get the selections that correspond to this question and add those to the form
							$s = Doctrine_Query::create()
								->from('Survey_Model_Selection s')
								->where('s.QuestionID = ' . $input->questionId);
							$selections = $s->fetchArray();
							$formSelections = array();
							foreach ($selections as $selection) {
								$formSelections[] = $selection['Text'];
							}
							$form->setSelections($formSelections);
							
							break;
						case enums_QuestionCategory::CommentEssayBox:
							$form = new Survey_Form_CommentEssayBoxQuestion;
							$form->setQuestionId($question[0]['ID']);
							
							// input all the values into the form

							$form->setQuestionType($question[0]['CategoryID']);
							$form->setQuestionDescription($question[0]['Text']);
							$form->setRequireAnswer($question[0]['RequireAnswer']);
							
							$form->setTextBoxSize($question[0]['eSingleLine']);
							break;
						case enums_QuestionCategory::DescriptiveText:
							$form = new Survey_Form_DescriptiveText;
							$form->setQuestionId($question[0]['ID']);
							$form->setQuestionType($question[0]['CategoryID']);
							$form->setQuestionDescription($question[0]['Text']);
							break;
						case enums_QuestionCategory::MatrixOfChoices:
							$form = new Survey_Form_MatrixOfChoicesQuestion;
							$form->setQuestionId($question[0]['ID']);
							$form->setQuestionType($question[0]['CategoryID']);
							$form->setQuestionDescription($question[0]['Text']);
							
							// input the rows (i.e. the child questions)
							$q = Doctrine_Query::create()
								->from('Survey_Model_Question q')
								->where('q.ParentQuestionID = ' . $input->questionId);
							$childQuestions = $q->fetchArray();
							$rows = array();
		
							foreach ($childQuestions as $child) {
								$rows[$child['QuestionIndex']] = $child['Text'];
							}
							
							$form->setRows($rows);
							
							// input the columns (i.e. the selections for this question)
							$q = Doctrine_Query::create()
								->from('Survey_Model_Selection s')
								->where('s.QuestionID = ' . $input->questionId);
							$selections = $q->fetchArray();
							$formSelections = array();
							foreach ($selections as $selection) {
								$formSelections[] = $selection['Text'];
							}
							$form->setColumns($formSelections);
							
							// set "randomize"
							$form->setRandomize($question[0]['RandomizeAnswers']);
							// set "require answer"
							$form->setRequireAnswer($question[0]['RequireAnswer']);
							
							break;
						default:
							throw new Zend_Controller_Action_Exception('Cannot show edit window for question of this type');
					}
				}
				
				
				$response = $form;
				// #### temporary:
				$myFile = "testFile.txt";
				$fh = fopen($myFile, 'w');
				fwrite($fh, $response);
			}
			else {
				$response = "ERROR:invalid input";
			}	
		}
		catch (Exception $e){
			$response = "ERROR:page threw exception: " . $e;
		}			
		
		echo $response; 
		
	}
	
	public function addAction() {
		
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
								
			// "get" variables: survey ID, page, new question index
			
			$validators = array(
					'surveyId' => array('NotEmpty', 'Int'),
					'page' => array('NotEmpty', 'Int'),
					'index' => array('NotEmpty', 'Int')
			);
				
			$filters = array(
					'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'page' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'index' => array('HtmlEntities', 'StripTags', 'StringTrim')
			);
				
			$input = new Zend_Filter_Input($filters, $validators);
			$input->setData($this->getRequest()->getParams());			
			


			if ($input->isValid())	{			
				$questionID = $this->addQuestionToPage($input->surveyId, $input->page, $input->index);
		
				// redirect to showEditAction
				$this->_redirect('/owner/question/showedit?surveyId=' . $input->surveyId . '&questionId=' . $questionID);
			}
		}
		catch (Exception $e){
			$response = "ERROR:page threw exception: " . $e;
		}
		
		echo $response;
	}
	
	public function addQuestionToPage($surveyId, $page, $index) {
		
		$userId = $this->getUserId();
	
		// first update the other indices in this page
		$this->incrementQuestionIndices($surveyId, $userId, $page, $index);
		
		// add new question to database
		$q = new Survey_Model_Question;
		$q->Text = '';
		$q->SurveyID = $surveyId;
		$q->QuestionIndex = $index;
		$q->PageNum = $page;
		$q->CategoryID = enums_QuestionCategory::Undefined;
		$q->RequireAnswer = 0;
		$q->save();
		$id = $q['ID'];
		
		return $id;
	}
	public function deleteQuestionFromPage($questionId) {
		
		// first verify that this is the right user for this survey question
		$userId = $this->getUserId();	
				
		$q = Doctrine_Query::create()
			->select('q.*, s.OwnerID')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.OwnerID = ' . $userId)
			->addWhere('q.ID = ' . $questionId);
		$questions = $q->fetchArray();
		if (count($questions) < 1) {
			throw new Zend_Controller_Action_Exception('Deletion failed for some reason');
		}
		
		$surveyId = $questions[0]['SurveyID'];
		$page = $questions[0]['PageNum'];
		$index = $questions[0]['QuestionIndex'];
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
				
		// remove this question from database
		$q = Doctrine_Query::create()
			->delete('Survey_Model_Question q')
			->addWhere('q.ID = ?', $questionId);
		$q->execute();
		
		// update the other indices in this page
		$this->decrementQuestionIndices($surveyId, $userId, $page, $index);
		
		$conn->commit();
	}
	
	public function deleteAction(){
		
		$validators = array(
				'questionId' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		$this->deleteQuestionFromPage($input->questionId);
				
		$this->_redirect('/owner/survey/show');
	}
	
	public function shownewcategoryAction() {
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
				
			$userId = $this->getUserId();
				
			// #### we don't really need surveyId, do we?
				
			$validators = array(
					'surveyId' => array('NotEmpty', 'Int'),
					'questionId' => array('NotEmpty', 'Int'),
					'newCategory' => array('NotEmpty'),
					'description' => array()
			);
				
			$filters = array(
					'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'newCategory' => array(),
					'description' => array()
			);
				
			$input = new Zend_Filter_Input($filters, $validators);
			$input->setData($this->getRequest()->getParams());
				
		
			$response = "";
			$form;
				
			if ($input->isValid())
			{
				$q = Doctrine_Query::create()
					->select('q.*, c.Name as CategoryName, s.OwnerID')
					->from('Survey_Model_Question q')
					->leftJoin('q.Survey_Model_Questioncategory c')
					->leftJoin('q.Survey_Model_Survey s')
					->where('q.SurveyID = ' . $input->surveyId)
					->addWhere('q.ID = ' . $input->questionId)
					->addWhere('s.OwnerID = ' . $userId);
				$question = $q->fetchArray();
		
				if (count($question) < 1) {
					echo "ERROR:database returned no value";
					return;
				}
				else {
					$form;
					
					switch ($input->newCategory) {
						case enums_QuestionCategory::Undefined:
							$form = new Survey_Form_UndefinedCategoryQuestion;
							break;
						case enums_QuestionCategory::MultipleChoiceOneAnswer:
						case enums_QuestionCategory::MultipleChoiceMultipleAnswers:							
							$form = new Survey_Form_MultipleChoiceQuestion;
							break;
						case enums_QuestionCategory::CommentEssayBox:
							$form = new Survey_Form_CommentEssayBoxQuestion;
							break;
						case enums_QuestionCategory::DescriptiveText:
							$form = new Survey_Form_DescriptiveText;
							break;
						case enums_QuestionCategory::MatrixOfChoices:
							$form = new Survey_Form_MatrixOfChoicesQuestion;
							break;
					}
							
					if ($form == null) {
						echo "ERROR:category " . $input->newCategory . " not implemented";
						return;
					}
					$form->setQuestionId($question[0]['ID']);
					
					$form->setQuestionType($input->newCategory);
					$form->setQuestionDescription($input->description);
					$form->setRequireAnswer($question[0]['RequireAnswer']);
							
				}
				
				
				$response = $form;
			}
			else {
				$response = "ERROR:invalid input";
			}	
		}
		catch (Exception $e){
			$response = "ERROR:page threw exception: " . $e;
		}			
		
		echo $response; 
	}
	
	public function saveAction()
	{
		
		$userId = $this->getUserId();
				
		
		// first do the general stuff that applies to all question types
		
		$validators = array(
				'questionId' => array('NotEmpty', 'Int'),
				'question' => array('NotEmpty'),
				'questionType' => array(),
				'requireAnswer' => array()
		);
		
		// already filtered, so just set to empty arrays
		$filters = array(
				'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'question' => array(),//'HtmlEntities', 'StringTrim'), // #### will we still be able to display original html tags back to user?
				'questionType' => array(),
				'requireAnswer' => array()
		);
			
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		

		// #### delete
		$myFile = "testFile.txt";
		$fh = fopen($myFile, 'w');
		fwrite($fh, $input->question . "\n");
		fwrite($fh, $this->getRequest()->getParam('question'));

		if ($input->isValid())
		{
			// verify that this user is authorized to update this survey
			
			$q = Doctrine_Query::create()
				->from('Survey_Model_Question q')
				->where('q.ID = ?', $input->questionId);
			$question = $q->fetchArray();
			if (sizeof($question) == 0) {
				throw new Zend_Controller_Action_Exception('No Question matches question ID ' . $input->questionId);
			}
			
			// make sure the user is authorized
			$surveyId = $question[0]['SurveyID'];
			$q = Doctrine_Query::create()
				->from('Survey_Model_Survey s')
				->where('s.ID = ?', $surveyId)
				->addWhere('s.OwnerID = ?', $userId);
			$user = $q->fetchArray();
			if (sizeof($user) == 0) {
				throw new Zend_Controller_Action_Exception('User is not associated with this survey');
			}
			
			$questionType = $this->getRequest()->getParam('questionType');
			$requireAnswer;
			if ($questionType == enums_QuestionCategory::DescriptiveText) {
				$requireAnswer = 0;
			} else {
				$requireAnswer = $input->requireAnswer;
			}
			
			$conn = Doctrine_Manager::connection();
			$conn->beginTransaction();
			
			// update the Question in the DB according to form
			// #### should this be in the form of some transaction? 
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.Text', '?', $input->question)
				->set('q.CategoryID' ,'?',  $questionType) 
				->set('q.RequireAnswer', '?', $requireAnswer)
				->where('q.ID = ?', $input->questionId);
			$q->execute();
		
		
			switch ($questionType) {
			case enums_QuestionCategory::MultipleChoiceOneAnswer:
			case enums_QuestionCategory::MultipleChoiceMultipleAnswers:			

				$form = new Survey_Form_MultipleChoiceQuestion;
				
				if ($form->isValid($this->getRequest()->getPost()))
				{
					if (!$form->getSubForm('selection')) {
						throw new Zend_Controller_Action_Exception('Can\'t find subform "selection"');
					}
					
					if (!$form->getSubForm('selection')->isValid($this->getRequest()->getParams())){
						throw new Zend_Controller_Action_Exception('Zend subform "selection" input is invalid');
					}			
					
					// update Selections	
					// each selection may or may not exist: to simplify, just delete all selections and add them back in
					$q = Doctrine_Query::create()
						->delete('Survey_Model_Selection s')
						->addWhere('s.QuestionID = ?', $input->questionId);
					$q->execute();
					
					$selections = $this->getRequest()->getParam('selection');
					for ($i = 1; $i <= count($selections); $i++) {
						// insert 
						
						if ($selections[$i] != ''){	// #### what if middle element is empty? let's use javascript to prevent that
							$selectionText = $selections[$i];
							$s = new Survey_Model_Selection;
							$s->SelectionIndex = $i;
							$s->Text = $selectionText;
							$s->QuestionID = $input->questionId;
						
							$s->save();
						}
						
					}
					
					// update "Other" field
					
					// get the value for otherFieldSize, which is only available if addOtherField is set
					$otherFieldSize = 0;
					if ($this->getRequest()->getParam('otherField')) {
						$otherFieldSize = $this->getRequest()->getParam('otherFieldSize');
					}
					
					// is this question ID already in the MultipleChoiceQuestion table?
					$q = Doctrine_Query::create()
						->from('Survey_Model_Multiplechoicequestion m')
						->where('m.QuestionID = ?', $input->questionId);
					$mcqs = $q->fetchArray();
					
					if (sizeof($mcqs) == 0) {
						// not in there, so add it
						$mcq = new Survey_Model_Multiplechoicequestion;
						$mcq->QuestionID = $input->questionId;
						$mcq->AddOtherField = $this->getRequest()->getParam('otherField');
						if ($this->getRequest()->getParam('otherField')) {
							$mcq->SingleLine = $otherFieldSize;
						}
						$mcq->save();
					} else {
						// already in there, so update it
						$q = Doctrine_Query::create()
							->update('Survey_Model_Multiplechoicequestion m')
							->set('m.AddOtherField', '?', $this->getRequest()->getParam('otherField'))
							->set('m.SingleLine' ,'?', $otherFieldSize)
							->where('m.QuestionID = ?', $input->questionId);
						$q->execute();
					}
					
				} else {
					throw new Zend_Controller_Action_Exception('Input is invalid');
				}
				break;
			case enums_QuestionCategory::CommentEssayBox:
				$form = new Survey_Form_CommentEssayBoxQuestion;
				
				if ($form->isValid($this->getRequest()->getPost()))
				{
					// is this question ID already in the EssayBoxQuestion table?
					$q = Doctrine_Query::create()
						->from('Survey_Model_Essayboxquestion e')
						->where('e.QuestionID = ?', $input->questionId);
					$ebqs = $q->fetchArray();
	
					if (sizeof($ebqs) == 0) {
						// not in there, so add it
						$ebq = new Survey_Model_Essayboxquestion;
						$ebq->QuestionID = $input->questionId;
						$ebq->SingleLine = $this->getRequest()->getParam('textBoxSize');
						$ebq->save();
					} else {
						// already in there, so update it
						$q = Doctrine_Query::create()
							->update('Survey_Model_Essayboxquestion e')
							->set('e.SingleLine', '?', $this->getRequest()->getParam('textBoxSize'))
							->where('e.QuestionID = ?', $input->questionId);
						$q->execute();
					}
				} else {
					throw new Zend_Controller_Action_Exception('Input is invalid');
				}
				break;
			case enums_QuestionCategory::DescriptiveText:
				// nothing left to do
				break;
			case enums_QuestionCategory::MatrixOfChoices:

				$form = new Survey_Form_MatrixOfChoicesQuestion;
				
				if ($form->isValid($this->getRequest()->getPost()))
				{
					// is this question ID already in the MatrixOfChoicesQuestion table?
					$q = Doctrine_Query::create()
					->from('Survey_Model_Matrixofchoicesquestion s')
					->where('s.QuestionID = ?', $input->questionId);
					$mcqs = $q->fetchArray();
	
					if (sizeof($mcqs) == 0) {
						// not in there, so add it
						$mcq = new Survey_Model_Matrixofchoicesquestion;
						$mcq->QuestionID = $input->questionId;
						$mcq->RandomizeAnswers = $this->getRequest()->getParam('randomize');
						$mcq->save();
					} else {
						// already in there, so update it
						$q = Doctrine_Query::create()
							->update('Survey_Model_Matrixofchoicesquestion s')
							->set('s.RandomizeAnswers', '?', $this->getRequest()->getParam('randomize'))
							->where('s.QuestionID = ?', $input->questionId);
						$q->execute();
					}
					
					// Update the row choices (these are the child questions of this question)
					// Delete the old ones and re-add
					$q = Doctrine_Query::create()
						->delete('Survey_Model_Question q')
						->addWhere('q.ParentQuestionID = ?', $input->questionId);
					$q->execute();
					
					// hiddenRowChoices value is comma delimited
					$rowChoices = $this->getRequest()->getParam('hiddenRowChoices');
					$i = 1;
					while (strpos($rowChoices, ',')) {
						$comma = strpos($rowChoices, ',');
						$cq = new Survey_Model_Question;
						$cq->Text = substr($rowChoices, 0, $comma);
						$cq->SurveyID = $question[0]['SurveyID'];
						$cq->QuestionIndex = $i++;
						$cq->PageNum = $question[0]['PageNum'];
						$cq->CategoryID = enums_QuestionCategory::MatrixOfChoicesChild;
						$cq->ParentQuestionID = $input->questionId;
						$cq->RequireAnswer = $input->requireAnswer;
						
						$rowChoices = substr($rowChoices, $comma + 1);
					
						$cq->save();
					}				

					
					// Update the column choices (these are the entries in the Selection table for this question)
					// Delete the old ones and re-add
					$q = Doctrine_Query::create()
						->delete('Survey_Model_Selection s')
						->addWhere('s.QuestionID = ?', $input->questionId);
					$q->execute();
					
					// hiddenColumnChoices value is comma delimited
					$columnChoices = $this->getRequest()->getParam('hiddenColumnChoices');
					$i = 1;
					while (strpos($columnChoices, ',')) {
						$comma = strpos($columnChoices, ',');
						$s = new Survey_Model_Selection;
						$s->SelectionIndex = $i++;
						$s->Text = substr($columnChoices, 0, $comma);
						$s->QuestionID = $input->questionId;
						$columnChoices = substr($columnChoices, $comma + 1);
					
						$s->save();
					}
				} else {
					
					throw new Zend_Controller_Action_Exception('Input is invalid: ' . print_r($form->getErrors()));
				}				
				
				break;
			default:
				throw new Zend_Controller_Action_Exception('Currently unable to handle questions of type ' . $questionType);
			
			}
			
			$conn->commit();
			$this->_redirect('/owner/survey/show');
		}
	}
	


	public function moveAction() {
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'questionId' => array('NotEmpty', 'Int'),
				'page' => array('NotEmpty', 'Int'),
				'newQuestionIndex' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'page' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'newQuestionIndex' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());	
		
		$userId = $this->getUserId();
		
		// get the current page/question index
		$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Question q')
			->addWhere('q.ID = ' . $input->questionId);
		$question = $q->fetchArray();
		$origPage = $question[0]['PageNum'];
		$origIndex = $question[0]['QuestionIndex'];		

		// use a transaction so that either all or no changes get committed: ### can we verify this actually works?
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		
		// in the new page, update the indices for any questions that follow
		$this->incrementQuestionIndices($input->surveyId, $userId, $input->page, $input->newQuestionIndex);
		
		
		// update the Question with the new page/index
		try {		
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.QuestionIndex' ,'?',  $input->newQuestionIndex)
				->set('q.PageNum', '?', $input->page)
				->where('q.ID = ?', $input->questionId);
			$q->execute();
		} catch (Exception $exc) {
			// rewind
			$this->decrementQuestionIndices($input->surveyId, $userId, $input->page, $input->newQuestionIndex);
			throw $exc;
		}
		
		
		$this->decrementQuestionIndices($input->surveyId, $userId, $origPage, $origIndex + 1);
		
		$conn->commit();
		
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	

	private function incrementQuestionIndices($surveyId, $userId, $page, $firstIndex) {

		$q = Doctrine_Query::create()
		->select('q.*, s.OwnerID')
		->from('Survey_Model_Question q')
		->leftJoin('q.Survey_Model_Survey s')
		->where('q.SurveyID = ' . $surveyId)
		->addWhere('q.PageNum = ' . $page)
		->addWhere('s.OwnerID = ' . $userId)
		->addWhere('q.QuestionIndex >= ' . $firstIndex)
		->addWhere('q.ParentQuestionID IS NULL');
		$questions = $q->fetchArray();
		
	
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.QuestionIndex' ,'?',  $question['QuestionIndex'] + 1)
				->where('q.ID = ?', $question['ID']);
			$q->execute();
		}
	}
	
	private function decrementQuestionIndices($surveyId, $userId, $page, $firstIndex) {
		$q = Doctrine_Query::create()
		->select('q.*, s.OwnerID')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('q.SurveyID = ' . $surveyId)
			->addWhere('q.PageNum = ' . $page)
			->addWhere('s.OwnerID = ' . $userId)
			->addWhere('q.QuestionIndex >= ' . $firstIndex)
			->addWhere('q.ParentQuestionID IS NULL');
		$questions = $q->fetchArray();
	
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.QuestionIndex' ,'?',  $question['QuestionIndex'] - 1)
				->where('q.ID = ?', $question['ID']);
			$q->execute();
		}
	}
	

	private function getUserId(){
		session_start();
			
		// get session variable for user id
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");
		}
			
		return $_SESSION["userId"];
	}
}