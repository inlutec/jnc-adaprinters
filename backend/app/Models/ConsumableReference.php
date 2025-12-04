<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\StockMovement;

class ConsumableReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'brand',
        'type',
        'custom_type',
        'color',
        'compatible_models',
        'description',
        'minimum_quantity',
        'is_active',
    ];

    protected $casts = [
        'compatible_models' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener movimientos de stock relacionados con esta referencia
     * a través de stocks que tienen consumables con el mismo SKU o nombre
     */
    public function getStockMovementsAttribute()
    {
        return StockMovement::whereHas('stock.consumable', function ($query) {
            $query->where('sku', $this->sku)
                ->orWhere('name', $this->name);
        })->with(['stock.consumable', 'performer'])
            ->latest('movement_at')
            ->get();
    }
}
