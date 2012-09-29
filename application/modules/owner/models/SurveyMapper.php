<?php

require_once 'enums.php';
require_once '../application/modules/owner/models/UserVerification.php';

class Owner_Model_SurveyMapper
{

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
	
	function addPage($surveyId, $newPageIndex) {
		$this->incrementPageNums($surveyId, $newPageIndex);
		$this->incrementNumPageNumsInSurvey($surveyId);
			
		$page = new Survey_Model_Page;
		$page->PageNum = $newPageIndex;
		$page->SurveyID = $surveyId;
		$page->save();
		
	}
	
	function deletePage($surveyId, $pageIndex){
		
		$pageId = $this->getPageAtIndex($surveyId, $pageIndex);
			
		// delete entry from Page
		$q = Doctrine_Query::create()
		->delete('Survey_Model_Page p')
		->addWhere('p.ID = ?', $pageId);
		$q->execute();
			
		// decrement PageNum in other entries of Page table
		$this->decrementPageNums($surveyId, $pageIndex);
		// reduce number of pages in survey
		$this->decrementNumPageNumsInSurvey($surveyId);
		
		// delete all of the questions on this page
		$q = Doctrine_Query::create()
		->select('q.ID')
		->from('Survey_Model_Question q')
		->leftJoin('q.Survey_Model_Survey s')
		->where('s.ID = ?', $surveyId)
		->addWhere('q.PageID = ?', $pageId);
		$questions = $q->fetchArray();
			
		$mapper = new Owner_Model_QuestionMapper();
		
		foreach ($questions as $question) {
			$mapper->delete($question['ID']);
		}
	}
	
	function movePage($surveyId, $currentPageIndex, $newPageIndex){

		$pageId = $this->getPageAtIndex($surveyId, $currentPageIndex);
			
		// incrementPageNums should reset page nums for other Page IDs
		$this->incrementPageNums($surveyId, $newPageIndex);
			
		// set to new index
		$q = Doctrine_Query::create()
		->update('Survey_Model_Page p')
		->set('p.PageNum', '?', $newPageIndex)
		->where('p.ID = ?', $pageId);
		$q->execute();
			
		$this->decrementPageNums($surveyId, $currentPageIndex + 1);
			
	}
	
	function copyPage($surveyId, $currentPageIndex, $newPageIndex){

		$pageId = $this->getPageAtIndex($surveyId, $currentPageIndex);
			
		// get the questions on the original page so they can be copied later
		$q = Doctrine_Query::create()
		->select('q.ID, q.QuestionIndex')
		->from('Survey_Model_Question q')
		->leftJoin('q.Survey_Model_Survey s')
		->where('s.ID = ?', $surveyId)
		->addWhere('q.PageID = ?', $pageId)
		->addWhere('q.CategoryID != ?', enums_QuestionCategory::MatrixOfChoicesChild)
		->orderBy('q.QuestionIndex');
		$questions = $q->fetchArray();
			
		// push back all the other pages to make room for the new page
		$this->incrementPageNums($surveyId, $newPageIndex);
		$this->incrementNumPageNumsInSurvey($surveyId);
			
		// get the original page information
		$q = Doctrine_Query::create()
		->select('p.Name')
		->from('Survey_Model_Page p')
		->where('p.SurveyID = ?', $surveyId)
		->addWhere('p.ID = ?', $pageId);
		$pages = $q->fetchArray();
			
		// create a new Page entry
		$page = new Survey_Model_Page;
		$page->PageNum = $newPageIndex;
		$page->SurveyID = $surveyId;
		$page->Name = $pages[0]['Name'];
		$page->save();
		$newPageId = $page->ID;
			
		
		$mapper = new Owner_Model_QuestionMapper();
			
		// for each question on the original page, copy it into the new page at the same index
		foreach ($questions as $question) {
			$mapper->copy($surveyId, $question['ID'], $newPageId, $question['QuestionIndex']);
		}
	}
	
