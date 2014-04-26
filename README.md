php-session
===========

Simple package for work with sessions


## Installation

This package is available via Composer:

```json
{
    "require": {
        "dmitrymomot/php-session": "1.*"
    }
}
```

## Example of usage

```php
// Set driver for session
// available: native, cookie
// as default - native
Session::$default = 'deriver_name'

// set session
Session::instance()->set('session_name', 'session value');

// get session
Session::instance()->get('session_name', 'default value');

// delete session
Session::instance()->set('session_name', 'session value');
```

## License

The MIT License (MIT). Please see [License File](https://github.com/dmitrymomot/php-session/blob/master/LICENSE) for more information.
