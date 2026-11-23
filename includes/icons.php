<?php
// includes/icons.php
// PKL Journey - Original SVG Outline Icon Set & Vector Logo

if (!function_exists('pkl_icon')) {
    /**
     * Render an original outline SVG icon matching the PKL Journey design system.
     *
     * @param string $name       Icon name (e.g. dashboard, siswa, jurnal, dll)
     * @param string $extraClass Optional additional CSS classes
     * @param int    $size       Icon width and height in px (default 20)
     * @param string $color      Stroke color (defaults to 'currentColor')
     * @return string Valid SVG markup
     */
    function pkl_icon(string $name, string $extraClass = '', int $size = 20, string $color = 'currentColor'): string
    {
        static $icons = null;
        if ($icons === null) {
            $icons = [
                // 1. Dashboard
                'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5" /><rect x="14" y="3" width="7" height="5" rx="1.5" /><rect x="14" y="12" width="7" height="9" rx="1.5" /><rect x="3" y="16" width="7" height="5" rx="1.5" />',

                // 2. Siswa (Student with graduation mortarboard / path)
                'siswa' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" />',
                'student' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z" /><path d="M6 12v5c3 3 9 3 12 0v-5" />',

                // 3. Pengguna (Users group / User account)
                'pengguna' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
                'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
                'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />',

                // 4. Jurnal (Activity Journal Notebook with Bookmark)
                'jurnal' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" /><path d="M8 7h8" /><path d="M8 11h8" /><path d="M8 15h5" />',
                'journal' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" /><path d="M8 7h8" /><path d="M8 11h8" /><path d="M8 15h5" />',

                // 5. Kehadiran (Attendance / Calendar with verification checkmark)
                'kehadiran' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><path d="m9 16 2 2 4-4" />',
                'attendance' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /><path d="m9 16 2 2 4-4" />',

                // 6. Pembimbing Sekolah (School Mentor / Teacher with school portal)
                'pembimbing_sekolah' => '<path d="M3 21h18" /><path d="M5 21V7l7-4 7 4v14" /><path d="M9 10h6" /><path d="M9 14h6" /><circle cx="12" cy="6" r="1" />',
                'guru' => '<path d="M3 21h18" /><path d="M5 21V7l7-4 7 4v14" /><path d="M9 10h6" /><path d="M9 14h6" /><circle cx="12" cy="6" r="1" />',

                // 7. Pembimbing Industri (Industry Mentor / Corporate Partner / DUDI)
                'pembimbing_industri' => '<rect x="4" y="2" width="16" height="20" rx="2" /><path d="M9 22v-4h6v4" /><line x1="8" y1="6" x2="8.01" y2="6" /><line x1="16" y1="6" x2="16.01" y2="6" /><line x1="8" y1="10" x2="8.01" y2="10" /><line x1="16" y1="10" x2="16.01" y2="10" /><line x1="8" y1="14" x2="8.01" y2="14" /><line x1="16" y1="14" x2="16.01" y2="14" />',
                'industri' => '<rect x="4" y="2" width="16" height="20" rx="2" /><path d="M9 22v-4h6v4" /><line x1="8" y1="6" x2="8.01" y2="6" /><line x1="16" y1="6" x2="16.01" y2="6" /><line x1="8" y1="10" x2="8.01" y2="10" /><line x1="16" y1="10" x2="16.01" y2="10" /><line x1="8" y1="14" x2="8.01" y2="14" /><line x1="16" y1="14" x2="16.01" y2="14" />',

                // 8. Penguji (Examiner / Board of Evaluators with checklist badge)
                'penguji' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" /><rect x="8" y="2" width="8" height="4" rx="1" ry="1" /><path d="m9 14 2 2 4-4" />',

                // 9. Sidang (PKL Defense / Presentation Hearing with Gavel)
                'sidang' => '<path d="m14 13-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10" /><path d="m16 16 6-6" /><path d="m8 8 6-6" /><path d="m9 7 8 8" /><path d="m21 11-8-8" />',

                // 10. Penilaian (Assessment / Star Score Rating)
                'penilaian' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />',
                'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />',

                // 11. Laporan (PKL Final Report Document with lines)
                'laporan' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" />',
                'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" />',

                // 12. Pengaturan (Settings Cog / Administration)
                'pengaturan' => '<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />',
                'settings' => '<circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />',

                // 13. Profil (Student / Staff Profile Badge)
                'profil' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />',
                'profile' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />',

                // 14. Logout (Exit Portal Door)
                'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" />',

                // Pelengkap Antarmuka (UI Helpers)
                'perusahaan' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />',
                'company' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />',
                'journey' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />',
                'map_pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" />',
                'check' => '<polyline points="20 6 9 17 4 12" />',
                'clock' => '<circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />',
                'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />',
                'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />',
                'trash' => '<polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" />',
                'key' => '<circle cx="7.5" cy="15.5" r="5.5" /><path d="m21 2-9.6 9.6" /><path d="m15.5 7.5 3 3L22 7l-3-3" />',
                'plus' => '<line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />',
                'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" />',
                'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" /><polyline points="17 21 17 13 7 13 7 21" /><polyline points="7 3 7 8 15 8" />',
                'send' => '<line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" />',
                'search' => '<circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" />',
                'close' => '<line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />',
                'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />',
            ];
        }

        $key = strtolower(trim($name));
        $content = $icons[$key] ?? $icons['dashboard'];
        $cls = trim('pkl-icon pkl-icon-' . htmlspecialchars($key) . ' ' . $extraClass);

        return sprintf(
            '<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            $cls,
            $size,
            $size,
            htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
            $content
        );
    }
}

