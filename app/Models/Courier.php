<?php

namespace App\Models;

use App\Services\CourierAvailabilityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Courier extends Model
{
    use HasFactory;

    public const STATUS_ONLINE = 'online';

    public const STATUS_OFFLINE = 'offline';

    public const STATUS_BUSY = 'busy';

    public const STATUS_ON_DELIVERY = 'on_delivery';

    protected $fillable = [
        'user_id',
        'nik',
        'phone',
        'address',
        'city',
        'photo',
        'vehicle_type',
        'vehicle_brand',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'vehicle_plate',
        'id_card_photo',
        'driving_license_photo',
        'skck_photo',
        'is_verified',
        'is_active',
        'work_status',
        'status_changed_at',
        'rating_avg',
        'rating_count',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_active' => 'boolean',
            'status_changed_at' => 'datetime',
            'rating_avg' => 'decimal:1',
            'rating_count' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Courier $courier): void {
            if (! Schema::hasColumn('couriers', 'work_status')) {
                return;
            }

            if (empty($courier->work_status)) {
                $courier->work_status = $courier->is_active
                    ? self::STATUS_ONLINE
                    : self::STATUS_OFFLINE;
            }

            if (Schema::hasColumn('couriers', 'status_changed_at') && $courier->status_changed_at === null) {
                $courier->status_changed_at = now();
            }
        });

        static::updating(function (Courier $courier): void {
            if (! Schema::hasColumn('couriers', 'work_status')) {
                return;
            }

            if ($courier->isDirty('is_active') && ! $courier->isDirty('work_status')) {
                if (! $courier->is_active) {
                    $courier->work_status = self::STATUS_OFFLINE;
                } elseif ($courier->hasActiveOrder()) {
                    $courier->work_status = self::STATUS_ON_DELIVERY;
                } else {
                    $courier->work_status = self::STATUS_ONLINE;
                }

                if (Schema::hasColumn('couriers', 'status_changed_at')) {
                    $courier->status_changed_at = now();
                }
            }
        });
    }

    public function activeOrders()
    {
        return $this->orders()->whereIn('status', Order::ACTIVE_STATUSES);
    }

    public function hasActiveOrder(): bool
    {
        return $this->activeOrders()->exists();
    }

    public function effectiveWorkStatus(): string
    {
        return app(CourierAvailabilityService::class)->resolveWorkStatus(
            $this,
            app(CourierAvailabilityService::class)->findActiveOrderForCourier($this)
        );
    }

    public function isAvailableForOrders(): bool
    {
        return app(CourierAvailabilityService::class)->canReceiveNewOrderOffer($this);
    }

    public function scopeAvailableForOrders($query)
    {
        $query->where('is_verified', true)
            ->where('is_active', true)
            ->whereDoesntHave('orders', function ($orderQuery): void {
                $orderQuery->whereIn('status', Order::ACTIVE_STATUSES);
            });

        if (Schema::hasColumn('couriers', 'work_status')) {
            $query->where(function ($statusQuery): void {
                $statusQuery->whereNull('work_status')
                    ->orWhere('work_status', self::STATUS_ONLINE);
            });
        }

        return $query;
    }

    /**
     * Get the user that owns this courier profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the orders assigned to this courier.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the wallet belonging to this courier.
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get attendance records for this courier.
     */
    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get the courier locations (telemetry).
     */
    public function locations()
    {
        return $this->hasMany(CourierLocation::class);
    }
}
