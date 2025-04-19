<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === FUNCIONES ===
function haversine($lat1, $lon1, $lat2, $lon2) {
    $radio = 6371000; // en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return 2 * $radio * atan2(sqrt($a), sqrt(1-$a));
}

function enviarAlerta($zona, $lat, $lon) {
    $to = "";
    $subject = "Alerta: Vehículo ha salido de la zona segura ($zona)";
    $message = "El vehículo ha salido de la zona segura '$zona'.\n\nUbicación actual:\nLat: $lat\nLon: $lon\n\nhttps://www.google.com/maps/search/?api=1&query=$lat,$lon";
    $headers = "From: ";
    mail($to, $subject, $message, $headers);
}

// === ZONAS SEGURAS ===
$zonas = [
    'Zona 1' => ['lat' => , 'lon' => ],
    'Zona 2'       => ['lat' => , 'lon' =>]
];

$margen_metros = 200;

// === DATOS FLESPI ===
$token = ''; // aquí deberá introducir el token de flespi
$id_flespi = ''; //aquí se deberá introducir el id del dispositivo de flespi
$url = "https://flespi.io/gw/devices/$id_flespi/telemetry/all";

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: FlespiToken $token"]
]);
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode !== 200) exit("Error al consultar Flespi (HTTP $httpCode)");

$data = json_decode($response, true);
$t = $data['result'][0]['telemetry'] ?? [];

$lat = $t['position.latitude']['value'] ?? null;
$lon = $t['position.longitude']['value'] ?? null;

if (!$lat || !$lon) exit("Coordenadas no válidas.");

// === CARGA ESTADO ANTERIOR ===
$estado_file = 'zona_alerta_estado.json';
$estado_actual = file_exists($estado_file) ? json_decode(file_get_contents($estado_file), true) : [];

foreach ($zonas as $nombre => $coords) {
    $distancia = haversine($lat, $lon, $coords['lat'], $coords['lon']);

    if ($distancia > $margen_metros) {
        if (empty($estado_actual[$nombre]) || $estado_actual[$nombre] !== 'fuera') {
            enviarAlerta($nombre, $lat, $lon);
            $estado_actual[$nombre] = 'fuera';
        }
    } else {
        $estado_actual[$nombre] = 'dentro';
    }
}

// === GUARDAR ESTADO ACTUAL ===
file_put_contents($estado_file, json_encode($estado_actual, JSON_PRETTY_PRINT));