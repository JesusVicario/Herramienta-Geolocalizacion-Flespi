<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_COOKIE['acceso_autorizado'])) {
    header("Location: index.php");
    exit;
}

function formatearDuracion($segundos)
{
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    return sprintf('%02dh %02dm', $horas, $minutos);
}

function calcularDistancia($puntos)
{
    $distancia = 0;
    for ($i = 1; $i < count($puntos); $i++) {
        $distancia += distanciaHaversine(
            $puntos[$i - 1]['lat'],
            $puntos[$i - 1]['lon'],
            $puntos[$i]['lat'],
            $puntos[$i]['lon']
        );
    }
    return round($distancia, 2);
}

function distanciaHaversine($lat1, $lon1, $lat2, $lon2)
{
    $radioTierra = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $radioTierra * $c;
}

$historial = array_reverse(json_decode(file_get_contents('historial.json'), true));

// Totales
$total_km = 0;
$total_duracion = 0;
$total_vel = 0;
$total_registros = 0;

foreach ($historial as $trayecto) {
    $velocidades = array_column($trayecto['puntos'], 'velocidad');
    $total_vel += array_sum($velocidades) / count($velocidades);
    $total_km += calcularDistancia($trayecto['puntos']);
    $total_duracion += ($trayecto['fin'] - $trayecto['inicio']);
    $total_registros++;
}
$vel_media_total = round($total_vel / $total_registros, 1);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Historial de Trayectos</title>
    <link rel="stylesheet" href="css/leaflet.css" />
    <link rel="stylesheet" href="css/style.css" />
    <meta name='viewport' content='width=device-width, initial-scale=1'>
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
        .trayecto {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            padding: 15px;
            margin-bottom: 20px;
        }

        .mini-mapa {
            height: 200px;
            width: 100%;
            margin-top: 10px;
            border-radius: 10px;
        }

        .trayectos-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .trayecto {
            width: 100%;
        }

        .btn-ver {
            margin-top: 10px;
            display: inline-block;
            padding: 10px 16px;
            background: #ff6600;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
        }

        .btn-ver:hover {
            background: #e55b00;
        }

        @media (min-width: 768px) {
            .trayecto {
                width: 45%;
            }
        }

        .resumen {
            background: #ff6600;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 16px;
        }

        .resumen span {
            display: inline-block;
            margin: 0 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="card resumen">
        <span>🧭 Total: <?= round($total_km, 2) ?> km</span>
        <span>⏱ Duración: <?= formatearDuracion($total_duracion) ?></span>
        <span>🚀 Vel. media global: <?= $vel_media_total ?> km/h</span>
    </div>

    <div class="trayectos-container">
        <?php foreach ($historial as $trayecto):
            $velocidades = array_column($trayecto['puntos'], 'velocidad');
            $velocidadMax = max($velocidades);
            $velocidadMedia = round(array_sum($velocidades) / count($velocidades), 1);
            $distancia = calcularDistancia($trayecto['puntos']);
            $inicio = date('d/m/Y H:i', $trayecto['inicio']);
            $fin = date('d/m/Y H:i', $trayecto['fin']);
            $duracion = formatearDuracion($trayecto['fin'] - $trayecto['inicio']);
            $coordenadas = array_map(function ($p) {
                return [$p['lat'], $p['lon']];
            }, $trayecto['puntos']);
            $coordsJS = json_encode($coordenadas);
            $mapId = "map_" . $trayecto['id'];
            ?>
            <div class="trayecto card">
                <h3>Trayecto #<?= $trayecto['id'] ?></h3>
                <p><strong>Inicio:</strong> <?= $inicio ?></p>
                <p><strong>Fin:</strong> <?= $fin ?></p>
                <p><strong>Duración:</strong> <?= $duracion ?></p>
                <p><strong>Vel. media:</strong> <?= $velocidadMedia ?> km/h</p>
                <p><strong>Vel. máxima:</strong> <?= $velocidadMax ?> km/h</p>
                <p><strong>Distancia:</strong> <?= $distancia ?> km</p>
                <div id="<?= $mapId ?>" class="mini-mapa"></div>
                <a href="trayecto.php?id=<?= $trayecto['id'] ?>" class="btn-ver">Ver trayecto completo</a>
                <script>
                    document.addEventListener("DOMContentLoaded", function () {
                        var map = L.map('<?= $mapId ?>').setView(<?= json_encode($coordenadas[0]) ?>, 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(map);

                        var latlngs = <?= $coordsJS ?>;
                        var polyline = L.polyline(latlngs, { color: 'orange' }).addTo(map);
                        map.fitBounds(polyline.getBounds());
                    });
                </script>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="js/leaflet.js"></script>
</body>

</html>