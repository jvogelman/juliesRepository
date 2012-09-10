<?php

require_once 'shared.php';

class Owner_SurveyController extends Zend_Controller_Action
{
	public function showAction(){
				
		session_start();		
		
		$userId = getUserId();
		
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int')
		);
		
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		verifyUserMatchesSurvey($input->surveyId);
		
		if ($input->isValid()) {
			
			$quest = array();
			$selections = array();
			$childQuestions = array();
		
			// get the id passed by variable in the url
			$surveyId = $this->getRequest()->getParam('surveyId');	
			
			// get the record in the survey table for this id
			$q = Doctrine_Query::create()
				->from('Survey_Model_Survey s')
				->addWhere('s.ID = ' . $surveyId);
			$result = $q->fetchArray();
			if (count($result) < 0) {
				throw new Zend_Controller_Action_Exception('No survey found with requested ID');
			}
					
			$this->view->survey = $result[0];
			
			// get the questions for this survey
			$q = Doctrine_Query::create()
      			->select('q.*, c.Name as CategoryName, e.SingleLine as SingleLine')
				->from('Survey_Model_Question q')
				->where('q.SurveyID = ' . $surveyId)
				->leftJoin('q.Survey_Model_Questioncategory c')
				->leftJoin('q.Survey_Model_Essayboxquestion e');
			$result = $q->fetchArray();
			$questions = $result;
			
			// build a map of page # to array of questions
			// build a map of question ID to selection array
			// build a map of question ID to child question ID array (for the case of "Matrix of Choices")
			
			for ($i = 1; $i <= $this->view->survey['NumPages']; $i++) {
				$quest[$i] = array();
			}
			
			foreach ($questions as $q) {
				$questionId = $q["ID"];
				$page = $q["PageNum"];
				$indexInPage = $q["QuestionIndex"];
				if ($q["CategoryName"] == 'Matrix of Choices') {
					if (!in_array($questionId, $childQuestions)) {
						$childQuestions[$questionId] = array();
					}
					$quest[$page][$indexInPage] = $q;
				}
				else if ($q["CategoryName"] == "Matrix of Choices Child"){
					$parentQuestionId = $q["ParentQuestionID"];
					$childQuestions[$parentQuestionId][] = $q;
				}
				else {
					$quest[$page][$indexInPage] = $q;
				} 

				$q = Doctrine_Query::create()
					->from('Survey_Model_Selection s')
					->where('s.QuestionID = ' . $questionId)
					->orderBy('s.SelectionIndex');
				$result = $q->fetchArray();
				
				$selections[$questionId] = $result;
			}			

			$this->view->questions = $quest;
			$this->view->selections = $selections;
			$this->view->childQuestions = $childQuestions;
		}
		
	}
	
	// ajax function: update the survey Description in table and return resulting Description
	public function updatedescriptionAction() {
		
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			
			session_start();
			
			$userId = getUserId();
			
			$validators = array(
					'surveyId' => array('NotEmpty', 'Int'),
					'description' => array()
			);
			
			$filters = array(
					'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'description' => array('HtmlEntities', 'StringTrim') // #### will we still be able to display original html tags back to user?
			);
			
	
	
			$input = new Zend_Filter_Input($filters, $validators);
			$input->setData($this->getRequest()->getParams());
			
			verifyUserMatchesSurvey($input->surveyId);
		
			$response = "";
			
			if ($input->isValid())
			{
				$q = Doctrine_Query::create()
					->update('Survey_Model_Survey s')
					->set('s.Description', '?', $input->description)
					->where('s.OwnerID = ?', $userId)
					->addWhere('s.ID = ?', $input->surveyId);
				$q->execute();
				$response = $input->description;
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
	
	public function addpageAction() {

		session_start();	


		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'newPageIndex' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'newPageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim') 
		);
					
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
			
		verifyUserMatchesSurvey($input->surveyId);
		

		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			// push back all the other pages to make room for the new page (i.e. for each page re-numbered, increment page numbers
			// for the corresponding questions)
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			$this->incrementNumPageNumsInSurvey($input->surveyId);
			
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	public function deletepageAction() {
		session_start();
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'pageIndex' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'pageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
			
		verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try {
		
			// delete all of the questions on this page
			$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.ID = ' . $input->surveyId)
			->addWhere('q.PageNum = ' . $input->pageIndex);
			$questions = $q->fetchArray();
			
			foreach ($questions as $question) {
				deleteQuestionFromPage($question['ID']);
			}
			
			$this->decrementPageNums($input->surveyId, $input->pageIndex);
			$this->decrementNumPageNumsInSurvey($input->surveyId);		
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	public function movepageAction() {

		session_start();
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'currentPageIndex' => array('NotEmpty', 'Int'),
				'newPageIndex' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'currentPageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'newPageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
			
		verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			// store the questions that will be moved from the old page to the new page
			$q = Doctrine_Query::create()
				->select('q.*, s.ID as surveyId')
				->from('Survey_Model_Question q')
				->leftJoin('q.Survey_Model_Survey s')
				->where('s.ID = ' . $surveyId)
				->addWhere('q.PageNum = ' . $input->currentPageIndex);
			$questions = $q->fetchArray();
			
			// update the page numbers for the other questions in the survey (there is some duplicated work here in incrementing and
			// decrementing many of the same page numbers), but the cost will generally be low - could be changed however)
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			
			// update the questions on the page being moved to reflect the new page num
			foreach ($questions as $question) {
				$q = Doctrine_Query::create()
					->update('Survey_Model_Question q')
					->set('q.PageNum', '?', $input->newPageIndex)
					->where('q.ID = ?', $question['ID']);
				$q->execute();
			}
			
			$this->decrementPageNums($input->surveyId, $input->currentPageIndex + 1);
			
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	public function copypageAction() {
		session_start();
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'currentPageIndex' => array('NotEmpty', 'Int'),
				'newPageIndex' => array('NotEmpty', 'Int')
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'currentPageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'newPageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
			
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());		
			
		verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			$q = Doctrine_Query::create()
				->select('q.*, s.ID as surveyId')
				->from('Survey_Model_Question q')
				->leftJoin('q.Survey_Model_Survey s')
				->where('s.ID = ' . $input->surveyId)
				->addWhere('q.PageNum = ' . $input->currentPageIndex)
				->addWhere('q.CategoryID != ' . enums_QuestionCategory::MatrixOfChoicesChild)
				->orderBy('q.QuestionIndex');
			$questions = $q->fetchArray();
			
			// push back all the other pages to make room for the new page (i.e. for each page re-numbered, increment page numbers
			// for the corresponding questions)
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			$this->incrementNumPageNumsInSurvey($input->surveyId);
				
			// for each question on the original page, copy it into the new page at the same index		
			foreach ($questions as $question) {
				copyQuestion($input->surveyId, $question['ID'], $input->newPageIndex, $question['QuestionIndex']);				
			}			
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	// for each page starting with $firstPage, increment the PageNum for all questions on that page
	private function incrementPageNums($surveyId, $firstPage) {
		$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.ID = ' . $surveyId)
			->addWhere('q.PageNum >= ' . $firstPage);
		$questions = $q->fetchArray();
		
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.PageNum', '?', $question['PageNum'] + 1)
				->where('q.ID = ?', $question['ID']);
			$q->execute();
		}
	}
	
	// for each page starting with $firstPage, decrement the PageNum for all questions on that page
	private function decrementPageNums($surveyId, $firstPage) {

		$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.ID = ' . $surveyId)
			->addWhere('q.PageNum >= ' . $firstPage);
		$questions = $q->fetchArray();
		
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.PageNum', '?', $question['PageNum'] - 1)
				->where('q.ID = ?', $question['ID']);
			$q->execute();
		}
	}
	
	private function incrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
			->select('s.*')
			->from('Survey_Model_Survey s')
			->where('s.ID = ' . $surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Survey s')
			->set('s.NumPages', '?', $surveys[0]['NumPages'] + 1)
			->where('s.ID = ' . $surveyId);
		$q->execute();
	}
	
	private function decrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
			->select('s.*')
			->from('Survey_Model_Survey s')
			->where('s.ID = ' . $surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
		
		// make sure num page nums isn't 0
		if ($surveys[0]['NumPages'] < 1){
			throw new Zend_Controller_Action_Exception('Can\'t decrease num pages for this survey. Current pages = 0');
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Survey s')
			->set('s.NumPages', '?', $surveys[0]['NumPages'] - 1)
			->where('s.ID = ' . $surveyId);
		$q->execute();
	}
	
}



