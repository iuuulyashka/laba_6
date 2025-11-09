<?php
require 'autoload.php';

use App\RedisExample;
use App\ElasticExample;
use App\ClickhouseExample;

$redis = new RedisExample();
$elastic = new ElasticExample();
$clickhouse = new ClickhouseExample();

$redisStatus = $redis->checkConnection();
$elasticStatus = $elastic->checkConnection();
$clickhouseStatus = $clickhouse->checkConnection();

$action = $_POST['action'] ?? '';
$searchTerm = $_POST['search'] ?? '';
$reportType = $_POST['report_type'] ?? 'hourly';

$initResults = [];
$searchResults = null;
$temperatureData = null;
$sensorsStats = null;

if ($action === 'init_data') {
    $initResults = array_merge(
        $elastic->initializeData(),
        $clickhouse->initializeData()
    );
}

if ($searchTerm) {
    $searchResults = $elastic->searchSensors($searchTerm);
}

// Получаем данные из ClickHouse
if ($reportType === 'hourly') {
    $temperatureData = $clickhouse->getAverageTemperaturePerHour();
} elseif ($reportType === 'sensors') {
    $sensorsStats = $clickhouse->getSensorsStats();
}

$elasticStats = $elastic->getIndexStats();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Мониторинг - Лабораторная работа №6</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f8fafc;
            --gray: #6b7280;
            --border: #e5e7eb;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .header h3 {
            color: var(--gray);
            font-weight: 400;
            font-size: 1.2rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .card-header i {
            font-size: 1.5rem;
            margin-right: 12px;
            color: var(--primary);
        }

        .card-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .status-connected {
            background: linear-gradient(135deg, var(--secondary), #34d399);
            color: white;
        }

        .status-error {
            background: linear-gradient(135deg, var(--danger), #f87171);
            color: white;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 5px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info), #0891b2);
            color: white;
        }

        .search-form {
            background: rgba(248, 250, 252, 0.8);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }

        .search-input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .sensor-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s ease;
        }

        .sensor-card:hover {
            transform: translateX(5px);
            border-color: var(--primary);
        }

        .sensor-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 12px;
        }

        .sensor-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
        }

        .sensor-location {
            color: var(--gray);
            font-style: italic;
        }

        .temperature {
            font-size: 1.3rem;
            font-weight: 800;
        }

        .temp-high { color: var(--danger); }
        .temp-normal { color: var(--warning); }
        .temp-low { color: var(--info); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: #f8fafc;
        }

        .init-results {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }

        .init-result-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(16, 185, 129, 0.2);
            display: flex;
            align-items: center;
        }

        .init-result-item:last-child {
            border-bottom: none;
        }

        .init-result-item i {
            color: var(--secondary);
            margin-right: 10px;
        }

        .operations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .operation-card {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }

        .use-cases {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }

        .use-case {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: var(--primary-dark);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .report-tabs {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .report-tab {
            padding: 12px 24px;
            border: 2px solid var(--border);
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .report-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .operations-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-microchip"></i> IoT Мониторинг</h1>
            <h3>Лабораторная работа №6 • Система мониторинга сенсоров</h3>
            <p>Вариант 11: Аналитика температурных данных в реальном времени</p>
        </div>

        <?php if (!empty($initResults)): ?>
            <div class="init-results">
                <h3><i class="fas fa-check-circle"></i> Результаты инициализации</h3>
                <?php foreach ($initResults as $result): ?>
                    <div class="init-result-item">
                        <i class="fas fa-check"></i> <?= htmlspecialchars($result) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Redis Section -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bolt"></i>
                <h2>Redis • Быстрый кэш</h2>
            </div>
            <div class="status-badge <?= $redisStatus['status'] === 'connected' ? 'status-connected' : 'status-error' ?>">
                <i class="fas <?= $redisStatus['status'] === 'connected' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?= $redisStatus['status'] === 'connected' ? $redisStatus['message'] : $redisStatus['message'] ?>
            </div>
            
            <h4><i class="fas fa-code"></i> Демонстрационные операции:</h4>
            <div class="operations-grid">
                <?php foreach ($redis->demoOperations() as $op): ?>
                    <div class="operation-card"><?= $op ?></div>
                <?php endforeach; ?>
            </div>

            <h4><i class="fas fa-lightbulb"></i> Сценарии использования:</h4>
            <div class="use-cases">
                <?php foreach ($redis->getUseCases() as $useCase): ?>
                    <div class="use-case"><?= $useCase ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Elasticsearch Section -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-search"></i>
                <h2>Elasticsearch • Поиск данных</h2>
            </div>
            <div class="status-badge <?= $elasticStatus['status'] === 'connected' ? 'status-connected' : 'status-error' ?>">
                <i class="fas <?= $elasticStatus['status'] === 'connected' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?= $elasticStatus['status'] === 'connected' ? 'Version ' . $elasticStatus['version'] : $elasticStatus['message'] ?>
            </div>

            <div class="search-form">
                <form method="post">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchTerm) ?>" 
                           placeholder="🔍 Введите ID сенсора, местоположение или тип..." 
                           class="search-input">
                    <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Найти сенсоры
                        </button>
                        <button type="button" onclick="document.querySelector('input[name=search]').value='temp_sensor'; document.forms[0].submit();" class="btn btn-info">
                            <i class="fas fa-thermometer-half"></i> Температура
                        </button>
                        <button type="button" onclick="document.querySelector('input[name=search]').value='hum_sensor'; document.forms[0].submit();" class="btn btn-info">
                            <i class="fas fa-tint"></i> Влажность
                        </button>
                    </div>
                </form>
                
                <form method="post" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="init_data">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-database"></i> Инициализировать тестовые данные
                    </button>
                    <small style="display: block; margin-top: 8px; color: var(--gray);">
                        4 сенсора • 48 часов данных • 768 записей
                    </small>
                </form>
            </div>

            <?php if ($searchResults): ?>
                <div class="search-results">
                    <h3><i class="fas fa-chart-line"></i> Результаты поиска: "<?= htmlspecialchars($searchTerm) ?>"</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $searchResults['total'] ?></div>
                            <div class="stat-label">Найдено записей</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= $searchResults['took'] ?>мс</div>
                            <div class="stat-label">Время поиска</div>
                        </div>
                    </div>
                    
                    <?php if ($searchResults['success'] && $searchResults['total'] > 0): ?>
                        <?php foreach ($searchResults['hits'] as $hit): ?>
                            <?php $sensor = $hit['_source']; ?>
                            <div class="sensor-card">
                                <div class="sensor-header">
                                    <div>
                                        <div class="sensor-name"><?= htmlspecialchars($sensor['sensor_id']) ?></div>
                                        <div class="sensor-location"><?= htmlspecialchars($sensor['location']) ?></div>
                                    </div>
                                    <div style="text-align: right;">
                                        <span style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 15px; font-size: 0.8rem;">
                                            <?= htmlspecialchars($sensor['sensor_type']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr auto; gap: 15px; align-items: center;">
                                    <div>
                                        <?php if ($sensor['temperature'] !== null): ?>
                                            <div class="temperature">🌡️ <?= number_format($sensor['temperature'], 1) ?>°C</div>
                                        <?php endif; ?>
                                        <?php if ($sensor['humidity'] !== null): ?>
                                            <div style="color: var(--info); font-weight: 600;">💧 <?= number_format($sensor['humidity'], 1) ?>%</div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right; color: var(--gray); font-size: 0.9rem;">
                                        <?= date('H:i:s', strtotime($sensor['timestamp'])) ?><br>
                                        <small>Рейтинг: <?= round($hit['_score'], 2) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>Данные не найдены</h3>
                            <p>Попробуйте другие ключевые слова или инициализируйте тестовые данные</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $elasticStats['docs_count'] ?? 0 ?></div>
                    <div class="stat-label">Записей в индексе</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= round(($elasticStats['size'] ?? 0) / 1024, 2) ?> KB</div>
                    <div class="stat-label">Размер индекса</div>
                </div>
            </div>
        </div>

        <!-- ClickHouse Section -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i>
                <h2>ClickHouse • Аналитика данных</h2>
            </div>
            <div class="status-badge <?= $clickhouseStatus['status'] === 'connected' ? 'status-connected' : 'status-error' ?>">
                <i class="fas <?= $clickhouseStatus['status'] === 'connected' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                <?= $clickhouseStatus['status'] === 'connected' ? $clickhouseStatus['message'] : $clickhouseStatus['message'] ?>
            </div>

            <div class="report-tabs">
                <button type="submit" name="report_type" value="hourly" 
                        class="report-tab <?= $reportType === 'hourly' ? 'active' : '' ?>" 
                        onclick="this.form.submit()">
                    <i class="fas fa-clock"></i> Средняя температура за час
                </button>
                <button type="submit" name="report_type" value="sensors" 
                        class="report-tab <?= $reportType === 'sensors' ? 'active' : '' ?>" 
                        onclick="this.form.submit()">
                    <i class="fas fa-microchip"></i> Статистика сенсоров
                </button>
            </div>

            <form method="post" id="reportForm"></form>

            <?php if ($reportType === 'hourly' && $temperatureData): ?>
                <h3><i class="fas fa-thermometer-three-quarters"></i> Средняя температура за час</h3>
                <p style="color: var(--gray); margin-bottom: 20px;">Анализ данных за последние 48 часов</p>
                
                <?php if ($temperatureData['success'] && !empty($temperatureData['data'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><i class="far fa-clock"></i> Время</th>
                                <th><i class="fas fa-microchip"></i> Сенсор</th>
                                <th><i class="fas fa-map-marker-alt"></i> Местоположение</th>
                                <th><i class="fas fa-thermometer-half"></i> Средняя</th>
                                <th><i class="fas fa-arrow-down"></i> Мин.</th>
                                <th><i class="fas fa-arrow-up"></i> Макс.</th>
                                <th><i class="fas fa-chart-bar"></i> Измерений</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($temperatureData['data'] as $stat): ?>
                                <tr>
                                    <td><strong><?= date('Y-m-d H:00', strtotime($stat['hour'])) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($stat['sensor_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($stat['location']) ?></td>
                                    <td>
                                        <span class="temperature 
                                            <?= $stat['avg_temperature'] > 24 ? 'temp-high' : ($stat['avg_temperature'] < 18 ? 'temp-low' : 'temp-normal') ?>">
                                            <?= $stat['avg_temperature'] ?>°C
                                        </span>
                                    </td>
                                    <td><?= $stat['min_temperature'] ?>°C</td>
                                    <td><?= $stat['max_temperature'] ?>°C</td>
                                    <td>
                                        <span style="background: var(--info); color: white; padding: 4px 8px; border-radius: 10px; font-size: 0.8rem;">
                                            <?= $stat['readings_count'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-thermometer-empty"></i>
                        <h3>Данные не найдены</h3>
                        <p>Инициализируйте тестовые данные для просмотра аналитики</p>
                    </div>
                <?php endif; ?>

            <?php elseif ($reportType === 'sensors' && $sensorsStats): ?>
                <h3><i class="fas fa-chart-pie"></i> Статистика сенсоров</h3>
                <p style="color: var(--gray); margin-bottom: 20px;">Общая информация по всем сенсорам системы</p>
                
                <?php if ($sensorsStats['success'] && !empty($sensorsStats['data'])): ?>
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-microchip"></i> Сенсор</th>
                                <th><i class="fas fa-map-marker-alt"></i> Местоположение</th>
                                <th><i class="fas fa-tag"></i> Тип</th>
                                <th><i class="fas fa-database"></i> Измерений</th>
                                <th><i class="fas fa-thermometer-half"></i> Средняя темп.</th>
                                <th><i class="fas fa-tint"></i> Средняя влажн.</th>
                                <th><i class="far fa-clock"></i> Активность</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sensorsStats['data'] as $sensor): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($sensor['sensor_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($sensor['location']) ?></td>
                                    <td>
                                        <span style="background: <?= $sensor['sensor_type'] === 'temperature' ? 'var(--warning)' : 'var(--info)' ?>; color: white; padding: 4px 8px; border-radius: 10px; font-size: 0.8rem;">
                                            <?= htmlspecialchars($sensor['sensor_type']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= $sensor['total_readings'] ?></strong></td>
                                    <td>
                                        <?php if ($sensor['avg_temperature']): ?>
                                            <span class="temperature"><?= $sensor['avg_temperature'] ?>°C</span>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($sensor['avg_humidity']): ?>
                                            <span style="color: var(--info); font-weight: 600;"><?= $sensor['avg_humidity'] ?>%</span>
                                        <?php else: ?>
                                            <span style="color: var(--gray);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= date('d.m.Y', strtotime($sensor['first_reading'])) ?><br>
                                            до <?= date('d.m.Y', strtotime($sensor['last_reading'])) ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-line"></i>
                        <h3>Статистика недоступна</h3>
                        <p>Инициализируйте тестовые данные для просмотра статистики</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="card" style="text-align: center; background: rgba(255, 255, 255, 0.8);">
            <p style="color: var(--gray); margin: 0;">
                <i class="fas fa-graduation-cap"></i> Лабораторная работа №6 • Нереляционные базы данных • 
                <strong>Вариант 11: Сенсоры - Средняя температура за час</strong>
            </p>
        </div>
    </div>

    <script>
        // Добавляем обработчики для кнопок отчетов
        document.querySelectorAll('.report-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const form = document.getElementById('reportForm');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'report_type';
                input.value = this.getAttribute('value');
                form.appendChild(input);
                form.submit();
            });
        });

        // Анимация появления карточек
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>