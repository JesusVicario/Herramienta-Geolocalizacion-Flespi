<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_COOKIE['acceso_autorizado'])) {
    header("Location: index.php");
    exit;
}

$archivo = 'historial.json';
$trayectos = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

function haversine($lat1, $lon1, $lat2, $lon2)
{
    $radio = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return 2 * $radio * atan2(sqrt($a), sqrt(1 - $a));
}

$datos_js = [];
foreach ($trayectos as $trayecto) {
    $velocidades = [];
    $timestamps = [];
    $aceleraciones = [];
    $distancia = 0;
    $anterior = null;

    foreach ($trayecto['puntos'] as $i => $punto) {
        $velocidades[] = $punto['velocidad'];
        $timestamps[] = date('H:i:s', $punto['timestamp']);
        if ($anterior) {
            $distancia += haversine($anterior['lat'], $anterior['lon'], $punto['lat'], $punto['lon']);
            $aceleraciones[] = $punto['velocidad'] - $anterior['velocidad'];
        } else {
            $aceleraciones[] = 0;
        }
        $anterior = $punto;
    }

    $datos_js[] = [
        'velocidades' => $velocidades,
        'aceleraciones' => $aceleraciones,
        'timestamps' => $timestamps,
        'distancia' => round($distancia / 1000, 2),
        'max' => max($velocidades),
        'min' => min($velocidades),
        'media' => round(array_sum($velocidades) / max(count($velocidades), 1), 2),
        'estado' => is_null($trayecto['fin']) ? 'En curso' : 'Cerrado'
    ];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gráficas del Vehículo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Icono de la pestaña -->
    <link rel="icon" type="image/png" href="/favicon.png" />

    <!-- Manifest PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ff6600" />

    <!-- Compatibilidad iOS (opcional pero recomendable) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f4f4;
            margin: 0 auto;
            padding: 20px;
            max-width: 1200px;
        }

        h1 {
            margin-bottom: 10px;
            color: #333;
        }

        .grafico {
            margin-bottom: 40px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        }

        select {
            padding: 10px;
            margin-bottom: 20px;
            font-size: 16px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }

        .estado {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        #distanciaTrayecto {
            font-size: 24px;
            color: #333;
            margin-top: 10px;
        }

        .volver-btn {
            display: inline-block;
            padding: 12px 20px;
            background: #ff6600;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .volver-btn:hover {
            background: #e55b00;
        }

        @media (max-width: 600px) {
            .grafico {
                padding: 15px;
            }

            canvas {
                width: 100% !important;
            }
        }
    </style>
</head>

<body>

    <h1>📊 Gráficas del Vehículo</h1>

    <label for="selector">Selecciona un trayecto:</label>
    <select id="selector">
        <?php foreach ($trayectos as $i => $t): ?>
            <option value="<?= $i ?>">Trayecto <?= $i ?><?= is_null($t['fin']) ? ' (En curso)' : '' ?></option>
        <?php endforeach; ?>
    </select>

    <div class="estado" id="estadoTrayecto"></div>

    <div class="grafico">
        <h2>Velocidad a lo largo del tiempo</h2>
        <canvas id="velocidadChart"></canvas>
    </div>

    <div class="grafico">
        <h2>Resumen de velocidades</h2>
        <canvas id="resumenChart"></canvas>
    </div>

    <div class="grafico">
        <h2>Aceleraciones registradas</h2>
        <canvas id="aceleracionChart"></canvas>
    </div>

    <div class="grafico">
        <h2>Distancia total recorrida</h2>
        <p id="distanciaTrayecto">0 km</p>
    </div>

    <a href="historial.php" class="volver-btn">← Volver al historial</a>

    <script>
        const datosTrayectos = <?= json_encode($datos_js) ?>;
        const ctx1 = document.getElementById('velocidadChart').getContext('2d');
        const ctx2 = document.getElementById('resumenChart').getContext('2d');
        const ctx3 = document.getElementById('aceleracionChart').getContext('2d');
        const distanciaDiv = document.getElementById('distanciaTrayecto');
        const estadoDiv = document.getElementById('estadoTrayecto');

        let velocidadChart, resumenChart, aceleracionChart;

        function actualizarGraficos(index) {
            const datos = datosTrayectos[index];

            estadoDiv.textContent = `Estado del trayecto: ${datos.estado}`;
            estadoDiv.style.color = datos.estado === 'En curso' ? '#2e7d32' : '#666';

            // Gráfico de velocidad
            if (velocidadChart) velocidadChart.destroy();
            velocidadChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: datos.timestamps,
                    datasets: [{
                        label: 'Velocidad (km/h)',
                        data: datos.velocidades,
                        fill: true,
                        borderColor: '#ff6600',
                        backgroundColor: 'rgba(255,102,0,0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { ticks: { maxTicksLimit: 10 } },
                        y: { beginAtZero: true }
                    }
                }
            });

            // Gráfico resumen
            if (resumenChart) resumenChart.destroy();
            resumenChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Máxima', 'Media', 'Mínima'],
                    datasets: [{
                        label: 'Velocidad (km/h)',
                        data: [datos.max, datos.media, datos.min],
                        backgroundColor: ['#2e7d32', '#fb8c00', '#c62828']
                    }]
                },
                options: {
                    responsive: true,
                    indexAxis: 'y',
                    scales: {
                        x: { beginAtZero: true }
                    }
                }
            });

            // Aceleraciones
            if (aceleracionChart) aceleracionChart.destroy();
            aceleracionChart = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: datos.timestamps,
                    datasets: [{
                        label: 'Variación de velocidad (km/h)',
                        data: datos.aceleraciones,
                        fill: false,
                        borderColor: '#3f51b5',
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: { ticks: { maxTicksLimit: 10 } },
                        y: { title: { display: true, text: 'Δ Velocidad' } }
                    }
                }
            });

            // Distancia
            distanciaDiv.textContent = datos.distancia + ' km';
        }

        document.getElementById('selector').addEventListener('change', function () {
            actualizarGraficos(this.value);
        });

        // Inicial
        actualizarGraficos(0);
    </script>

</body>

</html>