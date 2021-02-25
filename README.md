# Service Container
#### A lightweight dependency injection service container for cool kids
## Features

- Autowiring
- Automatic constructor injection
- Support Closures/Callable and Class
- Easy to use

# Installation
### Composer
```sh
composer require jersoncarin/container
```
### Git Clone

```sh
git clone https://github.com/jersoncarin/container.git

# and add manually
```

# How to use

```php

// Create instance of the container
$container = new Bulk\Components\Container\Container;

// Setting to container

// Set a object to a container
// it can be a closure/callable or a class
$container->set('key',User::class);

// Set a class instance object
$user = new User
$container->instance('key',$user);

// Set a object to a container but once only
// it can be a closure/callable or a class
// Note Once a once method is make/get, 
// the same object instance will be returned on subsequent calls into the container
$container->once('key',Request::class);

// Getting/Making from container

// Service Container implements PSR-11 Container Interface
// This will throw an exception if binding is not exist or has error thrown
$instance = $container->get('key');
// Return bool
$isExists = $container->has('key');

// make method accepts a parameters, it's just like get
// but the differents is if binding is not exist to the container
// it will automatically create and register to the container
$instance = $container->make('key',['param1','param2']);

// Calling a class or a closure/callable function
// This method call the callback or method from the class
// it will accept Closure/Callable function and method (PUBLIC)
// If you calling the class/method you can use 'User@show', or array base ['User::class','show'],
// [$this,'show'],[$instance,'show'], and a closure or a string callable function
// and it's accept a parameters
$callback = $container->call([User::class,'show'],'param1','param2');
```

# Examples
```php

// User class
class User
{
    //
}

// Direct Configuration

// If a class has no dependencies or only depends on other concrete classes/interfaces, the container does not need to be instructed on how to resolve that class.
// call() automatically resolve the class if it's exist and inject to your method/function/closure parameters

$container->call(function(User $user) {
    var_dump($user);
});

// Simple bindings
// Note you can bind with interfaces

$container->set('user',User::class);

// or using closure
$container->set('user',function() {
    return new User;
});

// and also it accept an instance object, although you can use instance()
$user = new User;
$container->set('user',$user);

// Using instance (No Di apply);
$container->instance('user',$user);

// Binding once
// Note that this function is like set()
// but have differences you can see above
$container->once('user',User::class);


// Resolving

// Using PSR-11 Compliance Container Interface
$user = $container->get('user');

// Using make method
$user = $container->make('user');
// also you may passed param arguments
$user = $container->make('user','someparam1','someparam2');

// Automatic Injection or Autowiring
// You may typehint a class and it will automatically resolved from the container

class Crush
{
    public function __construct(User $user) {
        //
    }
}

// Make it
$container->make(Crush::Class);

// Calling a callback or method from the class with DI applies
// call() method call the callback or a class method with injected parameters
// you may typehint the class and it will automatically resolved the class

class BestFriend
{
    public function __construct(Crush $crush) {
        //
    }
    
    public function sayHi(User $user) {
        //
    }
}

// Calling sayHi method from the BestFriend class
$container->call([BestFriend::class,'sayHi']);
// Also you may pass a optional parameters
$container->call([BestFriend::class,'sayHi'],'param1','param2');

// Removing bindings from the container
$container->remove('user');
```

# Extending
Service Container provides an [extendable](https://github.com/jersoncarin/extendable) method that you can extend the container
```php
// You may use extend() method 
Container::extend('method_name',function() {
    // You can access container instance using $this
    $this->call(...);
});

// you can call your method like the normal method
// Note you can use $this on static call
// also you can pass a optional parameters

// Using static method
Container::method_name(...$params)

// Using instance method
$container = new Bulk\Components\Container\Container;

$myMethod = $container->method_name(...$params);
```

# Author
  - Jerson Carin

# License
### GPL-3.0
You may view the license file for other informations
