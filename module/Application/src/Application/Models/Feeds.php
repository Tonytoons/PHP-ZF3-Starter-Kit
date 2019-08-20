<?php
namespace Application\Models;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Cache\StorageFactory;
use Zend\Cache\Storage\Adapter\Memcached;
use Zend\Cache\Storage\StorageInterface;
use Zend\Json\Json;
class Feeds
{
    protected $feedses;
################################################################################ 
	function __construct($inLang, $inID, $inPage, $noCache)
    {
        $this->cacheTime = 3600;
        $this->lang = $inLang;
        $this->id = $inID;
        $this->page = $inPage;
        $this->perpage = 21;
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
        
        $this->apiURL = 'https://dev.zenovly.com/api';  
		if($_SERVER['HTTP_HOST']=='safe-tonytoons.c9users.io'){ 
		    $this->apiURL = 'https://safe-tonytoons.c9users.io/public/api';
		}  
		
		$this->noCache = $noCache;
    }
################################################################################ 

################################################################
    function maMemCache($time, $namespace)
    {
    	$cache = StorageFactory::factory([
										    'adapter' => [
										        'name' => 'filesystem',
										        'options' => [
										            'namespace' => $namespace,
										            'ttl' => $time,
										        ],
										    ],
										    'plugins' => [
										        // Don't throw exceptions on cache errors
										        'exception_handler' => [
										            'throw_exceptions' => true
										        ],
										        'Serializer',
										    ],
										]);
		return($cache);
	}
################################################################################ 
}
    