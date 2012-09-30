<?php

require_once '../application/modules/owner/models/QuestionMapper.php';
require_once '../application/modules/owner/models/SurveyMapper.php';
require_once '../application/modules/owner/models/UserVerification.php';

class Owner_SurveyController extends Zend_Controller_Action
{
	public function showAction(){
				
		session_start();		
		
		$userVerification = new Owner_Model_UserVerification();
		$surveyMapper = new Owner_Model_SurveyMapper();
		
		$userId = $userVerification->getUserId();
		
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int')
		);
		
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
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
      			->select('q.*, p.PageNum as PageNum, c.Name as CategoryName, e.SingleLine as SingleLine, 
      					m.MultipleSelections as MultipleSelections, m.AddOtherField as AddOtherField, m.SingleLine as mSingleLine')
				->from('Survey_Model_Question q')
				->where('q.SurveyID = ?', $surveyId)
				->leftJoin('q.Survey_Model_Page p')
				->leftJoin('q.Survey_Model_Questioncategory c')
				->leftJoin('q.Survey_Model_Essayboxquestion e')
				->leftJoin('q.Survey_Model_Multiplechoicequestion m');
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


			$this->view->studyName = $surveyMapper->getStudyName($surveyId);
			$this->view->surveyNames = $surveyMapper->getSurveysInStudy($surveyId);
			$this->view->folderId = $surveyMapper->getFolder($surveyId);
			
			$this->view->questions = $quest;
			$this->view->selections = $selections;
			$this->view->childQuestions = $childQuestions;
			$this->view->pages = $pages;
		} else {
			$errStr = 'Sorry, the input is invalid: ';
			foreach ($this->getRequest()->getParams() as $key => $value) {
				$errStr .= ' parameter: ' . $key . ', value: ' . $value . ';';
			}
			throw new Zend_Controller_Action_Exception($errStr);
		}
		
	}
	
	// ajax function: update the survey Description in table and return resulting Description
	public function updatedescriptionAction() {
		
		try
		{
			$this->_helper->viewRenderer->setNoRender();
			$this->_helper->getHelper('layout')->disableLayout();
			
			session_start();
			
			$userVerification = new Owner_Model_UserVerification();
			$userId = $userVerification->getUserId();
			
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
			
			$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
			$response = "";
			
			if ($input->isValid())
			{
				$q = Doctrine_Query::create()
					->update('Survey_Model_Survey s')
					->set('s.Description', '?', $input->description)
					->addWhere('s.ID = ?', $input->surveyId);
				$q->execute();
				$response = $input->description;
			} else {
				$errStr = 'ERROR:Sorry, the input is invalid: ';
				foreach ($this->getRequest()->getParams() as $key => $value) {
					$errStr .= ' parameter: ' . $key . ', value: ' . $value . ';';
				}
				$response = $errStr;
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
			
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		

		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			// push back all the other pages to make room for the new page 
			$surveyMapper = new Owner_Model_SurveyMapper();
			$surveyMapper->addPage($input->surveyId, $input->newPageIndex);		
			
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
			
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try {
			
			// get page ID corresponding to page index
			$surveyMapper = new Owner_Model_SurveyMapper();
			$surveyMapper->deletePage($input->surveyId, $input->pageIndex);				
			
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
		
			
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			// get Page ID for currentPageIndex
			$surveyMapper = new Owner_Model_SurveyMapper();
			$surveyMapper->movePage($input->surveyId, $input->currentPageIndex, $input->newPageIndex);
			
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
			
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
		
		$conn = Doctrine_Manager::connection();
		$conn->beginTransaction();
		try
		{
			// get page ID for currentPageIndex
			$surveyMapper = new Owner_Model_SurveyMapper();
			$surveyMapper->copyPage($input->surveyId, $input->currentPageIndex, $input->newPageIndex);
			
			$conn->commit();
		} catch (Exception $exc) {
			$conn->rollback();
			throw $exc;
		}
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
	function editpagenameAction() {
		

		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		
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
			
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesSurvey($input->surveyId);
		
		
		// remove begin and end quotes from page name
		$pageName = $input->pageName;
		
		if (strlen($pageName) >= 6 && substr($pageName, 0, 6) == '&quot;') {
			$pageName = substr($pageName, 6); // 6 = length of '&quot;'
		}
		if (strlen($pageName) >= 6 && substr($pageName, strlen($pageName) - 6, 6) == '&quot;') {
			$pageName = substr($pageName, 0, strlen($pageName) - 6);
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Page p')
			->set('p.Name', '?', $pageName)
			->where('p.PageNum = ?', $input->pageIndex)
			->addWhere('p.SurveyID = ?', $input->surveyId);
		$q->execute();

		echo $pageName;
	}
	
	function dividepageAction() {
		$this->_helper->viewRenderer->setNoRender();
		$this->_helper->getHelper('layout')->disableLayout();
		
		session_start();
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int'),
				'pageIndex' => array('NotEmpty', 'Int'),
				'questionIndex' => array('NotEmpty', 'Int')
		);
		
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'pageIndex' => array('HtmlEntities', 'StripTags', 'StringTrim'),
				'questionIndex' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
			
			
		if ($input->isValid())	{
			$userVerification = new Owner_Model_UserVerification();
			$userVerification->verifyUserMatchesSurvey($input->surveyId);
			

			$conn = Doctrine_Manager::connection();
			$conn->beginTransaction();
			try
			{
				// update the page indices to allow for the new page
				$surveyMapper = new Owner_Model_SurveyMapper();
				$surveyMapper->dividePage($input->surveyId, $input->pageIndex, $input->questionIndex);
			
				$conn->commit();
			} catch (Exception $exc) {
				$conn->rollback();
				throw $exc;
			}

			$this->_redirect('/owner/survey/show/' . $input->surveyId);
		} else {
			$errStr = 'Sorry, the input is invalid: ';
			foreach ($this->getRequest()->getParams() as $key => $value) {
				$errStr .= ' parameter: ' . $key . ', value: ' . $value . ';';
			}
			throw new Zend_Controller_Action_Exception($errStr);
		}
	}
	
}



