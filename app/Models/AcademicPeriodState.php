<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriodState extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function periods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AcademicPeriod::class, 'state_id');
    }

    public const NAME_ACTIVO = 'Activo';

    public const NAME_TERMINADO = 'Terminado';

    private static array $defaultDescriptions = [
        self::NAME_ACTIVO => 'El periodo académico está en curso.',
        self::NAME_TERMINADO => 'El periodo académico ha finalizado.',
    ];

    public static function ensureActive(): self
    {
        return static::ensureState(self::NAME_ACTIVO);
    }

    public static function ensureFinished(): self
    {
        return static::ensureState(self::NAME_TERMINADO);
    }

    public static function activeId(): int
    {
        return static::ensureActive()->id;
    }

    public static function finishedId(): int
    {
        return static::ensureFinished()->id;
    }

    private static function ensureState(string $name): self
    {
        $description = static::$defaultDescriptions[$name] ?? $name;

        return static::query()->firstOrCreate(
            ['name' => $name],
            ['description' => $description]
        );
    }
}
