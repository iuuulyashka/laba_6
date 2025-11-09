<?php

namespace App;

use Exception;

class ElasticExample
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'http://laba6_elastic:9200/';
    }

    public function checkConnection()
    {
        try {
            $response = $this->httpRequest('', 'GET');
            $data = json_decode($response, true);
            return [
                'status' => 'connected',
                'version' => $data['version']['number'] ?? 'unknown'
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
            // Пытаемся удалить старый индекс
            try {
                $this->httpRequest('sensors', 'DELETE');
                $results[] = "Старый индекс удален";
                sleep(1);
            } catch (Exception $e) {
                $results[] = "Создаем новый индекс";
            }

            // Создаем индекс
            $indexConfig = [
                'mappings' => [
                    'properties' => [
                        'sensor_id' => ['type' => 'keyword'],
                        'sensor_type' => ['type' => 'keyword'],
                        'location' => ['type' => 'text'],
                        'temperature' => ['type' => 'float'],
                        'humidity' => ['type' => 'float'],
                        'timestamp' => ['type' => 'date']
                    ]
                ]
            ];

            $this->httpRequest('sensors', 'PUT', $indexConfig);
            $results[] = "Индекс сенсоров создан";
            sleep(1);

            // Добавляем тестовые данные
            $sensors = [
                ['sensor_id' => 'temp_sensor_1', 'sensor_type' => 'temperature', 'location' => 'Серверная комната A'],
                ['sensor_id' => 'temp_sensor_2', 'sensor_type' => 'temperature', 'location' => 'Офисное помещение B'],
                ['sensor_id' => 'hum_sensor_1', 'sensor_type' => 'humidity', 'location' => 'Лаборатория C']
            ];

            $docCount = 0;
            $baseTime = time() - (24 * 3600);
            
            foreach ($sensors as $sensor) {
                // Добавляем по 12 записей на сенсор (каждые 2 часа)
                for ($i = 0; $i < 12; $i++) {
                    $timestamp = $baseTime + ($i * 7200); // +2 часа
                    
                    if ($sensor['sensor_type'] === 'temperature') {
                        $temperature = 22 + rand(-30, 30) / 10;
                        $humidity = null;
                    } else {
                        $temperature = null;
                        $humidity = 50 + rand(-200, 200) / 10;
                    }
                    
                    $document = [
                        'sensor_id' => $sensor['sensor_id'],
                        'sensor_type' => $sensor['sensor_type'],
                        'location' => $sensor['location'],
                        'temperature' => $temperature,
                        'humidity' => $humidity,
                        'timestamp' => date('c', $timestamp)
                    ];
                    
                    $this->httpRequest("sensors/_doc/" . ($docCount + 1), 'PUT', $document);
                    $docCount++;
                }
                $results[] = "Добавлен сенсор: " . $sensor['sensor_id'];
            }

            sleep(2);
            $results[] = "Все данные добавлены! Всего записей: " . $docCount;

        } catch (Exception $e) {
            $results[] = "Ошибка: " . $e->getMessage();
        }

        return $results;
    }

    public function searchSensors($searchText)
    {
        try {
            $query = [
                'query' => [
                    'multi_match' => [
                        'query' => $searchText,
                        'fields' => ['sensor_id', 'location', 'sensor_type']
                    ]
                ],
                'size' => 20,
                'sort' => [
                    ['timestamp' => ['order' => 'desc']]
                ]
            ];

            $response = $this->httpRequest('sensors/_search', 'POST', $query);
            $result = json_decode($response, true);
            
            return [
                'success' => true,
                'took' => $result['took'] ?? 0,
                'total' => $result['hits']['total']['value'] ?? 0,
                'hits' => $result['hits']['hits'] ?? []
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'took' => 0,
                'total' => 0,
                'hits' => []
            ];
        }
    }

    public function getIndexStats()
    {
        try {
            $response = $this->httpRequest('sensors/_stats', 'GET');
            $data = json_decode($response, true);
            
            return [
                'success' => true,
                'docs_count' => $data['indices']['sensors']['total']['docs']['count'] ?? 0,
                'size' => $data['indices']['sensors']['total']['store']['size_in_bytes'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'docs_count' => 0,
                'size' => 0
            ];
        }
    }

    private function httpRequest($endpoint, $method = 'GET', $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => "Content-Type: application/json\r\n",
                'timeout' => 10,
                'ignore_errors' => true // Игнорируем HTTP ошибки для обработки в коде
            ]
        ];
        
        if ($data !== null) {
            $options['http']['content'] = json_encode($data);
        }
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new Exception('HTTP request failed: ' . ($error['message'] ?? 'Unknown error'));
        }
        
        // Проверяем HTTP код ответа
        if (isset($http_response_header[0])) {
            preg_match('/HTTP\/[0-9\.]+\s+([0-9]+)/', $http_response_header[0], $matches);
            $statusCode = $matches[1] ?? 200;
            
            if ($statusCode >= 400) {
                throw new Exception('HTTP error ' . $statusCode . ': ' . $response);
            }
        }
        
        return $response;
    }
}