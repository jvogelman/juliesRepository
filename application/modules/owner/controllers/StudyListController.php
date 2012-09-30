<?php
require_once '../application/modules/owner/models/UserVerification.php';
require_once '../application/modules/owner/models/UserMapper.php';
require_once '../application/modules/owner/models/StudyMapper.php';

class Owner_StudyListController extends Zend_Controller_Action
{
	public function testAction()
	{
		session_start();

		$_SESSION["userId"] = "1";
		if (!isset($_SESSION["userId"])) {
			echo "userId not found";
			return;

		}
		$this->_redirect('/owner/studylist/index');
	}

	public function indexAction()
	{
		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();

		// get all studies owned by this user
		$q = Doctrine_Query::create()
		->from('Survey_Model_Study s')
		->leftJoin('s.Survey_Model_Folder f')
		->where('f.OwnerID = ?', $userId);
		$result = $q->fetchArray();
		$this->view->records = $result;
	}
	
	// delete a study
	public function deleteAction() {
		session_start();
		
		$userVerification = new Owner_Model_UserVerification();
		$studyMapper = new Owner_Model_StudyMapper();

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
		
		$studyMapper->delete($input->studyId);
		
		$this->_redirect('/owner/studylist/index');
		
	}
	
	public function createAction(){
	
		session_start();
	
		$userVerification = new Owner_Model_UserVerification();
		$userMapper = new Owner_Model_UserMapper();
		$userId = $userVerification->getUserId();
	
		// set filters and validators for POST input
		$filters = array(
				'name' => array('HtmlEntities', 'StringTrim'),
				'description' => array('StringTrim'),
				'folderId' => array('HtmlEntities', 'StripTags', 'StringTrim')
		);
	
		$validators = array(
				'name' => array('NotEmpty'),
				'description' => array(),
				'folderID' => array()
		);
	
		$input = new Zend_Filter_Input($filters, $validators);
		$input->setData($this->getRequest()->getParams());
	

		if ($input->isValid())
		{
			$study = new Survey_Model_Study;
			$study->Name = $input->name;
			$study->Description = $input->description;
			
			if ($input->FolderID == null) {
				$study->FolderID = $userMapper->getFieldCurrentUser('DefaultFolder');
			} else
			{
				$study->FolderID = $input->FolderID;
			}
			$study->save();
		
			$this->_redirect('/owner/study/show/' . $study->ID);
		}
	}			
			
}


