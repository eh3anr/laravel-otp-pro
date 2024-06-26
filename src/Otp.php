<?php

namespace Eh3anr\Otp;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Otp
{
    private string $format = 'numeric';
    private $customize;
    private int|array $length = 6;
    private string $separator = '-';
    private bool $sensitive = false;
    private int $expires = 15;
    private int $attempts = 5;
    private string $prefix = 'OTPPX_';
    private $data;
    private bool $skip = false;
    private bool $demo = false;
    private array $demo_passwords = ['1234', '123456', '12345678'];

    public function __construct()
    {
        foreach ([
                     'format', 'customize', 'length', 'separator', 'sensitive', 'expires', 'attempts', 'repeated',
                     'disposable', 'prefix', 'data', 'demo', 'demo_passwords',
                 ] as $value) {
            if (!empty(config('otp.'.$value))) {
                $this->{$value} = config('otp.'.$value);
            }
        }
    }

    /**
     * @param  string  $method
     * @param $params
     * @return $this|void
     */
    public function __call(string $method, $params)
    {
        if (!str_starts_with($method, 'set')) {
            return;
        }

        $property = Str::camel(substr($method, 3));
        if (!property_exists($this, $property)) {
            return;
        }

        $this->{$property} = $params[0] ?? null;
        if ($property == 'customize') {
            $this->format = 'customize';
        }
        return $this;
    }

    /**
     * @throws Exception
     */
    public function generate(string $identifier = null, array $options = []): string
    {
        if (!empty($options)) {
            foreach ([
                         'format', 'customize', 'length', 'separator', 'sensitive', 'expires', 'repeated', 'prefix',
                         'data',
                     ] as $value) {
                if (isset($options[$value])) {
                    $this->{$value} = $options[$value];
                }
            }
        }

        if ($identifier === null) {
            $identifier = session()->getId();
        }
        $password = $this->generateNewPassword();
        if (!$this->sensitive) {
            $password = strtoupper($password);
        }
        $this->writeData($identifier, [
            'expires' => time() + $this->expires * 60,
            'data' => $this->data,
            'password' => Hash::make($password),
        ]);
        return $password;
    }

    /**
     * @throws Exception
     */
    public function validate(string $identifier = null, string $password = null, array $options = []): object
    {
        if (!empty($options)) {
            foreach (['attempts', 'sensitive', 'disposable', 'skip'] as $value) {
                if (isset($options[$value])) {
                    $this->{$value} = $options[$value];
                }
            }
        }

        if ($password === null) {
            if ($identifier === null) {
                throw new Exception("Validate parameter can not be null");
            }
            $password = $identifier;
            $identifier = null;
        }

        if ($this->demo && in_array($password, $this->demo_passwords)) {
            return (object) [
                'status' => true,
                'demo' => true,
            ];
        }

        if ($identifier === null) {
            $identifier = session()->getId();
        }
        $attempt = $this->readData('_attempt_'.$identifier, 0);
        if ($attempt >= $this->attempts) {
            return (object) [
                'status' => false,
                'error' => 'max_attempt',
            ];
        }

        $data = $this->readData($identifier, null);
        if (!$this->sensitive) {
            $password = strtoupper($password);
        }

        if (is_null($data)) {
            $this->writeData('_attempt_'.$identifier, $attempt + 1);
            return (object) [
                'status' => false,
                'error' => 'invalid',
            ];
        }

        if (time() > $data['expires']) {
            $this->writeData('_attempt_'.$identifier, $attempt + 1);
            return (object) [
                'status' => false,
                'error' => 'expired',
            ];
        }

        if (!Hash::check($password, $data['password'])) {
            $this->writeData('_attempt_'.$identifier, $attempt + 1);
            return (object) [
                'status' => false,
                'error' => 'invalid',
            ];
        }

        $response = [
            'status' => true,
        ];

        if (!empty($data['data'])) {
            $response['data'] = $data['data'];
        }

        if (!$this->skip) {
            $this->forget($identifier);
        }
        $this->resetAttempt($identifier);

        return (object) $response;
    }

    public function forget(string $identifier = null): bool
    {
        if ($identifier === null) {
            $identifier = session()->getId();
        }

        $this->deleteData($identifier);
        return true;
    }

    public function resetAttempt(string $identifier = null): bool
    {
        if ($identifier === null) {
            $identifier = session()->getId();
        }
        $this->deleteData('_attempt_'.$identifier);
        return true;
    }

    /**
     * @throws Exception
     */
    private function generateNewPassword(): string
    {
        try {
            $formats = [
                'string' => $this->sensitive ? '23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ' : '23456789ABCDEFGHJKLMNPQRSTUVWXYZ',
                'numeric' => '0123456789',
                'numeric-no-zero' => '123456789',
                'customize' => $this->customize,
            ];

            $lengths = is_array($this->length) ? $this->length : [$this->length];

            $password = [];
            foreach ($lengths as $length) {
                $password[] = substr(str_shuffle(str_repeat($x = $formats[$this->format], ceil($length / strlen($x)))),
                    1, $length);
            }

            return implode($this->separator, $password);
        } catch (Exception $e) {
            throw new Exception("Fail to generate password, please check the format is correct.");
        }
    }

    private function writeData(string $key, $value): void
    {
        $expires = $this->expires;
        if (app()->version() >= "5.8") {
            $expires *= 60;
        }
        Cache::put($this->prefix.$key, $value, $expires * 3);
    }

    private function readData(string $key, $default = null)
    {
        return Cache::get($this->prefix.$key, $default);
    }

    private function deleteData(string $key): void
    {
        Cache::forget($this->prefix.$key);
    }
}
