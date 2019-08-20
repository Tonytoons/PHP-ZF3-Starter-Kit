<?php
namespace Application\Models;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Cache\StorageFactory;
use Zend\Cache\Storage\Adapter\Memcached;
use Zend\Cache\Storage\StorageInterface;
#mail#
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
##AWS##
require 'vendor/aws/aws-autoloader.php';
use Aws\Ses\SesClient;
/*--s3--*/
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;

class Agent
{
    protected $apies;
################################################################################ 
	function __construct($adapter, $inLang, $inID, $inPage, $nocache)
    {
        $this->cacheTime = 3600;
        $this->lang = $inLang;
        $this->id = $inID;
        $this->adapter = $adapter;
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
		$this->noCache = $nocache;
		$this->webURL = 'https://api.tixget.com';
		$this->config = include __DIR__ . '../../../../config/module.config.php';
		//$this->s3 = 'https://s3-ap-southeast-1.amazonaws.com/tixget/';
		$this->s3 = 'https://files.tixget.com/';
    }
################################################################################ 
    function getLogin($username, $password)
    {
        $sql = $this->adapter->query("SELECT COUNT(id) as c FROM `api` WHERE username = '$username' AND password = '$password' AND active = '1' LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        $c = $row['c'];
        if($c == NULL) $c = 0;
		return($c);
    }
################################################################################ 
    function add($name, $email, $password, $phone, $manager, $business_registration_number, $atta, $pic)
    {
        $return = 0;
        try 
        {
            $id = $this->getNextID();
            if($id)
            {
                $sql = $this->adapter->query("INSERT INTO agents (id, active, name, business_registration_number, atta, pic, added_date, last_update) VALUES 
                        ('$id', '0', '$name', '$business_registration_number', '$atta', '', NOW(), NOW());");
                if($sql->execute())
                {
                    $admin_id = $this->getNextAID();
                    $passwordMD5 = md5($password);
                    $sql2 = $this->adapter->query("INSERT INTO agent_admin (id, active, agent_id, name, email, password, phone, pic, level, added_date, last_update) VALUES 
                        ('$admin_id', '1', '$id', '$manager', '$email', '$passwordMD5', '$phone', '', '1', NOW(), NOW());");
                    if($sql2->execute())
                    {
                        if($pic)
                        {
                            $img_name = $this->uploadIMG($pic, 'agents/gallery');
                            if($img_name)
                            {
                                $sql3 = $this->adapter->query("INSERT INTO agent_galleries (agent_id, name, last_update) VALUES 
                                        ('$id', '$img_name', NOW());");
                                $sql3->execute();
                            }
                        }
                        $txt = file_get_contents($this->webURL.'/email/agent_applied.html');
                        $txt = preg_replace(array('/{name}/'), array($manager), $txt);
                        $email_subject = "Thanks you. We can't wait to work with you!";
                        $this->sendMail($email_subject, 'Tixget team', 'admin@tixget.com', ucfirst($manager), $email, $txt, '', '');
                        $return = 1;
                    }
                }
            }
        }
        catch (\Exception $e)
        {
            $return = 0;//print_r($e);
        }   
        return($return);
    }
################################################################################ 
    function getNextAID()
    {
        $id = '';
        $sql = $this->adapter->query("SELECT MAX(id)+1 as id FROM `agent_admin` LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        $id = $row['id'];
        if($id == NULL) $id = 1;
		return($id);
    }
################################################################################ 
    function getNextID()
    {
        $id = '';
        $sql = $this->adapter->query("SELECT MAX(id)+1 as id FROM `agents` LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        $id = $row['id'];
        if($id == NULL) $id = 1;
		return($id);
    }
################################################################
    function uploadIMG($img, $fd)
    {
        /*
        base 64 format : 'data:image/[ext of image];base64,’+ [base64 of image]
        image = jpg or pdf or png
        ex : 'data:image/png;base64,xxxxxxxxxxxxxxxxxxxxxxxxxx
        */
        $img_name = '';
        try   
        {
            if (!preg_match('/data:([^;]*);base64,(.*)/', $img, $matches)) {
                die("error");
            }
    
            $content = str_replace('data:image/', '', $matches[0]);
            $content = str_replace('data:application/', '', $content);
            $content = explode(";", $content);
            $content = $content[0];
            $pname = $this->id.gmdate('YmdHis').rand(0000, 9999);
            $img_name = $pname.'.'.$content;
            $filenameext = $content;
            if($img_name)
            {
                $s3 = new S3Client($this->config['amazon_s3']['config']);    
                $bucket = $this->config['amazon_s3']['bucket'];
                $result = $s3->putObject(array(
                    'Bucket' => $bucket, 
                    'Key' => $fd.'/'.$img_name,
                    'ACL' => 'public-read',
                    'SourceFile' => $img,
                    'Expires'=> (string)(1000+(int)date("Y")),
                    'ContentType'=>'image/'.$filenameext,
                )); 
            }
        } catch (S3Exception $e) {    
            // Catch an S3 specific exception.
            /*echo "<pre>";
            echo $e->getMessage();
            echo "</pre>";
            exit; */   
        }
        return($img_name);
    }
################################################################################ 
    function addRequest($id, $attraction_id)
    {
        $nid = $this->getNextAAID();
        if($nid)
        {
            $sql = $this->adapter->query("INSERT INTO attraction_agents (id, attraction_id, agent_id, agent_class_id, status, added_date, last_update) VALUES 
                        ('$nid', '$attraction_id', '$id', '0', '0', NOW(), NOW());");
            return($sql->execute());
        }
    }
################################################################################ 
    function getNextAAID()
    {
        $id = '';
        $sql = $this->adapter->query("SELECT MAX(id)+1 as id FROM `attraction_agents` LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        $id = $row['id'];
        if($id == NULL) $id = 1;
		return($id);
    }
################################################################################ 
    function getCategories()
    {
        $data = array();
        $key_txt = md5('categories');
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
            $sql = $this->adapter->query("SELECT id, name FROM categories WHERE active = '1' ORDER BY name ASC ");
    		$results = $sql->execute();
            $resultSet = new ResultSet;
            $rs = $resultSet->initialize($results);
    		$rsa = $rs->toArray();
    		if($rsa)
    		{
        		foreach ($rsa as $key => $value)
        		{
        		    $data[$key] = array(
        		                            'id' => $value['id'],
        		                            'name' => $value['name']
        		                        );
                }
                $cache->setItem($key_txt, $data);
            }
		}
		return($data);
    }
################################################################################ 
    function getAttractions($city_id, $c_id)
    {
        $data = array();
        //$key_txt = md5('attractions_'.$c_id.'_city_'.$city_id);
        //$cache = $this->maMemCache($this->cacheTime, $key_txt);
        //$data = $cache->getItem($key_txt, $success);
		//if( empty($data) || ($this->noCache == 1) )
		//{// no cache coz of request status
		    if($c_id)
		    {
                $sql = $this->adapter->query("SELECT id, thumbnail, name FROM attractions WHERE active = '1' AND category_id = '$c_id' AND city_id = '$city_id' ORDER BY last_update DESC ");
		    }
		    else
		    {
		        $sql = $this->adapter->query("SELECT id, thumbnail, name FROM attractions WHERE active = '1' AND city_id = '$city_id' ORDER BY last_update DESC ");
		    }
    		$results = $sql->execute();
            $resultSet = new ResultSet;
            $rs = $resultSet->initialize($results);
    		$rsa = $rs->toArray();
    		if($rsa)
    		{
        		foreach ($rsa as $key => $value)
        		{
        		    $id = $value['id'];
        		    $pic = '';
        		    if(!empty($value['thumbnail'])) $pic = $this->s3.'attractions/thumbnail/'.$value['thumbnail'];
        		    $data[$key] = array(
        		                            'id' => $id,
        		                            'name' => $value['name'],
        		                            'request_status' => $this->requestStatus($this->id, $id),
        		                            'pic' => $pic,
        		                        );
                }
                //$cache->setItem($key_txt, $data);
            }
		//}
		return($data);
    }
################################################################################ 
    function requestStatus($agent_id, $attraction_id)
    {
        $status = '';
        $sql = $this->adapter->query("SELECT status FROM `attraction_agents` WHERE attraction_id = '$attraction_id' AND agent_id = '$agent_id' LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        $status = $row['status'];
        if($status == NULL) $status = '';
		return($status);
    }
################################################################################ 
    function getCities()
    {
        $data = array();
        $key_txt = md5('cities_list');
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
            $sql = $this->adapter->query("SELECT id, name FROM cities WHERE active = '1' ORDER BY id ASC ");
    		$results = $sql->execute();
            $resultSet = new ResultSet;
            $rs = $resultSet->initialize($results);
    		$rsa = $rs->toArray();
    		if($rsa)
    		{
        		foreach ($rsa as $key => $value)
        		{
        		    $data[$key] = array(
        		                            'id' => $value['id'],
        		                            'name' => $value['name']
        		                        );
                }
                $cache->setItem($key_txt, $data);
            }
		}
		return($data);
    }
################################################################################ 
    function getAlogin($email, $password)
    {
        $id = 0;
        $password = md5($password);
        $sql = $this->adapter->query("SELECT id FROM `agent_admin` WHERE active = '1' AND email = '$email' AND password = '$password' LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        if(@$row)
		{
		    $id = $row['id'];
		    if($id == NULL) $id = 0;
		}
		return($id);
    }
################################################################################ 
    function getAdmin($id)
    {
        $data = array();
        $key_txt = md5('agent_admin_id_'.$id);
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
	        $sql = $this->adapter->query("SELECT agent_id, name, email, phone, pic, level, last_update FROM `agent_admin` WHERE id = '$id' LIMIT 1");
            $results = $sql->execute();
            $row = $results->current();
            if(@$row) 
			{
			    $agent_id = $row['agent_id'];
				$name = $row['name'];
				$email = $row['email'];
				$phone = $row['phone'];
				$level = $row['level'];
				$last_update = $row['last_update'];
				$pic = $this->s3.'agents/admin/agent.jpg';
				if($row['pic']) $pic = $this->s3.'agents/admin/'.$row['pic'];
				$agent = $this->getAgentINFO($agent_id);
				$data = array(
									'id' => $id,
									'name' => $name,
									'email' => $email,
									'phone' => $phone,
									'level' => $level,
									'agent_id' => $agent_id,
									'agent' => $agent,
									'pic' => $pic,
									'last_update' => $last_update
							);
				$cache->setItem($key_txt, $data);
			}
		}
		return($data);
    }
################################################################################ 
    function getAgentINFO($id)
    {
        $data = array();
        $key_txt = md5('agent_info_id_'.$id);
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
	        $sql = $this->adapter->query("SELECT name, business_registration_number, atta FROM `agents` WHERE id = '$id' LIMIT 1");
            $results = $sql->execute();
            $row = $results->current();
            if(@$row) 
			{
			    $name = $row['name'];
				$business_registration_number = $row['business_registration_number'];
				$atta = $row['atta'];
				$pic = '';
				$data = array(
									'id' => $id,
									'name' => $name,
									'business_registration_number' => $business_registration_number,
									'atta' => $atta,
									'pic' => $pic
							);
				$cache->setItem($key_txt, $data);
			}
		}
		return($data);
    }
################################################################################ 
    function getChart()
    {
        $data = array();
        
        $key_txt = md5('chart_agent_id_'.$this->id);
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
            $m1 = date("Y-m", strtotime("-6 months"));
            $m2 = date("Y-m", strtotime("-5 months"));
            $m3 = date("Y-m", strtotime("-4 months"));
            $m4 = date("Y-m", strtotime("-3 months"));
            $m5 = date("Y-m", strtotime("-2 months"));
            $m6 = date("Y-m", strtotime("-1 months"));
            $m7 = date("Y-m");
            
            #b2b
            $b2b1 = 0;
            $sqlb2b = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m1%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b = $sqlb2b->execute();
            $rowb2b = $resultsb2b->current();
            $b2b1 = $rowb2b['c'];
            if($b2b1 == NULL) $b2b1 = 0;
            
            $b2b2 = 0;
            $sqlb2b2 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m2%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b2 = $sqlb2b2->execute();
            $rowb2b2 = $resultsb2b2->current();
            $b2b2 = $rowb2b2['c'];
            if($b2b2 == NULL) $b2b2 = 0;
            
            $b2b3 = 0;
            $sqlb2b3 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m3%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b3 = $sqlb2b3->execute();
            $rowb2b3 = $resultsb2b3->current();
            $b2b3 = $rowb2b3['c'];
            if($b2b3 == NULL) $b2b3 = 0;
            
            $b2b4 = 0;
            $sqlb2b4 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m4%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b4 = $sqlb2b4->execute();
            $rowb2b4 = $resultsb2b4->current();
            $b2b4 = $rowb2b4['c'];
            if($b2b4 == NULL) $b2b4 = 0;
            
            $b2b5 = 0;
            $sqlb2b5 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m5%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b5 = $sqlb2b5->execute();
            $rowb2b5 = $resultsb2b5->current();
            $b2b5 = $rowb2b5['c'];
            if($b2b5 == NULL) $b2b5 = 0;
            
            $b2b6 = 0;
            $sqlb2b6 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m6%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b6 = $sqlb2b6->execute();
            $rowb2b6 = $resultsb2b6->current();
            $b2b6 = $rowb2b6['c'];
            if($b2b6 == NULL) $b2b6 = 0;
            
            $b2b7 = 0;
            $sqlb2b7 = $this->adapter->query("SELECT COUNT(id) AS c FROM `engine_transactions` WHERE ticket_date like '$m7%' AND agent_id = '$this->id' AND type = '2' LIMIT 1");
            $resultsb2b7 = $sqlb2b7->execute();
            $rowb2b7 = $resultsb2b7->current();
            $b2b7 = $rowb2b7['c'];
            if($b2b7 == NULL) $b2b7 = 0;
            
            
            $data = array(
                                'b2b' => array(
                                                        'm1' => array(
                                                                        $m1,
                                                                        $b2b1,
                                                                        ),
                                                        'm2' => array(
                                                                        $m2,
                                                                        $b2b2,
                                                                        ),
                                                        'm3' => array(
                                                                        $m3,
                                                                        $b2b3,
                                                                        ),
                                                        'm4' => array(
                                                                        $m4,
                                                                        $b2b4,
                                                                        ),
                                                        'm5' => array(
                                                                        $m5,
                                                                        $b2b5,
                                                                        ),
                                                        'm6' => array(
                                                                        $m6,
                                                                        $b2b6,
                                                                        ),
                                                        'm7' => array(
                                                                        $m7,
                                                                        $b2b7,
                                                                        ),
                                                    ),
                            );
            $cache->setItem($key_txt, $data);
		}
		return($data);
    }
################################################################################ 
    function getAttractionDetail($id)
    {
        $data = array();
        $key_txt = md5('AttractionDetail_'.$id);
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
            $sql = $this->adapter->query("SELECT name, booking_in_advance, logo, img_cover, img_map, opening_time, close_day, detail, note, address, lat, lon FROM `attractions` WHERE id = '$id' AND active = '1' LIMIT 1");
            $results = $sql->execute();
            $row = $results->current();
            if(@$row)
    		{
    		    $name = $row['name'];
    		    $booking_in_advance = $row['booking_in_advance'];
    		    $logo = '';
    		    if($row['logo']) $logo = 'https://files.tixget.com/attractions/logo/'.$row['logo'];
    		    $img_cover = '';
    		    if($row['img_cover']) $img_cover = 'https://files.tixget.com/attractions/cover/'.$row['img_cover'];
    		    $img_map = '';
    		    if($row['img_map']) $img_map = 'https://files.tixget.com/attractions/map/'.$row['img_map'];
    		    $opening_time = $row['opening_time'];
    		    $close_day = $row['close_day'];
    		    $detail = $row['detail'];
    		    $note = $row['note'];
    		    $address = $row['address'];
    		    $lat = $row['lat'];
    		    $lon = $row['lon'];
    		    $gallery = $this->getAttractionGallery($id);
    		    $attractions_special_close_date = $this->getAttractionsSpecialCloseDate($id);
    		    $price_list = $this->getAprice($id);
    		    $data = array(
    		                    'id' => $id,
    		                    'name' => $name,
    		                    'booking_in_advance' => $booking_in_advance,
    		                    'opening_time' => $opening_time,
    		                    'close_day' => $close_day,
    		                    'attractions_special_close_date' => $attractions_special_close_date,
    		                    'detail' => $detail,
    		                    'note' => $note,
    		                    'address' => $address,
    		                    'price_list' => $price_list,
    		                    'lat' => $lat,
    		                    'lon' => $lon,
    		                    'logo' => $logo,
    		                    'img_cover' => $img_cover,
    		                    'img_map' => $img_map,
    		                    'gallery' => $gallery
    		            );
    	        $cache->setItem($key_txt, $data);
    		}
		}
		return($data);
    }
################################################################################ 
    function getAprice($id)
    {
        $data = array();
        $sql = $this->adapter->query("SELECT id, name, normal_price, price FROM `ticket` WHERE attraction_id = '$id' AND active = '1' AND type = '2' ORDER BY last_update ASC");
		$results = $sql->execute();
        $resultSet = new ResultSet;
        $rs = $resultSet->initialize($results);
		$rsa = $rs->toArray();
		if($rsa)
		{
    		foreach ($rsa as $key => $value)
    		{
                $data[$key] = array(
                                        'id' => $value['id'],
                                        'name' => $value['name'],
                                        'normal_price' => $value['normal_price'],
                                        'price' => $value['price']
                            );
            }
        }
		return($data);
    }
################################################################################ 
    function getAttractionsSpecialCloseDate($id)
    {
        $data = array();
        $sql = $this->adapter->query("SELECT date FROM attractions_special_close_date WHERE attraction_id = '$id' ORDER BY date ASC ");
    	$results = $sql->execute();
        $resultSet = new ResultSet;
        $rs = $resultSet->initialize($results);
    	$rsa = $rs->toArray();
    	if($rsa)
    	{
        	foreach ($rsa as $key => $value)
        	{
        	    $data[$key] = $value['date'];
            }
        }
		return($data);
    }
################################################################################ 
    function getAttractionGallery($id)
    {
        $data = array();
        $key_txt = md5('AttractionGallery_'.$id);
        $cache = $this->maMemCache($this->cacheTime, $key_txt);
        $data = $cache->getItem($key_txt, $success);
		if( empty($data) || ($this->noCache == 1) )
		{
            $sql = $this->adapter->query("SELECT img FROM attractions_galleries WHERE attraction_id = '$id' ORDER BY id ASC ");
    		$results = $sql->execute();
            $resultSet = new ResultSet;
            $rs = $resultSet->initialize($results);
    		$rsa = $rs->toArray();
    		if($rsa)
    		{
        		foreach ($rsa as $key => $value)
        		{
        		    $data[$key] = $value['img'];
                }
                $cache->setItem($key_txt, $data);
            }
		}
		return($data);
    }
################################################################################ 
    function getTransactions()
    {
        $search = '%';
        $data = array();
        $sql = $this->adapter->query("SELECT id, attraction_id, price, ticket, complete, refno
        FROM engine_transactions WHERE 
        agent_id = '$this->id' AND type = '2'
        ORDER BY last_update DESC LIMIT $this->pageStart, $this->perpage ");
		$results = $sql->execute();
        $resultSet = new ResultSet;
        $rs = $resultSet->initialize($results);
		$rsa = $rs->toArray();
		$total = $this->getTransactionsTotal($this->id);
		if($rsa)
		{
    		foreach ($rsa as $key => $value)
    		{
    		    $id = $value['id'];
    		    $attraction_id = $value['attraction_id'];
    		    $ticket = $value['ticket'];
    		    $data[$key] = array(
    		                            'id' => $id,
                                        'price' => $value['price'],
                                        'complete' => $value['complete'],
                                        'refno' => $value['refno'],
                                        'total' => $total
                            );
            }
        }
		return($data);
    }
################################################################################ 
    function getTransactionsTotal($id)
    {
        $search = '%';
        $c = 0;
        $sql = $this->adapter->query("SELECT COUNT(id) AS c
        FROM engine_transactions WHERE 
        agent_id = '$id' AND type = '2' LIMIT 1");
        $results = $sql->execute();
        $row = $results->current();
        if(@$row) $c = $row['c'];
        if($c == NULL) $c = 0;
		return($c);
    }
################################################################################ 
    function forgotpassword($email)
    {
        $data = '';
        $adapter = $this->adapter;
        $sql = "SELECT password FROM agent_admin WHERE email='".$email."' AND active='1' LIMIT 1";
        $statement = $adapter->query($sql);
        $results = $statement->execute();
        $row = $results->current();
        if($row['password'])
        {
            $date = date('Y-m-d H:i:s');
            $tmr = date('Y-m-d H:i:s',strtotime($date . "+1 days"));
            $token = base64_encode ($email.'*****'.$tmr);
            $link = '<a href="https://agent.tixget.com/en/reset/?token='.$token.'"> Click Here! </a>';
            $txt = file_get_contents('https://admin.tixget.com/email/forgotPassword.html');
            $txt = preg_replace(array('/{name}/', '/{link}/'), array('Admin user', $link), $txt);
            $email_subject = "Reset your Agent Password! - Tixget";
            $this->sendMail($email_subject, 'Tixget team', 'admin@tixget.com', 'Agent user', $email, $txt, '', '');
            $data = 'We will send password to your email.';
        }
        return ($data);
    }
################################################################################ 
    function cpasswordbemail($email, $password)
    {
        $data = '';
        $sql = "UPDATE `agent_admin` 
                SET password ='".md5($password)."', last_update = NOW()
                WHERE email = '".$email."'";    
        $sql = $this->adapter->query($sql);
        if($sql->execute()) $data = 'Success! Your Password has been changed!';
        return($data);
    }
################################################################
    function sendMail($subject, $fromName, $fromEmail, $toName, $toEmail, $body, $bccName, $bccEmail)
    {
        try 
        {
            $message = new Message();
            $html = new MimePart($body);
            $html->type = "text/html";
            
            $body = new MimeMessage();
            $body->setParts(array($html));
            
            $message = new Message();
            $message->setBody($body);
            
            $message->addTo($toEmail, $toName)
                    ->addFrom($fromEmail, $fromName)
                    ->setSubject($subject);
            
            // Setup SMTP transport using LOGIN authentication
            $transport = new SmtpTransport();
            
            $options   = new SmtpOptions(array(
                'name'              => 'Tixget',
                'host'              => 'smtp.sendgrid.net',
                'port'              => 587,
                'connection_class'  => 'login',
                'connection_config' => array(
                    'username' => 'Tixget',
                    'password' => 'RockTixget69',
                    'ssl'      => 'tls',
                ),
            ));
            
            /* ses
            $options   = new SmtpOptions(array(
                'name'              => 'ses-smtp-user.20170421-140044',
                'host'              => 'email-smtp.eu-west-1.amazonaws.com',
                'port'              => 587,
                'connection_class'  => 'login',
                'connection_config' => array(
                    'username' => 'AKIAITNCQXJMNWI36GEA',
                    'password' => 'Au7IxQkXfB5fDZmgfYFJe9SndoaKuAFz38QfBG6w78aZ',
                    'ssl'      => 'tls',
                ),
            ));
            */
            $transport->setOptions($options);
            $transport->send($message);
        }
        catch (\Exception $e)
        {
            print_r($e);
        }
    }
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
