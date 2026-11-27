<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth untuk status ketersediaan Courier dan aturan
 * kelayakan penerimaan / penawaran pesanan baru.
 */
class CourierAvailabilityService
{
    private ?bool $hasWorkStatusColumn = null;

    private ?bool $hasStatusChangedAtColumn = null;

    /**
     * Ambil pesanan aktif milik kurir (status masih berjalan & belum selesai/dibatalkan).
     */
    public function findActiveOrderForCourier(Courier $courier, bool $lock = false): ?Order
    {
        $query = Order::where('courier_id', $courier->id)
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->latest('updated_at');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Hitung status ketersediaan efektif kurir saat ini.
     */
    public function resolveWorkStatus(Courier $courier, ?Order $activeOrder = null): string
    {
        if ($activeOrder !== null) {
            return Courier::STATUS_ON_DELIVERY;
        }

        $storedStatus = $this->hasWorkStatusColumn()
            ? strtolower((string) ($courier->work_status ?? ''))
            : '';

        if (! $courier->is_active) {
            return $storedStatus === Courier::STATUS_BUSY
                ? Courier::STATUS_BUSY
                : Courier::STATUS_OFFLINE;
        }

        if ($storedStatus === Courier::STATUS_OFFLINE || $storedStatus === Courier::STATUS_BUSY) {
            return $storedStatus;
        }

        return Courier::STATUS_ONLINE;
    }

    /**
     * Evaluasi apakah kurir berhak menerima penawaran & menerima pesanan baru.
     */
    public function evaluateAvailability(Courier $courier, ?Order $activeOrder = null): array
    {
        $activeOrder ??= $this->findActiveOrderForCourier($courier);
        $workStatus = $this->resolveWorkStatus($courier, $activeOrder);

        if (! $courier->is_verified) {
            return [
                'allowed'     => false,
                'work_status' => $workStatus,
                'reason_code' => 'courier_not_verified',
                'message'     => 'Akun kurir belum terverifikasi atau belum memenuhi syarat operasional.',
                'active_order' => $activeOrder,
            ];
        }

        if ($activeOrder !== null) {
            $tracking = $activeOrder->tracking_number ?? $activeOrder->order_number ?? ('#' . $activeOrder->id);

            return [
                'allowed'      => false,
                'work_status'  => Courier::STATUS_ON_DELIVERY,
                'reason_code'  => 'courier_busy',
                'message'      => "Anda sedang mengerjakan pesanan aktif ({$tracking}). Selesaikan atau batalkan pesanan terlebih dahulu sebelum menerima pesanan baru.",
                'active_order' => $activeOrder,
            ];
        }

        if ($workStatus === Courier::STATUS_OFFLINE || ! $courier->is_active) {
            return [
                'allowed'      => false,
                'work_status'  => Courier::STATUS_OFFLINE,
                'reason_code'  => 'courier_offline',
                'message'      => 'Mode Offline sedang aktif. Ubah status ke Online untuk menerima pesanan baru.',
                'active_order' => null,
            ];
        }

        if (in_array($workStatus, [Courier::STATUS_BUSY, Courier::STATUS_ON_DELIVERY], true)) {
            return [
                'allowed'      => false,
                'work_status'  => $workStatus,
                'reason_code'  => 'courier_busy',
                'message'      => 'Status kurir sedang sibuk dan tidak dapat menerima pesanan baru.',
                'active_order' => null,
            ];
        }

        return [
            'allowed'      => true,
            'work_status'  => Courier::STATUS_ONLINE,
            'reason_code'  => null,
            'message'      => null,
            'active_order' => null,
        ];
    }

    /**
     * Penjaga utama sebelum mengirim penawaran / notifikasi pesanan baru ke kurir.
     */
    public function canReceiveNewOrderOffer(Courier $courier, ?Order $activeOrder = null): bool
    {
        return $this->evaluateAvailability($courier, $activeOrder)['allowed'];
    }

    /**
     * Penjaga sebelum memicu push notification penawaran pesanan baru.
     */
    public function shouldSendPushOfferNotification(Courier $courier, ?Order $order = null): bool
    {
        $freshCourier = $courier->fresh() ?? $courier;
        if (! $this->canReceiveNewOrderOffer($freshCourier)) {
            return false;
        }

        if ($order !== null) {
            $freshOrder = $order->fresh() ?? $order;
            if ($freshOrder->status !== 'pending') {
                return false;
            }
            if ($freshOrder->courier_id !== null && (int) $freshOrder->courier_id !== (int) $freshCourier->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Susun payload notifikasi penawaran hanya jika kurir memenuhi semua syarat kelayakan.
     */
    public function buildNewOrderOfferNotificationPayload(Courier $courier, Order $order): ?array
    {
        if (! $this->shouldSendPushOfferNotification($courier, $order)) {
            return null;
        }

        return [
            'type'         => 'new_order_offer',
            'courier_id'   => $courier->id,
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'title'        => 'Penawaran Pesanan Baru',
            'body'         => 'Ada permintaan pengiriman baru siap diambil.',
        ];
    }

    /**
     * Saring daftar kurir agar hanya kurir Online & bebas pesanan aktif yang masuk antrean distribusi.
     */
    public function filterEligibleCouriers(iterable $couriers): Collection
    {
        return collect($couriers)->filter(
            fn (Courier $courier): bool => $this->canReceiveNewOrderOffer($courier)
        )->values();
    }

    /**
     * Sinkronkan kolom work_status di tabel couriers berdasarkan kondisi pesanan aktif & shift kurir.
     */
    public function syncPersistedStatus(Courier $courier, ?Order $knownActiveOrder = null, bool $checkDatabase = true): Courier
    {
        $activeOrder = $checkDatabase
            ? $this->findActiveOrderForCourier($courier)
            : $knownActiveOrder;

        $targetStatus = $this->resolveWorkStatus($courier, $activeOrder);

        if ($this->hasWorkStatusColumn()) {
            $updates = [];
            if ($courier->work_status !== $targetStatus) {
                $updates['work_status'] = $targetStatus;
                if ($this->hasStatusChangedAtColumn()) {
                    $updates['status_changed_at'] = now();
                }
            }

            if (! empty($updates)) {
                $courier->forceFill($updates)->saveQuietly();
            }
        }

        return $courier;
    }

    /**
     * Bangun snapshot terstruktur status ketersediaan kurir untuk API response.
     */
    public function getAvailabilitySnapshot(Courier $courier, ?Order $activeOrder = null): array
    {
        $eval = $this->evaluateAvailability($courier, $activeOrder);
        $workStatus = $eval['work_status'];
        $resolvedActive = $eval['active_order'];

        return [
            'id'                  => $courier->id,
            'work_status'         => $workStatus,
            'is_active'           => (bool) $courier->is_active,
            'is_verified'         => (bool) $courier->is_verified,
            'is_online'           => $workStatus === Courier::STATUS_ONLINE,
            'is_offline'          => $workStatus === Courier::STATUS_OFFLINE,
            'is_busy'             => in_array($workStatus, [Courier::STATUS_BUSY, Courier::STATUS_ON_DELIVERY], true),
            'is_on_delivery'      => $workStatus === Courier::STATUS_ON_DELIVERY,
            'has_active_order'    => $resolvedActive !== null,
            'can_accept_orders'   => $eval['allowed'],
            'unavailable_reason'  => $eval['reason_code'],
            'unavailable_message' => $eval['message'],
            'active_order'        => $resolvedActive ? [
                'id'              => $resolvedActive->id,
                'order_number'    => $resolvedActive->order_number,
                'tracking_number' => $resolvedActive->tracking_number,
                'status'          => $resolvedActive->status,
            ] : null,
            'latitude'            => $courier->latitude,
            'longitude'           => $courier->longitude,
        ];
    }

    private function hasWorkStatusColumn(): bool
    {
        if ($this->hasWorkStatusColumn === null) {
            $this->hasWorkStatusColumn = Schema::hasColumn('couriers', 'work_status');
        }

        return $this->hasWorkStatusColumn;
    }

    private function hasStatusChangedAtColumn(): bool
    {
        if ($this->hasStatusChangedAtColumn === null) {
            $this->hasStatusChangedAtColumn = Schema::hasColumn('couriers', 'status_changed_at');
        }

        return $this->hasStatusChangedAtColumn;
    }
}
