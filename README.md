# container

A simple and minimal but yet powerful container

# Installing

Using composer:

```shell
composer require weebel/container
```

# Usage

You must make a container instance using the following command:

```php
$container = \Weebel\Container\Container::getInstance();
```

Suppose that we have a service called App\UserBuilder:

```php
namespace App;

class UserBuilder
{
    public function __construct(public UserRepositoryInterface $userRepository, public string $type) 
    {

    }

}

```

Now setting instances:

```php
$container->set('user_builder', new App\UserBuilder());
$container->set('user_builder', fn()=>new App\UserBuilder());
```

For getting a service from the container by id:

```php
$container->get('user_builder');
$container->get(App\UserBuilder::class);

```

Setting aliases:

```php
$container->alias('user_builder', App\UserBuilder::class);
$container->alias(App\UserBuilderInterface::class, App\UserBuilder::class);

```

* Remember when defining an alias, the result of the both of the keys are exactly the same which means

Defining multiple instances or tags for service

```php
$container->set('user_builder', App\UserBuilder::class);
$container->set('admin_user_builder', App\UserBuilder::class);
```

Setting arguments for an instance or tag

```php
$container->addArgument('admin_user_builder', 'type', 'admin');
$container->addArgument(App\UserBuilder::class, 'type', 'normal');
```

If you want to use a tag as an argument you must use '@' before the name of it. Otherwise, it would be considered as a
string

```php
$container->set('admin_repository', AdminRepository::class);
$container->addArgument(App\UserBuilder::class, 'userRepository', '@admin_repository');
$container->addArgument(App\UserBuilder::class, 'userRepository', fn(\Psr\Container\ContainerInterface $container)=> $container->get(AdminRepository::class));
$container->addArgument(App\UserBuilder::class, 'userRepository', '@'.AdminRepository::class);
```
