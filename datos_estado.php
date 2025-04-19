<?php
header('Content-Type: application/json');

// === DATOS FLESPI ===
$token = ''; // aquí deberá introducir el token de flespi
$id_flespi = ''; //aquí se deberá introducir el id del dispositivo de flespi
$url = "https://flespi.io/gw/devices/$id_flespi/telemetry/all";

// === CONSULTA ===
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: FlespiToken $token"]
]);
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode !== 200 || !$response) {
    echo json_encode(['error' => true, 'message' => "Flespi error ($httpCode)"]);
    exit;
}

$data = json_decode($response, true);
$t = $data['result'][0]['telemetry'] ?? [];
$info = $data['result'][0]['device'] ?? [];

// === Coordenadas ===
$lat_raw = $t['position.latitude']['value'] ?? null;
$lon_raw = $t['position.longitude']['value'] ?? null;
$lat = is_numeric($lat_raw) ? floatval($lat_raw) : null;
$lon = is_numeric($lon_raw) ? floatval($lon_raw) : null;

// === GEOLOCALIZACIÓN INVERSA ===
$direccion = 'No disponible';
if ($lat !== null && $lon !== null) {
    $geo = @file_get_contents("https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=$lat&lon=$lon");
    if ($geo) {
        $geojson = json_decode($geo, true);
        $direccion = $geojson['display_name'] ?? 'No disponible';
    }
}

// === TIEMPO ENCENDIDO ===
$tiempo_encendido = "No disponible";
if (!empty($t['engine.ignition.status']['value']) && isset($t['engine.ignition.status']['timestamp'])) {
    $inicio_ts = $t['engine.ignition.status']['timestamp'];
    $tiempo = time() - $inicio_ts;
    $tiempo_encendido = gmdate("H:i:s", $tiempo);
}

// === RESPUESTA FINAL ===
echo json_encode([
    'error' => false,
    'ignition' => $t['engine.ignition.status']['value'] ? 'Encendido' : 'Apagado',
    'movimiento' => $t['movement.status']['value'] ? 'Sí' : 'No',
    'velocidad' => isset($t['position.speed']['value']) ? $t['position.speed']['value'] . ' km/h' : 'No disponible',
    'direccion_vehiculo' => isset($t['position.direction']['value']) ? $t['position.direction']['value'] . '°' : 'No disponible',
    'rotacion' => $t['position.direction']['value'] ?? 0,
    'altitud' => isset($t['position.altitude']['value']) ? $t['position.altitude']['value'] . ' m' : 'No disponible',
    'gps_valid' => isset($t['position.valid']['value']) && $t['position.valid']['value'] ? 'Sí' : 'No',
    'gps_status' => isset($t['position.valid']['value']) && $t['position.valid']['value'] ? 'Correcta' : 'Sin señal',
    'sat_count' => $t['gnss.satellites.count']['value'] ?? 'No disponible',
    'gps_time' => isset($t['position.latitude']['timestamp']) ? date("Y-m-d H:i:s", $t['position.latitude']['timestamp']) : 'No disponible',
    'km' => isset($t['vehicle.mileage']['value']) ? round($t['vehicle.mileage']['value'], 2) . ' km' : 'No disponible',
    'voltaje' => isset($t['external.powersource.voltage']['value']) ? $t['external.powersource.voltage']['value'] . ' V' : 'No disponible',
    'bateria' => isset($t['battery.voltage']['value']) ? $t['battery.voltage']['value'] . ' V' : 'No disponible',
    'bateria_porcentaje' => isset($t['battery.level']['value']) ? $t['battery.level']['value'] . '%' : 'No disponible',
    'gsm' => $t['gsm.signal.level']['value'] ?? 'No disponible',
    'lat' => $lat,
    'lon' => $lon,
    'direccion' => $direccion,
    'nombre' => $info['name'] ?? 'Vehículo',
    'tiempo_encendido' => $tiempo_encendido
]);
