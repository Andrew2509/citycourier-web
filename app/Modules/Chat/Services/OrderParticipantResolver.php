<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Support\PhoneNumberNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves customer and courier user IDs across City Courier's order/shipment schema.
 * Supports joining Order <-> Shipment via both `order_number = shipment_number`
 * (City-Courier-web production schema) and optional `order_id` foreign keys.
 */
class OrderParticipantResolver
{
    /**
     * @return array{
     *   order_id: ?int,
     *   shipment_id: ?int,
     *   order_number: ?string,
     *   customer_user_id: int,
     *   courier_user_id: ?int,
     *   courier_id: ?int,
     *   status: ?string
     * }|null
     */
    public function resolve(int|string $identifier, ?int $actorUserId = null): ?array
    {
        $orderModelClass = config('chat.host_models.order');
        $shipmentModelClass = config('chat.host_models.shipment');

        $candidates = [];

        $order = $this->findOrder($orderModelClass, $identifier);
        if ($order !== null) {
            $pairedShipment = $this->findShipmentForOrder($shipmentModelClass, $order);
            $candidates[] = $this->buildResolved($order, $pairedShipment);
        }

        // Numeric IDs could refer to Shipment.id rather than Order.id if Order wasn't matched or belongs to someone else
        if (is_numeric($identifier) || $order === null) {
            $shipment = $this->findShipment($shipmentModelClass, $identifier);
            if ($shipment !== null) {
                $pairedOrder = $this->findOrderForShipment($orderModelClass, $shipment);
                $built = $this->buildResolved($pairedOrder, $shipment);
                if ($built !== null) {
                    $candidates[] = $built;
                }
            }
        }

        $candidates = array_values(array_filter($candidates));
        if ($candidates === []) {
            return null;
        }

        if ($actorUserId !== null && count($candidates) > 1) {
            foreach ($candidates as $candidate) {
                if (
                    $candidate['customer_user_id'] === $actorUserId
                    || ($candidate['courier_user_id'] !== null && $candidate['courier_user_id'] === $actorUserId)
                ) {
                    return $candidate;
                }
            }
        }

        return $candidates[0];
    }

    /**
     * @return array{
     *   order_id: ?int,
     *   shipment_id: ?int,
     *   order_number: ?string,
     *   customer_user_id: int,
     *   courier_user_id: ?int,
     *   courier_id: ?int,
     *   status: ?string
     * }|null
     */
    private function buildResolved(?Model $order, ?Model $shipment): ?array
    {
        if ($order === null && $shipment === null) {
            return null;
        }

        $orderId = $order !== null ? (int) $order->getKey() : (isset($shipment->order_id) ? (int) $shipment->order_id : null);
        $shipmentId = $shipment !== null ? (int) $shipment->getKey() : null;

        $customerUserId = (int) (
            $order?->getAttribute('customer_id')
            ?? $order?->getAttribute('user_id')
            ?? $shipment?->getAttribute('user_id')
            ?? $shipment?->getAttribute('customer_id')
            ?? 0
        );

        if ($customerUserId <= 0) {
            $customerPhone = $shipment?->getAttribute('customer_phone')
                ?? $order?->getAttribute('customer_phone')
                ?? $shipment?->getAttribute('sender_phone');
            $customerUserId = $this->resolveUserIdByPhone($customerPhone) ?? 0;
        }

        if ($customerUserId <= 0) {
            return null;
        }

        $courierId = $order?->getAttribute('courier_id') ?? $shipment?->getAttribute('courier_id');
        $courierId = $courierId !== null ? (int) $courierId : null;

        $courierUserId = $order?->getAttribute('courier_user_id')
            ?? $shipment?->getAttribute('courier_user_id');

        if ($courierUserId === null && $courierId !== null) {
            $courierUserId = $this->resolveCourierUserId($courierId);
        }

        $orderNumber = $order?->getAttribute('order_number')
            ?? $shipment?->getAttribute('tracking_number')
            ?? $shipment?->getAttribute('shipment_number')
            ?? ($orderId !== null ? 'ORD-' . $orderId : 'SHP-' . $shipmentId);

        return [
            'order_id' => $orderId,
            'shipment_id' => $shipmentId,
            'order_number' => (string) $orderNumber,
            'customer_user_id' => $customerUserId,
            'courier_user_id' => $courierUserId !== null ? (int) $courierUserId : null,
            'courier_id' => $courierId,
            'status' => $order?->getAttribute('status') ?? $shipment?->getAttribute('status'),
        ];
    }

