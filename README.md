[Mysqly](https://mysqly.com/) is a full-featured opensource small-overhead PHP data framework for Mysql built for fast and efficient development.

[![Mysqly logo](/mysqly.png)](https://mysqly.com/)

# Installation
```bash
wget https://mysqly.com/mysqly.php
```

# Usage and overview
```php
require 'mysqly.php'; # include library (single file)
mysqly::auth('user', 'pwd', 'db', '127.0.0.1'); # connect to Mysql server


// Dynamic methods for table names
$users = mysqly::users(['age' => 25]); # SELECT * FROM users WHERE age = 25
$user = mysqly::users_(6); # SELECT * FROM users WHERE id = 6 LIMIT 1


// retrieve key-values and lists
$ages = mysqly::key_vals('SELECT id, age FROM users WHERE age = :age', ['age' => 25]);
# [
#   [1 => 'm'],
#   [2 => 'f'],
# ]


// Ready to use job queue, caching and key/value store
```

# Documentation
- [Official website](https://mysqly.com/)
- [Learn advanced Mysql usage with PHP](https://mysqly.com/educate)





# Better authentication
To make authentication more secure, you can create auth file `/var/lib/mysqly/.auth.php` with auth data:
```php
<?php return [
  'user' => 'user',
  'pwd' => 'pwd',
  'db' => 'db'
];
```

# Fetch data

### Fetch random row based on parametric query
```php
$row = mysqly::random('users', ['age' => 25]);
# SELECT * FROM users WHERE age = 25 ORDER BY RAND() LIMIT 1
```

### Count rows based on parametric query

```php
$total = mysqly::count('users', ['age' => 25]);
# SELECT count(*) FROM users WHERE age = 25
```
or SQL:

```php
$total = mysqly::count('SELECT count(*) from users');
```


# Magic fetch

### Select list of rows from table by parameters
```php
$users = mysqly::users(['age' => 35]);
# SELECT * FROM users WHERE age = 35
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
To update gender column to "x" for all users with age = 45:
```php
mysqly::update('users', ['age' => 45], ['gender' => 'x']);
#               ▲        ▲              ▲
#               table    filter         update
```

# Remove data
To remove all rows with age = 46:
```php
mysqly::remove('users', ['age' => 46]);
```

# Exec SQL queries directly
```php
mysqly::exec('UPDATE stats SET val = val + 1 WHERE key = :key', [':key' => 'events']);
```
