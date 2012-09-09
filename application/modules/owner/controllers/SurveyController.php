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
		
		// push back all the other pages to make room for the new page (i.e. for each page re-numbered, increment page numbers
		// for the corresponding questions)
		$q = Doctrine_Query::create()
			->select('q.*, s.ID as surveyId')
			->from('Survey_Model_Question q')
			->leftJoin('q.Survey_Model_Survey s')
			->where('s.ID = ' . $input->surveyId)
			->addWhere('q.PageNum >= ' . $input->newPageIndex);
		$questions = $q->fetchArray();
		
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
				->update('Survey_Model_Question q')
				->set('q.PageNum', '?', $question['PageNum'] + 1)
				->where('q.ID = ?', $question['ID']);
			$q->execute();
		}
		
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
			->select('s.*')
			->from('Survey_Model_Survey s')
			->where('s.ID = ' . $input->surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
		
		$q = Doctrine_Query::create()
			->update('Survey_Model_Survey s')
			->set('s.NumPages', '?', $surveys[0]['NumPages'] + 1)
			->where('s.ID = ' . $input->surveyId);
		$q->execute();
		
		$conn->commit();
		
		$this->_redirect('/owner/survey/show/' . $input->surveyId);
	}
	
}



