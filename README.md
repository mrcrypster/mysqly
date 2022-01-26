[Mysqly](https://mysqly.com/) is a full-featured opensource small-overhead PHP data framework for Mysql built for fast and efficient development.

<p align="center">
  <a href="https://mysqly.com/"><img src="/mysqly.png"/></a>
</p>

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
