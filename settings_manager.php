<?php

function from_human_readable($val)
{
    $ret = 0;
    switch (substr($val, -1)) {
        case 'K': case 'k': $ret = (int)$val * 1024;
            break;
        case 'M': case 'm': $ret = (int)$val * 1048576;
            break;
        case 'G': case 'g': $ret = (int)$val * 1073741824;
            break;
        default: $ret = (int)$val;
    }
    return $ret;
}
if (from_human_readable(ini_get("memory_limit")) < 134217728) {
    ini_set("memory_limit", "128M");
}

mb_internal_encoding("UTF-8");
// Sets error reporting to hide notices.
error_reporting(E_ALL ^ E_NOTICE);
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');

    /* disable cache, finally. by Reimu */
    header("Expires: Sat, 17 Mar 1990 00:00:01 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

$cf = array();

// Host and domain stuff
$cf['KU_HOST'] = $_SERVER['HTTP_HOST'];
$cf['KU_REMOTE_ADDR'] = (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];
$cf['KU_PROTO'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? 'https://' : 'http://';

function configure_database()
{
    global $cf;
    if (isset($_POST['dsn']) && isset($_POST['dbusername']) && isset($_POST['dbpassword']) && isset($_POST['dbprefix'])) {
        file_put_contents('config.php', '<' . "?php\n");
        file_put_contents('config.php', '$cf["KU_DSN"]        = "' . $_POST['dsn'] . '";' . "\n", FILE_APPEND);
        file_put_contents('config.php', '$cf["KU_DBUSERNAME"] = "' . $_POST['dbusername'] . '";' . "\n", FILE_APPEND);
        file_put_contents('config.php', '$cf["KU_DBPASSWORD"] = "' . $_POST['dbpassword'] . '";' . "\n", FILE_APPEND);
        file_put_contents('config.php', '$cf["KU_DBPREFIX"]   = "' . $_POST['dbprefix'] . '";' . "\n", FILE_APPEND);
        header("Refresh: 0");
        die();
    }
    print('<!DOCTYPE html><html><head><title>Database configuration</title></head><body><h3>You have no configured database in config.php. Please provide database credentials:</h3>');
    print('<form action="settings_manager.php" method="POST">');
    print('Database DSN: <input name="dsn" value="' . (isset($cf['KU_DSN']) ? $cf['KU_DSN'] : 'mysql:host=localhost;dbname=kurisaba') . '"><br />');
    print('Database user name: <input name="dbusername" value="' . (isset($cf['KU_DBUSERNAME']) ? $cf['KU_DBUSERNAME'] : 'kurisaba') . '"><br />');
    print('Database password: <input name="dbpassword" value="' . (isset($cf['KU_DBPASSWORD']) ? $cf['KU_DBPASSWORD'] : 'change me') . '"><br />');
    print('Database table prefix: <input name="dbprefix" value="' . (isset($cf['KU_DBPREFIX']) ? $cf['KU_DBPREFIX'] : '') . '"><br />');
    print('<input type="submit" value="Submit"></form></body></html>');
    die();
}

if (!file_exists('config.php')) {
    configure_database();
}

// Filesystem path to a directory where engine files are located. Write access is needed only for engine update and initial configuration. Should end with "/".
$cf['KU_ENGINEDIR'] = realpath(dirname(__FILE__))."/";

// Filesystem path to a directory where data files are located. Full read/write access is needed. Should end with "/".
$cf['KU_STORAGEDIR']    = $cf['KU_ENGINEDIR'];

// Full URL to be used when linking engine files. Should not end with "/".
$cf['KU_WEBPATH'] = $cf['KU_PROTO'] . $cf['KU_HOST'];

// Full URL to be used when linking data files. Should not end with "/".
$cf['KU_BOARDSPATH']   = $cf['KU_WEBPATH'];

include 'config.php';

// Dwoo paths
$cf['KU_TEMPLATEDIR'] = $cf['KU_ENGINEDIR'] . 'dwoo/templates';
$cf['KU_CACHEDTEMPLATEDIR'] = $cf['KU_STORAGEDIR'] . 'tmp/dwoo';

$css_js_files = array("css/*.css", "css/styles/*.css", "lib/javascript/*.js", "lib/javascript/prettify/*.js");
$maxtimestamp = max(array_map("filemtime", array_merge(...array_map(fn($x) => glob($cf['KU_ENGINEDIR'] . $x), $css_js_files))));
$cf['KU_CSSVER'] = $cf['KU_JSVER'] = $maxtimestamp;

$cf['KU_SUPPORTED_LOCALES'] = 'ru|en';
$cf['KU_REACT_ENA'] = false;
$cf['KU_LOCAL_REACT_API'] = 'http://127.0.0.1:1337';
$cf['KU_REACT_SITENAME'] = 'yourchanid';
$cf['KU_CLI_REACT_API'] = 'http://example.com:1337';
$cf['KU_REACT_SRVTOKEN'] = 'ENTERRANDOMSHIT';

// Версия курисабы
$cf['KU_VERSION']    = '1.0 based on KusabaX-0.9.3/0chan';

class DatabaseProxy
{
    private $PDO_object;
    private $ErrorMessage;

    public function __construct($dsn, $dbusername, $dbpassword)
    {
        $this->$ErrorMessage = "No error";
        $this->$PDO_object = new PDO($dsn, $dbusername, $dbpassword);
    }

    public function ErrorMsg()
    {
        return $this->$PDO_object->errorInfo()[2];
    }

    public function Execute($query)
    {
        return $this->$PDO_object->query($query);
    }

    public function GetAll($query)
    {
        return $this->$PDO_object->query($query)->fetchAll();
    }

    public function GetOne($query)
    {
        return $this->$PDO_object->query($query)->fetchColumn();
    }

    public function Insert_Id()
    {
        return $this->$PDO_object->lastInsertId();
    }

    public function qstr($str)
    {
        return $this->$PDO_object->quote($str);
    }
}

function prepare_database()
{
    global $cf, $tc_db;
    $sql = explode(";\n", file_get_contents($cf['KU_ENGINEDIR'] . "lib/deploy/database.sql"));
    foreach ($sql as $statement) {
        $statement = trim(str_replace("\n", " ", str_replace("{{KU_DBPREFIX}}", $cf['KU_DBPREFIX'], $statement)));
        if(!empty($statement)) {
            $tc_db->Execute($statement);
        }
    }
    if(!file_exists($cf['KU_ENGINEDIR'] . 'front.php')) {
        copy($cf['KU_ENGINEDIR'] . 'lib/deploy/front.php', $cf['KU_ENGINEDIR'] . 'front.php');
    }
    if(stristr("Apache", $_SERVER['SERVER_SOFTWARE']) && !file_exists($cf['KU_ENGINEDIR'] . '.htaccess')) {
        copy($cf['KU_ENGINEDIR'] . 'lib/deploy/.htaccess.template', $cf['KU_ENGINEDIR'] . '.htaccess');
    }
    @mkdir($cf['KU_STORAGEDIR'] . "tmp", 0755);
    @mkdir($cf['KU_STORAGEDIR'] . "tmp/dwoo", 0755);
    @mkdir($cf['KU_STORAGEDIR'] . "tmp/xhrupload", 0755);
    header('Location: ' . $cf['KU_WEBPATH'] . '/manage.php');
}

// Connect to database and load config data
if (!isset($tc_db) && !isset($preconfig_db_unnecessary)) {
    if (!isset($cf['KU_DSN']) || empty($cf['KU_DSN'])) {
        configure_database();
    }
    try {
        $tc_db = new DatabaseProxy($cf['KU_DSN'], $cf['KU_DBUSERNAME'], $cf['KU_DBPASSWORD']);
    } catch (PDOException $e) {
        die('SQL database connection error: ' . $e->getMessage());
    }
    if (!$tc_db) {
        die('SQL database connection error');
    }
    $tc_db->Execute("SET NAMES 'utf8mb4'");
    $tc_db->Execute("SET character_set_connection = 'utf8mb4'");
    $tc_db->Execute("SET character_set_results = 'utf8mb4'");
    $tc_db->Execute("SET character_set_client = 'utf8mb4'");

    $tables = $tc_db->GetAll("SHOW TABLES");
    if(count($tables) == 0) {
        prepare_database();
    }

    $configs = yaml_parse(file_get_contents($cf['KU_ENGINEDIR'] . 'settings_schema.yaml'));
    $configdata = $tc_db->GetAll("SELECT HIGH_PRIORITY * FROM `" . $cf['KU_DBPREFIX'] . "kurisaba_ext_data`");
    foreach ($configs['config'] as $section) {
        foreach ($section['options'] as $optname => $option) {
            $dataval = isset($cf[$optname]) ? $cf[$optname] : $option['default'];
            foreach ($configdata as $configentry) {
                if ($configentry['name'] == $optname) {
                    $dataval = $configentry['value'];
                    break;
                }
            }
            if($option['type'] == 'bool') {
                if ($dataval === 'false') $dataval = false;
            }
            $cf[$optname] = $dataval;
        }
    }
}

$cf['KU_MENUSTYLES'] = $cf['KU_STYLES'];
$cf['KU_DEFAULTMENUSTYLE'] = $cf['KU_DEFAULTSTYLE'];

putenv('TZ='.$cf['KU_TZ']);

foreach ($cf as $key => $value) {
    define($key, $value);
}
unset($cf);

$modules_loaded = array();
require KU_ENGINEDIR . 'lib/gettext/gettext.inc.php';

function captcha_color($color)
{
    if(preg_match('/^rgb\(([0-9]+), ?([0-9]+), ?([0-9]+)\)$/', strtolower($color), $matches)) return array((int)$matches[1], (int)$matches[2], (int)$matches[3]);
    return array(85, 85, 85);
}

// Gettext
_textdomain('kusaba');
_setlocale(LC_ALL, KU_LOCALE);
_bindtextdomain('kusaba', KU_ENGINEDIR . 'inc/lang');
_bind_textdomain_codeset('kusaba', KU_CHARSET);

// Cookies
function setcookie_strict(string $name, string $value = "", int $expires_or_options = 0, string $path = "/", string $domain = "." . KU_HOST, bool $secure = false, bool $httponly = false): bool
{
    return setcookie($name, $value, [ 'expires' => $expires_or_options, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httponly' => $httponly, 'samesite' => 'Strict' ]);
}


function stripslashes_deep($value)
{
    $value = is_array($value) ?
        array_map('stripslashes_deep', $value) :
        stripslashes($value);
    return $value;
}

// Thanks Z
if (version_compare(PHP_VERSION, '7.4.0', '<') && get_magic_quotes_gpc()) {
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}
