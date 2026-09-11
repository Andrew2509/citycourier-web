<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;

class DemoFinanceSeeder extends Seeder
{
    /**
     * Seed demo data for the Keuangan & Setoran Kurir (Ci-Work Finance) page.
     *
     * Idempotent:
     * - Ensures the demo courier has exactly 2 delivered orders TODAY (Rp 30.000 omzet,
     *   10% komisi = Rp 3.000, bersih Rp 27.000) so the page matches the mockup.
     * - Creates/reconciles the wallet (available balance = net earnings).
     * - Creates wallet transactions (earning) for each of today's delivered orders.
     * - Seeds 1 pending and 1 completed withdrawal when none exist.
     * - Seeds finance settings (commission rate, min withdrawal, admin fee).
     */
    public function run(): void
    {
        $courier = Courier::whereHas('user', fn ($q) => $q->where('email', 'masbrightly@gmail.com'))->first();

        if (! $courier) {
            $this->command->warn('Demo courier (masbrightly@gmail.com) not found. Skipping finance seed.');

            return;
        }

        // ─── Finance settings ──────────────────────────────────────
        Setting::set('finance_commission_rate', '10', 'finance');
        Setting::set('finance_withdrawal_minimum', '20000', 'finance');
        Setting::set('finance_withdrawal_admin_fee', '0', 'finance');

        // ─── Ensure exactly 2 delivered orders today ──────────────
        $countToday = Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        while ($countToday < 2) {
            $idx = $countToday + 1;

            Order::create([
                'order_number' => Order::generateOrderNumber(),
                'courier_id' => $courier->id,
                'customer_name' => $idx === 2 ? 'Maya Sari' : 'Andi Saputra',
                'customer_phone' => $idx === 2 ? '089876543215' : '089876543299',
                'pickup_address' => $idx === 2 ? 'Jl. Menteng No. 10, Jakarta Pusat' : 'Jl. Darmo No. 12, Surabaya',
                'delivery_address' => $idx === 2 ? 'Jl. BSD No. 44, Tangerang Selatan' : 'Jl. Basuki Rahmat No. 5, Surabaya',
                'package_description' => $idx === 2 ? 'Kado ulang tahun' : 'Dokumen penting',
                'package_weight' => 0.8,
                'price' => 15000,
                'status' => 'delivered',
                'picked_up_at' => now()->subHours($idx + 2),
                'delivering_at' => now()->subHours($idx + 1),
                'delivered_at' => now()->subMinutes(15 * $idx),
            ]);

            $countToday++;
        }

        $todayOrders = Order::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->orderBy('delivered_at')
            ->take(2)
            ->get();

        $omzet      = (float) $todayOrders->sum('price');
        $commission = round($omzet * 10 / 100, 0);
        $net        = $omzet - $commission;

        // ─── Wallet ───────────────────────────────────────────────
        $wallet = Wallet::updateOrCreate(
            ['courier_id' => $courier->id],
            ['available_balance' => $net, 'pending_balance' => 0]
        );

        // ─── Earning transactions ─────────────────────────────────
        foreach ($todayOrders as $order) {
            $orderFee = round($order->price * 10 / 100, 0);

            WalletTransaction::updateOrCreate(
                [
                    'wallet_id' => $wallet->id,
                    'order_id'  => $order->id,
                ],
                [
                    'courier_id'  => $courier->id,
                    'type'        => 'earning',
                    'amount'      => (float) $order->price,
                    'fee'         => $orderFee,
                    'net_amount'  => (float) $order->price - $orderFee,
                    'status'      => 'success',
                    'reference'   => $order->order_number,
                    'description' => 'Pendapatan pengiriman ' . $order->order_number,
                ]
            );
        }

        // ─── Withdrawals (only when none exist) ───────────────────
        $name = $courier->user->name ?? 'Mitra Kurir';

        if (! Withdrawal::where('courier_id', $courier->id)->where('status', 'pending')->exists()) {
            Withdrawal::create([
                'courier_id'     => $courier->id,
                'wallet_id'      => $wallet->id,
                'bank_name'      => 'Bank Rakyat Indonesia (BRI)',
                'account_number' => '002901072327503',
                'account_name'   => $name,
                'amount'         => 20000,
                'fee'            => 0,
                'net_amount'     => 20000,
                'status'         => 'pending',
            ]);
        }

        if (! Withdrawal::where('courier_id', $courier->id)->whereIn('status', ['approved', 'completed'])->exists()) {
            Withdrawal::create([
                'courier_id'     => $courier->id,
                'wallet_id'      => $wallet->id,
                'bank_name'      => 'Bank Rakyat Indonesia (BRI)',
                'account_number' => '002901072327503',
                'account_name'   => $name,
                'amount'         => 15000,
                'fee'            => 0,
                'net_amount'     => 15000,
                'status'         => 'completed',
                'processed_at'   => now()->subDays(2),
            ]);
        }

        $this->command->info("Demo finance seeded for courier id {$courier->id}: omzet {$omzet}, komisi {$commission}, bersih {$net}, wallet {$wallet->id}.");
    }
}