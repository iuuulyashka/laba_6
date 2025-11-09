<?php

namespace App;

use Exception;

class ClickhouseExample
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'http://laba6_clickhouse:8123/';
    }

    public function checkConnection()
    {
        try {
            $response = $this->httpRequest('', 'GET');
            return [
                'status' => 'connected',
                'message' => 'ClickHouse connected successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function initializeData()
    {
        $results = [];
        
        try {
            // Создаем таблицу
            $sql = "CREATE TABLE IF NOT EXISTS sensor_data (
                sensor_id String,
                sensor_type String,
                location String,
                temperature Float32,
                humidity Float32,
                timestamp DateTime
            ) ENGINE = Memory";
            
            $this->httpRequest('', 'POST', $sql);
            $results[] = "Таблица sensor_data создана";

            // Очищаем старые данные
            $this->httpRequest('', 'POST', "TRUNCATE TABLE sensor_data");
            $results[] = "Старые данные очищены";

            // Добавляем тестовые данные
            $sensors = [
                ['id' => 'temp_sensor_1', 'type' => 'temperature', 'location' => 'Серверная 1', 'base_temp' => 22.5],
                ['id' => 'temp_sensor_2', 'type' => 'temperature', 'location' => 'Офис 2', 'base_temp' => 23.0],
                ['id' => 'temp_sensor_3', 'type' => 'temperature', 'location' => 'Склад 3', 'base_temp' => 19.5],
                ['id' => 'hum_sensor_1', 'type' => 'humidity', 'location' => 'Серверная 1', 'base_hum' => 45.0]
            ];

            $baseTime = time() - (48 * 3600);
            $totalRecords = 0;

            foreach ($sensors as $sensor) {
                $values = [];
                
                // Генерируем данные за 48 часов с интервалом 4 часа
                for ($i = 0; $i < 12; $i++) {
                    $timestamp = $baseTime + ($i * 14400); // +4 часа
                    
                    if ($sensor['type'] === 'temperature') {
                        $hour = date('H', $timestamp);
                        $daily_variation = sin(2 * pi() * $hour / 24) * 2;
                        $random_noise = (rand(-50, 50) / 100);
                        $temperature = $sensor['base_temp'] + $daily_variation + $random_noise;
                        $humidity = null;
                    } else {
                        $temperature = null;
                        $hour = date('H', $timestamp);
                        $daily_variation = sin(2 * pi() * $hour / 24) * 5;
                        $random_noise = (rand(-30, 30) / 100);
                        $humidity = $sensor['base_hum'] + $daily_variation + $random_noise;
                    }
                    
                    $values[] = sprintf(
                        "('%s', '%s', '%s', %s, %s, '%s')",
                        $sensor['id'],
                        $sensor['type'],
                        $sensor['location'],
                        $temperature !== null ? number_format($temperature, 2, '.', '') : 'NULL',
                        $humidity !== null ? number_format($humidity, 2, '.', '') : 'NULL',
                        date('Y-m-d H:i:s', $timestamp)
                    );
                    
                    $totalRecords++;
                }
                
                $insertSQL = "INSERT INTO sensor_data VALUES " . implode(', ', $values);
                $this->httpRequest('', 'POST', $insertSQL);
                $results[] = "Данные для " . $sensor['id'] . " добавлены";
            }

            $results[] = "Все данные сенсоров добавлены! Всего: " . $totalRecords . " записей";

        } catch (Exception $e) {
            $results[] = "Ошибка инициализации: " . $e->getMessage();
        }

        return $results;
    }

    public function getAverageTemperaturePerHour()
    {
        try {
            $sql = "
                SELECT 
                    toStartOfHour(timestamp) as hour,
                    sensor_id,
                    location,
                    round(avg(temperature), 2) as avg_temperature,
                    round(min(temperature), 2) as min_temperature,
                    round(max(temperature), 2) as max_temperature,
                    count() as readings_count
                FROM sensor_data 
                WHERE sensor_type = 'temperature' 
                    AND temperature IS NOT NULL
                GROUP BY hour, sensor_id, location
                ORDER BY hour DESC, sensor_id
                LIMIT 50
            ";
            
            $response = $this->httpRequest('', 'POST', $sql);
            
            $data = trim($response);
            $lines = explode("\n", $data);
            
            $stats = [];
            foreach ($lines as $line) {
                if (!empty(trim($line))) {
                    $parts = explode("\t", $line);
                    if (count($parts) >= 7) {
                        $stats[] = [
                            'hour' => $parts[0],
                            'sensor_id' => $parts[1],
                            'location' => $parts[2],
                            'avg_temperature' => $parts[3],
                            'min_temperature' => $parts[4],
                            'max_temperature' => $parts[5],
                            'readings_count' => $parts[6]
                        ];
                    }
                }
            }
            
            return [
                'success' => true,
                'data' => $stats,
                'rows' => count($stats)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'rows' => 0
            ];
        }
    }

    public function getSensorsStats()
    {
        try {
            $sql = "
                SELECT 
                    sensor_id,
                    location,
                    sensor_type,
                    count() as total_readings,
                    round(avg(temperature), 2) as avg_temperature,
                    round(avg(humidity), 2) as avg_humidity,
                    min(timestamp) as first_reading,
                    max(timestamp) as last_reading
                FROM sensor_data 
                GROUP BY sensor_id, location, sensor_type
                ORDER BY sensor_id
            ";
            
            $response = $this->httpRequest('', 'POST', $sql);
            
            $data = trim($response);
            $lines = explode("\n", $data);
            
            $sensors = [];
            foreach ($lines as $line) {
                if (!empty(trim($line))) {
                    $parts = explode("\t", $line);
                    if (count($parts) >= 8) {
                        $sensors[] = [
                            'sensor_id' => $parts[0],
                            'location' => $parts[1],
                            'sensor_type' => $parts[2],
                            'total_readings' => $parts[3],
                            'avg_temperature' => $parts[4],
                            'avg_humidity' => $parts[5],
                            'first_reading' => $parts[6],
                            'last_reading' => $parts[7]
                        ];
                    }
                }
            }
            
            return [
                'success' => true,
                'data' => $sensors,
                'rows' => count($sensors)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'rows' => 0
            ];
        }
    }

    private function httpRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ];
        
        if ($data !== null && $method === 'POST') {
            $options['http']['content'] = $data;
            $options['http']['header'] = "Content-Type: text/plain\r\n";
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }
        
        return $response;
    }
}