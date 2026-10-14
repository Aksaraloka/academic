<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

class Navigation
{
    /**
     * Menu utama per peran; urutan menentukan bar bawah di HP (empat pertama + "Lainnya").
     *
     * @return array<int, array{label: string, route: string, icon: string, match: string}>
     */
    public static function for(User $user): array
    {
        $items = match ($user->role) {
            Role::Admin => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Mahasiswa', 'students.index', 'users', 'students.*'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Dosen', 'teachers.index', 'identification', 'teachers.*'),
                self::item('Kelas', 'groups.index', 'user-group', 'groups.*'),
                self::item('Prodi', 'programs.index', 'building-library', 'programs.*'),
                self::item('Wisuda', 'graduation.overview', 'academic-cap', 'graduation.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Principal => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Wisuda', 'graduation.overview', 'academic-cap', 'graduation.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Curriculum => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Kelas', 'groups.index', 'user-group', 'groups.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Wisuda', 'graduation.overview', 'academic-cap', 'graduation.*'),
            ],
            Role::Teacher => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Duty => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
            ],
            Role::Student => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Status Kehadiran', 'attendance.mine', 'clipboard-document-check', 'attendance.mine'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Wisuda', 'graduation.overview', 'academic-cap', 'graduation.*'),
            ],
            Role::Guardian => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Kehadiran', 'reports.index', 'chart-bar', 'reports.*'),
            ],
        };

        if ($grades = self::gradesChildren($user)) {
            $items[] = ['label' => 'Nilai', 'icon' => 'check-badge', 'match' => 'grades.*', 'children' => $grades];
        }

        if ($user->role === Role::Student) {
            $items[] = self::item('KRS', 'enrollment.plan', 'clipboard-document-list', 'enrollment.plan');
        } elseif ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher)) {
            $items[] = self::item('Persetujuan KRS', 'enrollment.approvals', 'clipboard-document-list', 'enrollment.approvals');
        }

        $items[] = self::item('Kalender akademik', 'academic.calendar', 'calendar-days', 'academic.calendar');

        if ($user->role === Role::Admin) {
            $items[] = [
                'label' => 'Pengaturan',
                'icon' => 'cog-6-tooth',
                'match' => 'settings.*',
                'children' => [
                    self::item('Pengaturan kampus', 'settings.campus', 'building-office', 'settings.campus'),
                    self::item('Semester akademik', 'academic.terms', 'calendar-days', 'academic.terms'),
                ],
            ];
        }

        $items[] = [
            'label' => 'Survey',
            'icon' => 'chart-pie',
            'match' => 'survey.*',
            'children' => [
                self::item('Beranda', 'survey.home', 'home'),
                self::item('Kuesioner', 'survey.index', 'clipboard-document-list', 'survey.index'),
                self::item('Bantuan', 'survey.help', 'question-mark-circle'),
            ],
        ];
        $items[] = self::item('Masukan', 'feedback.index', 'chat-bubble-left-right', 'feedback.*');

        return $items;
    }

    /**
     * Submenu Nilai menurut peran; null bila perannya tidak punya menu nilai.
     *
     * @return array<int, array{label: string, route: string, icon: string, match: string}>|null
     */
    private static function gradesChildren(User $user): ?array
    {
        return match ($user->role) {
            Role::Student, Role::Guardian => [
                self::item('KHS', 'grades.khs', 'chart-bar', 'grades.khs'),
                self::item('Transkrip', 'grades.transcript', 'document-text', 'grades.transcript'),
            ],
            Role::Teacher => [
                self::item('Input nilai', 'grades.input.index', 'pencil-square', 'grades.input.*'),
                self::item('Revisi nilai', 'grades.revisions', 'arrow-path', 'grades.revisions'),
            ],
            Role::Principal => [
                self::item('Persetujuan revisi', 'grades.revisions', 'arrow-path', 'grades.revisions'),
                self::item('Kelengkapan nilai', 'grades.completeness', 'clipboard-document-check', 'grades.completeness'),
            ],
            Role::Admin => [
                self::item('Kelengkapan nilai', 'grades.completeness', 'clipboard-document-check', 'grades.completeness'),
                self::item('Revisi nilai', 'grades.revisions', 'arrow-path', 'grades.revisions'),
                self::item('Pengaturan nilai', 'grades.settings', 'cog-6-tooth', 'grades.settings'),
            ],
            Role::Curriculum => [
                self::item('Kelengkapan nilai', 'grades.completeness', 'clipboard-document-check', 'grades.completeness'),
            ],
            default => null,
        };
    }

    /**
     * Ratakan grup menu menjadi daftar datar (untuk navigasi HP); anak grup membawa nama grupnya.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function flatten(array $items): array
    {
        $flat = [];

        foreach ($items as $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    $flat[] = $child + ['group' => $item['label'], 'group_icon' => $item['icon']];
                }

                continue;
            }

            $flat[] = $item;
        }

        return $flat;
    }

    /**
     * @return array{label: string, route: string, icon: string, match: string}
     */
    private static function item(string $label, string $route, string $icon, ?string $match = null): array
    {
        return ['label' => $label, 'route' => $route, 'icon' => $icon, 'match' => $match ?? $route];
    }
}
