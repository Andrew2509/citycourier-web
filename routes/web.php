<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Admin\ResearchStudyAdminController;
use App\Http\Controllers\ResearchStudyController;
use Illuminate\Support\Facades\Route;

// ─── Guest Routes ────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// ─── Download APK (Google Drive - fast download) ────────────
Route::get('/download/app', function () {
    $download = \App\Models\AppDownload::getActive();
    
    if (!$download) {
        abort(404, 'APK tidak ditemukan');
    }

    // Check for Google Drive link first
    if ($download->google_drive_url) {
        return redirect($download->google_drive_url);
    }

    // Fallback: serve from server
    $publicPath = public_path('downloads/' . $download->filename);
    if (file_exists($publicPath)) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="CityCourier.apk"');
        header('Content-Length: ' . filesize($publicPath));
        header('Cache-Control: public, max-age=3600');
        readfile($publicPath);
        exit;
    }

    $storagePath = storage_path('app/public/' . $download->file_path);
    if (file_exists($storagePath)) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="CityCourier.apk"');
        header('Content-Length: ' . filesize($storagePath));
        header('Cache-Control: public, max-age=3600');
        readfile($storagePath);
        exit;
    }

    abort(404, 'File APK tidak ditemukan');
})->name('download.app');

// ─── Public Live Tracking & QR Code ──────────────────────────
Route::get('/track/{number}', [\App\Http\Controllers\TrackingWebController::class, 'show'])->name('tracking.show');
Route::get('/track/{number}/data', [\App\Http\Controllers\TrackingWebController::class, 'data'])->name('tracking.data');
Route::get('/track/{number}/qrcode.png', [\App\Http\Controllers\TrackingWebController::class, 'qrcode'])->name('tracking.qrcode');
Route::get('/shipments/{number}/qrcode.png', [\App\Http\Controllers\TrackingWebController::class, 'qrcode'])->name('shipments.qrcode');

// ─── Public Research Study Form (Tugas Akhir UI/UX) ──────────
Route::get('/studi-pendahuluan', [ResearchStudyController::class, 'show'])->name('research.create');
Route::post('/studi-pendahuluan', [ResearchStudyController::class, 'store'])
    ->middleware('throttle:12,1')
    ->name('research.store');
Route::get('/studi-pendahuluan/terima-kasih', [ResearchStudyController::class, 'thankyou'])->name('research.thankyou');


