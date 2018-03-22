<?php

namespace Psecio\Uri;

class Builder
{
    /**
     * Current hashing algorithm
     *
     * @var string
     */
    protected $algorithm = 'SHA256';

    /**
     * Current secret string
     *
     * @var string
     */
    protected $secret;

    protected $signatureName = 'signature';
    protected $expiresName = 'expires';

    /**
     * Initialize the object and set the secret
     *
     * @param string $secret Secret value
     */
    public function __construct($secret)
    {
        $this->setSecret($secret);
    }

    /**
     * Set the current secret value
     *
     * @param string $secret
     * @return void
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * Return the current secret value
     *
     * @return string Secret string
     */
    public function getSecret()
    {
        return $this->secret;
    }

    public function create($base, array $data = [], $timeout = null)
    {
        // If we're not given data, try to break apart the URL provided
        if (empty($data)) {
            // This might throw an exception if no query params are given
            $data = $this->buildFromUrlString($base);

            // Replace the current query value
            $uri = parse_url($base);
            $base = str_replace('?'.$uri['query'], '', $base);
        }

        // Check for the timeout
        if ($timeout !== null) {
            $timeout = (!is_int($timeout)) ? strtotime($timeout) : $timeout;
            if ($timeout < time()) {
                throw new \Psecio\Uri\Exception\InvalidTimeout('Timeout cannot be in the past');
            }
            $data[$this->expiresName] = $timeout;
        }

        $query = http_build_query($data);
        $signature = $this->buildHash($query);

        $uri = $base.'?'.$query.'&'.$this->signatureName.'='.$signature;
        return $uri;
    }

    /**
     * Build the data array from a provided URL, parsing out the current GET params
     *
     * @param string $base
     * @throws \InvalidArgumentException If no query params exist
     * @return array Set of key/value pairs from the URL
     */
    public function buildFromUrlString($base) : array
    {
        $uri = parse_url($base);
        if (!isset($uri['query'])) {
            throw new \Psecio\Uri\Exception\InvalidQuery('No query parameters specified');
        }

        $data = $this->parseQueryData($uri['query']);
        return $data;
    }

    public function verify($url)
    {
        $uri = parse_url($url);

        if (!isset($uri['query']) || empty($uri['query'])) {
            throw new \Psecio\Uri\Exception\InvalidQuery('No URI parameters provided, cannot validate');
        }
        $data = $this->parseQueryData($uri['query']);

        // Try to find our signature
        if (!isset($data[$this->signatureName]) || empty($data[$this->signatureName])) {
            throw new \Psecio\Uri\Exception\SignatureInvalid('No signature found!');
        } else {
            // Remove it
            $signature = $data[$this->signatureName];
            unset($data[$this->signatureName]);

            $uri['query'] = http_build_query($data);
        }

        // Do we need to validate the "expires" value?
        if (isset($data[$this->expiresName])) {
            if ($data[$this->expiresName] < time()) {
                throw new \Psecio\Uri\Exception\SignatureExpired('Signature has expired');
            }
        }

        $check = $this->buildHash($uri['query']);
        return ($check === $signature);
    }

    public function buildHash($queryString)
    {
        if (empty($queryString)) {
            throw new \Psecio\Uri\Exception\InvalidQuery('Hash cannot be created, query string empty');
        }
        $signature = hash_hmac($this->algorithm, $queryString, $this->getSecret());
        return $signature;
    }

    public function parseQueryData($query)
    {
        $data = [];
        foreach (explode('&', $query) as $param) {
            $parts = explode('=', $param);
            $data[$parts[0]] = $parts[1];
        }

        return $data;
    }
}