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
				->addWhere('s.ID = ?', $surveyId);
			$result = $q->fetchArray();
			if (count($result) < 0) {
				throw new Zend_Controller_Action_Exception('No survey found with requested ID');
			}
					
			$this->view->survey = $result[0];
			
			// get the questions for this survey
			$q = Doctrine_Query::create()
      			->select('q.*, p.PageNum as PageNum, c.Name as CategoryName, e.SingleLine as SingleLine')
				->from('Survey_Model_Question q')
				->where('q.SurveyID = ?', $surveyId)
				->leftJoin('q.Survey_Model_Page p')
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
					->where('s.QuestionID = ?', $questionId)
					->orderBy('s.SelectionIndex');
				$result = $q->fetchArray();
				
				$selections[$questionId] = $result;
			}			
			
			// build an array of pages to get any page names
			$q = Doctrine_Query::create()
				->select('p.*')
				->from('Survey_Model_Page p')
				->where('p.SurveyID = ?', $surveyId);
			$p = $q->fetchArray();
			
			$pages = array();

			foreach ($p as $page) {
				$pages[$page['PageNum']] = $page['Name'];
			}

			$this->view->questions = $quest;
			$this->view->selections = $selections;
			$this->view->childQuestions = $childQuestions;
			$this->view->pages = $pages;
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
			// push back all the other pages to make room for the new page 
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			$this->incrementNumPageNumsInSurvey($input->surveyId);
			
			$page = new Survey_Model_Page;
			$page->PageNum = $input->newPageIndex;
			$page->SurveyID = $input->surveyId;
			$page->save();
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
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
			
			// get page ID corresponding to page index
			$pageId = getPageAtIndex($input->surveyId, $input->pageIndex);
			
			// delete entry from Page
			$q = Doctrine_Query::create()
				->delete('Survey_Model_Page p')
				->addWhere('p.ID = ?', $pageId);
			$q->execute();
			
			// decrement PageNum in other entries of Page table
			$this->decrementPageNums($input->surveyId, $input->pageIndex);
			// reduce number of pages in survey
			$this->decrementNumPageNumsInSurvey($input->surveyId);	
		
			// delete all of the questions on this page
			$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.ID = ?', $input->surveyId)
			->addWhere('q.PageID = ?', $pageId);
			$questions = $q->fetchArray();
			
			foreach ($questions as $question) {
				//throw new Zend_Controller_Action_Exception('question ID = ' . $question['ID']);
				deleteQuestionFromPage($question['ID']);
			}
				
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
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
			// get Page ID for currentPageIndex
			$pageId = getPageAtIndex($input->surveyId, $input->currentPageIndex);
			
			// incrementPageNums should reset page nums for other Page IDs
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			
			// set to new index
			$q = Doctrine_Query::create()
				->update('Survey_Model_Page p')
				->set('p.PageNum', '?', $input->newPageIndex)
				->where('p.ID = ?', $pageId);
			$q->execute();
			
			$this->decrementPageNums($input->surveyId, $input->currentPageIndex + 1);
			
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
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
			// get page ID for currentPageIndex
			$pageId = getPageAtIndex($input->surveyId, $input->currentPageIndex);
			
			// get the questions on the original page so they can be copied later
			$q = Doctrine_Query::create()
				->select('q.*, s.ID as surveyId')
				->from('Survey_Model_Question q')
				->leftJoin('q.Survey_Model_Survey s')
				->where('s.ID = ?', $input->surveyId)
				->addWhere('q.PageID = ?', $pageId)
				->addWhere('q.CategoryID != ?', enums_QuestionCategory::MatrixOfChoicesChild)
				->orderBy('q.QuestionIndex');
			$questions = $q->fetchArray();
			
			// push back all the other pages to make room for the new page 
			$this->incrementPageNums($input->surveyId, $input->newPageIndex);
			$this->incrementNumPageNumsInSurvey($input->surveyId);
			
			// get the original page information
			$q = Doctrine_Query::create()
				->select('p.Name')
				->from('Survey_Model_Page p')
				->where('p.SurveyID = ?', $input->surveyId)
				->addWhere('p.ID = ?', $pageId);
			$pages = $q->fetchArray();
			
			// create a new Page entry
			$page = new Survey_Model_Page;
			$page->PageNum = $input->newPageIndex;
			$page->SurveyID = $input->surveyId;
			$page->Name = $pages[0]['Name'];
			$page->save();
			$newPageId = $page->ID;
			
				
			// for each question on the original page, copy it into the new page at the same index		
			foreach ($questions as $question) {
				copyQuestion($input->surveyId, $question['ID'], $newPageId, $question['QuestionIndex']);				
			}			
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	function editpagenameAction() {
		session_start();
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'pageIndex' => array('NotEmpty', 'Int'),
				'pageName' => array()
		);
			
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'pageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'pageName' => array()
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());		
			
		verifyUserMatchesSurvey($input->surveyId);
		
		
		// remove begin and end quotes from page name
		$pageName = $input->pageName;
		
		if (strlen($pageName) >= 6 && substr($pageName, 0, 6) == '&quot;') {
			$pageName = substr($pageName, 6); // 6 = length of '&quot;'
			fwrite($fh, "compare a successful: " . $pageName);
		}
		if (strlen($pageName) >= 6 && substr($pageName, strlen($pageName) - 6, 6) == '&quot;') {
			$pageName = substr($pageName, 0, strlen($pageName) - 6);
			fwrite($fh, "compare b successful: " . $pageName);
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Page p')
			->set('p.Name', '?', $pageName)
			->where('p.PageNum = ?', $input->pageIndex)
			->addWhere('p.SurveyID = ?', $input->surveyId);
		$q->execute();

		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	// for each page starting with $firstPage, increment its PageNum
	private function incrementPageNums($surveyId, $firstPage) {

		$q = Doctrine_Query::create()
			->select('p.ID, p.PageNum')
			->from('Survey_Model_Page p')
			->where('p.SurveyID = ?', $surveyId)
			->addWhere('p.PageNum >= ?', $firstPage);
		$pages = $q->fetchArray();
		
		foreach ($pages as $page) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Page p')
				->set('p.PageNum', '?', $page['PageNum'] + 1)
				->where('p.ID = ?', $page['ID']);
			$q->execute();
		}
	}
	
	// for each page starting with $firstPage, decrement its PageNum
	private function decrementPageNums($surveyId, $firstPage) {

	$q = Doctrine_Query::create()
			->select('p.ID, p.PageNum')
			->from('Survey_Model_Page p')
			->where('p.SurveyID = ?', $surveyId)
			->addWhere('p.PageNum >= ?', $firstPage);
		$pages = $q->fetchArray();
		
		foreach ($pages as $page) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Page p')
				->set('p.PageNum', '?', $page['PageNum'] - 1)
				->where('p.ID = ?', $page['ID']);
			$q->execute();
		}
	}
	
	private function incrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
			->select('s.*')
			->from('Survey_Model_Survey s')
			->where('s.ID = ?', $surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Survey s')
			->set('s.NumPages', '?', $surveys[0]['NumPages'] + 1)
			->where('s.ID = ?', $surveyId);
		$q->execute();
	}
	
	private function decrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
			->select('s.*')
			->from('Survey_Model_Survey s')
			->where('s.ID = ?', $surveyId);
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
			->where('s.ID = ?', $surveyId);
		$q->execute();
	}
	
}



