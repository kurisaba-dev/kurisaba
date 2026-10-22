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

// Caching (this needs to be set at the start because if enabled, it skips the rest of the configuration process)
$cf['KU_APC'] = false;

$cache_loaded = false;
if ($cf['KU_APC']) {
    if (apc_load_constants('config')) {
        $cache_loaded = true;
    }
}

if (!$cache_loaded) {

    $cf['KU_PROTO']             = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? 'https://' : 'http://';
    $cf['KU_BASE_HOST']         = $_SERVER['HTTP_HOST'];

    include 'config.php';

    putenv('TZ='.$cf['KU_TZ']);

    $cf['KU_ROOTDIR']           = realpath(dirname(__FILE__))."/";
    $cf['KU_WEBFOLDER']         = '/';
    $cf['KU_TEMPLATEDIR']       = $cf['KU_ROOTDIR'] . 'dwoo/templates';
    $cf['KU_CACHEDTEMPLATEDIR'] = $cf['KU_ROOTDIR'] . 'tmp/dwoo';

    $css_js_files = array("css/*.css", "css/styles/*.css", "lib/javascript/*.js", "lib/javascript/prettify/*.js");
    $maxtimestamp = max(array_map("filemtime", array_merge(...array_map(fn($x) => glob($cf['KU_ROOTDIR'] . $x), $css_js_files))));
    $cf['KU_CSSVER'] = $cf['KU_JSVER'] = $maxtimestamp;

    $cf['KU_SUPPORTED_LOCALES'] = 'ru|en';
    $cf['KU_REACT_ENA'] = false;
    $cf['KU_LOCAL_REACT_API'] = 'http://127.0.0.1:1337';
    $cf['KU_REACT_SITENAME'] = 'yourchanid';
    $cf['KU_CLI_REACT_API'] = 'http://example.com:1337';
    $cf['KU_REACT_SRVTOKEN'] = 'ENTERRANDOMSHIT';

    // Версия курисабы
    $cf['KU_VERSION']    = '1.0 based on KusabaX-0.9.3/0chan';

    // Debug
    $cf['KU_DEBUG'] = false; // When enabled, debug information will be printed (Warning: all queries will be shown publicly)

    // Host and domain stuff
    $cf['KU_HOST'] = isset($_SERVER['HTTP_HOST']) ? preg_replace("/[^A-Za-z0-9.:]/", '', $_SERVER['HTTP_HOST']) : $cf['KU_BASE_HOST']; // escape string
    $cf['KU_DOMAIN'] = '.' . $cf['KU_HOST']; // Used in cookies for the domain parameter.  Should be a period and then the top level domain, which will allow the cookies to be set for all subdomains.
    $cf['KU_WEBPATH'] = $cf['KU_PROTO'] . $cf['KU_HOST'];
    $cf['KU_REMOTE_ADDR'] = (array_key_exists("HTTP_CF_CONNECTING_IP", $_SERVER)) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];

    // Board subdomain/alternate directory (optional, change to enable)
    $cf['KU_BOARDSDIR']    = $cf['KU_ROOTDIR'];
    $cf['KU_BOARDSFOLDER'] = $cf['KU_WEBFOLDER'];
    $cf['KU_BOARDSPATH']   = $cf['KU_WEBPATH'];

    // CGI subdomain/alternate directory (optional, change to enable)
    $cf['KU_CGIDIR']    = $cf['KU_BOARDSDIR'];
    $cf['KU_CGIFOLDER'] = $cf['KU_BOARDSFOLDER'];
    $cf['KU_CGIPATH']   = $cf['KU_BOARDSPATH'];

    // Post-configuration actions
    $cf['KU_TAGS']       = serialize($cf['KU_TAGS']);
    $cf['KU_TRIPS']      = serialize($cf['KU_TRIPS']);

    if (substr($cf['KU_WEBFOLDER'], -2) == '//') {
        $cf['KU_WEBFOLDER'] = substr($cf['KU_WEBFOLDER'], 0, -1);
    }
    if (substr($cf['KU_BOARDSFOLDER'], -2) == '//') {
        $cf['KU_BOARDSFOLDER'] = substr($cf['KU_BOARDSFOLDER'], 0, -1);
    }
    if (substr($cf['KU_CGIFOLDER'], -2) == '//') {
        $cf['KU_CGIFOLDER'] = substr($cf['KU_CGIFOLDER'], 0, -1);
    }

    $cf['KU_WEBPATH'] = trim($cf['KU_WEBPATH'], '/');
    $cf['KU_BOARDSPATH'] = trim($cf['KU_BOARDSPATH'], '/');
    $cf['KU_CGIPATH'] = trim($cf['KU_CGIPATH'], '/');

    if ($cf['KU_APC']) {
        apc_define_constants('config', $cf);
    }
    foreach ($cf as $key => $value) {
        define($key, $value);
    }
    unset($cf);
}

$modules_loaded = array();
$required = array(KU_ROOTDIR, KU_WEBFOLDER, KU_WEBPATH);
if (in_array('CHANGEME', $required) || in_array('', $required)) {
    echo 'You must set KU_ROOTDIR, KU_WEBFOLDER, and KU_WEBPATH before installation will finish!';
    die();
}
require KU_ROOTDIR . 'lib/gettext/gettext.inc.php';
require KU_ROOTDIR . 'lib/adodb/adodb.inc.php';

function style_to_captcha_color($style)
{
    global $stylecolors;
    if (!isset($stylecolors[$style])) {
        $style = 'default';
    }
    return $stylecolors[$style];
}

// Gettext
_textdomain('kusaba');
_setlocale(LC_ALL, KU_LOCALE);
_bindtextdomain('kusaba', KU_ROOTDIR . 'inc/lang');
_bind_textdomain_codeset('kusaba', KU_CHARSET);

// Cookies
function setcookie_strict(string $name, string $value = "", int $expires_or_options = 0, string $path = "", string $domain = "", bool $secure = false, bool $httponly = false): bool
{
    // PHP < 7.3.0 (unsupported for now): return setcookie($name, $value, $expires_or_options, $path, $domain, $secure, $httponly);
    return setcookie($name, $value, [ 'expires' => $expires_or_options, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httponly' => $httponly, 'samesite' => 'Strict' ]);
}

// SQL  database
if (!isset($tc_db) && !isset($preconfig_db_unnecessary)) {
    $tc_db = &NewADOConnection(KU_DBTYPE);
    if (KU_DBUSEPERSISTENT) {
        $tc_db->PConnect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE) or die('SQL database connection error: ' . $tc_db->ErrorMsg());
    } else {
        $tc_db->Connect(KU_DBHOST, KU_DBUSERNAME, KU_DBPASSWORD, KU_DBDATABASE) or die('SQL database connection error: ' . $tc_db->ErrorMsg());
    }
    //file_put_contents('connects.log','.',FILE_APPEND);
    $tc_db->Execute("SET NAMES 'utf8mb4'");
    $tc_db->Execute("SET character_set_connection = 'utf8mb4'");
    $tc_db->Execute("SET character_set_results = 'utf8mb4'");
    $tc_db->Execute("SET character_set_client = 'utf8mb4'");
    //$tc_db->SetCharSet('utf8mb4');

    // SQL debug
    if (KU_DEBUG) {
        $tc_db->debug = true;
    }
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
if (version_compare(PHP_VERSION, '5.4.0', '<') && get_magic_quotes_runtime()) {
    set_magic_quotes_runtime(0);
}
