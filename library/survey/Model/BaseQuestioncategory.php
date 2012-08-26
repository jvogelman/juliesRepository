<?php
// Connection Component Binding
Doctrine_Manager::getInstance()->bindComponent('Survey_Model_Questioncategory', 'doctrine');

/**
 * Survey_Model_BaseQuestioncategory
 *
 * This class has been auto-generated by the Doctrine ORM Framework
 *
 * @property integer $ID
 * @property string $Name
 *
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
*/
abstract class Survey_Model_BaseQuestioncategory extends Doctrine_Record
{
	public function setTableDefinition()
	{
		$this->setTableName('questioncategory');
		$this->hasColumn('ID', 'integer', 4, array(
				'type' => 'integer',
				'length' => 4,
				'fixed' => false,
				'unsigned' => false,
				'primary' => true,
				'autoincrement' => true,
		));
		$this->hasColumn('Name', 'string', 45, array(
				'type' => 'string',
				'length' => 45,
				'fixed' => false,
				'unsigned' => false,
				'primary' => false,
				'notnull' => true,
				'autoincrement' => false,
		));
	}

	public function setUp()
	{
		parent::setUp();

	}
}