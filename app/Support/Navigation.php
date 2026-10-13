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
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Mahasiswa', 'students.index', 'users', 'students.*'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Dosen', 'teachers.index', 'identification', 'teachers.*'),
                self::item('Kelas', 'groups.index', 'user-group', 'groups.*'),
                self::item('Prodi', 'programs.index', 'building-library', 'programs.*'),
                self::item('Wisuda', 'wisuda.overview', 'academic-cap', 'wisuda.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Principal => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Wisuda', 'wisuda.overview', 'academic-cap', 'wisuda.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Curriculum => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Kelas', 'groups.index', 'user-group', 'groups.*'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Wisuda', 'wisuda.overview', 'academic-cap', 'wisuda.*'),
            ],
            Role::Teacher => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Rekap', 'reports.index', 'chart-bar', 'reports.*'),
                self::item('Izin', 'leaves.index', 'envelope', 'leaves.*'),
            ],
            Role::Duty => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Presensi', 'attendance.index', 'clipboard-document-check', 'attendance.*'),
                self::item('Check-in', 'checkin', 'map-pin'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
            ],
            Role::Student => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Presensi', 'attendance.mine', 'clipboard-document-check', 'attendance.mine'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Pindai QR', 'scan', 'qr-code'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Wisuda', 'wisuda.overview', 'academic-cap', 'wisuda.*'),
            ],
            Role::Guardian => [
                self::item('Beranda', 'dashboard', 'home'),
                self::item('Ringkasan', 'summary', 'clipboard-document-list'),
                self::item('Jadwal', 'schedule.index', 'calendar-days', 'schedule.*'),
                self::item('Kehadiran', 'reports.index', 'chart-bar', 'reports.*'),
            ],
        };

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
