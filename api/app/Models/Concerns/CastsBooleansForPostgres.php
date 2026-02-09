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

    /**
     * Cast an attribute to a native PHP type.
     * Override to correctly handle string 'true'/'false' stored for PostgreSQL.
     *
     * PHP's (bool) 'false' returns true since 'false' is a non-empty string.
     * This override ensures string boolean values are correctly interpreted.
     */
    protected function castAttribute($key, $value)
    {
        if (in_array($key, $this->getBooleanAttributes()) && is_string($value)) {
            return in_array(strtolower($value), ['true', 't', '1', 'yes']);
        }

        return parent::castAttribute($key, $value);
    }
}
