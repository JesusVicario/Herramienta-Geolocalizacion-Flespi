<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_COOKIE['acceso_autorizado'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    echo "ID no proporcionado.";
    exit;
}

$id = (int) $_GET['id'];
$historial = json_decode(file_get_contents('historial.json'), true);

$trayecto = null;
foreach ($historial as $t) {
    if ($t['id'] === $id) {
        $trayecto = $t;
        break;
    }
}

if (!$trayecto) {
    echo "Trayecto no encontrado.";
    exit;
}

function formatearDuracion($segundos) {
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    return sprintf('%02dh %02dm', $horas, $minutos);
}

function calcularDistancia($puntos) {
    $distancia = 0;
    for ($i = 1; $i < count($puntos); $i++) {
        $distancia += distanciaHaversine(
            $puntos[$i - 1]['lat'], $puntos[$i - 1]['lon'],
            $puntos[$i]['lat'], $puntos[$i]['lon']
        );
    }
    return round($distancia, 2);
}

function distanciaHaversine($lat1, $lon1, $lat2, $lon2) {
    $radioTierra = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $radioTierra * $c;
}

// Datos calculados
$inicio = date('d/m/Y H:i', $trayecto['inicio']);
$fin = date('d/m/Y H:i', $trayecto['fin']);
$duracion = formatearDuracion($trayecto['fin'] - $trayecto['inicio']);
$velocidades = array_column($trayecto['puntos'], 'velocidad');
$velMedia = round(array_sum($velocidades) / count($velocidades), 1);
$velMax = max($velocidades);
$distancia = calcularDistancia($trayecto['puntos']);
$coordenadas = array_map(fn($p) => [$p['lat'], $p['lon']], $trayecto['puntos']);
$labels = array_map(fn($p) => date('H:i', $p['timestamp']), $trayecto['puntos']);
$vel_data = $velocidades;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Trayecto completo</title>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel="stylesheet" href="css/leaflet.css" />
    <link rel="stylesheet" href="css/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        #mapaGrande {
            height: 400px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .grafica {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .stats p {
            margin: 5px 0;
        }

        .btn-exportar {
            display: inline-block;
            margin: 10px 10px 0 0;
            padding: 10px 18px;
            background: #ff6600;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
        }

        .btn-exportar:hover {
            background: #e55b00;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Trayecto #<?= $trayecto['id'] ?></h2>
    <div class="stats">
        <p><strong>Inicio:</strong> <?= $inicio ?></p>
        <p><strong>Fin:</strong> <?= $fin ?></p>
        <p><strong>Duración:</strong> <?= $duracion ?></p>
        <p><strong>Distancia:</strong> <?= $distancia ?> km</p>
        <p><strong>Vel. media:</strong> <?= $velMedia ?> km/h</p>
        <p><strong>Vel. máxima:</strong> <?= $velMax ?> km/h</p>
        <p><strong>Puntos GPS:</strong> <?= count($trayecto['puntos']) ?></p>
    </div>

    <div id="mapaGrande"></div>

    <a href="historial.php" class="btn-exportar">← Volver al historial</a>
    <a href="#" onclick="exportarCSV()" class="btn-exportar">📄 Exportar CSV</a>
    <a href="#" onclick="exportarGPX()" class="btn-exportar">📍 Exportar GPX</a>
</div>

<div class="grafica card">
    <h3>Gráfica de velocidad</h3>
    <canvas id="graficaVelocidad"></canvas>
</div>

<script src="js/leaflet.js"></script>
<script>
    const coords = <?= json_encode($coordenadas) ?>;
    const puntos = <?= json_encode($trayecto['puntos']) ?>;
    const etiquetas = <?= json_encode($labels) ?>;
    const velocidades = <?= json_encode($vel_data) ?>;

    // Mapa
    const map = L.map('mapaGrande').setView(coords[0], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    const polyline = L.polyline(coords, { color: 'orange' }).addTo(map);
    map.fitBounds(polyline.getBounds());

    // Gráfica
    new Chart(document.getElementById("graficaVelocidad"), {
        type: 'line',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Velocidad (km/h)',
                data: velocidades,
                borderColor: 'orange',
                backgroundColor: 'rgba(255,102,0,0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { title: { display: true, text: 'Hora' } },
                y: { beginAtZero: true, title: { display: true, text: 'Velocidad' } }
            }
        }
    });

    // Exportar CSV
    function exportarCSV() {
        let csv = "Lat,Lon,Velocidad,Hora\n";
        puntos.forEach(p => {
            csv += `${p.lat},${p.lon},${p.velocidad},${new Date(p.timestamp * 1000).toLocaleTimeString()}\n`;
        });
        descargar("trayecto_<?= $trayecto['id'] ?>.csv", csv);
    }

    // Exportar GPX
    function exportarGPX() {
        let gpx = `<?xml version="1.0" encoding="UTF-8"?>\n<gpx version="1.1" creator="FleScan">\n<trk><name>Trayecto <?= $trayecto['id'] ?></name><trkseg>\n`;
        puntos.forEach(p => {
            gpx += `<trkpt lat="${p.lat}" lon="${p.lon}"><time>${new Date(p.timestamp * 1000).toISOString()}</time></trkpt>\n`;
        });
        gpx += "</trkseg></trk></gpx>";
        descargar("trayecto_<?= $trayecto['id'] ?>.gpx", gpx);
    }

    function descargar(nombre, contenido) {
        const blob = new Blob([contenido], { type: 'text/plain' });
        const enlace = document.createElement('a');
        enlace.href = URL.createObjectURL(blob);
        enlace.download = nombre;
        enlace.click();
    }
</script>

</body>
</html>