<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initDoctrine()
    {
        require_once 'Doctrine/Doctrine.php';
        $this->getApplication()
             ->getAutoloader()
             ->pushAutoloader(array('Doctrine', 'autoload'), 'Doctrine');

        $manager = Doctrine_Manager::getInstance();
        $manager->setAttribute(
            Doctrine::ATTR_MODEL_LOADING,
            Doctrine::MODEL_LOADING_CONSERVATIVE
        );

        $config = $this->getOption('doctrine');
        $conn = Doctrine_Manager::connection($config['dsn'], 'doctrine');
        return $conn;
    }
    

    protected function _initView()
    {
    	$view = new Zend_View();
    	/*
    	$view->doctype('XHTML1_STRICT');
    	$view->headMeta()->appendHttpEquiv('Content-Type', 'text/html;charset=utf-8');
    	$view->headTitle()->setSeparator(' - ');
    	$view->headTitle('Survey Factory');*/
    	$view->env = APPLICATION_ENV;
    	$view->baseUrl = "";//Zend_Registry::get('config')->root_path;
    
    	$view->addHelperPath("ZendX/JQuery/View/Helper", "ZendX_JQuery_View_Helper");
    	$view->jQuery()->addStylesheet($view->baseUrl . '/js/jquery/css/ui-lightness/jquery-ui-1.8.22.custom.css');
    	$view->jQuery()->setLocalPath($view->baseUrl . '/js/jquery/js/jquery-1.7.2.min.js');
    	$view->jQuery()->setUiLocalPath($view->baseUrl .'/js/jquery/js/jquery-ui-1.8.22.custom.min.js');
    	$view->jQuery()->enable();
    	$view->jQuery()->uiEnable();
    	$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer();
    	$viewRenderer->setView($view);
    	Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

    	
    	return $view;
    }
    
}

