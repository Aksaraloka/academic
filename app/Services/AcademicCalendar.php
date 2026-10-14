<?php

namespace App\Services;

use App\Enums\AcademicEventType;
use App\Models\AcademicTerm;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AcademicCalendar
{
    /**
     * Seluruh agenda satu semester, berurutan menurut tanggal mulai. Perkuliahan dan batas input nilai
     * diturunkan dari data semester (id null); sisanya agenda tersimpan.
     *
     * @return Collection<int, array{id: ?int, type: AcademicEventType, title: string, starts_on: CarbonInterface, ends_on: CarbonInterface, notes: ?string}>
     */
    public function entries(AcademicTerm $term): Collection
    {
        $entries = $term->events()->get()->map(fn ($event): array => [
            'id' => $event->id, 'type' => $event->type, 'title' => $event->displayTitle(),
            'starts_on' => $event->starts_on, 'ends_on' => $event->ends_on, 'notes' => $event->notes,
        ]);

        $entries->push([
            'id' => null, 'type' => AcademicEventType::Lecture, 'title' => AcademicEventType::Lecture->label(),
            'starts_on' => $term->starts_at, 'ends_on' => $term->ends_at, 'notes' => null,
        ]);

        if ($term->grading_closes_on) {
            $entries->push([
                'id' => null, 'type' => AcademicEventType::GradingDeadline, 'title' => AcademicEventType::GradingDeadline->label(),
                'starts_on' => $term->grading_closes_on, 'ends_on' => $term->grading_closes_on, 'notes' => 'Dosen tidak bisa lagi mengisi atau mengunci nilai setelah tanggal ini.',
            ]);
        }

        return $entries->sortBy([
            fn (array $a, array $b): int => $a['starts_on'] <=> $b['starts_on'],
            fn (array $a, array $b): int => $a['ends_on'] <=> $b['ends_on'],
        ])->values();
    }

    /**
     * Agenda yang sedang berlangsung pada tanggal tertentu.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ongoing(AcademicTerm $term, ?CarbonInterface $on = null): Collection
    {
        $on ??= now();

        return $this->entries($term)->filter(fn (array $entry): bool => $entry['starts_on']->copy()->startOfDay()->lte($on) && $entry['ends_on']->copy()->endOfDay()->gte($on))->values();
    }

    /**
     * Apakah jenis agenda tertentu sedang berlangsung; dipakai fitur yang terikat jadwal (mis. KRS).
     * Jenis yang belum punya agenda sama sekali dianggap tidak dibatasi bila $openWhenUndefined true.
     */
    public function isOpen(AcademicTerm $term, AcademicEventType $type, ?CarbonInterface $on = null, bool $openWhenUndefined = false): bool
    {
        $matching = $this->entries($term)->where('type', $type);

        if ($matching->isEmpty()) {
            return $openWhenUndefined;
        }

        return $this->ongoing($term, $on)->contains(fn (array $entry): bool => $entry['type'] === $type);
    }
}
