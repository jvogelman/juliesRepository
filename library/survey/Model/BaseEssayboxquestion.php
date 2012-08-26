<?php
// Connection Component Binding
Doctrine_Manager::getInstance()->bindComponent('Survey_Model_Essayboxquestion', 'doctrine');

/**
 * Survey_Model_BaseEssayboxquestion
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @property integer $QuestionID
 * @property integer $SingleLine
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class Survey_Model_BaseEssayboxquestion extends Doctrine_Record
{
    public function setTableDefinition()
    {
        $this->setTableName('essayboxquestion');
        $this->hasColumn('QuestionID', 'integer', 4, array(
             'type' => 'integer',
             'length' => 4,
             'fixed' => false,
             'unsigned' => false,
             'primary' => true,
             'autoincrement' => false,
             ));
        $this->hasColumn('SingleLine', 'integer', 1, array(
             'type' => 'integer',
             'length' => 1,
             'fixed' => false,
             'unsigned' => false,
             'primary' => false,
             'default' => '0',
             'notnull' => true,
             'autoincrement' => false,
             ));
    }

    public function setUp()
    {
        parent::setUp();
        
    }
}