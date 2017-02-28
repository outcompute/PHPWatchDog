# PHPWatchDog
#### (based on [AOP-PHP/AOP][aopphp])
PHPWatchDog allows you to create fine grained access policies for function & method calls, and restrict file accesses. Although you can always set file permissions in your OS, and use disable_functions to disable certain functions throughout your PHP environment, but this library allows you to replicate that behaviour, but control it on a per-scope basis.
For example, you can restrict access to json_encode() to the method encode() in class CustomJSONEncoder by including the following line as early in your code as possible.
```php
        \OutCompute\PHPWatchDog\Main::configure(
                array(
                    'functions' => array(
                        'json_encode()' => array(
                            'default' => 'block',
                            'except' => array(
                                array(
                                    'scope' => 'CustomJSONEncoder->encode()'
                                )
                            )
                        )
                    )
                )
        );
```
The above block will setup PHPWatchDog to throw an Exception every time json_encode is accessed from outside the scope of the method, CustomJSONEncoder->encode().
Similarly, you can also limit read/write access to specific files from specific scopes, like this:
```php
        \OutCompute\PHPWatchDog\Main::configure(
                array(
                    'files' => array(
                        'licenceKeys' => array(
                            'default' => 'block',
                            'except' => array(
                                array(
                                    'scope' => 'Licence->get()'
                                )
                            )
                        )
                    )
                )
        );
```
**Note**: Any function that executes code, or enables executing code in the current context (eg.: eval()) will be monitored by this library, whereas code executed by breaking out of the context of the current execution process (eg.: exec()) will not be monitored by this library.

## What is Aspect Oriented Programming(AOP)?
The above features are achieved using this [AOP library][aopphp] which implements [AOP][aopwiki] in PHP.
It is strongly recommended that you read up about AOP from the links provided, however a simplification is provided here.
AOP enables the programmer to specify a function to be executed when a specific event in execution occurs, and the event can be any function or method call.
The PHPWatchDog library presented here uses AOP and enables the user of the library to specify access policies as a PHP associative array, and based on the array sets up the pointcuts.
In the first example provided above, the library will monitor every json_encode call, and checks to see if the scope and filename from where the call originated matches any 'except' rule, and after consulting every except and default filter, takes an action to either block or allow the execution to proceed.
The AOP library used is a great tool in itself, and has far more features than have been used here. You can read more about its [features here][aopphpwiki].

## How To Use
There are a few recommendations to use this library, as listed below:
- You will need to install the AOP extension for your PHP installation, the steps for which are [provided here][aopphpwiki]. It is recommended not to use the pecl install route. and instead compile it on your own system. The master branch has been tested on RedHat/Debian for PHP versions 5.4, 5.5 & 5.6 and they compile without issues. Further below, this document also lists steps to get the Docker images which come pre-installed and configured with the AOP extension, PHP & PHPUnit.
- Do not assign the result of the configure call to a variable. This will prevent any malicious code to delete it. The Main class is implemented as a singleton, and as such, the properties get set once you call configure() with valid parameters.
- Try to call Main::configure as early in your execution as possible to prevent any code from disabling AOP, trying to block the execution, or install measures to circumvent the effects of this library. The fully qualified class name, and arguments are like so:
```php
    /*
     * @var array $watchlist        An array of the following format.
     *                              array(
     *                                  'functions' => array(
     *                                      'targetFunction()' => array(
     *                                          'default' => 'block', # Mandatory Key, can be either 'allow' or 'block'
     *                                          # If default is block, the below combinations in 'except' will allow targetFunction().
     *                                          # If default is allow, then the below combinations will block targetFunction().
     *                                          'except' => array(
     *                                              array(
     *                                                  'file' => 'AllowedFile.php',
     *                                                  'scope' => 'AllowedClass->AllowedMethod()'
     *                                              ),
     *                                              ... # You can specify more combinations here targeting targetFunction()
     *                                          )
     *                                      )
     *                                  ),
     *                                  'files' => array(
     *                                      'logs.log' => array(
     *                                          'default' => 'allow',
     *                                          'except' => array(
     *                                              array(
     *                                                  'file' => 'upload.php'
     *                                              ),
     *                                              ...
     *                                          )
     *                                      )
     *                                  )
     *                              )
     * @var boolean $haltOnIncident Specifies if the library should call die() after throwing the Exception() anytime a policy violation incident happens
     */
    \OutCompute\PHPWatchDog\Main::configure($watchlist, $haltOnIncident)
```
- Be careful when you create a watchlist of methods, as it is possible to create a access policy which interferes with the very execution of this library as the library uses some PHP functions.

**NOTE**: This is still in a proof-of-concept state, and use in production systems is discouraged. However, you are encouraged to use it, invalidate the concept with proofs, to find bugs or extend it.

### Pre-built Docker images
3 Debian based Docker images with different versions of PHP(5.4, 5.5 & 5.6) alongwith the AOP extension & PHPUnit are also [available here][dockerrepo].
You can use the docker images, for example to run the PHPUnit tests, like so:
```sh
$ git clone https://github.com/outcompute/PHPWatchDog.git
$ docker pull outcompute/php-phpunit-aop
```
**PHP5.6**:
```sh
 $ docker run -itv <path to PHPWatchDog directory>:/app aop-php56 phpunit
```
**PHP5.5**:
```sh
 $ docker run -itv <path to PHPWatchDog directory>:/app aop-php55 phpunit
```
**PHP5.4**:
```sh
 $ docker run -itv <path to PHPWatchDog directory>:/app aop-php54 phpunit
```

### Use Cases
Several attack vectors inject code by uploading malicious files, or executing arbitrary code to overwrite valid code with their own attack vectors. PHPWatchDog can be used to prevent this, and also limit the execution of any code not explicitly permitted.
You can load PHPWatchDog by using the auto_prepend_file configuration in either .htaccess or the VirtualHost block and have your access policy enforced on all scripts as a default.

### TODO
 - Gather feedback & validate the concept

License
----
MIT

   [aopphp]: <https://github.com/AOP-PHP/AOP>
   [aopphpwiki]: <https://github.com/AOP-PHP/AOP/wiki>
   [aopwiki]: <https://en.wikipedia.org/wiki/Aspect-oriented_programming>
   [dockerrepo]: <https://hub.docker.com/r/outcompute/php-phpunit-aop/>
