# DIM SUM Asistencia móvil

Aplicación Flutter nativa para colaboradores y supervisores. Consume únicamente
la API `mobile/v1`: no expone vistas, reportes ni permisos administrativos.

## Flujos incluidos

- Inicio de sesión por dispositivo con token seguro revocable.
- Marcación de asistencia: ingreso, salida/reingreso de refrigerio y salida.
- Resumen personal de las marcaciones del día actual.
- Ingreso y salida de visita de supervisor según el local autorizado.
- Cámara nativa para QR y captura compartible de la confirmación.

## Requisitos de compilación

- Flutter 3.47.7 / Dart 3.13.5.
- Android SDK con API 24 o posterior para APK/AAB (Android 7.0+).
- macOS con Xcode para compilar y firmar iOS (mínimo iOS 15).

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --release
```

La URL de la API puede cambiarse por entorno sin editar código:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://assist.dimsumexpress.cloud/api/mobile/v1
```
