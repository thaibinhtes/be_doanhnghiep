<?php

namespace App\Support;

class DinhDanhHistoryContext
{
    /** @var array<string, mixed> */
    private static array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function set(array $data): void
    {
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }

    public static function clear(): void
    {
        self::$data = [];
    }

    /**
     * @template TReturn
     * @param  array<string, mixed>  $data
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function run(array $data, callable $callback): mixed
    {
        self::set($data);

        try {
            return $callback();
        } finally {
            self::clear();
        }
    }
}