if (!function_exists('pkl_logo')) {
    /**
     * Render the official PKL Journey vector emblem.
     * Combines an ascending student journey route with an academic cap & career horizon.
     *
     * @param int    $size       Width and height in px (default 36)
     * @param string $extraClass Optional CSS class
     * @return string Valid SVG markup
     */
    function pkl_logo(int $size = 36, string $extraClass = ''): string
    {
        $cls = trim('pkl-logo-svg ' . $extraClass);
        return <<<SVG
<svg class="{$cls}" width="{$size}" height="{$size}" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="Logo PKL Journey">
  <defs>
    <linearGradient id="pklGradBg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0F2744" />
      <stop offset="100%" stop-color="#1E3A8A" />
    </linearGradient>
    <linearGradient id="pklGradTrack" x1="0%" y1="100%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#38BDF8" />
      <stop offset="100%" stop-color="#0284C7" />
    </linearGradient>
  </defs>
  <!-- Rounded Shield Base -->
  <rect width="40" height="40" rx="10" fill="url(#pklGradBg)" />
  
  <!-- Journey Path Arc -->
  <path d="M 10 30 C 10 21, 18 24, 20 20 C 22 16, 29 18, 29 12" fill="none" stroke="url(#pklGradTrack)" stroke-width="3" stroke-linecap="round" />
  
  <!-- Secondary Roadmap Guide Dotted Line -->
  <path d="M 12 28 C 13 20, 26 23, 27 14" fill="none" stroke="#FFFFFF" stroke-opacity="0.35" stroke-width="1.2" stroke-dasharray="2 2" />
  
  <!-- Node 1: Sekolah (Start Point) -->
  <circle cx="10" cy="30" r="3" fill="#FFFFFF" />
  <circle cx="10" cy="30" r="1.5" fill="#0284C7" />
  
  <!-- Node 2: Siswa (In Progress) -->
  <circle cx="20" cy="20" r="2.75" fill="#38BDF8" stroke="#FFFFFF" stroke-width="1" />
  
  <!-- Node 3: Dunia Kerja & Sidang (Graduation Cap & Horizon) -->
  <polygon points="29,7 34.5,9.8 29,12.5 23.5,9.8" fill="#FFFFFF" />
  <path d="M 25.5 11.5 v 2.8 c 0 1.2 1.6 2 3.5 2 s 3.5 -0.8 3.5 -2 v -2.8" fill="none" stroke="#FFFFFF" stroke-width="1.1" stroke-linecap="round" />
  <path d="M 33.5 10.5 v 3.2" fill="none" stroke="#38BDF8" stroke-width="1" stroke-linecap="round" />
</svg>
SVG;
    }
}

