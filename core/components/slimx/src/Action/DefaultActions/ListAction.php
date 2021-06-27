<?php

namespace App\Action\DefaultActions;

use Slim\Exception\HttpUnauthorizedException;

class ListAction extends BaseAction {

    public $action = 'list';

    public function process() {
        $modx = & $this->modx;
        $migx = & $this->migx;
        $xpdo = & $this->xpdo;

        $result = []; 
        $result['properties'] = $this->getProperties();
        //$result['get'] = $this->get;
        $classname = $this->getConfig('classname');
        $status = 200; 
        $c = $xpdo->newQuery($classname);
        $this->setSelect($c);

        $this->setJoins($c);
        
        $this->restrictToOwner($c);
        $this->checkPublished($c); 
        $this->checkDeleted($c);
        $this->setWhere($c);
        $this->setGroupBy($c);
        $total = $this->getCount($c);
        $this->setSort($c);
        $this->setLimit($c);
        $items = $this->getCollection($c);
        if ($items) {
            $result['success'] = true;
            $result['total'] = $total;
            $result['results'] = $items;
        } else {
            $result['success'] = true;
            $result['total'] = 0;
            $result['results'] = [];
        }
        return $this->prepareResponse($result,$status);
    } 

    public function setSelect($c){
        $classname = $this->getConfig('classname');
        $selectfields = $this->getConfig('getlistselectfields');
        $selectfields = !empty($selectfields) ? explode(',', $selectfields) : null;
        $specialfields = $this->getConfig('getlistspecialfields');

        $c->select($this->xpdo->getSelectColumns($classname, $classname, '', $selectfields)); 
        if (!empty($specialfields)) {
            $c->select($specialfields);
        }           
    }

    public function setJoins($c){
        $classname = $this->getConfig('classname');
        $joins = json_decode($this->getConfig('joins'),true);
        if (is_array($joins)) {
            $this->migx->prepareJoins($classname, $joins, $c);
        }        
    }

    public function restrictToOwner($c){
        $xpdo = $this->xpdo;
        $migx = $this->migx;
        $classname = $this->getConfig('classname');
        $joinalias = $this->getConfig('joinalias');
        $owner_id = $this->getProperty('owner_id');
        if (!empty($joinalias)) {
            if ($fkMeta = $xpdo->getFKDefinition($classname, $joinalias)) {
                //print_r($fkMeta);
        
                $joinclass = $fkMeta['class'];
                if($fkMeta['owner'] == 'foreign'){
                    $joinfield = $fkMeta['foreign'];
                    //$parent_joinfield = $fkMeta['local']; 
                } elseif ($fkMeta['owner'] == 'local'){
                    $joinfield = $fkMeta['local'];
                    //$parent_joinfield = $fkMeta['foreign']; 
                }
            } else {
                $joinalias = '';
            }
        }
        if (!empty($joinalias)) {
            /*
            if ($joinFkMeta = $modx->getFKDefinition($joinclass, 'Resource')){
            $localkey = $joinFkMeta['local'];
            }    
            */
            $c->leftjoin($joinclass, $joinalias);
            $c->select($xpdo->getSelectColumns($joinclass, $joinalias, 'Joined_'));
        }
        if ($migx->checkForConnectedResource($owner_id, $config)) {

            if (!empty($joinalias)) {
                $joinvalue = $owner_id;
                if ($parent_object = $xpdo->getObject($joinclass,$owner_id)){
                    $joinvalue = $parent_object->get($joinfield);
                }
                $c->where(array($joinalias . '.' . $joinfield => $joinvalue));
            } else {
                $c->where(array($classname . '.resource_id' => $owner_id));
            }
        }              
    }

    public function checkDeleted($c){
        $xpdo = $this->xpdo;
        $classname = $this->getConfig('classname');
        $showtrash = $this->getConfig('showtrash');
        $permissions = $this->getConfig('permissions');
        $permission = $this->getOption('viewdeleted',$permissions);
        $fields = $xpdo->getFields($classname);
        if (array_key_exists('deleted',$fields)){
            if (!empty($showtrash)) {
                if (!empty($permission) && $xpdo->hasPermission($permission)){
                    $c->where(array($classname . '.deleted' => '1'));
                }  else {
                    throw new HttpUnauthorizedException($this->request, 'view deleted not permitted');
                }
                
            } else {
                $c->where(array($classname . '.deleted' => '0'));
            }                        
        }
    }

