@echo off
REM Flutter Run with Auto APK Sync
REM This script runs flutter build and syncs APK to database

echo ========================================
echo   CityCourier - Flutter Run + APK Sync
echo ========================================
echo.

cd /d F:\Flutter\city_courierapp

echo [1/3] Building APK...
flutter build apk --debug
if %errorlevel% neq 0 (
    echo Build failed!
    pause
    exit /b 1
)

echo.
echo [2/3] Syncing APK to database...
cd /d C:\laragon\www\City-Courier-web
php artisan app:sync-apk --path=F:\Flutter\city_courierapp\build\app\outputs\flutter-apk\app-debug.apk

echo.
echo [3/3] Starting Flutter...
cd /d F:\Flutter\city_courierapp
flutter run

pause
