<?php

namespace App\Models\Traits;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasCustomFields
{
    public function customFieldValues(): HasMany
    {
        $modelClass = get_class($this);
        $entityType = match (true) {
            $modelClass === \App\Models\Printer::class => 'printer',
            $modelClass === \App\Models\Consumable::class => 'consumable',
            $modelClass === \App\Models\Order::class => 'order',
            default => strtolower(class_basename($modelClass)),
        };

        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', $entityType);
    }

    public function getCustomFieldValue(string $slug): ?string
    {
        $modelClass = get_class($this);
        $entityType = match (true) {
            $modelClass === \App\Models\Printer::class => 'printer',
            $modelClass === \App\Models\Consumable::class => 'consumable',
            $modelClass === \App\Models\Order::class => 'order',
            default => strtolower(class_basename($modelClass)),
        };

        $field = CustomField::where('entity_type', $entityType)
            ->where('slug', $slug)
            ->first();

        if (! $field) {
            return null;
        }

        $value = $this->customFieldValues()
            ->where('custom_field_id', $field->id)
            ->first();

        return $value?->value;
    }

    public function setCustomFieldValue(string $slug, $value): void
    {
        $modelClass = get_class($this);
        $entityType = match (true) {
            $modelClass === \App\Models\Printer::class => 'printer',
            $modelClass === \App\Models\Consumable::class => 'consumable',
            $modelClass === \App\Models\Order::class => 'order',
            default => strtolower(class_basename($modelClass)),
        };

        $field = CustomField::where('entity_type', $entityType)
            ->where('slug', $slug)
            ->first();

        if (! $field) {
            return;
        }

        // Convertir el valor a string si no es null
        $stringValue = $value === null || $value === '' ? null : (string) $value;

        $this->customFieldValues()->updateOrCreate(
            [
                'custom_field_id' => $field->id,
                'entity_type' => $entityType,
                'entity_id' => $this->id,
            ],
            ['value' => $stringValue]
        );
    }
}

