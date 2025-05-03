let mapa = L.map('map').setView([36.7, -4.4], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(mapa);

let iconoCoche = L.icon({
    iconUrl: 'img/car-icon.png',
    iconSize: [60, 40],
    iconAnchor: [30, 50],
});

let marcador = L.marker([36.7, -4.4], { icon: iconoCoche }).addTo(mapa);
let ignition_anterior = null;
let conectado = false; // Estado de conexión persistente

function coordenadasValidas(lat, lon) {
    const latNum = parseFloat(lat);
    const lonNum = parseFloat(lon);
    return !isNaN(latNum) && !isNaN(lonNum) &&
        latNum >= -90 && latNum <= 90 &&
        lonNum >= -180 && lonNum <= 180;
}

function actualizarEstado() {
    const conexionEl = document.getElementById('conexionEstado');

    fetch('datos_estado.php')
        .then(r => r.json())
        .then(data => {
            // Mostrar conectado si las coordenadas son válidas
            if (coordenadasValidas(data.lat, data.lon)) {
                if (!conectado) {
                    conexionEl.textContent = 'Conectado al vehículo';
                    conexionEl.className = 'conexion estado-ok';
                    conectado = true;
                }
            } else {
                // Solo mostrar "No conectado" si nunca ha estado conectado
                if (!conectado) {
                    conexionEl.textContent = 'No conectado al vehículo';
                    conexionEl.className = 'conexion estado-error';
                }
            }

            // Actualizar datos visibles
            document.getElementById('ignition').textContent = data.ignition;
            document.getElementById('movimiento').textContent = data.movimiento;
            document.getElementById('velocidad').textContent = data.velocidad;
            document.getElementById('km').textContent = data.km;
            document.getElementById('voltaje').textContent = data.voltaje;
            document.getElementById('bateria').textContent = data.bateria;
            document.getElementById('gsm').textContent = data.gsm + "/100";
            document.getElementById('coordenadas').textContent = data.lat + ", " + data.lon;
            document.getElementById('direccion').textContent = data.direccion;
            document.getElementById('altitud').textContent = data.altitud;
            document.getElementById('horaActual').textContent = data.gps_time;

            document.getElementById('gps_status').textContent = data.gps_status;
            document.getElementById('sat_count').textContent = data.sat_count;
            document.getElementById('vehiculo_nombre').textContent = data.nombre;
            document.getElementById('tiempo_encendido').textContent = data.tiempo_encendido;

            document.getElementById('ignition').className = "estado " + (data.ignition === "Encendido" ? "activo" : "inactivo");
            document.getElementById('movimiento').className = "estado " + (data.movimiento === "Sí" ? "activo" : "inactivo");

            // Actualización del marcador y mapa
            if (coordenadasValidas(data.lat, data.lon)) {
                marcador.setLatLng([parseFloat(data.lat), parseFloat(data.lon)]);
                marcador.bindPopup(data.direccion).openPopup();
                mapa.setView([data.lat, data.lon]);
            }

            if (ignition_anterior === false && data.ignition === "Encendido") {
                document.getElementById('arranque').play();
            }

            ignition_anterior = data.ignition === "Encendido";
        })
        .catch(err => {
            // Si falla el fetch y nunca se conectó, mostrar error
            if (!conectado) {
                conexionEl.textContent = 'No conectado al vehículo';
                conexionEl.className = 'conexion estado-error';
            }
            console.error("Error al obtener datos:", err);
        });
}

// Botón manual de actualización
document.getElementById('actualizarBtn').addEventListener('click', actualizarEstado);

// Primer actualización y cada 15s
actualizarEstado();
setInterval(actualizarEstado, 15000);