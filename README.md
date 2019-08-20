
'########:::'#######:::'######::'##:::'##::'######::'########::::'###::::'########::
 ##.... ##:'##.... ##:'##... ##: ##::'##::'##... ##:... ##..::::'## ##::: ##.... ##:
 ##:::: ##: ##:::: ##: ##:::..:: ##:'##::: ##:::..::::: ##:::::'##:. ##:: ##:::: ##:
 ########:: ##:::: ##: ##::::::: #####::::. ######::::: ##::::'##:::. ##: ########::
 ##.. ##::: ##:::: ##: ##::::::: ##. ##::::..... ##:::: ##:::: #########: ##.. ##:::
 ##::. ##:: ##:::: ##: ##::: ##: ##:. ##::'##::: ##:::: ##:::: ##.... ##: ##::. ##::
 ##:::. ##:. #######::. ######:: ##::. ##:. ######::::: ##:::: ##:::: ##: ##:::. ##:
..:::::..:::.......::::......:::..::::..:::......::::::..:::::..:::::..::..:::::..::
    ----------------------------------------------------------------- 


Hi there!

Welcome to easy PHP framework for beginners!

Good for the beginner who has no idea about PHP framework, let start from easy code here.

To get you started, let download all files and test with Apache or try on Cloud9(AWS c9).

1) Start from ./module/Application/config/module.config.php

2) For serious configuration, you have to change DB configuration like below

First, remove /* under //'Zend\Db' and */ under ),

And then change your configuration

'Db' => array(
        'driver' => 'Pdo',
        'dsn' => 'mysql:dbname=dbname;host=db host',   
        'driver_options' => array( 
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        ),
        'username' => 'username',
        'password' => 'password', 
    ),

3) Make sure your DB is correct and have "users" table and contain fields "id", "name" and "last_update"

4) Check router from router array. [ Check return array(... ]

5) Add controller like below

'controllers' => array(
        'invokables' => array(
            //add controller
            'Application\Controller\Index' => 'Application\Controller\IndexController',
            //'Application\Controller\Xxx' => 'Application\Controller\XxxController',
        ),
    ),
    
6) Every Action have to add View like below

#index

'application/index/index' => __DIR__ . '/../view/index/index.phtml',

'application/index/user' => __DIR__ . '/../view/index/user.phtml',


Ex1)

Start from IndexController ./module/Application/src/Application/Controller/IndexController.php

Ex2)

How to use multiple languages

Check ./module/Application/src/Application/language/

and use .po and .mo

like below

For .po

msgid "title"

msgstr "TH ZF3"

and try <?=$this->translate('title');?>

* All Views are here /module/Application/view/

URL will be like this https://www.xxxx.com/[language]/[Action]/ :: https://www.xxxx.com/user/th/

For the Router, you can change later, but for the beginner let follow this structure first.

Ex3)

Check other Actions on IndexController(How to add data - edit - view - delete)

Happy coding!

Tonytoons


## Documentations

ZF3 https://docs.zendframework.com/tutorials/

Po2Mo https://po2mo.net/

Bootstrap http://getbootstrap.com/getting-started/ for css framework!

git url https://github.com/Tonytoons/PHP-ZF3-Starter-Kit.git


#Upgrade to php7.3 on C9. #follow below.

sudo yum remove httpd* php*

sudo yum install httpd24

sudo yum install php73

sudo yum install php73-gd

sudo yum install php73-imap

sudo yum install php73-mbstring

sudo yum install php73-mysqlnd

sudo yum install php73-opcache

sudo yum install php73-pdo

sudo yum install php73-pecl-apcu


sudo nano /etc/httpd/conf/httpd.conf

sudo service httpd start

sudo yum remove php-cli mod_php php-common

