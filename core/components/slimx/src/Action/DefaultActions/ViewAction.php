<?php

namespace App\Action\DefaultActions;

use App\Action\DefaultActions\BaseAction;

class ViewAction extends BaseAction{

    public $action = 'view';

    public function process() {
        $modx = & $this->modx;
        $migx = & $this->migx;
        $xpdo = & $this->xpdo;

        $result = []; 
        //$result['route'] = $route = $this->getArgument('route');
        $result['key'] = $key = $this->getArgument('key');
        $result['properties'] = $this->getProperties();

        $classname = $this->getConfig('classname');
       
        $status = 200; 
        if (!empty($key) && $object = $xpdo->getObject($classname,$key)) {
            $result['result'] = $object->toArray();
        } else {
            $result['error'] = ['message' => 'Resource not found'];
            $status = 404;
        }
        return $this->prepareResponse($result,$status);

    } 

}