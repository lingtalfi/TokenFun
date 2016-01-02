TokenFun
=================
2016-01-02




Tools for playing with php tokens.



TokenFun can be installed as a [planet](https://github.com/lingtalfi/Observer/blob/master/article/article.planetReference.eng.md).





TokenFinder
-------------------

The TokenFinder reads a token stream (as returned by the [token_get_all](http://php.net/manual/en/function.token-get-all.php) php function)
and parse it linearly, from left to right, finding sets that match the conditions defined by the developer.

It is used to find typical patterns, like a method declaration in a class file for instance.

A few classes have already been implemented, and it's fairly simple to create your own classes.
This package comes with classes to find:

-  array reference
-  class name
-  function
-  method
-  namespace
-  new object
-  use statements
-  variable assignments



TokenFinderTool
------------------

It leverages the power of the TokenFinder to provide one liner methods for common tasks 
like finding all the "use statements" in a given code or folder.



Example:

```php
<?php


use TokenFun\TokenFinder\Tool\TokenFinderTool;

require_once "bigbang.php";



$modulesDir = __DIR__ . "/../planets";



$f = $modulesDir . "/Bat/FileSystemTool.php";
$tokens = token_get_all(file_get_contents($f));
a(TokenFinderTool::getUseDependencies($tokens));



$dir = $modulesDir . "/Bat";
a(TokenFinderTool::getUseDependenciesByFolder($dir));
```



Some methods of the TokenFinderTool include the following.


### getNamespace

```php
false|string    getNamespace ( array:tokens )
```

Return the namespace found in the given tokens, or false if no namespace was found.


### getUseDependencies

```php
array    getUseDependencies ( array:tokens, bool:sort=true )
```

Return the dependencies imported via the php [use statement](http://php.net/manual/en/language.namespaces.importing.php) as an array.


### getUseDependencies

```php
array    getUseDependenciesByFolder ( str:dir )
```

Return the dependencies imported via the php [use statement](http://php.net/manual/en/language.namespaces.importing.php)
found in the php files under the given directory.




Dependencies
------------------

- [lingtalfi/Bat 1.23](https://github.com/lingtalfi/Bat)
- [lingtalfi/DirScanner 1.0.0](https://github.com/lingtalfi/DirScanner)




History Log
------------------
    
- 1.0.0 -- 2016-01-02

    - initial commit
    
    



