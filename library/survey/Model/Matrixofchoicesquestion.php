<?php

/**
 * Survey_Model_Matrixofchoicesquestion
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
class Survey_Model_Matrixofchoicesquestion extends Survey_Model_BaseMatrixofchoicesquestion
{
	public function setUp()
	{
		$this->hasOne('Survey_Model_Question', array(
				'local' => 'QuestionID',
				'foreign' => 'ID'
		));
	
	}
}