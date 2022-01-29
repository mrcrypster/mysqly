[Mysqly](https://mysqly.com/) is a full-featured opensource small-overhead PHP data framework for Mysql built for fast and efficient development.

<p align="center">
  <a href="https://mysqly.com/"><img src="/mysqly.png"/></a>
</p>

- [Official website](https://mysqly.com/)
- [Advanced Mysql knowledge base](https://mysqly.com/educate)

# Install
```bash
wget https://mysqly.com/mysqly.php
```

# Usage example
```php
require 'mysqly.php'; # include library (single file)
mysqly::auth('user', 'pwd', 'db', '127.0.0.1'); # connect to Mysql server

// Dynamic methods for table names
$users = mysqly::users(['age' => 25]); # SELECT * FROM users WHERE age = 25
$user = mysqly::users_(6); # SELECT * FROM users WHERE id = 6 LIMIT 1

// And many more features ↓
```

# All features & documentation

<ul class="contents">
  <li>Procedural style implementation</li>
  <li>Single static class in a <a href="https://mysqly.com/#install">single file</a></li>
  <li>PDO based - single dependancy</li>
  <li>Lazy connection to optimize resources usage</li>
  <li><a href="https://mysqly.com/#retrieve">Simplified parametric queries</a> for frequent use cases</li>
  <li><a href="https://mysqly.com/#lists">Lists</a> and <a href="https://mysqly.com/#key_values">key-value pairs</a> retrieval</li>
  <li><a href="https://mysqly.com/#dynamic">Dynamic methods</a> for less code</li>
  <li><a href="https://mysqly.com/#sql">Native SQL</a> support</li>
  <li>Secure <a href="https://mysqly.com/#binding">values binding</a></li>
  <li><a href="https://mysqly.com/#in_binding">"IN" array values</a> binding support</li>
  <li><a href="https://mysqly.com/#json">JSON</a> retrieval and attributes manipulation</li>
  <li>"Server has gone away" automatic reconnection</li>
  <li><a href="https://mysqly.com/#multiple_dbs">Multiple DB/server connections</a></li>
  <li><a href="https://mysqly.com/#auto_creation">Automatic fields/tables creation</a> mode</li>
  <li><a href="https://mysqly.com/#key_value_storage">Key-value storage</a> component</li>
  <li><a href="https://mysqly.com/#job_queue">Job queue </a> component</li>
  <li><a href="https://mysqly.com/#cache_storage">Cache storage</a> with TTL support component</li>
  <li><a href="https://mysqly.com/#export">CSV/TSV</a> export</li>
  <li><a href="https://mysqly.com/#increments">Increment and decrement</a> column values atomically</li>
  <li><a href="https://mysqly.com/#transactions">Transactions</a> support</li>
</ul>

[![Download mysqly](https://img.shields.io/sourceforge/dt/mysqly.svg)](https://sourceforge.net/projects/mysqly/files/latest/download)
