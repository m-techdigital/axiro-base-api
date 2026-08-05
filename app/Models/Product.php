<?php

namespace App\Models;

use App\Enums\OfferModeCode;
use App\Models\Concerns\HasOfferModes;
use App\Models\Concerns\TracksAuditHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use HasFactory, HasOfferModes, SoftDeletes, TracksAuditHistory;

    protected $fillable = [
        'code', 'name', 'slug', 'product_type', 'delivery_method', 'inspection_period_minutes', 'requires_pre_handover_snapshot', 'game_code', 'server_name', 'level', 'status',
        'approval_status', 'is_published', 'sale_price', 'sale_deposit_amount', 'installment_enabled',
        'max_installment_count', 'minimum_initial_payment', 'installment_interval_unit', 'installment_interval_count',
        'rental_price', 'rental_price_unit', 'minimum_rental_period', 'rental_period_unit', 'rental_billing_mode',
        'rental_billing_cycle_unit', 'rental_billing_cycle_count', 'rental_deposit_amount', 'available_from',
        'available_until', 'published_at', 'approved_at', 'approved_by', 'rejection_reason', 'availability_status',
        'held_by_transaction_id', 'hold_expires_at', 'availability_version', 'unavailable_reason', 'description',
        'image_url', 'image_urls', 'attributes', 'owner_customer_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean', 'inspection_period_minutes' => 'integer', 'requires_pre_handover_snapshot' => 'boolean', 'sale_price' => 'decimal:2', 'sale_deposit_amount' => 'decimal:2',
        'installment_enabled' => 'boolean', 'minimum_initial_payment' => 'decimal:2', 'rental_price' => 'decimal:2',
        'rental_deposit_amount' => 'decimal:2', 'available_from' => 'datetime', 'available_until' => 'datetime',
        'published_at' => 'datetime', 'approved_at' => 'datetime', 'hold_expires_at' => 'datetime',
        'availability_version' => 'integer', 'image_urls' => 'array', 'attributes' => 'array',
    ];

    protected $appends = ['offer_modes', 'transaction_types', 'sale_enabled', 'rental_enabled', 'images'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if ($product->status === 'published') {
                $product->approval_status = 'approved';
                $product->is_published = true;
                $product->status = 'active';
            } elseif ($product->status === 'rejected') {
                $product->approval_status = 'rejected';
                $product->is_published = false;
                $product->status = 'active';
            }
        });

        static::created(function (Product $product) {
            if (! Schema::hasTable('offer_modes')) {
                return;
            }
            if ($product->offerModes()->exists()) {
                return;
            }
            $modes = [];
            if ($product->sale_price !== null || $product->installment_enabled) {
                $modes[] = 'sell';
            }
            if ($product->rental_price !== null) {
                $modes[] = 'rent';
            }
            if ($modes) {
                $product->syncOfferModes($modes);
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function rentalRates(): HasMany
    {
        return $this->hasMany(ProductRentalRate::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(ProductHold::class);
    }

    public function activeHold(): HasOne
    {
        return $this->hasOne(ProductHold::class)->where('status', 'active')->latestOfMany();
    }

    public function availabilityHistory(): HasMany
    {
        return $this->hasMany(ProductAvailabilityHistory::class)->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getOfferModesAttribute(): array
    {
        return $this->offerModeCodes();
    }

    public function getTransactionTypesAttribute(): array
    {
        $modes = $this->offerModeCodes();
        $types = [];
        if (in_array(OfferModeCode::SELL->value, $modes, true)) {
            $types[] = 'sale';
        }
        if (in_array(OfferModeCode::RENT->value, $modes, true)) {
            $types[] = 'rental';
        }
        if ($this->installment_enabled) {
            $types[] = 'installment';
        }

        return $types;
    }

    public function getImagesAttribute(): array
    {
        return array_values(array_unique(array_filter([
            ...($this->image_urls ?? []),
            $this->image_url,
        ])));
    }

    public function getSaleEnabledAttribute(): bool
    {
        return in_array(OfferModeCode::SELL->value, $this->offerModeCodes(), true);
    }

    public function getRentalEnabledAttribute(): bool
    {
        return in_array(OfferModeCode::RENT->value, $this->offerModeCodes(), true);
    }

    public function supports(string $type): bool
    {
        return match ($type) {
            'sale', 'purchase', 'sell' => $this->sale_enabled,
            'rental', 'rent' => $this->rental_enabled,
            'installment' => $this->sale_enabled && $this->installment_enabled,
            default => false,
        };
    }
}
