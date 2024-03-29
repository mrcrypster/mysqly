<?php

# https://github.com/mrcrypster/mysqly

class mysqly {
  protected static $db;
  protected static $auth = [];
  protected static $auth_file = '/var/lib/mysqly/.auth.php';
  protected static $auto_create = false;
  
  
  
  /* Internal implementation */
  
  private static function filter($filter) {
    $bind = $query = [];
    
    if ( is_array($filter) ) {
      foreach ( $filter as $k => $v ) {
        static::condition($k, $v, $query, $bind);
      }
    }
    else {
      static::condition('id', $filter, $query, $bind);
    }
    
    return [$query ? (' WHERE ' . implode(' AND ', $query)) : '', $bind];
  }
  
  private static function condition($k, $v, &$where, &$bind) {
    $k = trim($k, ':');
    if ( is_array($v) ) {
      $in = [];
      
      foreach ( $v as $i => $sub_v ) {
        $in[] = ":{$k}_{$i}";
        $bind[":{$k}_{$i}"] = $sub_v;
      }
      
      $in = implode(', ', $in);
      $where[] = "`{$k}` IN ($in)";
    }
    else {
      $where[] = "`{$k}` = :{$k}";
      $bind[":{$k}"] = $v;
    }
  }
  
  private static function values($data, &$bind = []) {
    foreach ( $data as $name => $value ) {
      if ( strpos($name, '.') ) {
        $path = explode('.', $name);
        $place_holder = implode('_', $path);
        $name = array_shift($path);
        $key = implode('.', $path);
        $values[] = "`{$name}` = JSON_SET({$name}, '$.{$key}', :{$place_holder}) ";
        $bind[":{$place_holder}"] = $value;
      }
      else {
        $values[] = "`{$name}` = :{$name}";
        if ( is_array($value) && strpos($name, '_json') ) {
          $value = json_encode($value);
        }

        $bind[":{$name}"] = $value;
      }
    }
    
    return implode(', ', $values);
  }
  
  
  
  /* General SQL query execution */
  
  public static function exec($sql, $bind = []) {
    if ( !static::$db ) {
      if ( !static::$auth ) {
        static::$auth = @include static::$auth_file;
      }
      static::$db = new PDO('mysql:host=' . (isset(static::$auth['host']) ? static::$auth['host'] : '127.0.0.1') . ';port=' . (isset(static::$auth['port']) ? static::$auth['port'] : '3306') . (static::$auth['db'] ? ';dbname=' . static::$auth['db'] : ''), static::$auth['user'], static::$auth['pwd']);
      static::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    $params = [];
    if ( $bind ) foreach ( $bind as $k => $v ) {
      if ( is_array($v) ) {
        $in = [];
        foreach ( $v as $i => $sub_v ) {
          $in[] = ":{$k}_{$i}";
          $params[":{$k}_{$i}"] = $sub_v;
        }
        
        $sql = str_replace(':' . $k, implode(', ', $in), $sql);
      }
      else {
        $params[$k] = $v;
      }
    }
    
    $statement = static::$db->prepare($sql);
    if ( $params ) {
      foreach ( $params as $k => $v ) {
	$type = PDO::PARAM_STR;
	if ( is_int($v) ) $type = PDO::PARAM_INT;
	else if ( is_null($v) ) $type = PDO::PARAM_NULL;
	else if ( is_bool($v) ) $type = PDO::PARAM_BOOL;
	$statement->bindValue($k, $v, $type);
      }
    }
    $statement->execute();
    
    return $statement;
  }

  public static function query($sql, $bind = []) {
    return self::exec($sql, $bind);
  }
  
  
  
  /* Authentication */
  
  public static function auth($user, $pwd, $db, $host = 'localhost') {
    static::$auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  }
  
  public static function now() {
    return static::fetch('SELECT NOW() now')[0]['now'];
  }
  
  
  
  /* Transactions */
  
  public static function transaction($callback) {
    static::exec('START TRANSACTION');
    $result = $callback();
    static::exec( $result ? 'COMMIT' : 'ROLLBACK' );
  }
  
  
  
  /* Data retrieval */
  
  public static function fetch_cursor($sql_or_table, $bind_or_filter = [], $select_what = '*') {
    $bind = [];
    $where = [];

    if ( strpos($sql_or_table, ' ') || (strpos($sql_or_table, 'SELECT ') === 0) ) {
      $sql = $sql_or_table;
      $bind = $bind_or_filter;
    }
    else {
      $sql = "SELECT {$select_what} FROM {$sql_or_table}";
      $order = '';
      
      if ( $bind_or_filter ) {
        if ( is_array($bind_or_filter) ) {
          foreach ( $bind_or_filter as $k => $v ) {
            if ( $k == 'order_by' ) {
              $order = ' ORDER BY ' . $v;
              continue;
            }
            
            static::condition($k, $v, $where, $bind);
          }
          
          if ( $where ) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
          }
        }
        else {
          $sql .= ' WHERE id = :id';
          $bind[":id"] = $bind_or_filter;
        }
      }
      
      $sql .= $order;
    }
    
    return static::exec($sql, $bind);
  }
  
