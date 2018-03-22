
## Psecio\Uri

A common attack method that pentesters and actual attackers will use is to capture a URL with "id" values in it (like `/user/1234/view` where `1234` is an ID) and manually change this value to try to bypass authorization checks. While an application should always have some kind of auth check when the URL is called, there's another step that can help to prevent URL changes: a signature value.

This signature value is built using the contents of the current URL along with a "secret" value unique to the application. This signature is then appended to the URL and can be used directly in links. When the URL is used and the request is received, the signature is then checked against the current URL values. If there's no match, the check fails.

### Signing URLs

```php
<?php
require_once 'vendor/autoload.php';

use \Psecio\Uri\Builder;

// Secret is loaded from a configuration outside of the library
$secret = $_ENV['link_secret'];
$uri = new \Psecio\Uri\Builder($secret);

$data = [
    'foo' => 'this is a test'
];
$url = $uri->create('http://test.com', $data);
// http://test.com?foo=this+is+a+test&signature=90b7ac10b261213f71faaf8ce4008fdbdd037bab7192041de8d54d93a158467f
?>
```

In this example we've created a new `Builder` instance, loaded with the secret value, and are using it to create the URL based on the data and URL provided. The `$url` result has the `signature` value appended to the URL. This value can then be used directly.

You can also add a signature to a currently existing URL that already has URL parameters using the same `create` method:

```php
<?php
// Sign the URL: http://foo.com/user?test=1
$url = $uri->create('http://foo.com/user?test=1');
?>
```

### Verifying URLs

The other half of the equation is the verification of a URL. The library provides the `validate` method to help with that:

```php
<?php
$url = 'http://test.com?foo=this+is+a+test&signature=90b7ac10b261213f71faaf8ce4008fdbdd037bab7192041de8d54d93a158467f';

$valid = $uri->validate($url);
echo 'Is it valid? '.var_export($valid, true)."\n"; // boolean response

?>
```

### Expiring URLs

The library also provides the ability to create URLs that will fail validation because they've expired. To make use of this, simply pass in a third value for the `create` method call. This value should either be the number of seconds or a relative string (parsable by PHP's [strtotime](https://php.net/strtotime)) of the amount of time to add:

```php
<?php
$data = [
    'foo' => 'this is a test'
];
$expire = '+10 seconds';
$url = $uri->create('http://test.com', $data, $expire);
// http://test.com?foo=this+is+a+test&expires=1521661473&signature=009e2d70add85d79e19979434e3750e682d40a3d1403ee92458fe30aece2c826
?>
```

You'll notice the addition of a new URL parameter, the `expires` value. This value is automatically read when the `validate` call is made to ensure the URL hasn't timed out. If it has, even if the rest of the data is correct, the result will be `false`.

Even if the attacker tries to update the `expires` date to try to extend the length of the hash, the validation will fail as that's not the `expires` value it was originally hashed with.