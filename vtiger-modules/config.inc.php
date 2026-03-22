<?php
include('vtigerversion.php');

ini_set('memory_limit','512M');
ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

$HELPDESK_SUPPORT_EMAIL_ID = 'admin@essencialsaude.com.br';
$HELPDESK_SUPPORT_NAME = 'Essencial Saude';
$HELPDESK_SUPPORT_EMAIL_REPLY_ID = $HELPDESK_SUPPORT_EMAIL_ID;

$dbconfig['db_server'] = 'vtiger-db';
$dbconfig['db_port'] = ':3306';
$dbconfig['db_username'] = 'vtiger';
$dbconfig['db_password'] = getenv('VTIGER_DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: 'COLOQUE_NO_ENV');
$dbconfig['db_name'] = 'vtiger';
$dbconfig['db_type'] = 'mysqli';
$dbconfig['db_status'] = 'true';
$dbconfig['db_hostname'] = $dbconfig['db_server'].$dbconfig['db_port'];
$dbconfig['log_sql'] = false;

$dbconfigoption['persistent'] = true;
$dbconfigoption['autofree'] = false;
$dbconfigoption['debug'] = 0;
$dbconfigoption['seqname_format'] = '%s_seq';
$dbconfigoption['portability'] = 0;
$dbconfigoption['ssl'] = false;

$host_name = $dbconfig['db_hostname'];

$site_URL = 'https://crm.bot74.com.br';

// Proxy reverso com SSL termination — forçar HTTPS sempre
// (o servidor sempre roda atrás de HTTPS, nunca exposto direto)
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;
$root_directory = '/var/www/html/';
$cache_dir = 'cache/';
$tmp_dir = 'cache/images/';
$import_dir = 'cache/import/';
$upload_dir = 'cache/upload/';
$upload_maxsize = 3145728;
$allow_exports = 'all';
$upload_badext = array('php','php3','php4','php5','pl','cgi','py','asp','cfm','js','vbs','html','htm','exe','bin','bat','sh','dll','phps','phtml','xhtml','rb','msi','jsp','shtml','sth','shtm','htaccess','phar');
$list_max_entries_per_page = '20';
$history_max_viewed = '5';
$default_action = 'index';
$default_theme = 'softed';
$default_user_name = '';
$default_password = '';
$create_default_user = false;
$currency_name = 'Brazilian Real';
$default_charset = 'UTF-8';
$default_language = 'pt_br';
$display_empty_home_blocks = false;
$disable_stats_tracking = false;
$application_unique_key = 'crmbot74essencial2026abcdef123456';
$listview_max_textlength = 40;
$php_max_execution_time = 0;
$default_layout = 'v7';

if(isset($default_timezone) && function_exists('date_default_timezone_set')) {
    @date_default_timezone_set($default_timezone);
}

include_once 'config.security.php';
