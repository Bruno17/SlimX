<?php

namespace App\Action\DefaultActions;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpNotFoundException;

class BaseAction {

    protected $container;
    protected $modx;
    protected $response;

    /**
     * Configs, overwritable by Request Parameters 'configkey' => 'request parameter key'
     * @var array
     */
    public $overwritableConfigs = [
        'getlistselectfields'=>'selectfields',
        'getlistsortdir'=>'sortdir',
        'requestsort'=>'sort',
        'requestsortconfig'=>'sortconfig',
        'limit'=>'limit',
        'offset'=>'offset',
        'getlistwhere'=>'where',
        'getlistgroupby'=>'groupby',
        'showtrash'=>'showtrash',
        'showunpublished'=>'showunpublished'
        ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = & $container;
    }

    public function __invoke(ServerRequestInterface $request,ResponseInterface $response, array $args): ResponseInterface {
        $this->response = $response;
        $this->request = $request;
        $routeContext = RouteContext::fromRequest($request);
        $this->route = $routeContext->getRoute();        
        
        $this->modx = $this->container->get('modX');
        $this->modx->invokeEvent('OnHandleRequest');
        echo 'context:' . $this->modx->context->get('key');
        $this->migx = $this->container->get('Migx');        
        
        $this->init();

        return $this->process();
    }
    
    public function init() {
        $this->initProperties();
        $this->initArgProperties(); 
        $this->initConfig();
        $this->overwriteConfigsFromRequest();
        $this->checkAccessPermission();
        $this->initXpdo();
    }

    public function initProperties(){
        $this->properties = $this->request->getQueryParams();
        //$this->properties = $_GET;
    }

    public function initArgProperties(){
        $args = $this->route->getArguments();
        foreach ($args as $key => $arg){
            $this->setProperty('arg_' . $key,$arg);
        };            
    }

    public function initConfig() {
        $modx = & $this->modx;
        $migx = & $this->migx; 
        
        $properties = $this->getProperties();

        $migxconfig = $this->getArgument('migxconfig');

        $migx->config['configs'] = $migxconfig;
        //$migx->config['tvname'] = isset($_REQUEST['tv_name']) ? $_REQUEST['tv_name'] : ''; 
        $migx->loadConfigs();        
        
        $this->config = $migx->customconfigs;

        $configname = $this->getConfig('name');
        if(empty($configname)){
            throw new HttpNotFoundException($this->request,'Config ' . $migxconfig . ' not found');
        }

        $this->setConfig('overwritableConfigs',$this->overwritableConfigs);

        $this->hooksnippets = $hooksnippets = json_decode($modx->getOption('hooksnippets', $this->config, ''),true);
        if (is_array($hooksnippets)) {
            $hooksnippet_getcustomconfigs = $modx->getOption('getcustomconfigs', $hooksnippets, '');
        }

        $snippetProperties = array();
        $snippetProperties['scriptProperties'] = $properties;
        $snippetProperties['action'] = $this->action;
        $snippetProperties['config'] = $this->config;
        $snippetProperties['controller'] = $this;

        if (!empty($hooksnippet_getcustomconfigs)) {
            $customconfigs = $modx->runSnippet($hooksnippet_getcustomconfigs, $snippetProperties);
            $customconfigs = json_decode($customconfigs,1);
            if (is_array($customconfigs)) {
                $this->config = array_merge($this->config, $customconfigs);
            }
        }
    }

    public function overwriteConfigsFromRequest(){
        $properties = $this->getProperties();
        if (is_array($this->overwritableConfigs)) {
            foreach ($this->overwritableConfigs as $config => $property){
                if (!empty($properties[$property])){
                    $this->setConfig($config,$properties[$property]);
                }                 
            }
        }
    }

    public function checkAccessPermission(){
        $modx = $this->modx;
        $permissions = $this->getConfig('permissions');
        $access_permission = $this->getOption('apiaccess',$permissions);
        if (empty($access_permission)){
            throw new HttpUnauthorizedException($this->request, '');
        }
        if ($result && !$modx->hasPermission($access_permission)){
            throw new HttpUnauthorizedException($this->request);
        }
        return true;       
    }

