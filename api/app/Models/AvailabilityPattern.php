<?php

namespace App\Models;

use App\Models\Concerns\CastsBooleansForPostgres;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailabilityPattern extends Model
{
    use CastsBooleansForPostgres, HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'days_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'repeat_every_weeks',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'slot_duration_minutes' => 'integer',
            'repeat_every_weeks' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helpers

    /**
     * Genere les creneaux pour ce pattern jusqu'a une date limite
     *
     * @param  Carbon  $untilDate  Date limite de generation
     * @return array Les creneaux crees
     */
    public function generateSlots(Carbon $untilDate): array
    {
        $slots = [];
        $currentDate = Carbon::parse($this->start_date);
        $endDate = $this->end_date ? Carbon::parse($this->end_date)->min($untilDate) : $untilDate;

        // Map jour semaine: 1=Lundi (Carbon: 1=Lundi aussi avec isoWeekday)
        $daysOfWeek = $this->days_of_week;

        while ($currentDate->lte($endDate)) {
            // Verifier si le jour actuel est dans les jours configures
            $dayOfWeek = $currentDate->isoWeekday(); // 1=Lundi, 7=Dimanche

            if (in_array($dayOfWeek, $daysOfWeek)) {
                // Verifier que le creneau n'existe pas deja
                $existingSlot = AvailabilitySlot::where('date', $currentDate->toDateString())
                    ->where('start_time', $this->start_time)
                    ->where('end_time', $this->end_time)
                    ->first();

                if (! $existingSlot) {
                    $slot = AvailabilitySlot::create([
                        'date' => $currentDate->toDateString(),
                        'start_time' => $this->start_time,
                        'end_time' => $this->end_time,
                        'duration_minutes' => $this->slot_duration_minutes,
                        'status' => 'available',
                        'notes' => "Genere depuis: {$this->name}",
                    ]);
                    $slots[] = $slot;
                }
            }

            // Passer au jour suivant, en respectant repeat_every_weeks
            if ($currentDate->isoWeekday() === 7) {
                // Fin de semaine, avancer de (repeat_every_weeks - 1) semaines supplementaires
                $currentDate->addWeeks($this->repeat_every_weeks - 1);
            }
            $currentDate->addDay();
        }

        return $slots;
    }

    /**
     * Retourne les jours de la semaine en francais
     */
    public function getDaysOfWeekLabelsAttribute(): array
    {
        $labels = [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ];

        return array_map(fn ($day) => $labels[$day] ?? '', $this->days_of_week ?? []);
    }
}
