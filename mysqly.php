<?php

# https://github.com/mrcrypster/mysqly

class mysqly {
  private static $db;
  private static $auth = [];
  protected static $auth_file = '/var/lib/mysqly/.auth.php';
  protected static $auto_create = false;
  
  
  
  /* Internal implementation */
  
  private static function filter($filter) {
    $bind = $query = [];
    
    if ( is_array($filter) ) {
      foreach ( $filter as $k => $v ) {
        self::condition($k, $v, $query, $bind);
      }
    }
    else {
      self::condition('id', $filter, $query, $bind);
    }
    
    return [$query ? (' WHERE ' . implode(' AND ', $query)) : '', $bind];
  }
  
  private static function condition($k, $v, &$where, &$bind) {
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
        $bind[":{$name}"] = $value;
      }
    }
    
    return implode(', ', $values);
  }
  
  
  
  /* General SQL query execution */
  
  public static function exec($sql, $bind = []) {
    if ( !self::$db ) {
      if ( !self::$auth ) {
        self::$auth = @include self::$auth_file;
      }
      self::$db = new PDO('mysql:host=' . (self::$auth['host'] ?: 'localhost') . ';dbname=' . self::$auth['db'], self::$auth['user'], self::$auth['pwd']);
      self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    
    $statement = self::$db->prepare($sql);
    $statement->execute($params);
    
    return $statement;
  }
  
  
  
  /* Authentication */
  
  public static function auth($user, $pwd, $db, $host = 'localhost') {
    self::$auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  }
  
  public static function now() {
    return self::fetch('SELECT NOW() now')[0]['now'];
  }
  
  
  
  /* Transactions */
  
  public static function transaction($callback) {
    self::exec('START TRANSACTION');
    $result = $callback();
    self::exec( $result ? 'COMMIT' : 'ROLLBACK' );
  }
  
  
  
  /* Data retrieval */
  
  public static function fetch_cursor($sql_or_table, $bind_or_filter = [], $select_what = '*') {
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
            
            self::condition($k, $v, $where, $bind);
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
    
    return self::exec($sql, $bind);
  }
  
  public static function fetch($sql_or_table, $bind_or_filter = [], $select_what = '*') {
    
    $statement = self::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
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
    $rows = self::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  public static function key_vals($sql_or_table, $bind_or_filter = []) {
    $rows = self::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  public static function count($sql_or_table, $bind_or_filter = []) {
    $rows = self::fetch($sql_or_table, $bind_or_filter, 'count(*)');
    return intval(array_shift(array_shift($rows)));
  }
  
  public static function random($table, $filter = []) {
    list($where, $bind) = self::filter($filter);
    $sql = 'SELECT * FROM `' . $table . '` ' . $where . ' ORDER BY RAND() LIMIT 1';
    return self::fetch($sql, $bind)[0];
  }
  
  
  
  /* --- Atomic increments/decrements --- */
  
  public static function increment($column, $table, $filters, $step = 1) {
    $bind = $where = [];
    foreach ( $filters as $k => $v ) {
      self::condition($k, $v, $where, $bind);
    }
    
    $where = implode(' AND ', $where);
    
    $step = intval($step);
    if ( $step > 0 ) {
      $step = "+{$step}";
    }
    
    return self::exec("UPDATE `{$table}` SET `{$column}` = `{$column}` {$step} WHERE {$where}", $bind);
  }
  
  public static function decrement($column, $table, $filters) {
    return self::increment($column, $table, $filters, -1);
  }
  
  
  
  /* --- Toggle column value --- */
  
  public static function toggle($table, $filters, $column, $if, $then) {
    $bind = $where = [];
    foreach ( $filters as $k => $v ) {
      self::condition($k, $v, $where, $bind);
    }
    
    $bind[':if'] = $if;
    $bind[':v'] = $if;
    $bind[':then'] = $then;
    
    $where = implode(' AND ', $where);
    
    return self::exec("UPDATE `{$table}` SET `{$column}` = IF(`{$column}` = :if, :then, :v) WHERE {$where}", $bind);
  }
  
  
  
  /* Data insertion */
  
  public static function insert($table, $data, $ignore = false) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}` SET {$values}";
    
    try {
      self::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      self::handle_insert_exception($e, $table, $data, $ignore);
    }
    
    return self::$db->lastInsertId();
  }
  
  public static function insert_update($table, $data) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    
    try {
      self::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      self::handle_insert_update_exception($e, $table, $data);
    }
  }
  
  public static function multi_insert($table, $rows, $ignore = false) {
    $bind = [];
    
    $cols = array_keys($rows[0]);
    $cols = implode(',', $cols);
    
    foreach ( $rows as $r => $row ) {
      $values[] = '(' . implode(',', array_map(function($c) use($r) { return ":r{$r}{$c}"; }, range(0, count($row)-1))) . ')';
      
      $c = 0;
      foreach ( $row as $v ) {
        $bind[":r{$r}{$c}"] = $v;
        $c++;
      }
    }
    
    $values = implode(',', $values);
    
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO `{$table}`({$cols}) VALUES{$values}";
    self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  
  
  /* Data export */
  
  public static function export_csv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*') {
    $cursor = self::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row);
    }
    
    fclose($f);
  }
  
  public static function export_tsv($file, $sql_or_table, $bind_or_filter = [], $select_what = '*') {
    $cursor = self::fetch_cursor($sql_or_table, $bind_or_filter, $select_what);
    $f = fopen($file, 'w');
    while ( $row = $cursor->fetch() ) {
      fputcsv($f, $row, "\t");
    }
    
    fclose($f);
  }
  
  

  /* Data update */  

  public static function update($table, $filter, $data) {
    list($where, $bind) = self::filter($filter);
    $values = self::values($data, $bind);
    
    $sql = "UPDATE `{$table}` SET {$values} {$where}";
    
    try {
      $statement = self::exec($sql, $bind);
    }
    catch ( PDOException $e ) {
      self::handle_update_exception($e, $table, $filter, $data);
    }
  }
  
  public static function remove($table, $filter) {
    list($where, $bind) = self::filter($filter);
    self::exec("DELETE FROM `{$table}` " . $where, $bind);
  }
  
  
  
  /* --- Dynamic methods --- */
  
  public static function __callStatic($name, $args) {
    
    # get row or column from table
    if ( $args[0] && (count($args) == 1) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      list($where, $bind) = self::filter($args[0]);
      $row = self::fetch('SELECT ' . ($col ? "`{$col}`" : '*') . ' FROM `' . $table . '` ' . $where, $bind)[0];
      return $col ? $row[$col] : $row;
    }
    
    # get aggregates by filters
    else if ( $args[0] && (count($args) == 2) && strpos($name, '_') && in_array(explode('_', $name)[0], ['min', 'max', 'avg']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = self::filter($args[1]);
      $row = self::fetch('SELECT ' . $agr . '( ' . $col . ') FROM `' . $table . '` ' . $where, $bind)[0];
      return array_shift($row);
    }
    
    # get list of rows from table
    else if ( count($args) == 0 || count($args) == 1 ) {
      return self::fetch($name, $args[0] ?: []);
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
    $table = self::key_value_table($space);
    
    try {
      $value = self::fetch($table, ['key' => $key], 'value')[0]['value'];
      return $value;
    }
    catch (PDOException $e) {
      return;
    }
  }
  
  public static function set($key, $value, $space = 'default') {
    $table = self::key_value_table($space);
    
    try {
      self::insert_update($table, ['key' => $key, 'value' => $value]);
    }
    catch (PDOException $e) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        self::exec("CREATE TABLE `{$table}`(`key` varchar(128) PRIMARY KEY, `value` TEXT) ENGINE = INNODB");
        self::insert($table, ['key' => $key, 'value' => $value]);
      }
    }
  }
  
  public static function unset($key, $space = 'default') {
    $table = self::key_value_table($space);
    
    try {
      self::remove($table, ['key' => $key]);
    }
    catch (PDOException $e) {}
  }
  
  
  
  /* Cache storage */

  public static function cache($key, $populate = null, $ttl = 60) {
    $key = sha1($key);
    
    try {
      $data = self::fetch('_cache', ['key' => $key])[0];
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        self::exec("CREATE TABLE _cache(`key` varchar(40) PRIMARY KEY, `expire` int unsigned, `value` TEXT) ENGINE = INNODB");
      }
    }
    
    if ( !$data || ($data['expire'] < time()) ) {
      if ( $populate ) {
        $value = $populate();
        
        try {
          self::insert_update('_cache', [
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
      self::remove('_cache', ['key' => $key]);
    }
    catch ( PDOException $e ) {}
  }
  
  
  
  /* Cache storage */
  
  public static function write($event, $data) {
    try {
      self::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
    }
    catch ( PDOException $e ) {
      if ( strpos($e->getMessage(), "doesn't exist") ) {
        self::exec("CREATE TABLE _queue(`id` SERIAL PRIMARY KEY, `event` varchar(32), `data` TEXT, KEY event_id(`event`, `id`)) ENGINE = INNODB");
        self::insert('_queue', ['event' => $event, 'data' => json_encode($data)]);
      }
    }
  }
  
  public static function read($event) {
    try {
      self::exec('START TRANSACTION');
      
      $row = self::fetch('SELECT * FROM _queue WHERE event = :event ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED', [':event' => $event])[0];
      if ( $row ) {
        self::remove('_queue', ['id' => $row['id']]);
        $return = json_decode($row['data'], 1);
      }
      
      self::exec('COMMIT');
      
      return $return;
    }
    catch ( PDOException $e ) {}
  }
  
  public static function on($event, $cb) {
    while ( true ) {
      $data = self::read($event);
      
      if ( $data === null ) {
        usleep(1000);
        continue;
      }
      
      $cb($data);
    }
  }
  
  
  
  /* Auto fields creation mode */
  
  public static function auto_create($flag = true) {
    self::$auto_create = $flag;
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
    if ( !self::$auto_create || strpos($exception->getMessage(), "doesn't exist") === false ) {
      throw $exception;
    }
    
    $create = self::create_table_columns(array_keys($insert));
    self::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    self::insert($table, $insert, $ignore);
  }
  
  protected static function handle_insert_update_exception($exception, $table, $insert) {
    if ( !self::$auto_create ||
         ( (strpos($exception->getMessage(), "doesn't exist") === false) &&
           (strpos($exception->getMessage(), "Unknown column") === false) )
       ) {
      throw $exception;
    }
    
    if ( strpos($exception->getMessage(), "doesn't exist") !== false ) {
      $create = self::create_table_columns(array_keys($insert));
      self::exec("CREATE TABLE `{$table}` ({$create}) Engine = INNODB");
    }
    else {
      preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
      $col = $m[1];
      
      self::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    }
    
    self::insert_update($table, $insert);
  }
  
  protected static function handle_update_exception($exception, $table, $filder, $data) {
    if ( !self::$auto_create || strpos($exception->getMessage(), "Unknown column") === false ) {
      throw $exception;
    }
    
    preg_match('/Unknown column \'(.+?)\' in/', $exception->getMessage(), $m);
    $col = $m[1];
    
    self::exec("ALTER TABLE `{$table}` ADD `{$col}` TEXT");
    self::update($table, $filder, $data);
  }
}
