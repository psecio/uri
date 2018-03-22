<?php

namespace Psecio\Uri;

use \Psecio\Uri\Builder;

class BuilderTest extends \PHPUnit\Framework\TestCase
{
    public function testSetSecretOnConstruct()
    {
        $secret = 'testing123';

        $b = new Builder($secret);
        $this->assertEquals($secret, $b->getSecret());
    }

    public function testChangeSecretSet()
    {
        $secret = 'testing123';

        $b = new Builder($secret);
        $b->setSecret('foobarbaz');

        $this->assertNotEquals($secret, $b->getSecret());
        $this->assertEquals('foobarbaz', $b->getSecret());
    }

    public function testBuildDataFromUrl()
    {
        $url = 'http://foobar.com?test=this&and=that';

        $b = new Builder('test');
        $data = $b->buildFromUrlString($url);

        $this->assertEquals([
            'test' => 'this',
            'and' => 'that'
        ], $data);
    }

    /**
     * @expectedException \Psecio\Uri\Exception\InvalidQuery
     */
    public function testBuildDataFromUrlNoParams()
    {
        $url = 'http://foobar.com';

        $b = new Builder('test');
        $data = $b->buildFromUrlString($url);
    }

    public function testBuildHash()
    {
        $queryString = 'test=this&and=that';
        $match = '94857a73d16605dc084751a66f8ac05e2be478b79563e49625f0d87b733dcbb1';

        $b = new Builder('test');
        $hash = $b->buildHash($queryString);

        $this->assertEquals($match, $hash);
    }

    /**
     * @expectedException \Psecio\Uri\Exception\InvalidQuery
     */
    public function testBuildHashEmptyString()
    {
        $queryString = '';

        $b = new Builder('test');
        $hash = $b->buildHash($queryString);
    }

    public function testCreateFromUrlString()
    {
        $url = 'http://test.com?foo=bar&baz=1';
        $match = 'http://test.com?foo=bar&baz=1&signature=395150b277ca25dd7a52e9345bb9c7bc4b133f001e912fe3a7ed48316a8f5a29';

        $b = new Builder('test');
        $result = $b->create($url);

        $this->assertEquals($match, $result);
    }

    public function testCreateFromUrlData()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $match = 'http://test.com?foo=bar&baz=1&'
            .'signature=395150b277ca25dd7a52e9345bb9c7bc4b133f001e912fe3a7ed48316a8f5a29';

        $b = new Builder('test');
        $result = $b->create($base, $data);

        $this->assertEquals($match, $result);
    }

    public function testCreateFromUrlDataTimeout()
    {
        $base = 'http://test.com';
        $timeout = '+1 minute';
        $data = [
            'foo' => 'bar',
            'baz' => 1,
            'expires' => strtotime($timeout)
        ];
        $b = new Builder('test');

        $queryString = http_build_query($data);
        $hash = $b->buildHash($queryString);
        $match = 'http://test.com?'.$queryString.'&signature='.$hash;
        
        $result = $b->create($base, $data, $timeout);
        $this->assertEquals($match, $result);
    }

    /**
     * @expectedException \Psecio\Uri\Exception\InvalidTimeout
     */
    public function testCreateFromUrlDataPastTimeout()
    {
        $base = 'http://test.com';
        $timeout = '-1 minute';
        $data = [
            'foo' => 'bar',
            'baz' => 1,
            'expires' => strtotime($timeout)
        ];
        $b = new Builder('test');
        
        $result = $b->create($base, $data, $timeout);
        $this->assertEquals($match, $result);
    }

    public function testValidateValidSignedUrl()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $b = new Builder('test');
        
        $result = $b->create($base, $data);
        $this->assertTrue($b->validate($result));
    }

    public function testValidateInvalidSignedUrl()
    {
        $base = 'http://test.com';
        $data = [
            'foo' => 'bar',
            'baz' => 1
        ];
        $b = new Builder('test');
        
        $result = $b->create($base, $data);
        $result = preg_replace('/signature=[0-9a-z]+/', 'signature=1234', $result);

        $this->assertFalse($b->validate($result));
    }

    /**
     * @expectedException \Psecio\Uri\Exception\InvalidQuery
     */
    public function testValidateSignedUrlNoQuery()
    {
        $url = 'http://test.com';
        $b = new Builder('test');

        $b->validate($url);
    }

    /**
     * @expectedException \Psecio\Uri\Exception\SignatureInvalid
     */
    public function testValidateNoSignature()
    {
        $url = 'http://test.com?foo=bar';
        $b = new Builder('test');

        $b->validate($url);
    }

    /**
     * @expectedException \Psecio\Uri\Exception\SignatureExpired
     */
    public function testValidateSignatureExpired()
    {
        $url = 'http://test.com?foo=bar&signature=1234&expires='.strtotime('-1 hour');
        $b = new Builder('test');

        $result = $b->validate($url);
        var_export($result);
    }
}