    public function checkPublished($c){
        $xpdo = $this->xpdo;
        $classname = $this->getConfig('classname');
        $showunpublished = $this->getConfig('showunpublished');
        $permissions = $this->getConfig('permissions');
        $permission = $this->getOption('viewunpublished',$permissions);
        $fields = $xpdo->getFields($classname);
        if (array_key_exists('published',$fields)){
            if (!empty($showunpublished)) {
                if (!empty($permission) && $xpdo->hasPermission($permission)){
                    //show both, published and unpublished, if permitted, so no additional where - condition
                    //$c->where(array($classname . '.published' => '1'));
                }  else {
                    throw new HttpUnauthorizedException($this->request, 'view unpublished not permitted');
                }
                
            } else {
                //usually show only published
                $c->where(array($classname . '.published' => '1'));
            }                        
        }
    }    

    public function setWhere($c){
        $where = $this->getConfig('getlistwhere');
        if (!empty($where)) {
            $c->where(json_decode($where,true));
        }        
    }

    public function setGroupBy($c){
        $groupby = $this->getConfig('getlistgroupby');
        if (!empty($groupby)) {
            $c->groupby($groupby);
        }        
    }

    public function getCount($c){
        $classname = $this->getConfig('classname');
        $count= 0;
        $count = $this->xpdo->getCount($classname, $c);
        /*
        if($c->prepare() && $c->stmt->execute()){
            $count= $c->stmt->rowCount();
        }
        */
        return $count;        
    }

    public function setSort($c){
        $classname = $this->getConfig('classname');
        $defaultsort = $this->xpdo->getPK($classname);
        $sort = $this->getConfig('getlistsort');
        $dir = $this->getConfig('getlistsortdir','ASC');
        $requestsort = $this->getConfig('requestsort');
        $sortConfig =  json_decode($this->getConfig('sortconfig'),1);
        $requestsortconfig = $this->getConfig('requestsortconfig');
        if (!empty($requestsort)){
            $defaultsort = '';
            $sort = $requestsort;
            $sortConfig = '';
        }
        if (!empty($requestsortconfig)){
            $sortConfig = json_decode($requestsortconfig,true);
        }
        if (is_array($sortConfig)){
            $defaultsort = '';
            $sort = '';
        }        
        if (empty($sort)){
            $sort = $defaultsort;
        }        
        if (empty($sort)) {
            if (is_array($sortConfig)) {
                foreach ($sortConfig as $sort) {
                    $sortby = $sort['sortby'];
                    $sortdir = isset($sort['sortdir']) ? $sort['sortdir'] : 'ASC';
                    $c->sortby($sortby, $sortdir);
                }
            }
        } else {
            $c->sortby($sort, $dir);
        }        
    }

    public function setLimit($c){
        $limit = $this->getConfig('limit');
        if (!empty($limit)){
            $offset = $this->getConfig('offset');
            $start = !empty($offset) ? $offset : 0;
            $c->limit($limit, $start);
        }
    }

    public function getCollection($c) {
        $classname = $this->getOption('classname');
        $c->prepare();        
        //echo $c->toSql();
        $rows = array();
        /*
        if ($collection = $this->migx->getCollection($c)) {
            $pk = $this->xpdo->getPK($classname);
            foreach ($collection as $row) {
                $row['id'] = !isset($row['id']) ? $row[$pk] : $row['id'];
                $rows[] = $row;
            }
        }
        */
        if ($collection = $this->modx->getCollection($classname,$c)) {
            $pk = $this->xpdo->getPK($classname);
            foreach ($collection as $object) {
                $row = $object->toArray('',true,true);
                $row['id'] = !isset($row['id']) ? $row[$pk] : $row['id'];
                $rows[] = $row;
            }
        }        
        return $rows;        
    }    
    
}