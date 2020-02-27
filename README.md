# Symfony multi-dump service

Current version: 1.1

**Service works with Symfony 4 and 5**

`multi-dump` allows to create grouped dumps and displaying them in dockable overlayed window with quickly access to them. A service can dumps all data just like standard Symfony var dumper but with `multi-dump` you can group and pin this data into logical categories. You can also use this to displaying core parameters of your app and have all of them always by hand in one place against dumping them manualy in different places of code. `multi-dump` also displays dump of the most common using components like `request stack`, authenticated `user`, `session` and more.

`multi-dump` comes with one new global function:

```
mdump($var)
```

![222222](https://user-images.githubusercontent.com/61396542/75476364-c00af280-599a-11ea-8f40-27c81cfed830.png)


## Usage

Usage of `multi-dump` is very similar to standard Symfony `dump`, just use new `mdump` function against standard `dump`:

```
mdump($variable)
```

`multi-dump` window is divided to columns (sections), each column can have different piece of debug data, you can specify column to use by second parameter (column names are fully customable):

```
mdump($var, 'third column')
```

All dumps created with the same column name will be grouped into it. If column name is not specified then second column is used by default. You can create as many columns as you want.

By default, `multi-dump` displays backtrace headers for all dumped data (filename, line, class and method/function) when flushing output in response. You can override this behaviour by specyfing custom title as third parameter:

```
mdump($var, 'third column', 'My variable')
```


## Extending multi-dump window by custom callbacks

`multi-dump` is extendable by custom callbacks. You can append custom code to every column/section in debug window. By your own callbacks you can displaying there any data, not only dumps. To register new block in specified column just add new callback function using `MultiDump::extend` static method:

```
MultiDump::extend($section, $callback, $title)
```
where:

`$section` - (string) name of the section/column where callback will append to

`$callback` (callable) - callback function, it takes one argument with `kernel` instance offered by `Symfony\Component\HttpKernel\KernelInterface`. Thanks to that you have access to `kernel` object inside every callback and you can e.g. get `service container` from here. 

`$title` - (string) a title to display in window

Every callback function must returns string with HTML code to display. You can add as many callbacks as you want.

**Defining example callback (accessing Doctrine for example):**

```
MultiDump::extend('primary', function($kernel) {     
    $doctrine = $kernel->getContainer()->get('doctrine');
    mdump($doctrine, 'secondary'); 

    $name = $doctrine->getName();      
    return $name; 
}, 'Doctrine');
```

**Result:**

![ok_dev](https://user-images.githubusercontent.com/61396542/75478609-8cca6280-599e-11ea-9239-b00f025ab803.png)

As you see above, you can use `mdump` also inside callbacks.


## Installation:

1) copy this 2 files to your Symfony project:

- `src/Service/MultiDump.php` - multi-dump service
- `src/Functions/mdump.php` - mdump global function


`multi-dump` will be automaticaly attached to response via `KernelEvents::RESPONSE` event listening.

2) exclude `src/Functions` directory in `services.yaml`:

config/services.yaml
```
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/*'
        exclude: '../src/{Functions,DependencyInjection,Entity,Migrations,Tests,Kernel.php}'
```


## Deploying included Symfony 5 example

Repository includes example sceleton Symfony 5 project with `multi-dump` included.
You can check it out by deploying package:

```
$ composer install
```

## Default items

As you see `multi-dump` displays some useful items like `user` and `session` by default.
You can change this behaviour by adding or removing default items in `initDefaults()` method inside `MultiDump.php` class file.


## Restricting environments

You can specify for which environments `multi-dump` can be enabled with class constant `ALLOW_FOR_ENVIRONMENTS` in `MultiDump.php` class file.


### Symfony multi-dump is free to use but if you liked then you can donate project via BTC: 

**1LK9tDPBuBFXCKUThFWXNvdcdJ4gzx1Diz**

or by PayPal:
 **[https://www.paypal.me/szczyglinski](https://www.paypal.me/szczyglinski)**


Enjoy!


MIT License | 2020 Marcin 'szczyglis' Szczygliński

https://github.com/szczyglis-dev/symfony-multi-dump

Contact: szczyglis@protonmail.com
