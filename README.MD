# Control de Vehículo con Flespi

Este proyecto permite monitorizar y controlar un vehículo en tiempo real mediante la API de [Flespi](https://flespi.com/). Incluye funcionalidades para registrar trayectos, visualizar estadísticas gráficas y recibir alertas por correo cuando el vehículo abandona zonas seguras definidas por el usuario.

## 🔧 Funcionalidades

- Consulta en tiempo real del estado del vehículo.
- Registro automático de trayectos con guardado histórico.
- Gráficas del uso del vehículo.
- Alertas por geocercas enviadas por correo electrónico.
- Interfaz web con control de acceso por clave.

## 🚀 Archivos a configurar

### `auto_recoge.php`
- **`$token`**: Token de acceso generado desde tu cuenta Flespi.
- **`$id`**: ID de tu dispositivo Teltonika (u otro compatible con Flespi).

### `datos_estado.php`
- **`$token`**: Token Flespi.
- **`$id`**: ID del dispositivo.

### `geofence_alert.php`
- **`$token`**: Token Flespi.
- **`$id`**: ID del dispositivo.
- **`$from`**: Dirección de correo electrónico que enviará las alertas.
- **`$to`**: Dirección de correo electrónico que recibirá las alertas.
- **`$zonas`**: Array de coordenadas `[lat, lng]` que definen las zonas seguras.

### `index.php`
- **`$clave`**: Clave de acceso para visualizar la información del vehículo desde el navegador. Esta clave se utilizará como medida básica de seguridad para proteger el acceso al panel.

## 🕐 Tareas programadas (cron)

Para que el sistema funcione automáticamente, debes crear dos tareas programadas que se ejecuten cada minuto:

```bash
* * * * * /usr/bin/php /ruta/completa/auto_recoge.php
* * * * * /usr/bin/php /ruta/completa/geofence_alert.php
