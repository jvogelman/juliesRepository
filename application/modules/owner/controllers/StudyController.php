<?php

require_once '../application/modules/owner/models/UserVerification.php';
require_once '../application/modules/owner/models/SurveyMapper.php';

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
			->select('study.Name, study.Description, study.ID, study.FolderID')
			->from('Survey_Model_Study study')
			->where('study.ID = ?', $input->studyId);
		$studies = $q->fetchArray();
		if (count($studies) < 1) {
			throw new Zend_Controller_Action_Exception('No study found with ID = ' . $input->studyId);
		} else if (count($studies) > 1) {
			throw new Zend_Controller_Action_Exception('Multiple studies found with ID = ' . $input->studyId);
		}
		
		// get all surveys that belong to this study
		$q = Doctrine_Query::create()
			->select('s.Name, s.ID')
			->from('Survey_Model_Survey s')
			->where('s.StudyID = ?', $input->studyId);
		$surveys = $q->fetchArray();
		
		
		$this->view->study = $studies[0];
		$this->view->surveys = $surveys;
		//$this->view->folderId = 
	}
	
	// delete a survey
	public function deletesurveyAction()
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
			$userVerification->verifyUserMatchesSurvey($input->surveyId);
			
			$surveyMapper = new Owner_Model_SurveyMapper();
			$study = $surveyMapper->getField('StudyID', $input->surveyId);
			
			// delete this survey
			$surveyMapper->delete($input->surveyId);
		}

		$this->_redirect('/owner/study/show/' . $study);
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
					'studyId' => array('NotEmpty', 'Int'),
					'description' => array()
			);
				
			$filters = array(
					'studyId' => array('HtmlEntities', 'StripTags', 'StringTrim'),
					'description' => array('HtmlEntities', 'StringTrim') 
			);
				
	
	
			$input = new Zend_Filter_Input($filters, $validators);
			$input->setData($this->getRequest()->getParams());
				
			$userVerification->verifyUserMatchesStudy($input->studyId);
	
			$response = "";
				
			if ($input->isValid())
			{
				$q = Doctrine_Query::create()
				->update('Survey_Model_Study s')
				->set('s.Description', '?', $input->description)
				->addWhere('s.ID = ?', $input->studyId);
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
	
	
	public function createAction(){

		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		
		// set filters and validators for POST input
		$filters = array(
				'name' => array('HtmlEntities', 'StringTrim'),
				'description' => array('StringTrim')
		);
		
		$validators = array(
				'name' => array('NotEmpty'),
				'description' => array()
		);
		
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
		
		/*if ($input->isValid())
		{			

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
		}*/
	}
	
}
