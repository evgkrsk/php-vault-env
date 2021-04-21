# Installation
This library can be installed with composer:

```json
{
  "require": {
    "evgkrsk/php-vault-env": "master"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/evgkrsk/php-vault-env"
    }
  ]
}
```

# Example
**PHP:**
```php
include_once __DIR__.'/vendor/autoload.php';

var_dump($_ENV);
var_dump(secEnv('XXX'));
var_dump(secEnv('DB_PASSWORD'));
```
**Output:**
```
array(4) {
  ["VAULT_ADDR"]=>
  string(14) "127.0.0.1:8200"
  ["VAULT_TOKEN"]=>
  string(36) "41c85f60-d0da-1219-af2f-6a3253636606"
  ["XXX"]=>
  string(3) "YYY"
  ["DB_PASSWORD"]=>
  string(22) "VAULT:app1/db/password"
}
string(3) "YYY"
string(9) "xxxqwerty"
```
