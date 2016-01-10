# Interrogate

Interrogate is a lightweight, standalone Object Relational Mapping (ORM) for PHP 5.5+.

## Installing

```sh
composer require molovo/interrogate
```

Copy the included `.env.example` file from `vendor/molovo/interrogate` to your web server's `DOCUMENT_ROOT`, and rename it to `.env`. Then, update the new `.env` file with your database connection information.

## Getting Started
Queries are built using chained methods, which try to follow SQL grammar as much as possible. Queries return a `Collection` object containing multiple `Model` objects.

```php
use Molovo\Interrogate\Database;
use Molovo\Interrogate\Query;

Database::bootstrap();

$query = Query::table('users')
    ->select('username', 'email')
    ->where('name', 'Joe Bloggs');

// @var $users Molovo\Interrogate\Collection
$users = $query->fetch();

// @var $user Molovo\Interrogate\Model
foreach ($users as $user) {
    echo $user->username;
    echo $user->email;
}
```

## Using Joins
Queries with joins can be built by passing another `Query` object to the `join()` method. The models returned by the joined query are stored in a property on the model, using the table name (or alias if defined). The joined query can compare fields on the parent query with dot syntax, using either the table name (or alias) directly, or the keyword `parent` as below.

```php
use Molovo\Interrogate\Query;

$query = Query::table('users_table', 'users')
    ->select('name')
    ->join(Query::table('addresses_table', 'addresses')
        ->select('town')
        ->on('user_id', 'parent.id'));

$users = $query->fetch();

foreach ($users as $user) {
    // @var $addresses Molovo\Interrogate\Collection
    $addresses = $user->addresses;

    foreach ($addresses as $address) {
        echo $address->town;
    }
}
```

## Using Models
Model classes can created for tables to allow for quick query creation, and adding functionality on a per-table basis. The simplest form of a model is shown below:

```php
namespace Models;

use Molovo\Interrogate\Model;

class User extends Model {}
```

By default, the table name is the pluralized snake_cased equivalent of the class name. E.g. the model `UserDetail` refers to a table `user_details`. To use a different table name, define the static property `$tableName`.

```php
class User extends Model {
    protected static $tableName = 'the_users_table';
}
```

Models make use of magic methods to allow static access to methods in the Query class.

```php
$user = User::where('name', 'Joe Bloggs');

// is equivalent to

$user = Query::table('users')->where('name', 'Joe Bloggs');
```

You can also statically call methods on the `Collection` class.

```php
$names = User::toList('name');

// is equivalent to

$collection = Query::table('users')->fetch();
$names = $collection->toList('name');
```
