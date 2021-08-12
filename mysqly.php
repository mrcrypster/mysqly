<?php

# SRC: https://raw.githubusercontent.com/mrcrypster/mysqly/main/mysqly.php

class mysqly {
  private static $db;
  private static $auth = [];
  
  
  
  # --- Internal implementations
  
  # Execute query
  private static function exec($sql, $bind = []) {
    if ( !self::$db ) {
      if ( !self::$auth ) {
        self::$auth = @include '/var/lib/mysqly/.auth.php';
      }
      self::$db = new PDO('mysql:host=' . self::$auth['host'] . ';dbname=' . self::$auth['db'], self::$auth['user'], self::$auth['pwd']);
    }
    
    $statement = self::$db->prepare($sql);
    $statement->execute($bind);
    
    if ( (int)$statement->errorInfo()[0] ) {
      throw new Exception($statement->errorInfo()[2]);
    }
    
    return $statement;
  }
  
  # Transform parametric filter into SQL clauses and binds
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
  
  # Transform single condition and add to where/bind clauses arrays
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
  
  # Transform data to values clause
  private static function values($data, &$bind = []) {
    foreach ( $data as $name => $value ) {
      $values[] = "`{$name}` = :{$name}";
      $bind[":{$name}"] = $value;
    }
    
    return implode(', ', $values);
  }
  
  
  
  # --- Public interfaces
  
  # Connect to server
  public static function auth($user, $pwd, $db, $host = 'localhost') {
    self::$auth = ['user' => $user, 'pwd' => $pwd, 'db' => $db, 'host' => $host];
  }
  
  
  # Fetch array of rows based on SQL or parametric query
  # ::fetch('table', ['age' => 27]);
  # ::fetch('table', $id);
  # ::fetch('SELECT id, name FROM table WHERE age = :age', [':age' => 27])
  public static function fetch($sql_or_table, $bind_or_filter = []) {
    if ( strpos($sql_or_table, ' ') || (strpos($sql_or_table, 'SELECT ') === 0) ) {
      $sql = $sql_or_table;
      $bind = $bind_or_filter;
    }
    else {
      $sql = "SELECT * FROM {$sql_or_table}";
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
    foreach ( $rows as $row ) $list[] = $row;
    
    return $list;
  }
  
  # Fetch array of values (single column list, 1st column values)
  # ::array('table', ['age' => 27]);
  # ::array('SELECT name FROM table WHERE age = :age', [':age' => 27])
  public static function array($sql_or_table, $bind_or_filter = []) {
    $rows = self::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[] = array_shift($row);
    return $list;
  }
  
  # Fetch 2-column rows and trasform that to associaltive array: [1st column => 2nd column]
  # ::key_vals('table', ['col' => 'val'])
  # ::key_vals('SELECT id, name FROM table WHERE age = :age', [':age' => 27])
  public static function key_vals($sql_or_table, $bind_or_filter = []) {
    $rows = self::fetch($sql_or_table, $bind_or_filter);
    foreach ( $rows as $row ) $list[array_shift($row)] = array_shift($row);
    return $list;
  }
  
  # Insert new data & return last insert ID (if any auto_increment column)
  # ::insert('table', ['col' => 'val']);
  public static function insert($table, $data, $ignore = false) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = 'INSERT ' . ($ignore ? ' IGNORE ' : '') . "INTO {$table} SET {$values}";
    self::exec($sql, $bind);
    return self::$db->lastInsertId();
  }
  
  # Insert/update data
  # ::insert_update('table', ['col' => 'val']);
  public static function insert_update($table, $data) {
    $bind = [];
    $values = self::values($data, $bind);
    $sql = "INSERT INTO {$table} SET {$values} ON DUPLICATE KEY UPDATE {$values}";
    self::exec($sql, $bind);
  }
  
  # Update data
  # ::update('table', $id, ['col' => 'val']);
  # ::update('table', ['age' => 27], ['col' => 'val']);
  public static function update($table, $filter, $data) {
    list($where, $bind) = self::filter($filter);
    $values = self::values($data, $bind);
    
    $sql = "UPDATE {$table} SET {$values} {$where}";
    $statement = self::exec($sql, $bind);
    
    return self::$db->lastInsertId();
  }
  
  # Remove data
  # ::remove('table', $id)
  # ::remove('table', ['age' => 25])
  public static function remove($table, $filter) {
    list($where, $bind) = self::filter($filter);
    self::exec("DELETE FROM {$table} " . $where, $bind);
  }
  
  
  
  # --- Magick methods ---
  
  public static function __callStatic($name, $args) {
    
    # Get single or all columns from table by filter
    # ::table_column($id) - for a single column
    # ::table_($id) - for all columns
    if ( is_numeric($args[0]) && strpos($name, '_') ) {
      list($table, $col) = explode('_', $name);
      return mysqly::fetch('SELECT ' . ($col ?: '*') . ' FROM ' . $table . ' WHERE id = :id', [':id' => $args[0]])[0][$col];
    }
    else {
      throw new PDOException($name . '() method is unknown' );
    }
  }
}
