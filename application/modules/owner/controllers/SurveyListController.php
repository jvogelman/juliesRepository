<?php

class Owner_SurveyListController extends Zend_Controller_Action
{
	public function testAction()
	{
		session_start();
		
		$_SESSION["userId"] = "1";
		if (!isset($_SESSION["userId"])) {
			echo "userId not found";
			return;
				
		}
		$this->_redirect('/owner/surveylist/index');
	}
	
	public function indexAction()
	{
		session_start();
		
		// get session variable for user id 
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");			
		}
		
		$userId = $_SESSION["userId"];
		
		// get all surveys owned by this user
		$q = Doctrine_Query::create()
			->from('Survey_Model_Survey s')
			->leftJoin('s.Survey_Model_User o')
			->where('s.OwnerID = ' . $userId);
		$result = $q->fetchArray();
		$this->view->records = $result;
	}
	
	public function deleteAction()
	{
		session_start();
		
		// get session variable for user id 
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");			
		}
		
		$userId = $_SESSION["userId"]; 
		
		// set filters and validators for GET input
		$filters = array(
				'surveyId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$validators = array(
				'surveyId' => array('NotEmpty', 'Int')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		if ($input->isValid())
		{			
			// delete this survey
			$q = Doctrine_Query::create()
				->delete('Survey_Model_Survey s')
				->where('s.ID = ' . $input->surveyId)
				->addWhere('s.OwnerID = ' . $userId);
			$q->execute();
		}

		$this->_redirect('/owner/surveylist/index');
	}
	
	public function createAction(){

		session_start();
		
		// get session variable for user id
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");
		}
		
		$userId = $_SESSION["userId"];
		

		$form = new Survey_Form_SurveyCreate();
		$this->view->form = $form;
		
		if ($this->getRequest()->isPost()){
			if ($form->isValid($this->getRequest()->getPost())) {

				// enter into database
				$survey = new Survey_Model_Survey;
				$survey->Name = $this->getRequest()->getParam('Name');
				$survey->Description = $this->getRequest()->getParam('Description');
				$survey->OwnerID = $userId;
				$survey->NumPages = 1;
				$survey->DateCreated = gmdate("Y-m-d H:i:s");
				$survey->DateModified = $survey->DateCreated;
				$survey->save();
				$id = $survey->ID;
				$this->_redirect('/owner/survey/show/' . $id);
			}
		}
	}
	
}