  public static function fetch($sql_or_table, $bind_or_filter = [], $select_what = '*') {
    
    $statement = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ( $rows as $row ) {
      foreach ( $row as $k => $v ) {
        if ( strpos($k, '_json') ) {
          $row[$k] = json_decode($v, 1);
        }
      }
      
      $list[] = $row;
    }
    
    return $list;
  }
  
  public static function array($sql_or_table, $bind_or_filter = []) {
    $rows = static::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  public static function key_vals($sql_or_table, $bind_or_filter = []) {
    $rows = static::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  public static function count($sql_or_table, $bind_or_filter = []) {
    $rows = static::fetch($sql_or_table, $bind_or_filter, 'count(*)');
    $row = array_shift($rows);
    $col = array_shift($row);
    return intval($col);
  }

  public static function row($sql_or_table, $bind_or_filter = []) {
    $rows = static::fetch($sql_or_table, $bind_or_filter);
    $row = $rows ? array_shift($rows) : [];
    return $row;
  }

  public static function one($sql_or_table, $bind_or_filter = []) {
    $rows = static::fetch($sql_or_table, $bind_or_filter);
    $row = $rows ? array_shift($rows) : [];
    $val = $row ? array_shift($row) : null;
    return $val;
  }

  public static function random($table, $filter = []) {
    list($where, $bind) = static::filter($filter);
    $sql = 'SELECT * FROM `' . $table . '` ' . $where . ' ORDER BY RAND() LIMIT 1';
    return static::fetch($sql, $bind)[0];
  }
  
  
  
  /* --- Atomic increments/decrements --- */
  
  public static function increment($column, $table, $filters, $step = 1) {
    $bind = $where = [];
    foreach ( $filters as $k => $v ) {
      static::condition($k, $v, $where, $bind);
    }
    
    $where = implode(' AND ', $where);
    
    $step = intval($step);
    if ( $step > 0 ) {
      $step = "+{$step}";
    }
    
    return static::exec("UPDATE `{$table}` SET `{$column}` = `{$column}` {$step} WHERE {$where}", $bind);
  }
  
  public static function decrement($column, $table, $filters) {
    return static::increment($column, $table, $filters, -1);
  }
  
  
  
  /* --- Toggle column value --- */
  
  public static function toggle($table, $filters, $column, $if, $then) {
    $bind = $where = [];
    foreach ( $filters as $k => $v ) {
      static::condition($k, $v, $where, $bind);
    }
    
    $bind[':if'] = $if;
    $bind[':v'] = $if;
    $bind[':then'] = $then;
    
    $where = implode(' AND ', $where);
    
    return static::exec("UPDATE `{$table}` SET `{$column}` = IF(`{$column}` = :if, :then, :v) WHERE {$where}", $bind);
  }
  
  
  
  /* Data insertion */
  
  public static function insert($table, $data, $ignore = false, $replace = false) {
    $bind = [];
    $values = static::values($data, $bind);
    $sql = ($replace ? 'REPLACE' : 'INSERT') . ' ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}` SET {$values}";
    
    try {
      static::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      static::handle_insert_exception($e, $table, $data, $ignore);
    }
    
    return static::$db->lastInsertId();
  }

  public static function replace($table, $data) {
    return self::insert($table, $data, false, true);
  }
  
  public static function insert_update($table, $data) {
    $bind = [];
    $values = static::values($data, $bind);
    $sql = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    
    try {
      static::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      static::handle_insert_update_exception($e, $table, $data);
    }

    return static::$db->lastInsertId();
  }
  
  public static function multi_insert($table, $rows, $ignore = false, $replace = false) {
    $bind = [];
    
    $cols = array_keys($rows[0]);
    $cols = implode(',', $cols);
    
    foreach ( $rows as $r => $row ) {
      $values[] = '(' . implode(',', array_map(function($c) use($r) { return ":r{$r}_{$c}"; }, range(0, count($row)-1))) . ')';
      
      $c = 0;
      foreach ( $row as $v ) {
        $bind[":r{$r}_{$c}"] = $v;
        $c++;
      }
    }
    
    $values = implode(',', $values);
    
    $sql = ($replace ? 'REPLACE' : 'INSERT') . ' ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}`({$cols}) VALUES{$values}";
    static::exec($sql, $bind);
    return static::$db->lastInsertId();
  }

  public static function multi_replace($table, $rows) {
    return self::multi_insert($table, $rows, false, true);
  }
  
  
  
  /* Data export */
  
  public static function export_csv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*') {
    $cursor = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row);
    }
    
