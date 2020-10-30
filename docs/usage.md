# 深入讲解 Laravel 的 IoC 服务容器

> 众所周知，[Laravel](https://laravel.com/) 控制反转 (IoC) / 依赖注入 (DI) 的功能非常强大。遗憾的是， [官方文档](https://laravel.com/docs/5.4/container) 并没有详细讲解它的所有功能，所以我决定自己实践一下，并整理成文。下面的代码是基于 [Laravel 5.4.26](https://github.com/laravel/framework/tree/5.4/src/Illuminate/Container) 的，其他版本可能会有所不同。

## 相关链接

* [原文地址](https://gist.github.com/davejamesmiller/bd857d9b0ac895df7604dd2e63b23afe)
* [译文地址](https://learnku.com/laravel/t/27434)
* [Laravel 服务容器解析](https://learnku.com/docs/laravel/5.4/container/1222)

## 了解依赖注入

我在这里不会详细讲解依赖注入/控制反转的原则 - 如果你对此还不是很了解，建议阅读 Fabien Potencier （[Symfony](http://symfony.com/) 框架的创始人）的  [What is Dependency Injection?](http://fabien.potencier.org/what-is-dependency-injection.html) 。

## 访问容器

通过 Laravel 访问 Container 实例的方式有很多种，最简单的就是调用辅助函数  `app()`：

``` php
$container = app();
```

为了突出重点 Container 类，这里就不赘述其他方式了。

**注意：**  [官方文档](https://laravel.com/docs/5.4/container)中使用的是  `$this->app`  而不是 `$container`。

(* 在 Laravel 应用中，[Application](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Foundation/Application.php) 实际上是 Container 的一个子类
( 这也说明了辅助函数 `app()` 的由来 )，不过在这篇文章中我还是将重点讲解 [Container](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Container/Container.php) 类的方法。)

### 在 Laravel 之外使用 Illuminate\Container

想要不基于 Laravel 使用 Container，[安装](https://packagist.org/packages/illuminate/container) 然后：

``` php
use Illuminate\Container\Container;

$container = Container::getInstance();
```

## 基础用法

最简单的用法是通过构造函数注入依赖类。

``` php
class MyClass
{
    private $dependency;

    public function __construct(AnotherClass $dependency)
    {
        $this->dependency = $dependency;
    }
}
```

使用 Container 的 `make()` 方法实例化 `MyClass` 类：

``` php
$instance = $container->make(MyClass::class);
```

container 会自动实例化依赖类，所以上面代码实现的功能就相当于：

``` php
$instance = new MyClass(new AnotherClass());
```

（ 假设  `AnotherClass` 还有需要依赖的类 - 在这种情况下，Container 会递归式地实例化所有的依赖。）

### 实战

下面是一些基于 [PHP-DI 文档](http://php-di.org/doc/getting-started.html) 的例子 - 将发送邮件与用户注册的代码解耦：

``` php
class Mailer
{
    public function mail($recipient, $content)
    {
        // 发送邮件
        // ...
    }
}
```

``` php
class UserManager
{
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function register($email, $password)
    {
        // 创建用户账号
        // ...

        // 给用户发送问候邮件
        $this->mailer->mail($email, 'Hello and welcome!');
    }
}
```

``` php
use Illuminate\Container\Container;

$container = Container::getInstance();

$userManager = $container->make(UserManager::class);
$userManager->register('dave@davejamesmiller.com', 'MySuperSecurePassword!');
```

## 绑定接口与具体实现

通过 Container 类，我们可以轻松实现从接口到具体类到实例的过程。首先定义接口：

``` php
interface MyInterface { /* ... */ }
interface AnotherInterface { /* ... */ }
```

声明实现接口的具体类，具体类还可以依赖其他接口（ 或者是像上个例子中的具体类 ）：

``` php
class MyClass implements MyInterface
{
    private $dependency;

    public function __construct(AnotherInterface $dependency)
    {
        $this->dependency = $dependency;
    }
}
```

然后使用  `bind()`  方法把接口与具体类进行绑定：

``` php
$container->bind(MyInterface::class, MyClass::class);
$container->bind(AnotherInterface::class, AnotherClass::class);
```

最后，在 `make()` 方法中，使用接口作为参数：

``` php
$instance = $container->make(MyInterface::class);
```

**注意：** 如果没有将接口与具体类进行绑定操作，就会报错：

```plain
Fatal error: Uncaught ReflectionException: Class MyInterface does not exist

```

这是因为 container 会尝试实例化接口 ( `new MyInterface`)，这本身在语法上就是错误的。

### 实战

可更换的缓存层：

``` php
interface Cache
{
    public function get($key);
    public function put($key, $value);
}
```

``` php
class RedisCache implements Cache
{
    public function get($key) { /* ... */ }
    public function put($key, $value) { /* ... */ }
}
```

``` php
class Worker
{
    private $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function result()
    {
        // 应用缓存
        $result = $this->cache->get('worker');

        if ($result === null) {
            $result = do_something_slow();

            $this->cache->put('worker', $result);
        }

        return $result;
    }
}
```

``` php
use Illuminate\Container\Container;

$container = Container::getInstance();
$container->bind(Cache::class, RedisCache::class);

$result = $container->make(Worker::class)->result();
```

## 绑定抽象类与具体类

也可以与抽象类进行绑定：

``` php
$container->bind(MyAbstract::class, MyConcreteClass::class);
```

或者将具体类与其子类进行绑定：

``` php
$container->bind(MySQLDatabase::class, CustomMySQLDatabase::class);
```

## 自定义绑定

在使用 `bind()` 方法进行绑定操作时，如果某个类需要额外的配置，还通过闭包函数来实现：

``` php
$container->bind(Database::class, function (Container $container) {
    return new MySQLDatabase(MYSQL_HOST, MYSQL_PORT, MYSQL_USER, MYSQL_PASS);
});
```

每次带着配置信息创建一个 MySQLDatabase 类的实例的时候（ 下面后讲到如何通过  *Singletons*  创建一个可以共享的实例），都要用到 Database 接口。我们看到闭包函数接收了 Container 的实例作为参数，如果需要的话，还可以用它来实例化其他类：

``` php
$container->bind(Logger::class, function (Container $container) {
    $filesystem = $container->make(Filesystem::class);

    return new FileLogger($filesystem, 'logs/error.log');
});
```

还可以通过闭包函数自定义要如何实例化某个类：

``` php
$container->bind(GitHub\Client::class, function (Container $container) {
    $client = new GitHub\Client;
    $client->setEnterpriseUrl(GITHUB_HOST);
    return $client;
});
```

### 解析回调函数

可以使用 `resolving()` 方法来注册一个回调函数，当绑定被解析的时候，就调用这个回调函数：

``` php
$container->resolving(GitHub\Client::class, function ($client, Container $container) {
    $client->setEnterpriseUrl(GITHUB_HOST);
});
```

所有的注册的回调函数都会被调用。这种方法也适用于接口和抽象类：

``` php
$container->resolving(Logger::class, function (Logger $logger) {
    $logger->setLevel('debug');
});

$container->resolving(FileLogger::class, function (FileLogger $logger) {
    $logger->setFilename('logs/debug.log');
});

$container->bind(Logger::class, FileLogger::class);

$logger = $container->make(Logger::class);
```

还可以注册一个任何类被解析时都会被调用的回调函数 - 但是我想这可能仅适用于登录和调试：

``` php
$container->resolving(function ($object, Container $container) {
    // ...
});
```

### 扩展类

你还可以使用 `extend()` 方法把一个类与另一个类的实例进行绑定：

``` php
$container->extend(APIClient::class, function ($client, Container $container) {
    return new APIClientDecorator($client);
});
```

这里返回的另外一个类应该也实现了同样的接口，否则会报错。

## 单例绑定

只要使用 `bind()` 方法进行绑定，每次用的时候，就会创建一个新的实例（ 闭包函数就会被调用一次）。为了共用一个实例，可以使用 `singleton()` 方法来代替 `bind()` 方法：

``` php
$container->singleton(Cache::class, RedisCache::class);
```

或者是闭包：

``` php
$container->singleton(Database::class, function (Container $container) {
    return new MySQLDatabase('localhost', 'testdb', 'user', 'pass');
});
```

为一个具体类创建单例，就只传这个类作为唯一的参数：

``` php
$container->singleton(MySQLDatabase::class);
```

在以上的每种情况下，单例对象都是一次创建，反复使用。如果想要复用的实例已经生成了，则可以使用 `instance()`  方法。例如，Laravel 就是用这种方式来确保 Container 的实例有且仅有一个的：

``` php
$container->instance(Container::class, $container);
```

## 自定义绑定的名称

其实，你可以使用任意字符串作为绑定的名称，而不一定非要用类名或者接口名 - 但是这样做的弊端就是不能使用类名实例化了，而只能使用 `make()` 方法：

``` php
$container->bind('database', MySQLDatabase::class);

$db = $container->make('database');
```

为了同时支持类和接口，并且简化类名的写法，可以使用 `alias()` 方法:

``` php
$container->singleton(Cache::class, RedisCache::class);
$container->alias(Cache::class, 'cache');

$cache1 = $container->make(Cache::class);
$cache2 = $container->make('cache');

assert($cache1 === $cache2);
```

## 存储值

你也可以使用 container 来存储任何值 - 比如：配置数据：

``` php
$container->instance('database.name', 'testdb');

$db_name = $container->make('database.name');
```

支持以数组的形式存储：

``` php
$container['database.name'] = 'testdb';

$db_name = $container['database.name'];
```

在通过闭包进行绑定的时候，这种存储方式就显示出其好用之处了：

``` php
$container->singleton('database', function (Container $container) {
    return new MySQLDatabase(
        $container['database.host'],
        $container['database.name'],
        $container['database.user'],
        $container['database.pass']
    );
});
```

（ Laravel 框架没有用 container 来存储配置文件，而是用了单独的 [Config](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Config/Repository.php) 类 - 但是 [PHP-DI](http://php-di.org/doc/php-definitions.html#values) 用了）

**小贴士：** 在实例化对象的时候，还可以用数组的形式来代替 `make()` 方法：

``` php
$db = $container['database'];
```

## 通过方法 / 函数做依赖注入

到目前为止，我们已经看了很多通过构造函数进行依赖注入的例子，其实，Laravel 还支持对任何方法做依赖注入：

``` php
function do_something(Cache $cache) { /* ... */ }

$result = $container->call('do_something');
```

除了依赖类，还可以传其他参数：

``` php
function show_product(Cache $cache, $id, $tab = 'details') { /* ... */ }

// show_product($cache, 1)
$container->call('show_product', [1]);
$container->call('show_product', ['id' => 1]);

// show_product($cache, 1, 'spec')
$container->call('show_product', [1, 'spec']);
$container->call('show_product', ['id' => 1, 'tab' => 'spec']);
```

可用于任何可调用的方法：

#### 闭包

``` php
$closure = function (Cache $cache) { /* ... */ };

$container->call($closure);
```

#### 静态方法

``` php
class SomeClass
{
    public static function staticMethod(Cache $cache) { /* ... */ }
}

```

``` php
$container->call(['SomeClass', 'staticMethod']);
// 或者：
$container->call('SomeClass::staticMethod');
```

#### 普通方法

``` php
class PostController
{
    public function index(Cache $cache) { /* ... */ }
    public function show(Cache $cache, $id) { /* ... */ }
}
```

``` php
$controller = $container->make(PostController::class);

$container->call([$controller, 'index']);
$container->call([$controller, 'show'], ['id' => 1]);
```

### 调用实例方法的快捷方式

通过这种语法结构  `ClassName@methodName`，就 可以达到实例化一个类并调用其方法的目：

``` php
$container->call('PostController@index');
$container->call('PostController@show', ['id' => 4]);
```

容器用于实例化类，这意味着：

1. 依赖项被注入构造函数（以及方法）。
2. 如果希望重用这个类，则可以将该类定义为单例类。
3. 你可以使用接口或任意名称，而不是具体的类。

例如，这将会启作用：

``` php
class PostController
{
    public function __construct(Request $request) { /* ... */ }
    public function index(Cache $cache) { /* ... */ }
}
```

``` php
$container->singleton('post', PostController::class);
$container->call('post@index');
```

最后，你可以将「默认方法」作为第三个参数。如果第一个参数是一个没有指定方法的类名，则将调用默认的方法。 Laravel 使用 [事件处理](https://laravel.com/docs/5.4/events#registering-events-and-listeners) 来实现：

``` php
$container->call(MyEventHandler::class, $parameters, 'handle');

// Equivalent to:
$container->call('MyEventHandler@handle', $parameters);
```

### 方法调用绑定

可以使用 `bindMethod()` 方法重写方法调用，例如传递其他参数：

``` php
$container->bindMethod('PostController@index', function ($controller, $container) {
    $posts = get_posts(...);

    return $controller->index($posts);
});
```

所有这些都会奏效，调用闭包而不是的原始方法：

``` php
$container->call('PostController@index');
$container->call('PostController', [], 'index');
$container->call([new PostController, 'index']);
```

但是， `call()` 的任何附加参数都不会传递到闭包中，因此不能使用它们。

``` php
$container->call('PostController@index', ['Not used :-(']);
```

***注意：** 这个方法不属于 [容器接口](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Contracts/Container/Container.php), 只是具体的 [容器类](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Container/Container.php). 参考 [提交的 PR](https://github.com/laravel/framework/pull/16800) 了解为什么忽略参数。*

## 上下文绑定

有时候，你希望在不同的地方使用接口的不同实现。下面是来自 [Laravel 文档](https://laravel.com/docs/5.4/container#contextual-binding) 中的一个例子：

``` php
$container
    ->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(LocalFilesystem::class);

$container
    ->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give(S3Filesystem::class);
```

现在， PhotoController 和 VideoController 都可以依赖于文件系统接口，但是每个都将接收不同的实现。你还可以为 `give()` 使用闭包，就像使用 `bind()` 一样：

``` php
$container
    ->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give(function () {
        return Storage::disk('s3');
    });
```

或者命名依赖项：

``` php
$container->instance('s3', $s3Filesystem);

$container
    ->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give('s3');
```

### 将参数绑定基本类型

你还可以通过将变量名称传递给 `needs()`（而不是接口）并将值传递给 `give()` 来绑定基本类型（字符串，整数等）：

``` php
$container
    ->when(MySQLDatabase::class)
    ->needs('$username')
    ->give(DB_USER);
```

您可以使用闭包来延迟检索值，直到需要它：

``` php
$container
    ->when(MySQLDatabase::class)
    ->needs('$username')
    ->give(function () {
        return config('database.user');
    });
```

在这里你不能传递一个类或一个命名的依赖项（例如 `give('database.user')`）因为它将作为文字值返回 - 为此你必须使用一个闭包：

``` php
$container
    ->when(MySQLDatabase::class)
    ->needs('$username')
    ->give(function (Container $container) {
        return $container['database.user'];
    });
```

## 标记

你可以使用容器 `tag` 来绑定相关标记：

``` php
$container->tag(MyPlugin::class, 'plugin');
$container->tag(AnotherPlugin::class, 'plugin');
```

然后将所有标记的实例检索为数组：

``` php
foreach ($container->tagged('plugin') as $plugin) {
    $plugin->init();
}
```

`tag()` 的参数都接受数组：

``` php
$container->tag([MyPlugin::class, AnotherPlugin::class], 'plugin');
$container->tag(MyPlugin::class, ['plugin', 'plugin.admin']);
```

## 重新绑定

***Note:** 这是一个更高级的，只是很少需要-请随意跳过它！*

在绑定或实例已经被使用后需要更改时，可以调用 `rebinding()` 回调 - 例如，此  `Session` 类在被 `Auth` 类使用后被替换，因此需要通知 `Auth` 类变化：

``` php
$container->singleton(Auth::class, function (Container $container) {
    $auth = new Auth;
    $auth->setSession($container->make(Session::class));

    $container->rebinding(Session::class, function ($container, $session) use ($auth) {
        $auth->setSession($session);
    });

    return $auth;
});

$container->instance(Session::class, new Session(['username' => 'dave']));
$auth = $container->make(Auth::class);
echo $auth->username(); // dave
$container->instance(Session::class, new Session(['username' => 'danny']));

echo $auth->username(); // danny
```

(有关重新绑定的更多信息, 看 [这里](https://stackoverflow.com/questions/38974593/laravels-ioc-container-rebinding-abstract-types) 和 [这里](https://code.tutsplus.com/tutorials/digging-in-to-laravels-ioc-container--cms-22167).)

### refresh()

还有一个快捷方法 `refresh()` 来处理这个常见模式：

``` php
$container->singleton(Auth::class, function (Container $container) {
    $auth = new Auth;
    $auth->setSession($container->make(Session::class));

    $container->refresh(Session::class, $auth, 'setSession');

    return $auth;
});
```

它还返回现有实例或绑定（如果有的话），因此您可以这样做：

``` php
// This only works if you call singleton() or bind() on the class
$container->singleton(Session::class);

$container->singleton(Auth::class, function (Container $container) {
    $auth = new Auth;
    $auth->setSession($container->refresh(Session::class, $auth, 'setSession'));
    return $auth;
});
```

(就个人而言，我发现这种语法更加混乱，并且更喜欢上面更详细的版本!)

***Note:** 这些方法不属于 [Container interface](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Contracts/Container/Container.php), 只有具体 [Container class](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Container/Container.php).*

## 覆盖构造函数参数

`makeWith()` 方法允许你将其他参数传递给构造函数。 它忽略任何现有的实例或单例，并且在创建具有不同参数的类的多个实例时仍然有用，同时仍然注入依赖项：

``` php
class Post
{
    public function __construct(Database $db, int $id) { /* ... */ }
}

```

``` php
$post1 = $container->makeWith(Post::class, ['id' => 1]);
$post2 = $container->makeWith(Post::class, ['id' => 2]);
```

***Note:** 在 Laravel 5.3 及以下版本中，它很简单 `make($class, $parameters)`. 它是在 [Laravel 5.4 被移除](https://github.com/laravel/internals/issues/391), 但后来 [重新添加为 makeWith()](https://github.com/laravel/framework/pull/18271) 在 5.4.16. 在 Laravel 5.5 中，它似乎将[恢复为 Laravel 5.3 语法](https://github.com/laravel/framework/pull/19201).*

## 其他方法

这涵盖了我认为有用的所有方法 - 但只是为了解决问题，这里是剩下的公共方法的摘要……

### bound()

如果类或名称已与 `bind()`, `singleton()`, `instance()` or `alias()` 绑定，则 `bound()` 返回 true。

``` php
if (! $container->bound('database.user')) {
    // ...
}
```

还可以使用数组访问语法和 `isset()`:

``` php
if (! isset($container['database.user'])) {
    // ...
}
```

它可以用 `unset()` 重置，它删除指定的绑定/实例/别名。

``` php
unset($container['database.user']);
var_dump($container->bound('database.user')); // false
```

### bindIf()

`bindIf()` 与 `bind()` 做同样的事情，除了它只注册一个绑定（如果还没有）（参考上面的 `bound()`）。 它可能用于在包中注册默认绑定，同时允许用户覆盖它。

``` php
$container->bindIf(Loader::class, FallbackLoader::class);
```

没有 `singletonIf()` 方法，但你可以使用 `bindIf($abstract, $concrete, true)` 代替：

``` php
$container->bindIf(Loader::class, FallbackLoader::class, true);
```

或者这样写全也可以:

``` php
if (! $container->bound(Loader::class)) {
    $container->singleton(Loader::class, FallbackLoader::class);
}
```

### resolved()

如果已经解析了类 `resolved()` 则返回 true。

``` php
var_dump($container->resolved(Database::class)); // false
$container->make(Database::class);
var_dump($container->resolved(Database::class)); // true
```

我不确定它有什么用处，如果使用 `unset()` 它会被重置 (可以看上面的 `bound()`)。

``` php
unset($container[Database::class]);
var_dump($container->resolved(Database::class)); // false
```

### factory()

`factory()` 方法返回一个不带参数的闭包，并调用 `make()`。

``` php
$dbFactory = $container->factory(Database::class);

$db = $dbFactory();
```

我不确定它有什么用处...

### wrap()

 `wrap()` 方法包装一个闭包，以便在执行时注入它的依赖项。 wrap 方法接受一组参数, 返回的闭包没有参数：

``` php
$cacheGetter = function (Cache $cache, $key) {
    return $cache->get($key);
};

$usernameGetter = $container->wrap($cacheGetter, ['username']);

$username = $usernameGetter();
```

我不确定它有什么用处，因为闭包没有参数...

***Note:** 这种方法不属于 [Container interface](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Contracts/Container/Container.php), 只属于 [Container class](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Container/Container.php).*

### afterResolving()

 `afterResolving()` 方法与 `resolving()` 完全相同，只是在「解析」回调之后调用 「解析后」 回调。 我不确定什么时候会有用...

### 最后

* `isShared()` - 确定给定类型是否为共享单例/实例
* `isAlias()` - 确定给定字符串是否是已注册的别名
* `hasMethodBinding()` - 确定容器是否具有给定的方法绑定
* `getBindings()` - 检索所有已注册绑定的原始数组
* `getAlias($abstract)` - 解析基础类/绑定名称的别名
* `forgetInstance($abstract)` - 清除单个实例对象
* `forgetInstances()` - 清除所有实例对象
* `flush()` - 清除所有绑定和实例，有效地重置容器
* `setInstance()` - 替换 `getInstance()` 使用的实例(Tip:使用 `setInstance(null)` 清除它，所以下次它将生成一个新实例)

***Note:** 最后一节中没有一个方法是其中的一部分 [Container interface](https://github.com/laravel/framework/blob/5.4/src/Illuminate/Contracts/Container/Container.php).*
