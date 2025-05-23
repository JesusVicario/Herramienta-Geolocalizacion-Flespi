<?php
header('Content-Type: application/json');

// === DATOS FLESPI ===
$token = ''; // aquí tu token de Flespi
$id_flespi = ''; // aquí el ID del dispositivo en Flespi
$url = "https://flespi.io/gw/devices/$id_flespi/telemetry/all";

// === CONSULTA ===
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: FlespiToken $token"],
]);
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['error' => true, 'message' => "Flespi error ($httpCode)"]);
    exit;
}

// === DECODIFICAR RESPUESTA ===
$data = json_decode($response, true);
$result = $data['result'][0] ?? [];
$t = $result['telemetry'] ?? [];
$info = $result['device'] ?? [];

// === COORDENADAS ===
$lat = is_numeric($t['position.latitude']['value'] ?? null)
    ? floatval($t['position.latitude']['value'])
    : null;
$lon = is_numeric($t['position.longitude']['value'] ?? null)
    ? floatval($t['position.longitude']['value'])
    : null;

// === GEOLOCALIZACIÓN INVERSA con User-Agent ===
$direccion = 'No disponible';
if ($lat !== null && $lon !== null) {
    $opts = [
        'http' => [
            'header' => "User-Agent: MiApp/1.0\r\n"
        ]
    ];
    $ctx = stream_context_create($opts);
    $geo = @file_get_contents(
        "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=$lat&lon=$lon",
        false,
        $ctx
    );
    if ($geo) {
        $g = json_decode($geo, true);
        $direccion = $g['display_name'] ?? 'No disponible';
    }
}

// Inicializamos con cero
$tiempo_encendido = '00:00:00';

// === TIEMPO ENCENDIDO según historial.json ===
// Solo si el motor está encendido
if (
    isset($t['engine.ignition.status']['value']) &&
    (int) $t['engine.ignition.status']['value'] === 1
) {
    // Cargamos el historial de trayectos
    $histFile = __DIR__ . '/historial.json';
    if (file_exists($histFile) && is_readable($histFile)) {
        $historial = json_decode(file_get_contents($histFile), true);
        if (is_array($historial) && count($historial) > 0) {
            // Tomamos el último elemento del array
            $ultimo = end($historial);
            $inicio = isset($ultimo['inicio']) ? (int) $ultimo['inicio'] : null;
            if ($inicio) {
                // diferencia en segundos entre ahora y el inicio del trayecto
                $delta = time() - $inicio;
                // formateamos HH:MM:SS
                $tiempo_encendido = gmdate("H:i:s", max(0, $delta));
            }
        }
    }
} else {
    // si está apagado o no hay dato, lo dejamos a cero
    $tiempo_encendido = '00:00:00';
}

// === HORA GPS (fallback de 'position.latitude.ts') ===
if (isset($t['position.timestamp']['value'])) {
    // algunos dispositivos pueden exponer position.timestamp.value
    $gps_time = date("Y-m-d H:i:s", $t['position.timestamp']['value']);
} elseif (isset($t['position.latitude']['ts'])) {
    $gps_time = date("Y-m-d H:i:s", $t['position.latitude']['ts']);
} else {
    $gps_time = 'No disponible';
}

// === SALIDA JSON ===
echo json_encode([
    'error' => false,
    'ignition' => !empty($t['engine.ignition.status']['value']) ? 'Encendido' : 'Apagado',
    'movimiento' => !empty($t['movement.status']['value']) ? 'Sí' : 'No',
    'velocidad' => isset($t['position.speed']['value']) ? $t['position.speed']['value'] . ' km/h' : 'No disponible',
    'direccion_vehiculo' => isset($t['position.direction']['value']) ? $t['position.direction']['value'] . '°' : 'No disponible',
    'altitud' => isset($t['position.altitude']['value']) ? $t['position.altitude']['value'] . ' m' : 'No disponible',
    'gps_valid' => !empty($t['position.valid']['value']) ? 'Sí' : 'No',
    'gps_status' => !empty($t['position.valid']['value']) ? 'Correcta' : 'Sin señal',
    'sat_count' => isset($t['position.satellites']['value']) ? $t['position.satellites']['value'] : 'No disponible',
    'gps_time' => $gps_time,
    'km' => isset($t['vehicle.mileage']['value']) ? round($t['vehicle.mileage']['value'], 2) . ' km' : 'No disponible',
    'voltaje' => isset($t['external.powersource.voltage']['value']) ? $t['external.powersource.voltage']['value'] . ' V' : 'No disponible',
    'bateria' => isset($t['battery.voltage']['value']) ? $t['battery.voltage']['value'] . ' V' : 'No disponible',
    'bateria_porcentaje' => isset($t['battery.level']['value']) ? $t['battery.level']['value'] . '%' : 'No disponible',
    'gsm' => $t['gsm.signal.level']['value'] ?? 'No disponible',
    'lat' => $lat,
    'lon' => $lon,
    'direccion' => $direccion,
    'nombre' => $info['name'] ?? 'Vehículo',
    'tiempo_encendido' => $tiempo_encendido,
]);