	function dividePage($surveyId, $pageIndex, $questionIndex){

		$this->incrementPageNums($surveyId, $pageIndex + 1);
		$this->incrementNumPageNumsInSurvey($surveyId);
		
		////////////////////////////////////////////////////////////////////////////////////////////////
		// create a new page and reset the last questions on the original page to it
		////////////////////////////////////////////////////////////////////////////////////////////////
		
		// create the page
		$page = new Survey_Model_Page;
		$page->PageNum = $pageIndex + 1;
		$page->SurveyID = $surveyId;
		$page->save();
		
		// get the questions that need to be moved
		$pageId = $this->getPageAtIndex($surveyId, $pageIndex);
		
		
		$q = Doctrine_Query::create()
		->select('q.ID')
		->from('Survey_Model_Question q')
		->where('q.SurveyID = ?', $surveyId)
		->addWhere('q.PageID = ?', $pageId)
		->addWhere('q.QuestionIndex >= ?', $questionIndex)
		->orderBy('q.QuestionIndex');
		$questions = $q->fetchArray();
		
		// move the questions to the new page
		$newIndex = 1;
		foreach ($questions as $question) {
			$q = Doctrine_Query::create()
			->update('Survey_Model_Question q')
			->set('q.PageID', '?', $page['ID'])
			->set('q.QuestionIndex', '?', $newIndex)
			->where('q.ID = ?', $question['ID']);
			$q->execute();
			$newIndex++;
		}
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
	

	// for each page starting with $firstPage, increment its PageNum
	private function incrementPageNums($surveyId, $firstPage) {
	
		$q = Doctrine_Query::create()
		->select('p.ID, p.PageNum')
		->from('Survey_Model_Page p')
		->where('p.SurveyID = ?', $surveyId)
		->addWhere('p.PageNum >= ?', $firstPage);
		$pages = $q->fetchArray();
	
		foreach ($pages as $page) {
			$q = Doctrine_Query::create()
			->update('Survey_Model_Page p')
			->set('p.PageNum', '?', $page['PageNum'] + 1)
			->where('p.ID = ?', $page['ID']);
			$q->execute();
		}
	}
	
	// for each page starting with $firstPage, decrement its PageNum
	private function decrementPageNums($surveyId, $firstPage) {
	
		$q = Doctrine_Query::create()
		->select('p.ID, p.PageNum')
		->from('Survey_Model_Page p')
		->where('p.SurveyID = ?', $surveyId)
		->addWhere('p.PageNum >= ?', $firstPage);
		$pages = $q->fetchArray();
	
		foreach ($pages as $page) {
			$q = Doctrine_Query::create()
			->update('Survey_Model_Page p')
			->set('p.PageNum', '?', $page['PageNum'] - 1)
			->where('p.ID = ?', $page['ID']);
			$q->execute();
		}
	}
	
	private function incrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
		->select('s.NumPages')
		->from('Survey_Model_Survey s')
		->where('s.ID = ?', $surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
	
		$q = Doctrine_Query::create()
		->update('Survey_Model_Survey s')
		->set('s.NumPages', '?', $surveys[0]['NumPages'] + 1)
		->where('s.ID = ?', $surveyId);
		$q->execute();
	}
	
	private function decrementNumPageNumsInSurvey($surveyId) {
		// update the number of pages in the survey
		$q = Doctrine_Query::create()
		->select('s.NumPages')
		->from('Survey_Model_Survey s')
		->where('s.ID = ?', $surveyId);
		$surveys = $q->fetchArray();
		if (count($surveys) < 1) {
			throw new Zend_Controller_Action_Exception('No survey found with requested ID');
		}
	
		// make sure num page nums isn't 0
		if ($surveys[0]['NumPages'] < 1){
			throw new Zend_Controller_Action_Exception('Can\'t decrease num pages for this survey. Current pages = 0');
		}
	
		$q = Doctrine_Query::create()
		->update('Survey_Model_Survey s')
		->set('s.NumPages', '?', $surveys[0]['NumPages'] - 1)
		->where('s.ID = ?', $surveyId);
		$q->execute();
	}
	

}

?>