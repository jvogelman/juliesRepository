<?php

require_once 'enums.php';
require_once '../application/modules/owner/models/SurveyMapper.php';
require_once '../application/modules/owner/models/UserVerification.php';

class Owner_Model_QuestionMapper
{

	function delete($questionId) {
	
		// first verify that this is the right user for this survey question
		$userVerification = new Owner_Model_UserVerification();
		$userVerification->verifyUserMatchesQuestion($questionId);
	
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
		$surveyMapper = new Owner_Model_SurveyMapper();
		$surveyMapper->decrementQuestionIndices($surveyId, $userId, $page, $index);
	
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
	
	
		$this->deleteSelections($questionId);
	
		//$conn->commit('deleteQuestionFromPage');
	}
	
	function deleteSelections($questionId) {
		$q = Doctrine_Query::create()
		->delete('Survey_Model_Selection s')
		->where('s.QuestionID = ?', $questionId);
		$q->execute();
	}
	
	function addSelections($questionId, $selections) {

		for ($i = 1; $i <= count($selections); $i++) {
			// insert
		
			if ($selections[$i] != ''){	// #### what if middle element is empty? let's use javascript to prevent that
				$selectionText = $selections[$i];
				$s = new Survey_Model_Selection;
				$s->SelectionIndex = $i;
				$s->Text = $selectionText;
				$s->QuestionID = $questionId;
		
				$s->save();
			}		
		}
	}
	
	function add($surveyId, $pageIndex, $index) {
		
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		
		// get page ID corresponding to page index
		$surveyMapper = new Owner_Model_SurveyMapper();
		$pageId = $surveyMapper->getPageAtIndex($surveyId, $pageIndex);
	
		// first update the other indices in this page
		$surveyMapper = new Owner_Model_SurveyMapper();
		$surveyMapper->incrementQuestionIndices($surveyId, $userId, $pageId, $index);
		
		// add new question to database
		$q = new Survey_Model_Question;
		$q->Text = '';
		$q->SurveyID = $surveyId;
		$q->QuestionIndex = $index;
		$q->PageID = $pageId;
		$q->CategoryID = enums_QuestionCategory::Undefined;
		$q->RequireAnswer = 0;
		$q->save();
		$id = $q['ID'];
		
		return $id;
	}
	
	function save(){}
	
	function move($surveyId, $questionId, $newPage, $newQuestionIndex)
	{

		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		$userVerification->verifyUserMatchesQuestion($questionId);		
		
		// get the current page/question index
		$q = Doctrine_Query::create()
		->select('q.PageID, q.QuestionIndex')
		->from('Survey_Model_Question q')
		->addWhere('q.ID = ' . $questionId);
		$question = $q->fetchArray();
		$origPageId = $question[0]['PageID'];
		$origIndex = $question[0]['QuestionIndex'];
		
		
		$surveyMapper = new Owner_Model_SurveyMapper();
		$surveyMapper->incrementQuestionIndices($surveyId, $userId, $newPage, $newQuestionIndex);
			
		// update the Question with the new page/index
		$q = Doctrine_Query::create()
		->update('Survey_Model_Question q')
		->set('q.QuestionIndex' ,'?',  $newQuestionIndex)
		->set('q.PageID', '?', $newPage)
		->where('q.ID = ?', $questionId);
		$q->execute();
			
		$surveyMapper->decrementQuestionIndices($surveyId, $userId, $origPageId, $origIndex + 1);	
		
	}


	function copy($surveyId, $questionId, $newPage, $newQuestionIndex) {
	
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		$userVerification->verifyUserMatchesQuestion($questionId);
	
		// get the current page/question index
		$q = Doctrine_Query::create()
		->select('q.*')
		->from('Survey_Model_Question q')
		->addWhere('q.ID = ' . $questionId);
		$questions = $q->fetchArray();
		$origQuestion = $questions[0];
		$origIndex = $origQuestion['QuestionIndex'];
	
		if ($origQuestion['CategoryID'] == enums_QuestionCategory::MatrixOfChoicesChild) {
			throw new Zend_Controller_Action_Exception('Cannot copy questions of type "Matrix Of Choices Child"');
		}
	
		// #### temporary:
		$myFile = "copyQuestion.txt";
		$fh = fopen($myFile, 'w');
		fwrite($fh, 'surveyId = ' . $surveyId . ', userId = ' . $userId . ', newPage = ' . $newPage . ', newQuestionIndex = ' . $newQuestionIndex);
	
		// in the new page, update the indices for any questions that follow
		$surveyMapper = new Owner_Model_SurveyMapper();
		$surveyMapper->incrementQuestionIndices($surveyId, $userId, $newPage, $newQuestionIndex);
	
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
}

?>