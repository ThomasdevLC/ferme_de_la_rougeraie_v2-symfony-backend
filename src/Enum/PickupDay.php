<?php
// src/Enum/PickupDay.php
namespace App\Enum;

enum PickupDay: int
{
    case MONDAY    = 1;
    case TUESDAY   = 2;
    case WEDNESDAY = 3;
    case THURSDAY  = 4;
    case FRIDAY    = 5;
    case SATURDAY  = 6;
    case SUNDAY    = 7;

    public function label(string $locale = 'fr'): string
    {
        return match($this) {
            self::MONDAY    => 'Lundi',
            self::TUESDAY   => 'Mardi',
            self::WEDNESDAY => 'Mercredi',
            self::THURSDAY  => 'Jeudi',
            self::FRIDAY    => 'Vendredi',
            self::SATURDAY  => 'Samedi',
            self::SUNDAY    => 'Dimanche',
        };
    }

    public static function fromWeekday(int $weekday): self
    {
        return self::tryFrom($weekday)
            ?? throw new \ValueError("Invalid weekday: $weekday");
    }
}
