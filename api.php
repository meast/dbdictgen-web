<?php
/**
 * charset:utf-8
 */
$url_ref = '*';
if(!empty($_SERVER['HTTP_REFERER'])) {
    # CORS
    $arr_ref = parse_url($_SERVER['HTTP_REFERER']);
    $url_ref = $arr_ref['scheme'] . '://' . $arr_ref['host'];
    if(!empty($arr_ref['port']) && $arr_ref['port'] != 80) {
        $url_ref .= ':' . $arr_ref['port'];
    }
}
header('Access-Control-Allow-Origin: ' . $url_ref);

$api = new dbdictapi();
if(!empty($_REQUEST['act'])) {
    $act = $_REQUEST['act'];
    $method = 'act_' . $act;
    if(method_exists($api, $method)) {
        call_user_func(array($api, $method));
    }
}

class dbdictapi {
    private $_dbhost = 'localhost';
    private $_dbuser = 'root';
    private $_dbpwd = 'root';
    private $_dbcharset = 'utf8';
    private $_dbname = 'information_schema';

    public function __construct() {
        if(!empty($_REQUEST['dbhost'])) { $this -> _dbhost = trim($_REQUEST['dbhost']); }
        if(!empty($_REQUEST['dbuser'])) { $this -> _dbuser = trim($_REQUEST['dbuser']); }
        if(!empty($_REQUEST['dbpwd'])) { $this -> _dbpwd = trim($_REQUEST['dbpwd']); }
        if(!empty($_REQUEST['dbname'])) { $this -> _dbname = trim($_REQUEST['dbname']); }
        if(!empty($_REQUEST['dbcharset'])) { $this -> _dbcharset = trim($_REQUEST['dbcharset']); }
    }

    private function _json_return($arr) {
        header('content-type: application/json');
        exit(json_encode($arr));
    }

    public function act_test() {
        
    }

    public function act_listdb() {
        $res = $this -> listdb();
        $this -> _json_return($res);
    }

    public function act_listtable() {
        $res = $this -> listtable($this -> _dbname);
        $this -> _json_return($res);
    }

    /**
     * 生成字典
     */
    public function act_gendict() {
        $tables = $this -> listtable();
        $res = array();
        if(!empty($tables)) {
            foreach($tables as $k => $v) {
                $res1 = array('db_name' => $v['TABLE_SCHEMA']);
                $res1['table_name'] = $v['TABLE_NAME'];
                $res1['table_info'] = $v;
                $res1['fields'] = $this -> listfield($v['TABLE_SCHEMA'], $v['TABLE_NAME']);
                $res[$k] = $res1;
            }
        }
        $this -> _json_return($res);
    }

    public function act_save() {
        $dict = empty($_REQUEST['dict']) ? '' : $_REQUEST['dict'];
        $prefix = empty($_REQUEST['prefix']) ? '' : $_REQUEST['prefix'];
        $type = empty($_REQUEST['type']) ? 'html' : $_REQUEST['type'];
        $type = strtolower($type);
        $arr_type_allow = array('html', 'md', 'txt', 'log', 'wiki');
        if(!in_array($type, $arr_type_allow)) {
            $type = 'html';
        }
        $path_save = __DIR__ . '/dict/';
        $res = array('success' => 0, 'msg' => 'error');
        if(!empty($dict)) {
            if(!is_writeable(dirname($path_save))) { @chmod($path_save, 0755); }
            if(!is_dir($path_save)) { @mkdir($path_save, 0755, true); }
            $file_save = $path_save . $prefix . $this -> _dbname . '.' . $type;
            $res_save = file_put_contents($file_save, $dict);
            if($res_save) {
                $res['success'] = 1;
                $res['msg'] = 'success';
                $res['path'] = './dict/' . $prefix . $this -> _dbname . '.' . $type;
            }
        } else {
            $res['msg'] = 'empty dict';
        }
        $this -> _json_return($res);
    }

    /**
     * 列出数据库
     */
    private function listdb() {
        $sql = 'select * from information_schema.schemata';
        $res = $this -> query($sql);
        return $res;
    }

    /**
     * 列出表
     */
    private function listtable($dbname = '') {
        if(empty($dbname) && !empty($this -> _dbname)) { $dbname = $this -> _dbname;}
        $res = array();
        $sql_fmt = "select * from  information_schema.`TABLES` where TABLE_SCHEMA='%s'";
        if(!empty($dbname)) {
            $sql = sprintf($sql_fmt, $dbname);
            $res = $this -> query($sql);
        }
        return $res;
    }

    /**
     * 列出字段
     */
    private function listfield($dbname = '', $table = '') {
        if(empty($dbname) && !empty($this -> _dbname)) { $dbname = $this -> _dbname;}
        $res = array();
        $sql_fmt = "select * from information_schema.`COLUMNS` where TABLE_SCHEMA='%s' AND TABLE_NAME='%s'";
        if(!empty($dbname) && !empty($table)) {
            $sql = sprintf($sql_fmt, $dbname, $table);
            $res = $this -> query($sql);
        }
        return $res;
    }

    public function query($sql = '') {
        $dsn = 'mysql:host=' . $this -> _dbhost . ';dbname=' . $this -> _dbname . ';';
        $opt = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $this -> _dbcharset);
        $res = array();
        if(!empty($sql)) {
            try {
                $pdo = new PDO($dsn, $this -> _dbuser, $this -> _dbpwd, $opt);
                $stmt = $pdo -> query($sql);
                $res = $stmt -> fetchAll(PDO::FETCH_ASSOC);
            } catch(Exception $ex) {

            }
        }
        return $res;
    }

}