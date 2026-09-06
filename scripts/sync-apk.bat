@echo off
REM Sync APK to Database
REM Run this after flutter run to sync the latest APK

echo ========================================
echo   Syncing APK to Database...
echo ========================================

cd /d C:\laragon\www\City-Courier-web

REM Check for APK in Flutter build directory
if exist "F:\Flutter\city_courierapp\build\app\outputs\flutter-apk\app-debug.apk" (
    echo Found debug APK
    php artisan app:sync-apk --path=F:\Flutter\city_courierapp\build\app\outputs\flutter-apk\app-debug.apk
) else if exist "F:\Flutter\city_courierapp\build\app\outputs\flutter-apk\app-release.apk" (
    echo Found release APK
    php artisan app:sync-apk --path=F:\Flutter\city_courierapp\build\app\outputs\flutter-apk\app-release.apk
) else (
    echo No APK found in Flutter build directory!
    echo Please run 'flutter build apk' first.
)

echo.
pause
