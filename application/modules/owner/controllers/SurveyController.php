<?php

class Owner_SurveyController extends Zend_Controller_Action
{
	public function showAction(){
				
		session_start();
		
		// get session variable for user id 
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");			
		}
		
		$userId = $_SESSION["userId"]; 
		
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int')
		);
		
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		if ($input->isValid()) {
			
			$quest = array();
			$selections = array();
			$childQuestions = array();
		
			// get the id passed by variable in the url
			$surveyId = $this->getRequest()->getParam('surveyId');	
			
			// get the record in the survey table for this id
			$q = Doctrine_Query::create()
				->from('Survey_Model_Survey s')
				->where('s.OwnerID = ' . $userId)
				->addWhere('s.ID = ' . $surveyId);
			$result = $q->fetchArray();
			
					
			$this->view->record = $result;
			
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
			foreach ($questions as $q) {
				$questionId = $q["ID"];
				$page = $q["PageNum"];
				$indexInPage = $q["QuestionIndex"];
				if ($q["CategoryName"] != "Matrix of Choices Child") {
					$quest[$page][$indexInPage] = $q;
				} else {
					$parentQuestionId = $q["ParentQuestionID"];
					$childQuestions[$parentQuestionId][] = $q;
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
			
			// get session variable for user id 
			if (!isset($_SESSION["userId"])) {
				throw new Zend_Controller_Action_Exception("session variable 'userId' not found");			
			}
			
			$userId = $_SESSION["userId"]; 
			
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
			$response = "ERROR:page through exception: " . $e;
		}			
		
		echo $response; 
	}
}