    public function initXpdo() {
        $modx = & $this->modx;
        $migx = & $this->migx;
        $config = $this->config;
        
        $prefix = isset($config['prefix']) && !empty($config['prefix']) ? $config['prefix'] : null;
        if (isset($config['use_custom_prefix']) && !empty($config['use_custom_prefix'])) {
            $prefix = isset($config['prefix']) ? $config['prefix'] : '';
        }

        if (!empty($config['packageName'])) {
            $packageNames = explode(',', $config['packageName']);
            $packageName = isset($packageNames[0]) ? $packageNames[0] : '';    

            if (count($packageNames) == '1') {
                //for now connecting also to foreign databases, only with one package by default possible
                $xpdo = $migx->getXpdoInstanceAndAddPackage($config);
            } else {
                //all packages must have the same prefix for now!
                foreach ($packageNames as $packageName) {
                    $packagepath = $modx->getOption('core_path') . 'components/' . $packageName . '/';
                    $modelpath = $packagepath . 'model/';
                    if (is_dir($modelpath)) {
                        $modx->addPackage($packageName, $modelpath, $prefix);
                    }

                }
                $xpdo = &$modx;
            }
            if ($this->modx->lexicon) {
                $this->modx->lexicon->load($packageName . ':default');
            }    
        }else{
            $xpdo = &$modx;    
        }

        $this->xpdo = &$xpdo;        
    }

    public function get($key) {
        return isset($this->$key) ? $this->$key : null;
    }

     /**
     * Get a configuration option value by key.
     *
     * @param string $key The option key.
     * @param array $options A set of options to override those from this Controller.
     * @param mixed $default An optional default value to return if no value is found.
     * @param boolean $skipEmpty . Whether or not to skip empty options
     * @return mixed The configuration option value.
     */
    public function getOption($key, $options = null, $default = null, $skipEmpty = false){
        return $this->getConfig($key, $default, $skipEmpty, $options,);
    }

        /**
     * Get a configuration option value by key.
     *
     * @param string $key The option key.
     * @param mixed $default An optional default value to return if no value is found.
     * @param boolean $skipEmpty . Whether or not to skip empty options
     * @param array $options A set of options to override those from this Controller.
     * @return mixed The configuration option value.
     */
    public function getConfig($key, $default = null, $skipEmpty = false, $options = null) {
        $option = null;
        if (is_string($key) && !empty($key)) {
            $found = false;
            if (isset($options[$key])) {
                $found = true;
                $option = $options[$key];
            }

            if ((!$found || ($skipEmpty && $option === '')) && isset($this->config[$key])) {
                $found = true;
                $option = $this->config[$key];
            }

            if (!$found || ($skipEmpty && $option === ''))
                $option = $default;
        }
        else if (is_array($key)) {
            if (!is_array($option)) {
                $default = $option;
                $option = array();
            }
            foreach($key as $k) {
                $option[$k] = $this->getOption($k, $options, $default);
            }
        }
        else
            $option = $default;

        return $option;
    }

    /**
     * Set a config value for the controller
     *
     * @param string $key
     * @param string $value
     */
	public function setConfig($key,$value) {
	    $this->config[$key] = $value;
	}    

    /**
     * Get a REQUEST property for the controller
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
	public function getProperty($key,$default =null) {
	    $value = $default;
	    if (array_key_exists($key,$this->properties)) {
	        $value = $this->properties[$key];
	    }
	    return $value;
	}

    /**
     * Set a request property for the controller
     *
     * @param string $key
     * @param string $value
     */
	public function setProperty($key,$value) {
	    $this->properties[$key] = $value;
	}

    /**
     * Unset a request property for the controller
     * @param string $key
     */
	public function unsetProperty($key) {
	    unset($this->properties[$key]);
	}

    /**
     * Get the request properties for the controller
     * @return array
     */
	public function getProperties() {
	    return $this->properties;
	}

	/**
     * Set a collection of properties for the controller
     *
     * @param array $properties An array of properties
     * @param bool $merge Optionally, only merge properties in if this is true
     */
	public function setProperties(array $properties = array(),$merge = false) {
        $this->properties = $merge ? array_merge($this->properties,$properties) : $properties;
	}    

    
    public function prepareResponse($result,$status) {
        $this->response->getBody()->write(json_encode($result,JSON_PRETTY_PRINT));
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);        
    }

    public function getArgument($key) {
        return $this->route->getArgument($key);
    }
    
}