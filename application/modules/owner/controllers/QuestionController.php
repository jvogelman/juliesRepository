<?php

require_once 'survey/Form/QuestionCreate.php';
require_once 'survey/Model/Question.php';
require_once 'enums.php';
require_once 'shared.php';

class Owner_QuestionController extends Zend_Controller_Action
{
	public function init()
	{	
	}
	
	public function showeditAction(){
		session_start();
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			
			$userId = getUserId();
			
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
				verifyUserMatchesQuestion($input->questionId);
				
				$q = Doctrine_Query::create()
					->select('q.*, m.AddOtherField as AddOtherField, m.SingleLine as mSingleLine, e.SingleLine as eSingleLine, 
							mat.RandomizeAnswers as RandomizeAnswers, c.Name as CategoryName')
					->from('Survey_Model_Question q')
					->leftJoin('q.Survey_Model_Questioncategory c')
					->leftJoin('q.Survey_Model_Survey s')
					->leftJoin('q.Survey_Model_Multiplechoicequestion m')
					->leftJoin('q.Survey_Model_Essayboxquestion e')
					->leftJoin('q.Survey_Model_Matrixofchoicesquestion mat')
					->where('q.SurveyID = ?', $input->surveyId)
					->addWhere('q.ID = ?', $input->questionId);
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
								->where('s.QuestionID = ?', $input->questionId);
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
								->where('q.ParentQuestionID = ?', $input->questionId);
							$childQuestions = $q->fetchArray();
							$rows = array();
		
							foreach ($childQuestions as $child) {
								$rows[$child['QuestionIndex']] = $child['Text'];
							}
							
							$form->setRows($rows);
							
							// input the columns (i.e. the selections for this question)
							$q = Doctrine_Query::create()
								->from('Survey_Model_Selection s')
								->where('s.QuestionID = ?', $input->questionId);
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
		session_start();
		
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
				verifyUserMatchesSurvey($input->surveyId);
				
				$questionID = $this->addQuestionToPage($input->surveyId, $input->page, $input->index);
		
				// redirect to showEditAction
				$this->_redirect('/owner/question/showedit?surveyId=' . $input->surveyId . '&questionId=' . $questionID);
			}
		}
		catch (Exception $e){
			$response = "ERROR:page threw exception: " . $e;
		}
		
