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
            Role::Finance => [
                self::item('Beranda', 'dashboard', 'home'),
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

        if ($user->hasRole(Role::Student, Role::Guardian)) {
            $items[] = self::item('Aksaraloka Pay', 'pay.index', 'credit-card', 'pay.*');
        } elseif ($user->hasRole(Role::Admin, Role::Finance, Role::Principal)) {
            $items[] = self::item('Keuangan', 'finance.index', 'banknotes', 'finance.*');
        }

        if ($thesisChildren = self::thesisChildren($user)) {
            $items[] = [
                'label' => 'Tugas Akhir & Kelulusan',
                'icon' => 'academic-cap',
                'match' => ['thesis.*', 'yudisium.*'],
                'children' => $thesisChildren,
            ];
        }

        if ($examChildren = self::examChildren($user)) {
            $items[] = [
                'label' => 'Ujian & Evaluasi',
                'icon' => 'clipboard-document-check',
                'match' => ['exams.*', 'evaluations.*', 'journals.*'],
                'children' => $examChildren,
            ];
        }

        $items[] = [
            'label' => 'Akademik',
            'icon' => 'academic-cap',
            'match' => ['academic.calendar', 'enrollment.*', 'status.*', 'advising.*', 'curricula.*'],
            'children' => self::academicChildren($user),
        ];

        if ($user->role === Role::Admin) {
            $items[] = [
                'label' => 'Pengaturan',
                'icon' => 'cog-6-tooth',
                'match' => ['settings.*', 'academic.terms'],
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
     * Submenu Tugas Akhir & Kelulusan: skripsi, bimbingan, sidang, dan yudisium menurut peran.
     *
     * @return array<int, array{label: string, route: string, icon: string, match: string}>
     */
    private static function thesisChildren(User $user): array
    {
        return match ($user->role) {
            Role::Student => [
                self::item('Tugas akhir', 'thesis.mine', 'document-text'),
                self::item('Status kelulusan', 'yudisium.mine', 'check-badge'),
            ],
            Role::Teacher => [self::item('Bimbingan tugas akhir', 'thesis.index', 'document-text', 'thesis.*')],
            Role::Admin, Role::Principal, Role::Curriculum => [
                self::item('Tugas akhir', 'thesis.index', 'document-text', 'thesis.*'),
                self::item('Yudisium', 'yudisium.index', 'check-badge', 'yudisium.*'),
            ],
            default => [],
        };
    }

    /**
     * Submenu Ujian & Evaluasi: jadwal dan kartu ujian, EDOM, dan jurnal perkuliahan menurut peran.
     *
     * @return array<int, array{label: string, route: string, icon: string, match: string}>
     */
    private static function examChildren(User $user): array
    {
        return match ($user->role) {
            Role::Student => [
                self::item('Kartu ujian', 'exams.card', 'identification'),
                self::item('Evaluasi dosen', 'evaluations.mine', 'chart-bar', 'evaluations.mine'),
                self::item('Jurnal kuliah', 'journals.index', 'clipboard-document-list', 'journals.*'),
            ],
            Role::Guardian => [self::item('Kartu ujian', 'exams.card', 'identification')],
            Role::Teacher => [
                self::item('Jadwal ujian', 'exams.index', 'calendar-days', 'exams.*'),
                self::item('Hasil evaluasi', 'evaluations.results', 'chart-bar'),
                self::item('Jurnal kuliah', 'journals.index', 'clipboard-document-list', 'journals.*'),
            ],
            Role::Admin, Role::Principal, Role::Curriculum => [
                self::item('Jadwal ujian', 'exams.index', 'calendar-days', 'exams.*'),
                self::item('Periode evaluasi', 'evaluations.periods', 'cog-6-tooth'),
                self::item('Hasil evaluasi', 'evaluations.results', 'chart-bar'),
                self::item('Jurnal kuliah', 'journals.index', 'clipboard-document-list', 'journals.*'),
            ],
            default => [],
        };
    }

    /**
     * Submenu Akademik: KRS, perwalian, kurikulum, status, dan kalender akademik menurut peran.
     *
     * @return array<int, array{label: string, route: string, icon: string, match: string}>
     */
    private static function academicChildren(User $user): array
    {
        $children = [];

        if ($user->role === Role::Student) {
            $children[] = self::item('KRS', 'enrollment.plan', 'clipboard-document-list');
            $children[] = self::item('Bimbingan akademik', 'advising.mine', 'user-group');
            $children[] = self::item('Kurikulum', 'curricula.mine', 'academic-cap');
            $children[] = self::item('Status akademik', 'status.mine', 'identification');
        } else {
            if ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum, Role::Teacher)) {
                $children[] = self::item('Persetujuan KRS', 'enrollment.approvals', 'clipboard-document-list');
                $children[] = self::item('Perwalian', 'advising.index', 'user-group');
            }

            if ($user->hasRole(Role::Admin, Role::Principal, Role::Curriculum)) {
                $children[] = self::item('Kurikulum', 'curricula.index', 'academic-cap');
                $children[] = self::item('Status mahasiswa', 'status.manage', 'identification');
            }
        }

        $children[] = self::item('Kalender akademik', 'academic.calendar', 'calendar-days');

        return $children;
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
