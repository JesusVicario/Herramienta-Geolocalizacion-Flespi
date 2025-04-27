<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === FUNCIÓN PARA LOG CON LÍMITE DE 100 LÍNEAS ===
function agregarAlLog($texto) {
    $archivo = 'log_auto_recoge.txt';
    $linea = "[" . date('Y-m-d H:i:s') . "] " . $texto;
    $lineas = file_exists($archivo) ? file($archivo, FILE_IGNORE_NEW_LINES) : [];
    $lineas[] = $linea;
    if (count($lineas) > 100) $lineas = array_slice($lineas, -100);
    file_put_contents($archivo, implode(PHP_EOL, $lineas) . PHP_EOL);
}

// === FUNCIONES ===
function haversine($lat1, $lon1, $lat2, $lon2) {
    $radio = 6371000; // metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return 2 * $radio * atan2(sqrt($a), sqrt(1-$a));
}

// === DATOS FLESPI ===
$token = '';
$id_flespi = '';
$url = "https://flespi.io/gw/devices/$id_flespi/telemetry/all";

// === CONSULTA A FLESPI ===
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: FlespiToken $token"]
]);
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

agregarAlLog("HTTP $httpCode");

if ($httpCode !== 200) {
    agregarAlLog("Error en la consulta a Flespi");
    http_response_code(500);
    exit("Error Flespi ($httpCode)");
}

$data = json_decode($response, true);
$t = $data['result'][0]['telemetry'] ?? [];

$ignition_bool = $t['engine.ignition.status']['value'] ?? false;
$lat = $t['position.latitude']['value'] ?? null;
$lon = $t['position.longitude']['value'] ?? null;
$velocidad = (float)($t['position.speed']['value'] ?? 0);
$timestamp = time();

// === VALIDACIÓN DE COORDENADAS ===
if (!$lat || !$lon) {
    agregarAlLog("Coordenadas no válidas: lat=$lat lon=$lon");
    exit("Coordenadas no válidas.");
}

// === PUNTO NUEVO ===
$nuevo_punto = [
    'lat' => $lat,
    'lon' => $lon,
    'velocidad' => $velocidad,
    'ignition' => $ignition_bool,
    'timestamp' => $timestamp
];

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
    echo "Ignition: "; var_dump($ignition_bool);
    echo "Nuevo punto:\n"; print_r($nuevo_punto);
    echo "</pre>";
}

// === CARGA DE HISTORIAL ===
$archivo = 'historial.json';
$trayectos = file_exists($archivo) ? json_decode(file_get_contents($archivo), true) : [];

if (empty($trayectos)) {
    if ($ignition_bool && $velocidad >= 5) {
        $trayectos[] = [
            'id' => 0,
            'inicio' => $timestamp,
            'fin' => null,
            'puntos' => [$nuevo_punto]
        ];
        file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
        agregarAlLog("Primer trayecto creado");
        exit("Primer trayecto creado.");
    } else {
        agregarAlLog("Motor apagado o sin movimiento. No se inicia trayecto.");
        exit("Motor apagado o sin movimiento.");
    }
}

// === ANÁLISIS ÚLTIMO TRAYECTO ===
$ultimo_trayecto = &$trayectos[count($trayectos) - 1];
$puntos = &$ultimo_trayecto['puntos'];

if (!is_array($puntos) || empty($puntos)) {
    agregarAlLog("Último trayecto sin puntos");
    exit("Error: Último trayecto inválido.");
}

$ultimo_punto = end($puntos);
$tiempo_desde_ultimo = $timestamp - $ultimo_punto['timestamp'];
$distancia = haversine($ultimo_punto['lat'], $ultimo_punto['lon'], $lat, $lon);
$se_ha_movido = ($distancia > 30 && $velocidad > 4.17);
$tiempo_total_trayecto = $timestamp - $ultimo_trayecto['inicio'];

// === NUEVA LÓGICA: CIERRE SI LLEVA 10 MINUTOS SIN MOVERSE ===
$ultimo_movimiento = $ultimo_punto['timestamp'];
foreach (array_reverse($puntos) as $p) {
    $dist = haversine($p['lat'], $p['lon'], $lat, $lon);
    if ($dist > 30 || $p['velocidad'] > 10) {
        $ultimo_movimiento = $p['timestamp'];
        break;
    }
}
$tiempo_sin_moverse = $timestamp - $ultimo_movimiento;

if (is_null($ultimo_trayecto['fin']) && $ignition_bool && $tiempo_sin_moverse >= 10 * 60) {
    $ultimo_trayecto['fin'] = $timestamp;
    file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
    agregarAlLog("Trayecto cerrado por 10 minutos sin movimiento real");
    exit("Cerrado por inactividad real.");
}

// === CREACIÓN DE NUEVO TRAYECTO SI EL ANTERIOR ESTÁ CERRADO ===
if (!is_null($ultimo_trayecto['fin'])) {
    if ($ignition_bool && $se_ha_movido) {
        // Limitar a 20 trayectos
        if (count($trayectos) >= 20) {
            array_shift($trayectos); // Eliminar el más antiguo
            foreach ($trayectos as $k => &$trayecto) {
                $trayecto['id'] = $k;
            }
        }

        $trayectos[] = [
            'id' => count($trayectos),
            'inicio' => $timestamp,
            'fin' => null,
            'puntos' => [$nuevo_punto]
        ];
        file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
        agregarAlLog("Nuevo trayecto iniciado tras uno cerrado.");
        exit("Nuevo trayecto iniciado.");
    } else {
        agregarAlLog("Trayecto ya cerrado, sin movimiento real.");
        exit("Trayecto cerrado anteriormente.");
    }
}

// === REGISTRO DE NUEVOS PUNTOS ===
if ($ignition_bool) {
    if ($ultimo_punto['ignition'] === false && $se_ha_movido) {
        // Limitar a 10 trayectos
        if (count($trayectos) >= 10) {
            array_shift($trayectos); // Eliminar el más antiguo
            foreach ($trayectos as $k => &$trayecto) {
                $trayecto['id'] = $k;
            }
        }

        $trayectos[] = [
            'id' => count($trayectos),
            'inicio' => $timestamp,
            'fin' => null,
            'puntos' => [$nuevo_punto]
        ];
        file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
        agregarAlLog("Nuevo trayecto iniciado (motor se encendió y hay movimiento)");
        exit("Nuevo trayecto iniciado.");
    } elseif ($se_ha_movido && $tiempo_desde_ultimo > 15) {
        $puntos[] = $nuevo_punto;
        file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
        agregarAlLog("Punto añadido (hay movimiento)");
        exit("Punto añadido al trayecto.");
    } else {
        agregarAlLog("Ignition activo, pero sin movimiento real");
        exit("Sin movimiento, no se guarda.");
    }
} else {
    if (is_null($ultimo_trayecto['fin'])) {
        $ultimo_trayecto['fin'] = $timestamp;
        file_put_contents($archivo, json_encode($trayectos, JSON_PRETTY_PRINT));
        agregarAlLog("Motor apagado, trayecto cerrado");
        exit("Trayecto cerrado.");
    } else {
        agregarAlLog("Motor apagado, trayecto ya cerrado");
        exit("Motor apagado, sin cambios.");
    }
}
?>