// ─── Auth Routes ─────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');        // Admin Panel
        Route::prefix('admin')->name('admin.')->middleware('menu.permission')->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard.index');

        // User Management
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
        Route::resource('permissions', \App\Http\Controllers\Admin\PermissionController::class);
        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);

        // Couriers
        Route::get('/couriers', [AdminController::class, 'couriers'])->name('couriers');
        Route::get('/couriers/export', [AdminController::class, 'exportCouriers'])->name('couriers.export');
        Route::post('/couriers', [AdminController::class, 'storeCourier'])->name('couriers.store');
        Route::put('/couriers/{courier}', [AdminController::class, 'updateCourier'])->name('couriers.update');
        Route::patch('/couriers/{courier}/verify', [AdminController::class, 'toggleVerifyCourier'])->name('couriers.verify');
        Route::patch('/couriers/{courier}/toggle-active', [AdminController::class, 'toggleActiveCourier'])->name('couriers.toggle-active');

        // Orders
        Route::get('/orders', [AdminController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [AdminController::class, 'orderDetail'])->name('orders.detail');
        Route::post('/orders/{order}/courier-cancel-decision', [AdminController::class, 'handleCourierCancelDecision'])->name('orders.courier-cancel-decision');
        Route::delete('/orders/{order}', [AdminController::class, 'destroyOrder'])->name('orders.destroy');

        // Shipments (Request Pickup dari Flutter)
        Route::get('/shipments', [\App\Http\Controllers\Admin\ShipmentController::class, 'index'])->name('shipments.index');
        Route::get('/shipments/create', [\App\Http\Controllers\Admin\ShipmentController::class, 'create'])->name('shipments.create');
        Route::post('/shipments', [\App\Http\Controllers\Admin\ShipmentController::class, 'store'])->name('shipments.store');
        Route::post('/shipments/match-region', [\App\Http\Controllers\Admin\ShipmentController::class, 'matchRegion'])->name('shipments.match-region');
        Route::get('/shipments/regions/provinces', [\App\Http\Controllers\Admin\ShipmentController::class, 'getProvinces'])->name('shipments.regions.provinces');
        Route::get('/shipments/regions/cities', [\App\Http\Controllers\Admin\ShipmentController::class, 'getCities'])->name('shipments.regions.cities');
        Route::get('/shipments/regions/districts', [\App\Http\Controllers\Admin\ShipmentController::class, 'getDistricts'])->name('shipments.regions.districts');
        Route::delete('/shipments/delete-all', [\App\Http\Controllers\Admin\ShipmentController::class, 'destroyAll'])->name('shipments.destroy-all');
        Route::get('/shipments/{shipment}', [\App\Http\Controllers\Admin\ShipmentController::class, 'show'])->name('shipments.show');
        Route::patch('/shipments/{shipment}', [\App\Http\Controllers\Admin\ShipmentController::class, 'update'])->name('shipments.update');
        Route::post('/shipments/{shipment}/logs', [\App\Http\Controllers\Admin\ShipmentController::class, 'addLog'])->name('shipments.logs.store');
        Route::post('/shipments/{shipment}/send-whatsapp', [\App\Http\Controllers\Admin\ShipmentController::class, 'sendWhatsapp'])->name('shipments.send-whatsapp');
        Route::delete('/shipments/{shipment}', [\App\Http\Controllers\Admin\ShipmentController::class, 'destroy'])->name('shipments.destroy');

        // Settings / Provider (Unified)
        Route::get('/providers', [\App\Http\Controllers\Admin\SettingController::class, 'providers'])->name('settings.providers');
        Route::post('/providers/{provider}/toggle', [\App\Http\Controllers\Admin\SettingController::class, 'toggleProvider'])->name('settings.providers.toggle');

        // Legacy individual settings (kept for direct access / bookmarks)
        Route::get('/settings/whatsapp', [\App\Http\Controllers\Admin\SettingController::class, 'whatsapp'])->name('settings.whatsapp');
        Route::post('/settings/whatsapp', [\App\Http\Controllers\Admin\SettingController::class, 'updateWhatsapp'])->name('settings.whatsapp.update');
        Route::post('/settings/whatsapp/test', [\App\Http\Controllers\Admin\SettingController::class, 'testWhatsapp'])->name('settings.whatsapp.test');

        Route::get('/settings/rajaongkir', [\App\Http\Controllers\Admin\SettingController::class, 'rajaongkir'])->name('settings.rajaongkir');
        Route::post('/settings/rajaongkir', [\App\Http\Controllers\Admin\SettingController::class, 'updateRajaongkir'])->name('settings.rajaongkir.update');
        Route::post('/settings/rajaongkir/test', [\App\Http\Controllers\Admin\SettingController::class, 'testRajaongkir'])->name('settings.rajaongkir.test');

        Route::get('/settings/payment', [\App\Http\Controllers\Admin\SettingController::class, 'payment'])->name('settings.payment');
        Route::post('/settings/payment', [\App\Http\Controllers\Admin\SettingController::class, 'updatePayment'])->name('settings.payment.update');
        Route::post('/settings/payment/test', [\App\Http\Controllers\Admin\SettingController::class, 'testPayment'])->name('settings.payment.test');

        Route::get('/settings/map', [\App\Http\Controllers\Admin\SettingController::class, 'map'])->name('settings.map');
        Route::post('/settings/map', [\App\Http\Controllers\Admin\SettingController::class, 'updateMap'])->name('settings.map.update');
        Route::post('/settings/map/test', [\App\Http\Controllers\Admin\SettingController::class, 'testMap'])->name('settings.map.test');

        Route::get('/settings/dana', [\App\Http\Controllers\Admin\SettingController::class, 'dana'])->name('settings.dana');
        Route::post('/settings/dana', [\App\Http\Controllers\Admin\SettingController::class, 'updateDana'])->name('settings.dana.update');
        Route::post('/settings/dana/test', [\App\Http\Controllers\Admin\SettingController::class, 'testDana'])->name('settings.dana.test');

        Route::get('/settings/ovo', [\App\Http\Controllers\Admin\SettingController::class, 'ovo'])->name('settings.ovo');
        Route::post('/settings/ovo', [\App\Http\Controllers\Admin\SettingController::class, 'updateOvo'])->name('settings.ovo.update');
        Route::post('/settings/ovo/test', [\App\Http\Controllers\Admin\SettingController::class, 'testOvo'])->name('settings.ovo.test');

        Route::post('/settings/xendit', [\App\Http\Controllers\Admin\SettingController::class, 'updateXendit'])->name('settings.xendit.update');
        Route::post('/settings/xendit/test', [\App\Http\Controllers\Admin\SettingController::class, 'testXendit'])->name('settings.xendit.test');


        // Drop Points
        Route::get('drop-points/export', [\App\Http\Controllers\Admin\DropPointController::class, 'exportCsv'])->name('drop-points.export');
        Route::resource('drop-points', \App\Http\Controllers\Admin\DropPointController::class);
        Route::patch('drop-points/{drop_point}/toggle-active', [\App\Http\Controllers\Admin\DropPointController::class, 'toggleActive'])->name('drop-points.toggle-active');
        Route::patch('drop-points/{drop_point}/radius', [\App\Http\Controllers\Admin\DropPointController::class, 'updateRadius'])->name('drop-points.radius');

        // App Download Management
        Route::get('/app-download', [\App\Http\Controllers\Admin\AppDownloadController::class, 'index'])->name('app-download');
        Route::post('/app-download', [\App\Http\Controllers\Admin\AppDownloadController::class, 'store'])->name('app-download.store');
        Route::delete('/app-download/{appDownload}', [\App\Http\Controllers\Admin\AppDownloadController::class, 'destroy'])->name('app-download.destroy');
        Route::post('/app-download/{appDownload}/set-active', [\App\Http\Controllers\Admin\AppDownloadController::class, 'setActive'])->name('app-download.set-active');

        // Ci-Work Operational
        Route::prefix('ci-work')->name('ci-work.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\CiWorkController::class, 'index'])->name('index');
            Route::get('/tasks/refresh', [\App\Http\Controllers\Admin\CiWorkController::class, 'refreshTasks'])->name('tasks.refresh');
            Route::post('/auto-assign', [\App\Http\Controllers\Admin\CiWorkController::class, 'autoAssign'])->name('auto-assign');
            Route::post('/queue/assign/{order}', [\App\Http\Controllers\Admin\CiWorkController::class, 'assignQueueOrder'])->name('queue.assign');
            Route::get('/attendance', [\App\Http\Controllers\Admin\CiWorkController::class, 'attendance'])->name('attendance');
            Route::get('/attendance/locations', [\App\Http\Controllers\Admin\CiWorkController::class, 'attendanceLocations'])->name('attendance.locations');
            Route::get('/attendance/export', [\App\Http\Controllers\Admin\CiWorkController::class, 'exportAttendance'])->name('attendance.export');
            Route::post('/attendance/{courier}/toggle-active', [\App\Http\Controllers\Admin\CiWorkController::class, 'toggleActive'])->name('attendance.toggle-active');
            Route::get('/tasks', [\App\Http\Controllers\Admin\CiWorkController::class, 'tasks'])->name('tasks');
            Route::get('/finance', [\App\Http\Controllers\Admin\CiWorkController::class, 'finance'])->name('finance');
            Route::post('/finance/commission', [\App\Http\Controllers\Admin\CiWorkController::class, 'updateCommission'])->name('finance.commission');
            Route::get('/finance/export', [\App\Http\Controllers\Admin\CiWorkController::class, 'exportFinance'])->name('finance.export');
            Route::post('/finance/reconcile/{courier}', [\App\Http\Controllers\Admin\CiWorkController::class, 'reconcile'])->name('finance.reconcile');
            Route::post('/finance/withdrawal/{id}', [\App\Http\Controllers\Admin\CiWorkController::class, 'updateWithdrawalStatus'])->name('finance.withdrawal.update');
            Route::post('/finance/withdrawal/{id}/check', [\App\Http\Controllers\Admin\CiWorkController::class, 'checkWithdrawalStatus'])->name('finance.withdrawal.check');
        Route::get('/finance/dana-transactions', [\App\Http\Controllers\Admin\CiWorkController::class, 'danaTransactions'])->name('finance.dana-transactions');
        });

        // Penelitian — Studi Pendahuluan (khusus admin)
        Route::prefix('studi-pendahuluan')->name('research.')->middleware('admin')->group(function () {
            Route::get('/', [ResearchStudyAdminController::class, 'index'])->name('index');
            Route::get('/export/excel', [ResearchStudyAdminController::class, 'exportExcel'])->name('export.excel');
            Route::get('/export/csv', [ResearchStudyAdminController::class, 'exportCsv'])->name('export.csv');
            Route::get('/export/pdf', [ResearchStudyAdminController::class, 'exportPdf'])->name('export.pdf');
            Route::get('/{submission}', [ResearchStudyAdminController::class, 'show'])->name('show');
            Route::get('/{submission}/pdf', [ResearchStudyAdminController::class, 'exportDetailPdf'])->name('pdf-detail');
            Route::delete('/{submission}', [ResearchStudyAdminController::class, 'destroy'])->name('destroy');
        });

        // Payment & Wallet Finance Command Center (Admin Only)
        Route::prefix('finance')->name('finance.')->middleware('admin')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('index');
            Route::get('/wallets', [\App\Http\Controllers\Admin\FinanceController::class, 'wallets'])->name('wallets');
            Route::post('/wallets/{wallet}/freeze', [\App\Http\Controllers\Admin\FinanceController::class, 'freezeWallet'])->name('wallets.freeze');
            Route::post('/wallets/{wallet}/unfreeze', [\App\Http\Controllers\Admin\FinanceController::class, 'unfreezeWallet'])->name('wallets.unfreeze');

            Route::get('/topups', [\App\Http\Controllers\Admin\FinanceController::class, 'topups'])->name('topups');
            Route::post('/topups/{topup}/simulate', [\App\Http\Controllers\Admin\FinanceController::class, 'simulateTopupPaid'])->name('topups.simulate');

            Route::get('/payments', [\App\Http\Controllers\Admin\FinanceController::class, 'payments'])->name('payments');
            Route::get('/withdrawals', [\App\Http\Controllers\Admin\FinanceController::class, 'withdrawals'])->name('withdrawals');

            Route::get('/earnings', [\App\Http\Controllers\Admin\FinanceController::class, 'earnings'])->name('earnings');
            Route::post('/earnings/release', [\App\Http\Controllers\Admin\FinanceController::class, 'releaseMatureEarnings'])->name('earnings.release');

            Route::get('/payouts', [\App\Http\Controllers\Admin\FinanceController::class, 'payouts'])->name('payouts');
            Route::post('/payouts/run-auto', [\App\Http\Controllers\Admin\FinanceController::class, 'triggerAutoPayouts'])->name('payouts.run-auto');
            Route::post('/payouts/{payout}/verify', [\App\Http\Controllers\Admin\FinanceController::class, 'verifyPayout'])->name('payouts.verify');

            Route::get('/refunds', [\App\Http\Controllers\Admin\FinanceController::class, 'refunds'])->name('refunds');
            Route::post('/refunds/{refund}/approve', [\App\Http\Controllers\Admin\FinanceController::class, 'approveRefund'])->name('refunds.approve');
            Route::post('/refunds/{refund}/reject', [\App\Http\Controllers\Admin\FinanceController::class, 'rejectRefund'])->name('refunds.reject');

            Route::get('/destinations', [\App\Http\Controllers\Admin\FinanceController::class, 'destinations'])->name('destinations');

            Route::get('/settings', [\App\Http\Controllers\Admin\FinanceController::class, 'settings'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\Admin\FinanceController::class, 'updateSettings'])->name('settings.update');

            Route::get('/reconciliation', [\App\Http\Controllers\Admin\FinanceController::class, 'reconciliation'])->name('reconciliation');
            Route::post('/reconciliation/run', [\App\Http\Controllers\Admin\FinanceController::class, 'runReconciliationNow'])->name('reconciliation.run');

            Route::get('/reports', [\App\Http\Controllers\Admin\FinanceController::class, 'reports'])->name('reports');
            Route::get('/reports/export', [\App\Http\Controllers\Admin\FinanceController::class, 'exportReport'])->name('reports.export');

            Route::get('/audit-logs', [\App\Http\Controllers\Admin\FinanceController::class, 'auditLogs'])->name('audit-logs');
        });
    });
});

// Redirect root to safe location
Route::get('/', function () {
    if (\Illuminate\Support\Facades\Auth::check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
});
