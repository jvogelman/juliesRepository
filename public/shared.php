<?php


require_once 'enums.php';

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

function deleteQuestionFromPage($questionId) {

	// first verify that this is the right user for this survey question
	$userId = getUserId();

	$q = Doctrine_Query::create()
	->select('q.*, s.OwnerID')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('s.OwnerID = ' . $userId)
	->addWhere('q.ID = ' . $questionId);
	$questions = $q->fetchArray();
	if (count($questions) < 1) {
		throw new Zend_Controller_Action_Exception('Deletion failed for some reason');
	}

	$surveyId = $questions[0]['SurveyID'];
	$page = $questions[0]['PageNum'];
	$index = $questions[0]['QuestionIndex'];

	//$conn = Doctrine_Manager::connection();
	//$conn->beginTransaction('deleteQuestionFromPage');

	// remove this question from database
	$q = Doctrine_Query::create()
	->delete('Survey_Model_Question q')
	->addWhere('q.ID = ?', $questionId);
	$q->execute();

	// update the other indices in this page
	decrementQuestionIndices($surveyId, $userId, $page, $index);

	switch ($questions[0]['CategoryID']) {
		case enums_QuestionCategory::MultipleChoiceOneAnswer:
		case enums_QuestionCategory::MultipleChoiceMultipleAnswers:
			$q = Doctrine_Query::create()
			->delete('Survey_Model_Multiplechoicequestion m')
			->where('m.QuestionID = ?', $questionId);
			$q->execute();
			break;
		case enums_QuestionCategory::CommentEssayBox:
			$q = Doctrine_Query::create()
			->delete('Survey_Model_Essayboxquestion e')
			->where('e.QuestionID = ?', $questionId);
			$q->execute();
			break;
		case enums_QuestionCategory::DescriptiveText:
			break;
		case enums_QuestionCategory::MatrixOfChoices:
			$q = Doctrine_Query::create()
			->delete('Survey_Model_Matrixofchoicesquestion m')
			->where('m.QuestionID = ?', $questionId);
			$q->execute();

			// delete child questions
			$q = Doctrine_Query::create()
			->delete('Survey_Model_Question q')
			->addWhere('q.ParentQuestionID = ?', $questionId);
			$q->execute();
			break;
	}
	

	deleteSelections($questionId);

	//$conn->commit('deleteQuestionFromPage');
}

function deleteSelections($questionId) {
	$q = Doctrine_Query::create()
	->delete('Survey_Model_Selection s')
	->where('s.QuestionID = ?', $questionId);
	$q->execute();
}

function incrementQuestionIndices($surveyId, $userId, $page, $firstIndex) {

	$q = Doctrine_Query::create()
	->select('q.*, s.OwnerID')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('q.SurveyID = ' . $surveyId)
	->addWhere('q.PageNum = ' . $page)
	->addWhere('s.OwnerID = ' . $userId)
	->addWhere('q.QuestionIndex >= ' . $firstIndex)
	->addWhere('q.ParentQuestionID IS NULL');
	$questions = $q->fetchArray();


	foreach ($questions as $question) {
		$q = Doctrine_Query::create()
		->update('Survey_Model_Question q')
		->set('q.QuestionIndex' ,'?',  $question['QuestionIndex'] + 1)
		->where('q.ID = ?', $question['ID']);
		$q->execute();
	}
}

function decrementQuestionIndices($surveyId, $userId, $page, $firstIndex) {
	$q = Doctrine_Query::create()
	->select('q.*, s.OwnerID')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('q.SurveyID = ' . $surveyId)
	->addWhere('q.PageNum = ' . $page)
	->addWhere('s.OwnerID = ' . $userId)
	->addWhere('q.QuestionIndex >= ' . $firstIndex)
	->addWhere('q.ParentQuestionID IS NULL');
	$questions = $q->fetchArray();

	foreach ($questions as $question) {
		$q = Doctrine_Query::create()
		->update('Survey_Model_Question q')
		->set('q.QuestionIndex' ,'?',  $question['QuestionIndex'] - 1)
		->where('q.ID = ?', $question['ID']);
		$q->execute();
	}
}

?>