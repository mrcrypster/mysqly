Micro PHP mysql lib (~ 150 lines of code) with powerful yet simple fetch & update. Features:
- fetch/insert/update/delete based on associative arrays
- native SQL queries
- parametric queries and values binding for security
- PDO-based, no extra dependencies
- near-zero overhead because of static class in a single file

# Installation
Download latest version of library file:
```bash
wget https://raw.githubusercontent.com/mrcrypster/mysqly/main/mysqly.php
```

# Usage
Include:
```php
require 'mysqly.php';
```

Authenticate:
```php
mysqly::auth('user', 'pwd', 'db', 'localhost');
```

Fetch something:
```php
$rows = mysqly::fetch('SELECT NOW()');
print_r( $rows );
```

# Better authentication
To make authentication more secure, you can create auth file `/var/lib/mysqly/.auth.php` with auth data:
```php
<?php return [
  'user' => 'crypster',
  'pwd' => '23049ujasdW_',
  'db' => 'crypto'
];
```

# Fetch data
### Fetch rows by SQL query
```php
$users = mysqly::fetch('SELECT * FROM users');
```

### Parameters binding
```php
$users = mysqly::fetch('SELECT * FROM users WHERE age = :age', [ ':age' => $_GET['age'] ]);
```

### Fetch rows from table with filters
```php
$users = mysqly::fetch('users', [ 'age' => 45 ]);
# The same as "SELECT * FROM users WHERE age = 45"
```

### Simple sorting
```php
$users = mysqly::fetch('users', [ 'age' => 45, 'order_by' => 'id DESC' ]);
# The same as "SELECT * FROM users WHERE age = 45 ORDER BY id DESC"
```

### Fetch rows from table by ID
```php
$user = mysqly::fetch('users', 45)[0]; # ! you'll have to select only first row from results
# The same as "SELECT * FROM users WHERE id = 45"
```

### Fetch single column list (one-dimensional array)
```php
$ids = mysqly::array('SELECT id FROM users');
```

### Fetch key-value pairs (one-dimensional associative array)
```php
$ages = mysqly::key_vals('SELECT id, age FROM users');
# example resulting array: [ 1 => 45, 2 => 46 ... ]
```

# Insert data
### Insert single row as associative array
```php
mysqly::insert('users', ['age' => 46, 'gender' => 'x']);
```

### Insert with ignore (if duplicate primary/unique key)
```php
mysqly::insert('users', ['age' => 46, 'gender' => 'x'], true);
```

### Insert update (update data if duplicate primary/unique key)
```php
mysqly::insert_update('users', ['age' => 46, 'gender' => 'x']);
```

# Update data
### Update data by filters
To update gender column to "x" for all users with age = 45:
```php
mysqly::update('users', ['age' => 45], ['gender' => 'x']);
#               ▲        ▲              ▲
#               table    filter         update
```

# Remove data
### Remove data by filters
To remove all rows with age = 46:
```php
mysqly::remove('users', ['age' => 46]);
```
