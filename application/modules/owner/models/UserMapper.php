<?php

require_once '../application/modules/owner/models/UserVerification.php';


class Owner_Model_UserMapper
{
	// get field from User table for current user
	function getFieldCurrentUser($fieldName) {
		$userVerification = new Owner_Model_UserVerification();
		$userId = $userVerification->getUserId();
		
		$q = Doctrine_Query::create()
		->select('u.' . $fieldName)
		->from('Survey_Model_User u')
		->where('u.ID = ?', $userId);
		$users = $q->fetchArray();
		if (count($users) < 1) {
			throw new Zend_Controller_Action_Exception('No user found with ID ' . $userId);
		} else if (count($users) > 1) {
			throw new Zend_Controller_Action_Exception('Multiple users found with ID ' . $userId);
		}
	
		return $users[0][$fieldName];
	}

	// get field from User table for specified user
	function getField($fieldName, $userId) {
		$q = Doctrine_Query::create()
		->select('u.' . $fieldName)
		->from('Survey_Model_User u')
		->where('u.ID = ?', $userId);
		$users = $q->fetchArray();
		if (count($users) < 1) {
			throw new Zend_Controller_Action_Exception('No user found with ID ' . $userId);
		} else if (count($users) > 1) {
			throw new Zend_Controller_Action_Exception('Multiple users found with ID ' . $userId);
		}
	
		return $users[0][$fieldName];
	}
}