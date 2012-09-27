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
	$userId = getUserId();

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
	verifyUserMatchesSurvey($questions[0]['SurveyID']);
}

function deleteQuestionFromPage($questionId) {

	// first verify that this is the right user for this survey question
	verifyUserMatchesQuestion($questionId);

	$q = Doctrine_Query::create()
	->select('q.SurveyID, q.QuestionIndex, q.PageID, q.CategoryID')
	->from('Survey_Model_Question q')
	->addWhere('q.ID = ?', $questionId);
	$questions = $q->fetchArray();
	if (count($questions) < 1) {
		throw new Zend_Controller_Action_Exception('Deletion failed for some reason, question ID = ' . $questionId . ', user id = ' . $userId);
	}

	$surveyId = $questions[0]['SurveyID'];
	$index = $questions[0]['QuestionIndex'];
	$page = $questions[0]['PageID'];

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
		case enums_QuestionCategory::MultipleChoice:
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

function copyQuestion($surveyId, $questionId, $newPage, $newQuestionIndex) {

	$userId = getUserId();
	verifyUserMatchesQuestion($questionId);
	
	// get the current page/question index
	$q = Doctrine_Query::create()
	->select('q.*')
	->from('Survey_Model_Question q')
	->addWhere('q.ID = ' . $questionId);
	$questions = $q->fetchArray();
	$origQuestion = $questions[0];
	$origIndex = $origQuestion['QuestionIndex'];
	
	if ($origQuestion['QuestionCategory'] == enums_QuestionCategory::MatrixOfChoicesChild) {
		throw new Zend_Controller_Action_Exception('Cannot copy questions of type "Matrix Of Choices Child"');
	}
	
	// #### temporary:
	$myFile = "copyQuestion.txt";
	$fh = fopen($myFile, 'w');
	fwrite($fh, 'surveyId = ' . $surveyId . ', userId = ' . $userId . ', newPage = ' . $newPage . ', newQuestionIndex = ' . $newQuestionIndex);
	
	// in the new page, update the indices for any questions that follow
	incrementQuestionIndices($surveyId, $userId, $newPage, $newQuestionIndex);
		
	// make a new question which is a copy of the original

	$newQuestion = new Survey_Model_Question;
	$newQuestion->Text = $origQuestion['Text'];
	$newQuestion->SurveyID = $surveyId;
	$newQuestion->QuestionIndex = $newQuestionIndex;
	$newQuestion->PageID = $newPage;
	$newQuestion->CategoryID = $origQuestion['CategoryID'];
	$newQuestion->RequireAnswer = $origQuestion['RequireAnswer'];
	$newQuestion->save();
	$newId = $newQuestion['ID'];
		
	// make copies from question category-specific tables as well
	switch ($origQuestion['CategoryID']) {
		case enums_QuestionCategory::CommentEssayBox:
			$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Essayboxquestion q')
			->addWhere('q.QuestionID = ?', $questionId);
			$questions = $q->fetchArray();
			if (count($questions) < 1) {
				throw new Zend_Controller_Action_Exception('Failed to locate question category specific entry in database');
			}
				
			$newQ = new Survey_Model_Essayboxquestion;
			$newQ->QuestionID = $newId;
			$newQ->SingleLine = $questions[0]['SingleLine'];
			$newQ->save();
			break;
		case enums_QuestionCategory::DescriptiveText:
			break;
		case enums_QuestionCategory::MatrixOfChoices:
			$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Matrixofchoicesquestion q')
			->addWhere('q.QuestionID = ?', $questionId);
			$questions = $q->fetchArray();
			if (count($questions) < 1) {
				throw new Zend_Controller_Action_Exception('Failed to locate question category specific entry in database');
			}
				
			$newQ = new Survey_Model_Matrixofchoicesquestion;
			$newQ->QuestionID = $newId;
			$newQ->RandomizeAnswers = $questions[0]['RandomizeAnswers'];
			$newQ->save();
				
			// add corresponding children
			$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Question q')
			->addWhere('q.ParentQuestionID = ' . $questionId);
			$childQuestions = $q->fetchArray();

			foreach ($childQuestions as $cq) {
				$newQuestion = new Survey_Model_Question;
				$newQuestion->Text = $cq['Text'];
				$newQuestion->SurveyID = $surveyId;
				$newQuestion->QuestionIndex = $cq['QuestionIndex'];
				$newQuestion->CategoryID = enums_QuestionCategory::MatrixOfChoicesChild;
				$newQuestion->ParentQuestionID = $newId;
				$newQuestion->save();
			}
				
			break;
		case enums_QuestionCategory::MultipleChoice:

			$q = Doctrine_Query::create()
			->select('q.*')
			->from('Survey_Model_Multiplechoicequestion q')
			->addWhere('q.QuestionID = ?', $questionId);
			$questions = $q->fetchArray();
			if (count($questions) < 1) {
				throw new Zend_Controller_Action_Exception('Failed to locate question category specific entry in database');
			}
				
			$newQ = new Survey_Model_Multiplechoicequestion;
			$newQ->QuestionID = $newId;
			$newQ->AddOtherField = $questions[0]['AddOtherField'];
			$newQ->SingleLine = $questions[0]['SingleLine'];
			$newQ->save();
			break;
	}
		
		
		
	// make copies from the Selection table as well
	$q = Doctrine_Query::create()
	->select('s.*')
	->from('Survey_Model_Selection s')
	->where('s.QuestionID = ?', $questionId);
	$selections = $q->fetchArray();

	foreach ($selections as $selection) {
		$newSelection = new Survey_Model_Selection;
		$newSelection->QuestionID = $newId;
		$newSelection->SelectionIndex = $selection['SelectionIndex'];
		$newSelection->Text = $selection['Text'];
		$newSelection->save();
	}
			
		
}


// return page ID corresponding to PageNum
function getPageAtIndex($surveyId, $pageIndex) {
	$q = Doctrine_Query::create()
	->select('p.ID')
	->from('Survey_Model_Page p')
	->where('p.PageNum = ?', $pageIndex)
	->addWhere('p.SurveyID = ?', $surveyId);
	$pages = $q->fetchArray();

	if (count($pages) == 0) {
		throw new Zend_Controller_Action_Exception('No page found at index ' . $pageIndex . ', survey ID = ' . $surveyId);
	}

	return $pages[0]['ID'];
}


function incrementQuestionIndices($surveyId, $userId, $page, $firstIndex) {

	$q = Doctrine_Query::create()
	->select('q.ID, q.QuestionIndex')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('q.SurveyID = ?', $surveyId)
	->addWhere('q.PageID = ?', $page)
	->addWhere('q.QuestionIndex >= ?', $firstIndex)
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
	->select('q.ID, q.QuestionIndex')
	->from('Survey_Model_Question q')
	->leftJoin('q.Survey_Model_Survey s')
	->where('q.SurveyID = ?', $surveyId)
	->addWhere('q.PageID = ?', $page)
	->addWhere('q.QuestionIndex >= ?', $firstIndex)
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