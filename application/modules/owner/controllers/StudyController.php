<?php

require_once 'shared.php';
require_once '../application/modules/owner/models/UserVerification.php';

class Owner_StudyController extends Zend_Controller_Action
{
	
	public function showAction()
	{
		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();

		// set filters and validators for GET input
		$filters = array(
				'studyId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
		
		$validators = array(
				'studyId' => array('NotEmpty', 'Int')
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		$userVerification->verifyUserMatchesStudy($input->studyId);
		
		// get information about the study
		$q = Doctrine_Query::create()
			->select('study.Name, study.Description')
			->from('Survey_Model_Study study')
			->where('study.ID = ?', $input->studyId);
		$studies = $q->fetchArray();
		if (count($studies) < 1) {
			throw new Zend_Controller_Action_Exception('No study found with ID = ' . $input->studyId);
		}
		
		// get all surveys that belong to this study
		$q = Doctrine_Query::create()
			->select('s.Name, s.ID')
			->from('Survey_Model_Survey s')
			->where('s.StudyID = ?', $input->studyId);
		$surveys = $q->fetchArray();
		
		
		$this->view->study = $studies[0];
		$this->view->surveys = $surveys;
	}
	
	public function deleteAction()
	{
		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		
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

		$this->_redirect('/owner/study/index');
	}
	
	public function createAction(){

		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();

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
