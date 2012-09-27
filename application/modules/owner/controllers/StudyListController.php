<?php
require_once 'shared.php';

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
		
		$userId = getUserId();

		// get all studies owned by this user
		$q = Doctrine_Query::create()
		->from('Survey_Model_Study s')
		->leftJoin('s.Survey_Model_Folder f')
		->where('f.OwnerID = ' . $userId);
		$result = $q->fetchArray();
		$this->view->records = $result;
	}
}