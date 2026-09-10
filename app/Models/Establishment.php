<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstablishmentSegment;
use Carbon\CarbonImmutable;
use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'legal_name', 'slug', 'segment', 'description',
    'logo_path', 'cover_path', 'phone', 'whatsapp', 'instagram',
    'address_street', 'address_number', 'address_complement', 'address_district',
    'address_city', 'address_state', 'address_zipcode',
    'primary_color', 'secondary_color', 'manual_status', 'timezone',
    'is_published', 'is_indexable', 'published_at',
])]
class Establishment extends Model
{
    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'segment' => EstablishmentSegment::class,
            'is_published' => 'boolean',
            'is_indexable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Produtos que nao foram atribuidos a nenhuma categoria.
     * Eles ainda precisam aparecer no cardapio publico.
     *
     * @return HasMany<Product, $this>
     */
    public function uncategorizedProducts(): HasMany
    {
        return $this->hasMany(Product::class)->whereNull('category_id');
    }

    /** @return HasMany<AddonGroup, $this> */
    public function addonGroups(): HasMany
    {
        return $this->hasMany(AddonGroup::class);
    }

    /** @return HasMany<BusinessHour, $this> */
    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<QrCode, $this> */
    public function qrCode(): HasOne
    {
        return $this->hasOne(QrCode::class);
    }

    /** @return HasMany<Setting, $this> */
    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    /** @return HasMany<MenuView, $this> */
    public function menuViews(): HasMany
    {
        return $this->hasMany(MenuView::class);
    }

    /**
     * Assinatura vigente. A ordem por id desc garante que a ultima criada vence
     * quando existir historico de assinaturas para o mesmo estabelecimento.
     *
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function publicUrl(): string
    {
        return route('menu.show', $this->slug);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /**
     * Numero de WhatsApp somente com digitos, com codigo do pais garantido.
     * Ex.: "(11) 98888-1234" => "5511988881234"
     */
    public function whatsappNumber(): string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->whatsapp) ?? '';
        $countryCode = (string) config('whatsapp.default_country_code', '55');

        if ($digits === '') {
            return '';
        }

        if (! str_starts_with($digits, $countryCode) || strlen($digits) <= 11) {
            $digits = $countryCode.$digits;
        }

        return $digits;
    }

    /**
     * Estado aberto/fechado. O override manual tem prioridade sobre o horario.
     */
    public function isOpen(?CarbonImmutable $now = null): bool
    {
        if ($this->manual_status === self::STATUS_OPEN) {
            return true;
        }

        if ($this->manual_status === self::STATUS_CLOSED) {
            return false;
        }

        $now = ($now ?? CarbonImmutable::now())->setTimezone($this->timezone ?: config('app.timezone'));

        $hours = $this->relationLoaded('businessHours')
            ? $this->businessHours
            : $this->businessHours()->get();

        $today = $hours->firstWhere('weekday', (int) $now->dayOfWeek);

        if (! $today || $today->is_closed || ! $today->opens_at || ! $today->closes_at) {
            return false;
        }

        return $today->coversTime($now->format('H:i:s'));
    }

    public function activePlan(): ?Plan
    {
        $subscription = $this->relationLoaded('subscription')
            ? $this->subscription
            : $this->subscription()->with('plan')->first();

        if (! $subscription) {
            return null;
        }

        return $subscription->grantsAccess()
            ? $subscription->plan
            : Plan::query()->where('is_default', true)->first();
    }
}
