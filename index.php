<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === CONTROL DE CONTRASEÑA ===
$clave = ''; // contraseña acceso
$duracion = 60 * 60 * 24 * 2;

if (isset($_POST['pass']) && $_POST['pass'] === $clave) {
    setcookie('acceso_autorizado', '1', time() + $duracion, "/");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_COOKIE['acceso_autorizado'])) {
    echo '
    <form method="POST" style="margin:100px auto; max-width:320px; font-family:sans-serif; text-align:center; background:#fff; padding:30px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);">
        <h2 style="color:#ff6600;">Acceso protegido</h2>
        <label style="display:block; margin:20px 0 10px;">Introduce la contraseña:</label>
        <input type="password" name="pass" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px;" autofocus><br><br>
        <button type="submit" style="width:100%; padding:10px; background:#ff6600; color:white; border:none; border-radius:6px; font-weight:bold;">Entrar</button>
    </form>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Estado del Vehículo</title>
    <link rel="stylesheet" href="css/leaflet.css" />
    <link rel="stylesheet" href="css/style.css" />
    <link rel="icon" href="favicon.png" type="image/png">
    <meta name='viewport' content='user-scalable=no, width=device-width, initial-scale=1'>
    <!-- Icono de la pestaña -->
    <link rel="icon" type="image/png" href="/favicon.png" />

    <!-- Manifest PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ff6600" />

    <!-- Compatibilidad iOS (opcional pero recomendable) -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

</head>

<body>

    <div class="card">
        <h2>Estado actual del vehículo</h2>
        <p class="timestamp">Fecha y hora actual: <span id="horaActual"></span></p>
        <p id="conexionEstado" class="conexion estado-conectando">Conectando al vehículo...</p>
        <button id="actualizarBtn" class="btn">Actualizar ahora</button>
        <table id="estado">
            <tr>
                <th>Motor</th>
                <td id="ignition" class="estado">Cargando...</td>
            </tr>
            <tr>
                <th>Movimiento</th>
                <td id="movimiento" class="estado">Cargando...</td>
            </tr>
            <tr>
                <th>Velocidad</th>
                <td id="velocidad">Cargando...</td>
            </tr>
            <tr>
                <th>Kilometraje</th>
                <td id="km">Cargando...</td>
            </tr>
            <tr>
                <th>Voltaje coche</th>
                <td id="voltaje">Cargando...</td>
            </tr>
            <tr>
                <th>Voltaje GPS</th>
                <td id="bateria">Cargando...</td>
            </tr>
            <tr>
                <th>Señal GSM</th>
                <td id="gsm">Cargando...</td>
            </tr>
            <tr>
                <th>Estado GPS</th>
                <td id="gps_status">Cargando...</td>
            </tr>
            <tr>
                <th>Satélites</th>
                <td id="sat_count">Cargando...</td>
            </tr>
            <tr>
                <th>Tiempo encendido</th>
                <td id="tiempo_encendido">Cargando...</td>
            </tr>
            <tr>
                <th>Nombre/matrícula</th>
                <td id="vehiculo_nombre">Cargando...</td>
            </tr>
            <tr>
                <th>Coordenadas</th>
                <td id="coordenadas">Cargando...</td>
            </tr>
            <tr>
                <th>Dirección estimada</th>
                <td id="direccion">Cargando...</td>
            </tr>
            <tr>
                <th>Altitud</th>
                <td id="altitud">Cargando...</td>
            </tr>
            <tr>
                <th>Hora del GPS</th>
                <td id="gps_time">Cargando...</td>
            </tr>
        </table>
    </div>

    <div class="card">
        <h2>Ubicación en el mapa</h2>
        <div id="map"></div>
    </div>

    <div class="card center">
        <a href="historial.php" class="btn">Ver historial del vehículo</a>
        <a href="graficas.php" class="btn">Ver gráficas del vehículo</a>
    </div>

    <script src="js/leaflet.js"></script>
    <script src="js/script.js"></script>

</body>

</html>