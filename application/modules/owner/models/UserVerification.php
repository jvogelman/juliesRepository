<?php

require_once 'enums.php';
	
class Owner_Model_UserVerification {
	
	function getUserId(){
	
		// get session variable for user id
		if (!isset($_SESSION["userId"])) {
			throw new Zend_Controller_Action_Exception("session variable 'userId' not found");
		}
	
		return $_SESSION["userId"];
	}
	
	// verify that this user is the owner of this study
	function verifyUserMatchesStudy($studyId) {
		$userId = $this->getUserId();
	
		$q = Doctrine_Query::create()
		->select('')
		->from('Survey_Model_Study study')
		->leftJoin('study.Survey_Model_Folder f')
		->where('f.OwnerID = ?', $userId)
		->addWhere('study.ID = ?', $studyId);
		$result = $q->fetchArray();
		if (count($result) < 1) {
			throw new Zend_Controller_Action_Exception('User does not have permission to access this study: user = ' . $userId . ', study ID = ' . $studyId);
		}
	}
	
	// verify that this user is the owner of this survey
	function verifyUserMatchesSurvey($surveyId){
		$userId = $this->getUserId();
	
		// find this survey and get its study ID
		$q = Doctrine_Query::create()
		->select('study.ID')
		->from('Survey_Model_Study study')
		->leftJoin('Survey_Model_Survey s')
		->addWhere('s.ID = ?', $surveyId);
		$studies = $q->fetchArray();
		if (count($studies) < 1) {
			throw new Zend_Controller_Action_Exception('Survey ID = ' . $surveyId . ' not found');
		} 
		
		// verify this study belongs to the owner of this survey
		$q = Doctrine_Query::create()
		->select('')
		->from('Survey_Model_Study study')
		->leftJoin('study.Survey_Model_Folder f')
		//->leftJoin('Survey_Model_Folder f')
		->where('f.OwnerID = ?', $userId)
		->addWhere('study.ID = ?', $studies[0]['ID']);
		$result = $q->fetchArray();
		if (count($result) < 1) {
			throw new Zend_Controller_Action_Exception('User does not have permission to access this survey: user = ' . $userId . ', survey ID = ' . $surveyId);
		}
	}
	
	// verify that this user is the owner of the survey that this question belongs to
	function verifyUserMatchesQuestion($questionId){
		$userId = $this->getUserId();
	
		// get the survey ID
		$q = Doctrine_Query::create()
		->select('q.SurveyID')
		->from('Survey_Model_Question q')
		->addWhere('q.ID = ?', $questionId);
		$questions = $q->fetchArray();
		if (count($questions) < 1) {
			throw new Zend_Controller_Action_Exception('QuestionId = ' . $questionId . ' not found');
		}
	
		// now verify that this survey belongs to this user
		$this->verifyUserMatchesSurvey($questions[0]['SurveyID']);
	}
	
}
