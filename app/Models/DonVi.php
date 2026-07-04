<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonVi extends Model
{
    protected $table = 'don_vis';

    protected $fillable = [
        'parent_id',
        'cap',
        'ma',
        'ten',
        'mo_ta',
        'thu_tu',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cap' => 'integer',
            'thu_tu' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('thu_tu')->orderBy('ma');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'don_vi_id');
    }

    public function doanhNghieps(): HasMany
    {
        return $this->hasMany(DoanhNghiep::class, 'don_vi_id');
    }

    /**
     * @return array<int, int>
     */
    public function collectDescendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->collectDescendantIds());
        }

        return array_values(array_unique($ids));
    }

    public static function idsWithDescendants(int $donViId): array
    {
        $donVi = self::query()
            ->with(['children.children.children.children'])
            ->find($donViId);

        return $donVi ? $donVi->collectDescendantIds() : [$donViId];
    }

    private static ?int $rootIdCache = null;

    public static function rootId(): ?int
    {
        if (self::$rootIdCache === null) {
            self::$rootIdCache = self::query()->where('ma', 'ROOT')->value('id');
        }

        return self::$rootIdCache;
    }

    public static function userBelongsToRoot(?User $user): bool
    {
        if ($user?->don_vi_id === null) {
            return false;
        }

        return (int) $user->don_vi_id === (int) self::rootId();
    }
}
