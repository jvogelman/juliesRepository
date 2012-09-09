<?php

function getUserId(){
		
	// get session variable for user id
	if (!isset($_SESSION["userId"])) {
		throw new Zend_Controller_Action_Exception("session variable 'userId' not found");
	}
		
	return $_SESSION["userId"];
}



// verify that this user is the owner of this survey
function verifyUserMatchesSurvey($surveyId){
	$userId = getUserId();

	$q = Doctrine_Query::create()
	->select('s.*')
	->from('Survey_Model_Survey s')
	->where('s.OwnerID = ' . $userId)
	->addWhere('s.ID = ' . $surveyId);
	$surveys = $q->fetchArray();
	if (count($surveys) < 1) {
		throw new Zend_Controller_Action_Exception('User does not have permission to access this survey');
	}
}

// verify that this user is the owner of the survey that this question belongs to
function verifyUserMatchesQuestion($questionId){
	$userId = getUserId();

	$q = Doctrine_Query::create()
	->select('q.*, s.OwnerID')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('s.OwnerID = ' . $userId)
	->addWhere('q.ID = ' . $questionId);
	$questions = $q->fetchArray();
	if (count($questions) < 1) {
		throw new Zend_Controller_Action_Exception('User does not have permission to access this question');
	}

}

?>