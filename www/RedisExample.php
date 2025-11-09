<?php

namespace App;

use GuzzleHttp\Client;

class RedisExample
{
    public function checkConnection()
    {
        try {
            $redis = new \Redis();
            $connected = $redis->connect('laba6_redis', 6379, 2.0);
            
            if ($connected) {
                return [
                    'status' => 'connected',
                    'message' => 'Redis connected successfully'
                ];
            }
            
            return [
                'status' => 'error', 
                'message' => 'Could not connect to Redis'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function demoOperations()
    {
        return [
            "SET sensor:last_update '2024-01-15 14:30:00'",
            "GET sensor:last_update => '2024-01-15 14:30:00'", 
            "SET sensor:alerts 3",
            "INCR sensor:alerts => 4"
        ];
    }

    public function getUseCases()
    {
        return [
            'Кэширование данных сенсоров',
            'Хранение последних показаний', 
            'Счетчики событий и алертов',
            'Очереди сообщений между сервисами'
        ];
    }
}