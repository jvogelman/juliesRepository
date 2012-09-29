<?php

require_once '../application/modules/owner/models/UserVerification.php';
require_once '../application/modules/owner/models/SurveyMapper.php';

class Owner_Model_StudyMapper
{
	function delete($studyId) {

		$surveyMapper = new Owner_Model_SurveyMapper();
		
		// delete all surveys that comprise this study
		
		$q = Doctrine_Query::create()
		->select('s.ID')
		->from('Survey_Model_Survey s')
		->where('s.StudyID = ?', $studyId);
		$surveys = $q->fetchArray();
		
		foreach ($surveys as $survey) {
			$surveyMapper->delete($survey['ID']);
		}
		
		// delete the study itself
		$q = Doctrine_Query::create()
		->delete('Survey_Model_Study s')
		->where('s.ID = ?', $studyId);
		$q->execute();
	}
	
}