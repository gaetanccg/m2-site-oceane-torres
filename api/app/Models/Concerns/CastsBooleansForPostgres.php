<?php

namespace App\Models\Concerns;

/**
 * Trait to handle boolean casting for PostgreSQL when using PDO::ATTR_EMULATE_PREPARES.
 *
 * When EMULATE_PREPARES is true, PDO sends booleans as 1/0 instead of true/false,
 * which PostgreSQL rejects for boolean columns.
 */
trait CastsBooleansForPostgres
{
    /**
     * Get the attributes that should be cast to booleans.
     */
    protected function getBooleanAttributes(): array
    {
        $booleans = [];

        foreach ($this->getCasts() as $key => $cast) {
            if ($cast === 'boolean' || $cast === 'bool') {
                $booleans[] = $key;
            }
        }

        return $booleans;
    }

    /**
     * Set a given attribute on the model.
     * Override to convert booleans to PostgreSQL-compatible format.
     */
    public function setAttribute($key, $value)
    {
        // Convert boolean values to PostgreSQL format for boolean columns
        if (in_array($key, $this->getBooleanAttributes()) && ! is_null($value)) {
            // Use string 'true'/'false' which PostgreSQL accepts
            $value = $value ? 'true' : 'false';

            $this->attributes[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}
