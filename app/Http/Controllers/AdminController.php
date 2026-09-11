<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function __construct()
    {
        // Share pending counts to all admin views for sidebar badges
        View::share('pendingShipments', Shipment::where('status', 'pending')->count());
    }

    /**
     * Dashboard with statistics.
     */
    public function dashboard()
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'delivering_orders' => Order::whereIn('status', ['assigned', 'picking_up', 'delivering'])->count(),
            'completed_orders' => Order::where('status', 'delivered')->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'total_couriers' => Courier::count(),
            'active_couriers' => Courier::where('is_active', true)->count(),
            'verified_couriers' => Courier::where('is_verified', true)->count(),
            'unverified_couriers' => Courier::where('is_verified', false)->count(),
            'total_revenue' => Order::where('status', 'delivered')->sum('price'),
        ];

        $recentOrders = Order::with('courier.user')
            ->latest()
            ->take(5)
            ->get();

        $activeCouriers = Courier::with('user')
            ->where('is_active', true)
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'activeCouriers'));
    }

    /**
     * Courier management page (Manajemen Armada Kurir).
     */
    public function couriers()
    {
        $couriers = Courier::with([
            'user',
            // Today's delivered orders = "Rekap Hari Ini" for each courier
            'orders' => function ($q) {
                $q->where('status', 'delivered')->whereDate('delivered_at', today());
            },
        ])->latest()->paginate(10);

        $stats = [
            'total' => Courier::count(),
            'active' => Courier::where('is_active', true)->count(),
            'verified' => Courier::where('is_verified', true)->count(),
            'unverified' => Courier::where('is_verified', false)->count(),
        ];

        return view('admin.couriers', compact('couriers', 'stats'));
    }

    /**
     * Export courier data as CSV.
     */
    public function exportCouriers()
    {
        $couriers = Courier::with('user')->latest()->get();

        $filename = 'data-kurir-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($couriers) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM (Excel compatibility)

            fputcsv($handle, [
                'Nama', 'Email', 'Telepon', 'Kota', 'Kendaraan', 'Merk', 'Plat Nomor',
                'Bergabung', 'Verifikasi', 'Status',
            ]);

            foreach ($couriers as $courier) {
                fputcsv($handle, [
                    $courier->user?->name ?? '-',
                    $courier->user?->email ?? '-',
                    $courier->phone ?? '-',
                    $courier->city ?? '-',
                    $courier->vehicle_type ?? '-',
                    $courier->vehicle_brand ?? '-',
                    $courier->vehicle_plate ?? '-',
                    $courier->created_at?->format('d M Y') ?? '-',
                    $courier->is_verified ? 'Terverifikasi' : 'Belum Verifikasi',
                    $courier->is_active ? 'Aktif' : 'Nonaktif',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Create a new courier (user + courier profile) from the admin panel.
     */
    public function storeCourier(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:6|max:255',
            'phone' => 'nullable|string|max:20',
            'nik' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'vehicle_type' => 'required|in:motor,mobil,pickup,box,truck,sepeda',
            'vehicle_brand' => 'nullable|string|max:100',
            'vehicle_year' => 'nullable|string|max:5',
            'vehicle_plate' => 'required|string|max:20',
            'activate' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'id_card_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'driving_license_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'skck_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $password = $request->filled('password')
            ? $request->input('password')
            : Str::random(10);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($password),
            'role' => 'courier',
        ]);
        $user->syncRoles(['courier']);

        $activate = $request->boolean('activate', true);

        $courier = Courier::create([
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'nik' => $data['nik'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'photo' => $this->storeCourierPhoto($request, 'photo', 'couriers/photos'),
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_brand' => $data['vehicle_brand'] ?? null,
            'vehicle_year' => $data['vehicle_year'] ?? null,
            'vehicle_plate' => strtoupper($data['vehicle_plate']),
            'id_card_photo' => $this->storeCourierPhoto($request, 'id_card_photo', 'couriers/documents'),
            'driving_license_photo' => $this->storeCourierPhoto($request, 'driving_license_photo', 'couriers/documents'),
            'skck_photo' => $this->storeCourierPhoto($request, 'skck_photo', 'couriers/documents'),
            'is_verified' => $activate,
            'is_active' => $activate,
        ]);

        $msg = "Kurir {$user->name} berhasil ditambahkan. "
            . ($request->filled('password') ? '' : "Password awal: {$password}. ");

        return back()->with('success', rtrim($msg));
    }

    /**
     * Update an existing courier profile from the admin panel.
     */
    public function updateCourier(Request $request, Courier $courier)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $courier->user_id,
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'nik' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'vehicle_type' => 'required|in:motor,mobil,pickup,box,truck,sepeda',
            'vehicle_brand' => 'nullable|string|max:100',
            'vehicle_year' => 'nullable|string|max:5',
            'vehicle_plate' => 'required|string|max:20',
            'is_active' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'id_card_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'driving_license_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
            'skck_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:4096',
        ]);

        $courier->user?->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $courier->update([
            'phone' => $data['phone'] ?? null,
            'nik' => $data['nik'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'photo' => $this->replaceCourierPhoto($courier->photo, $request, 'photo', 'couriers/photos'),
            'vehicle_type' => $data['vehicle_type'],
            'vehicle_brand' => $data['vehicle_brand'] ?? null,
            'vehicle_year' => $data['vehicle_year'] ?? null,
            'vehicle_plate' => strtoupper($data['vehicle_plate']),
            'id_card_photo' => $this->replaceCourierPhoto($courier->id_card_photo, $request, 'id_card_photo', 'couriers/documents'),
            'driving_license_photo' => $this->replaceCourierPhoto($courier->driving_license_photo, $request, 'driving_license_photo', 'couriers/documents'),
            'skck_photo' => $this->replaceCourierPhoto($courier->skck_photo, $request, 'skck_photo', 'couriers/documents'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Data kurir {$courier->user?->name} berhasil diperbarui.");
    }

    /**
     * Store an uploaded courier document photo onto the public disk.
     */
    private function storeCourierPhoto(Request $request, string $field, string $folder): ?string
    {
        if ($request->hasFile($field)) {
            return $request->file($field)->store($folder, 'public');
        }

        return null;
    }

    /**
     * Replace a courier document photo when a new file is uploaded (keeps old one otherwise).
     */
    private function replaceCourierPhoto(?string $current, Request $request, string $field, string $folder): ?string
    {
        if (! $request->hasFile($field)) {
            return $current;
        }

        if ($current && Storage::disk('public')->exists($current)) {
            Storage::disk('public')->delete($current);
        }

        return $request->file($field)->store($folder, 'public');
    }

    /**
     * Toggle courier verification status.
     */
    public function toggleVerifyCourier(Courier $courier)
    {
        $courier->update([
            'is_verified' => !$courier->is_verified,
            'is_active' => !$courier->is_verified, // activate when verified
        ]);

        return back()->with('success', 'Status kurir berhasil diperbarui.');
    }

    /**
     * Toggle courier active status.
     */
    public function toggleActiveCourier(Courier $courier)
    {
        $courier->update(['is_active' => !$courier->is_active]);
        return back()->with('success', 'Status aktif kurir berhasil diperbarui.');
    }

    /**
     * Order management page.
     */
    public function orders(Request $request)
    {
        $query = Order::with(['courier.user', 'shipment']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate(10);

        $statusCounts = [
            'all' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'assigned' => Order::where('status', 'assigned')->count(),
            'picking_up' => Order::where('status', 'picking_up')->count(),
            'delivering' => Order::where('status', 'delivering')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        return view('admin.orders', compact('orders', 'statusCounts'));
    }

    /**
     * Show single order detail.
     */
    public function orderDetail(Order $order)
    {
        $order->load(['courier.user', 'shipment']);
        return view('admin.order-detail', compact('order'));
    }

    /**
     * Remove an order from storage.
     */
    public function destroyOrder(Order $order)
    {
        $order->delete();

        return redirect()->route('admin.orders')
            ->with('success', 'Pesanan berhasil dihapus.');
    }
}
