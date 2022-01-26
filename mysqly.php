<?php

# SRC: https://raw.githubusercontent.com/mrcrypster/mysqly/main/mysqly.php

class mysqly {
  private static $db;
  private static $auth = [];
  protected static $auth_file = '/var/lib/mysqly/.auth.php';
  
  
  
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
      self::$db = new PDO('mysql:host=' . self::$auth['host'] . ';dbname=' . self::$auth['db'], self::$auth['user'], self::$auth['pwd']);
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
  
  public static function fetch($sql_or_table, $bind_or_filter = [], $select_what = '*') {
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
    
    $statement = self::exec($sql, $bind);
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
    $sql = 'SELECT * FROM ' . $table . ' ' . $where . ' ORDER BY RAND() LIMIT 1';
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
    
    self::exec("UPDATE {$table} SET `{$column}` = {$column} {$step} WHERE {$where}", $bind);
  }
  
  public static function decrement($column, $table, $filters) {
    return self::increment($column, $table, $filters, -1);
  }
  
  
  
  /* Data insertion */
  
  public static function insert($table, $data, $ignore = false) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO {$table} SET {$values}";
    self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  public static function insert_update($table, $data) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = "INSERT INTO {$table} SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    self::exec($sql, $bind);
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
    
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO {$table}({$cols}) VALUES{$values}";
    self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  

  /* Data update */  

  public static function update($table, $filter, $data) {
    list($where, $bind) = self::filter($filter);
    $values = self::values($data, $bind);
    
    $sql = "UPDATE {$table} SET {$values} {$where}";
    $statement = self::exec($sql, $bind);
    
    return self::$db->lastInsertId();
  }
  
  public static function remove($table, $filter) {
    list($where, $bind) = self::filter($filter);
    self::exec("DELETE FROM {$table} " . $where, $bind);
  }
  
  
  
  /* --- Dynamic methods --- */
  
  public static function __callStatic($name, $args) {
    
    # get row or column from table
    if ( $args[0] && (count($args) == 1) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      list($where, $bind) = self::filter($args[0]);
      $row = mysqly::fetch('SELECT ' . ($col ? "`{$col}`" : '*') . ' FROM ' . $table . ' ' . $where, $bind)[0];
      return $col ? $row[$col] : $row;
    }
    
    # get aggregates by filters
    else if ( $args[0] && (count($args) == 2) && strpos($name, '_') && in_array(explode('_', $name)[0], ['min', 'max', 'avg']) ) {
      list($agr, $col) = explode('_', $name);
      $table = $args[0];
      list($where, $bind) = self::filter($args[1]);
      $row = mysqly::fetch('SELECT ' . $agr . '( ' . $col . ') FROM ' . $table . ' ' . $where, $bind)[0];
      return array_shift($row);
    }
    
    # get list of rows from table
    else if ( count($args) == 0 || count($args) == 1 ) {
      return mysqly::fetch($name, $args[0] ?: []);
    }
    
    
    else {
      throw new PDOException($name . '() method is unknown' );
    }
  }
  
  
  
  # --- Key-value set/get & storage ---
  
  public static function set($key, $value) {
    
  }
  
  public static function get($key) {
    
  }
}