		echo $response;
		fwrite($fh, $response);
	}
	
	public function addQuestionToPage($surveyId, $pageIndex, $index) {
		
		$userId = getUserId();
		
		// get page ID corresponding to page index
		$pageId = getPageAtIndex($surveyId, $pageIndex);
	
		// first update the other indices in this page
		incrementQuestionIndices($surveyId, $userId, $pageId, $index);
		
		// add new question to database
		$q = new Survey_Model_Question;
		$q->Text = '';
		$q->SurveyID = $surveyId;
		$q->QuestionIndex = $index;
		$q->PageID = $pageId;
		$q->CategoryID = enums_QuestionCategory::Undefined;
		$q->RequireAnswer = 0;
		$q->save();
		$id = $q['ID'];
		
		return $id;
	}
	
	
	public function deleteAction(){
		session_start();
		
		$validators = array(
				'questionId' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());

		verifyUserMatchesQuestion($input->questionId);		


		// get corresponding survey ID 
		$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->addWhere('q.ID = ?', $input->questionId);
		$questions = $q->fetchArray();
		$surveyId = $questions[0]['surveyId'];
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();		
		try {
			deleteQuestionFromPage($input->questionId);		
		
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
		}
				
		$this->_redirect('/owner/survey/show/' . $surveyId);
	}
	
	public function shownewcategoryAction() {
		session_start();
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
				
			$userId = getUserId();
				
				
			$validators = array(
					'surveyId' => array('NotEmpty', 'Int'),
					'questionId' => array('NotEmpty', 'Int'),
					'newCategory' => array('NotEmpty'),
					'description' => array('allowEmpty' => true)
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
				verifyUserMatchesQuestion($input->questionId);
				
				$q = Doctrine_Query::create()
					->select('q.*, c.Name as CategoryName')
					->from('Survey_Model_Question q')
					->leftJoin('q.Survey_Model_Questioncategory c')
					->leftJoin('q.Survey_Model_Survey s')
					->where('q.SurveyID = ?', $input->surveyId)
					->addWhere('q.ID = ?', $input->questionId);
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
		
		session_start();
		
		$userId = getUserId();
		
		// first do the general stuff that applies to all question types
		
		$validators = array(
				'questionId' => array('NotEmpty', 'Int'),
				'question' => array('NotEmpty'),
				'questionType' => array(),
				'requireAnswer' => array(),
				'hiddenCloseDlg' => array('NotEmpty', 'Int')
		);
		
		// already filtered, so just set to empty arrays
		$filters = array(
				'questionId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'question' => array(),//'HtmlEntities', 'StringTrim'), // #### will we still be able to display original html tags back to user?
				'questionType' => array(),
				'requireAnswer' => array(),
				'hiddenCloseDlg' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		if ($input->hiddenCloseDlg == 0) {	// "Save/Next Question" button clicked
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
		}
			
		try {				
	
			if ($input->isValid())
			{
				// verify that this user is authorized to update this survey
				verifyUserMatchesQuestion($input->questionId);
				
				$q = Doctrine_Query::create()
					->select('q.*, s.ID as surveyId, p.PageNum as PageNum')
					->from('Survey_Model_Question q')
					->leftJoin('q.Survey_Model_Survey s')
					->leftJoin('q.Survey_Model_Page p')
					->addWhere('q.ID = ?', $input->questionId);
				$question = $q->fetchArray();
				if (sizeof($question) == 0) {
					throw new Zend_Controller_Action_Exception('No Question matches question ID ' . $input->questionId);
				}
				
				$surveyId = $question[0]['surveyId'];
				$origQuestionType = $question[0]['CategoryID'];
				
				$questionType = $this->getRequest()->getParam('questionType');
				$requireAnswer;
				if ($questionType == enums_QuestionCategory::DescriptiveText) {
					$requireAnswer = 0;
				} else {
					$requireAnswer = $input->requireAnswer;
				}
				
				$conn = Doctrine_Manager::connection();
				$conn->beginTransaction();
				try {
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
					
					// if question type has changed, we might need to clean up old entries in question category specific tables
					if ($origQuestionType != $questionType) {
						switch ($origQuestionType) {
							case enums_QuestionCategory::Undefined:
								break;
							case enums_QuestionCategory::CommentEssayBox:
								$q = Doctrine_Query::create()
									->delete('Survey_Model_Essayboxquestion e')
									->addWhere('e.QuestionID = ?', $input->questionId);
								$q->execute();
								break;
							case enums_QuestionCategory::DescriptiveText:
								break;
							case enums_QuestionCategory::MatrixOfChoices:
								$q = Doctrine_Query::create()
									->delete('Survey_Model_Matrixofchoicesquestion m')
									->addWhere('m.QuestionID = ?', $input->questionId);
								$q->execute();
								break;
							case enums_QuestionCategory::MultipleChoiceMultipleAnswers:
							case enums_QuestionCategory::MultipleChoiceOneAnswer:
								$q = Doctrine_Query::create()
									->delete('Survey_Model_Multiplechoicequestion m')
									->addWhere('m.QuestionID = ?', $input->questionId);
								$q->execute();
								break;
						}
					}
					
					$conn->commit();
				} catch (Exception $exc) {
					$conn->rollback();
					
					throw $exc;
				}
				
	
				
				// if "Save/Close" was clicked, then close the dialog;
				// if "Save/Next Question" was clicked, then show user a new dialog
				if ($input->hiddenCloseDlg == 1) {
					$this->_redirect('/owner/survey/show/' . $surveyId);
				} else {
					$this->_redirect('/owner/question/add?surveyId=' . $surveyId . '&page=' . $question[0]['PageNum'] . '&index=' . ($question[0]['QuestionIndex'] + 1));		
				}
			}
		} catch (Exception $e) {
			if ($input->hiddenCloseDlg == 1) {
				throw $e;
			} else {
				echo "ERROR: " . $e;
			}
		}
	}
	


	public function moveAction() {
		session_start();
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
		
		$userId = getUserId();
		verifyUserMatchesQuestion($input->questionId);
		
		
		// get the current page/question index
		$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Question q')
			->addWhere('q.ID = ' . $input->questionId);
		$question = $q->fetchArray();
		$origPageId = $question[0]['PageID'];
		$origIndex = $question[0]['QuestionIndex'];		
		
		// get new page ID
		$newPageId = getPageAtIndex($input->surveyId, $input->page);

		// use a transaction so that either all or no changes get committed: 
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try {
		
			incrementQuestionIndices($input->surveyId, $userId, $newPageId, $input->newQuestionIndex);				
			
			// update the Question with the new page/index				
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.QuestionIndex' ,'?',  $input->newQuestionIndex)
				->set('q.PageID', '?', $newPageId)
				->where('q.ID = ?', $input->questionId);
			$q->execute();
					
			decrementQuestionIndices($input->surveyId, $userId, $origPageId, $origIndex + 1);
		
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
		}
		
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	

	public function copyAction() {
		session_start();
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
	
		$userId = getUserId();
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try {
			$pageId = getPageAtIndex($input->surveyId, $input->page);
			copyQuestion($input->surveyId, $input->questionId, $pageId, $input->newQuestionIndex);
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
		}
			
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	

	

}