    private function findOrder(?string $modelClass, int|string $identifier): ?Model
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $table = $instance->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        if (is_numeric($identifier)) {
            $found = $modelClass::query()->find((int) $identifier);
            if ($found !== null) {
                return $found;
            }
        }

        if (Schema::hasColumn($table, 'order_number')) {
            return $modelClass::query()->where('order_number', (string) $identifier)->first();
        }

        return null;
    }

    private function findShipment(?string $modelClass, int|string $identifier): ?Model
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $table = $instance->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        if (is_numeric($identifier)) {
            $found = $modelClass::query()->find((int) $identifier);
            if ($found !== null) {
                return $found;
            }
        }

        return $modelClass::query()
            ->where(function ($q) use ($table, $identifier): void {
                $first = true;
                foreach (['tracking_number', 'shipment_number', 'order_number'] as $col) {
                    if (Schema::hasColumn($table, $col)) {
                        $method = $first ? 'where' : 'orWhere';
                        $q->{$method}($col, (string) $identifier);
                        $first = false;
                    }
                }
            })
            ->first();
    }

    private function findShipmentForOrder(?string $modelClass, Model $order): ?Model
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $table = $instance->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        if (Schema::hasColumn($table, 'order_id')) {
            $byOrderId = $modelClass::query()->where('order_id', (int) $order->getKey())->first();
            if ($byOrderId !== null) {
                return $byOrderId;
            }
        }

        $orderNumber = $order->getAttribute('order_number');
        if ($orderNumber !== null && Schema::hasColumn($table, 'shipment_number')) {
            return $modelClass::query()->where('shipment_number', (string) $orderNumber)->first();
        }

        return null;
    }

    private function findOrderForShipment(?string $modelClass, Model $shipment): ?Model
    {
        if (! $modelClass || ! class_exists($modelClass)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $table = $instance->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        if (isset($shipment->order_id)) {
            $byId = $modelClass::query()->find((int) $shipment->order_id);
            if ($byId !== null) {
                return $byId;
            }
        }

        $shipmentNumber = $shipment->getAttribute('shipment_number');
        if ($shipmentNumber !== null && Schema::hasColumn($table, 'order_number')) {
            return $modelClass::query()->where('order_number', (string) $shipmentNumber)->first();
        }

        return null;
    }

    private function resolveCourierUserId(int $courierId): ?int
    {
        $courierModelClass = config('chat.host_models.courier');
        if (! $courierModelClass || ! class_exists($courierModelClass)) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $courierModelClass();
        if (! Schema::hasTable($instance->getTable())) {
            return null;
        }

        $courier = $courierModelClass::query()->find($courierId);
        if ($courier !== null && isset($courier->user_id)) {
            return (int) $courier->user_id;
        }

        return null;
    }

    private function resolveUserIdByPhone(mixed $rawPhone): ?int
    {
        $userModelClass = config('chat.host_models.user');
        if (! $userModelClass || ! class_exists($userModelClass)) {
            return null;
        }

        $e164 = PhoneNumberNormalizer::toE164(is_string($rawPhone) ? $rawPhone : null);
        if ($e164 === null) {
            return null;
        }

        /** @var Model $instance */
        $instance = new $userModelClass();
        if (! Schema::hasTable($instance->getTable())) {
            return null;
        }

        $local = str_starts_with($e164, '62') ? '0' . substr($e164, 2) : null;
        $variants = array_values(array_filter([$e164, '+' . $e164, $local]));

        $user = $userModelClass::query()->whereIn('phone', $variants)->first();

        return $user !== null ? (int) $user->getKey() : null;
    }
}