    fclose($f);
  }
  
  public static function export_tsv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*') {
    $cursor = static::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row, "\t");
    }
    
    fclose($f);
  }
  
  

  /* Data update */  

  public static function update($table, $filter, $data) {
    list($where, $bind) = static::filter($filter);
    $values = static::values($data, $bind);
    
    $sql = "UPDATE `{$table}` SET {$values} {$where}";
    
    try {
      $statement = static::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      static::handle_update_exception($e, $table, $filter, $data);
    }
  }
  
  public static function remove($table, $filter) {
    list($where, $bind) = static::filter($filter);
    static::exec("DELETE FROM `{$table}` " . $where, $bind);
  }
  
  
  
  /* --- Dynamic methods --- */
  
  public static function __callStatic($name, $args) {
    
    # get row or column from table
    if ( $args[0] && (count($args) == 1) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      list($where, $bind) = static::filter($args[0]);
      $rows = static::fetch('SELECT ' . ($col ? "`{$col}`" : '*') . ' FROM `' . $table . '` ' . $where, $bind);
      if ( $rows ) {
        return $col ? $rows[0][$col] : $rows[0];
      }
    }
    
    # get aggregates by filters
    else if ( $args[0] && (count($args) == 2) && strpos($name, '_') && in_array(explode('_', $name)[0], ['min', 'max', 'avg']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = static::filter($args[1]);
      $row = static::fetch('SELECT ' . $agr . '( ' . $col . ') FROM `' . $table . '` ' . $where, $bind)[0];
      return array_shift($row);
    }
    
    # get list of rows from table
    else if ( count($args) == 0 || count($args) == 1 ) {
      return static::fetch($name, $args[0] ?: []);
    }
    
    
    else {
      throw new PDOException($name . '() method is unknown' );
    }
  }
  
  
  
  /* Key-value set/get & storage */
  
  protected static function key_value_table($space) {
    return '_kv_' . $space;
  }
  
  public static function get($key, $space = 'default') {
    $table = static::key_value_table($space);
    
    try {
      $rows = static::fetch($table, ['key' => $key], 'value');
      if ( $rows ) {
        return $rows[0]['value'];
      }
      else {
        return;
      }
    }
    catch (PDOException $e) {
      return;
    }
  }
  
  public static function set($key, $value, $space = 'default') {
    $table = static::key_value_table($space);
    
    try {
      static::insert_update($table, ['key' => $key, 'value' => $value]);
    }
    catch (PDOException $e) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE `{$table}`(`key` varchar(128) PRIMARY KEY, `value` TEXT) ENGINE = INNODB");
        static::insert($table, ['key' => $key, 'value' => $value]);
      }
    }
  }
  
  public static function unset($key, $space = 'default') {
    $table = static::key_value_table($space);
    
    try {
      static::remove($table, ['key' => $key]);
    }
    catch (PDOException $e) {}
  }
  
  
  
  /* Cache storage */

  public static function cache($key, $populate = null, $ttl = 60) {
    $key = sha1($key);
    
    try {
      $data = null;
      $rows = static::fetch('_cache', ['key' => $key]);
      if ( $rows ) {
        $data = $rows[0];
      }
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE _cache(`key` varchar(40) PRIMARY KEY, `expire` int unsigned, `value` TEXT) ENGINE = INNODB");
      }
    }
    
    if ( !isset($data) || ($data['expire'] < time()) ) {
      if ( $populate ) {
        $value = $populate();
        
        try {
          static::insert_update('_cache', [
            'key' => $key,
            'expire' => time() + $ttl,
            'value' => json_encode($value)
          ]);
        }
        catch ( PDOException $e ) {}
        
        return $value;
      }
    }
    else {
      return json_decode($data['value'], 1);
    }
  }
  
  public static function uncache($key) {
    $key = sha1($key);
    
    try {
      static::remove('_cache', ['key' => $key]);
    }
    catch ( PDOException $e ) {}
  }
  
  
  
  /* Cache storage */
  
  public static function write($event, $data) {
    try {
      static::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        static::exec("CREATE TABLE _queue(`id` SERIAL PRIMARY KEY, `event` varchar(32), `data` TEXT, KEY event_id(`event`, `id`)) ENGINE = INNODB");
        static::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
      }
    }
  }
  
  public static function read($event) {
    try {
      static::exec('START TRANSACTION');
      
      $rows = static::fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event]);

      if ( $rows ) {
        $row = $rows[0];
        static::remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      static::exec('COMMIT');
      
      return isset($return) ? $return : null;
    }
    catch ( PDOException $e ) {}
  }
  
  public static function on($event, $cb) {
    while ( true ) {
      $data = static::read($event);
      
      if ( $data === null ) {
        usleep(1000);
        continue;
      }
      
      $cb($data);
    }
  }
  
  
  
  /* Auto fields creation mode */
  
  public static function auto_create($flag = true) {
    static::$auto_create = $flag;
  }
  
  protected static function create_table_columns($names) {
    $cols = [];
    foreach ( $names as $k ) {
      $type = 'TEXT';
      
      if ( $k == 'id' ) {
        $type = 'SERIAL PRIMARY KEY';
      }
      
      $cols[] = "`{$k}` {$type}";
    }
    
    return implode(',', $cols);
  }
  
  protected static function handle_insert_exception($exception, $table, $insert, $ignore) {
    if ( !static::$auto_create || strpos($exception->getMessage(), "doesn't exist") === false ) {
      throw $exception;
    }
    
    $create = static::create_table_columns(array_keys($insert));
    static::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    static::insert($table, $insert, $ignore);
  }
  
  protected static function handle_insert_update_exception($exception, $table, $insert) {
    if ( !static::$auto_create ||
         ( (strpos($exception->getMessage(), "doesn't exist") === false) &&
           (strpos($exception->getMessage(), "Unknown column") === false) )
       ) {
      throw $exception;
    }
    
    if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
      $create = static::create_table_columns(array_keys($insert));
      static::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    }
    else {
      preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
      $col = $m[1];
      
      static::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    }
    
    static::insert_update($table, $insert);
  }
  
  protected static function handle_update_exception($exception, $table, $filder, $data) {
    if ( !static::$auto_create || strpos($exception->getMessage(), "Unknown column") === false ) {
      throw $exception;
    }
    
    preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
    $col = $m[1];
    
    static::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    static::update($table, $filder, $data);
  }
}
