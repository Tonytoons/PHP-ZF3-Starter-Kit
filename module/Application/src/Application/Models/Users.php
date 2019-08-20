<?php
namespace Application\Models;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Cache\StorageFactory;
use Zend\Cache\Storage\Adapter\Memcached;
use Zend\Cache\Storage\StorageInterface;
 
class Users
{ 
    protected $users; 
################################################################################ 
	function __construct($adapter, $inID, $inPage) 
    {
        $this->id = $inID; 
        $this->adapter = $adapter;
        $this->perpage = 100;
        $this->page = $inPage;
        $this->pageStart = ($this->perpage*($this->page-1));
        $this->now = date('Y-m-d H:i');
        $this->ip = '';
        if (getenv('HTTP_CLIENT_IP'))
        {
            $this->ip = getenv('HTTP_CLIENT_IP');
        }
        else if(getenv('HTTP_X_FORWARDED_FOR'))
        {
            $this->ip = getenv('HTTP_X_FORWARDED_FOR');
        }
        else if(getenv('HTTP_X_FORWARDED'))
        {
            $this->ip = getenv('HTTP_X_FORWARDED');
        }
        else if(getenv('HTTP_FORWARDED_FOR'))
        {
            $this->ip = getenv('HTTP_FORWARDED_FOR');
        }
        else if(getenv('HTTP_FORWARDED'))
        {
            $this->ip = getenv('HTTP_FORWARDED');
        }
        else if(getenv('REMOTE_ADDR'))
        {
            $this->ip = getenv('REMOTE_ADDR');
        }
        else
        {
            $this->ip = 'UNKNOWN';
        }
    } 

################################################################################ 
    function getList()
    {
        $data = [];
        $sql = "SELECT id, name, last_update FROM users WHERE 1 ORDER BY last_update DESC LIMIT ".$this->pageStart.", ".$this->perpage;
        $query = $this->adapter->query($sql);
        $results = $query->execute();
        $resultSet = new ResultSet;
        $data = $resultSet->initialize($results); 
        $data = $data->toArray();
        return $data;
    }
################################################################################  
    public function getNextId()
    { 
		$sql    = "SELECT MAX(id) + 1 AS id FROM `users` LIMIT 1";
   		$statement = $this->adapter->query($sql);     
        $results = $statement->execute();
        $row = $results->current();  
		$id     = $row['id'];   
		if($id == NULL) $id = '1';
		return ( $id ); 
	}
################################################################################  
    function add($name)    
    {
        $id = $this->getNextId();
        $sql = $this->adapter->query("INSERT INTO `users` (id, name, last_update) VALUES ('$id', '$name', '$this->now')");
        return($sql->execute());
    }
################################################################################ 
    function getDetail($id=0)
    { 
        $sql = "SELECT * FROM users WHERE id=".$id." LIMIT 1";
        $statement = $this->adapter->query($sql);
        $results = $statement->execute();
        $row = $results->current();
        return $row;
    }
################################################################################
    function edit($name) 
    { 
        $sql = "UPDATE `users` SET name = '$name', last_update = '$this->now' WHERE id=".$this->id;  
        $sql = $this->adapter->query($sql); 
        return($sql->execute());
    }
################################################################################    
    function del() 
    { 
       $sql    = "DELETE FROM `users` WHERE id=".$this->id." LIMIT 1";
   	   $statement = $this->adapter->query($sql);      
       return $statement->execute();    
    }
################################################################################ 
}
    