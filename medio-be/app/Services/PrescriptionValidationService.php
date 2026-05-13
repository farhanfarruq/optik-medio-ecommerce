<?php

namespace App\Services;

class PrescriptionValidationService
{
    /**
     * Validasi dan analisis data resep mata.
     * Return array berisi: errors, warnings, recommendations, completeness_score.
     */
    public function analyze(array $prescription): array
    {
        $errors          = [];
        $warnings        = [];
        $recommendations = [];

        $od = $prescription['od'] ?? $prescription['right'] ?? [];
        $os = $prescription['os'] ?? $prescription['left'] ?? [];

        $odSph  = isset($od['sph'])  ? (float) $od['sph']  : null;
        $odCyl  = isset($od['cyl'])  ? (float) $od['cyl']  : null;
        $odAxis = isset($od['axis']) ? (int)   $od['axis'] : null;
        $odAdd  = isset($od['add'])  ? (float) $od['add']  : null;

        $osSph  = isset($os['sph'])  ? (float) $os['sph']  : null;
        $osCyl  = isset($os['cyl'])  ? (float) $os['cyl']  : null;
        $osAxis = isset($os['axis']) ? (int)   $os['axis'] : null;
        $osAdd  = isset($os['add'])  ? (float) $os['add']  : null;

        $pdSingle = isset($prescription['pd_single']) ? (float) $prescription['pd_single'] : null;
        $pdRight  = isset($prescription['pd_right'])  ? (float) $prescription['pd_right']  : null;
        $pdLeft   = isset($prescription['pd_left'])   ? (float) $prescription['pd_left']   : null;

        // ── Missing fields ──────────────────────────────────────────────────
        if ($odSph === null) {
            $errors[] = 'Sphere kanan (OD) wajib diisi.';
        }
        if ($osSph === null) {
            $errors[] = 'Sphere kiri (OS) wajib diisi.';
        }

        // Cylinder tanpa axis
        if ($odCyl !== null && $odCyl != 0 && $odAxis === null) {
            $errors[] = 'Axis kanan wajib diisi jika cylinder kanan diisi.';
        }
        if ($osCyl !== null && $osCyl != 0 && $osAxis === null) {
            $errors[] = 'Axis kiri wajib diisi jika cylinder kiri diisi.';
        }

        // PD
        if ($pdSingle === null && ($pdRight === null || $pdLeft === null)) {
            $warnings[] = 'PD (Pupillary Distance) belum diisi. Diperlukan untuk lensa single vision dan progressive.';
        }

        // ── Impossible values ───────────────────────────────────────────────
        if ($odSph !== null && ($odSph < -20 || $odSph > 20)) {
            $errors[] = "Sphere kanan ({$odSph}) di luar rentang normal (-20 hingga +20).";
        }
        if ($osSph !== null && ($osSph < -20 || $osSph > 20)) {
            $errors[] = "Sphere kiri ({$osSph}) di luar rentang normal (-20 hingga +20).";
        }
        if ($odCyl !== null && ($odCyl < -8 || $odCyl > 8)) {
            $warnings[] = "Cylinder kanan ({$odCyl}) tidak biasa. Periksa kembali.";
        }
        if ($osCyl !== null && ($osCyl < -8 || $osCyl > 8)) {
            $warnings[] = "Cylinder kiri ({$osCyl}) tidak biasa. Periksa kembali.";
        }
        if ($odAxis !== null && ($odAxis < 0 || $odAxis > 180)) {
            $errors[] = "Axis kanan ({$odAxis}) harus antara 0 dan 180.";
        }
        if ($osAxis !== null && ($osAxis < 0 || $osAxis > 180)) {
            $errors[] = "Axis kiri ({$osAxis}) harus antara 0 dan 180.";
        }
        if ($pdSingle !== null && ($pdSingle < 50 || $pdSingle > 80)) {
            $warnings[] = "PD ({$pdSingle}mm) di luar rentang normal (50-80mm). Periksa kembali.";
        }

        // ── Lens recommendations ────────────────────────────────────────────
        $maxSph = max(abs($odSph ?? 0), abs($osSph ?? 0));
        $maxCyl = max(abs($odCyl ?? 0), abs($osCyl ?? 0));

        if ($maxSph > 6 || $maxCyl > 2) {
            $recommendations[] = [
                'type'   => 'high_index',
                'label'  => 'Lensa High Index (1.67 atau 1.74)',
                'reason' => 'Minus/plus tinggi — lensa high index lebih tipis dan ringan.',
            ];
        }

        if ($odAdd !== null || $osAdd !== null) {
            $recommendations[] = [
                'type'   => 'progressive',
                'label'  => 'Lensa Progressive',
                'reason' => 'Nilai ADD menunjukkan kebutuhan lensa baca — progressive cocok untuk pengguna presbyopia.',
            ];
        }

        if ($maxSph > 0 || $maxCyl > 0) {
            $recommendations[] = [
                'type'   => 'anti_radiation',
                'label'  => 'Coating Anti Radiasi',
                'reason' => 'Direkomendasikan untuk pengguna layar digital.',
            ];
        }

        if ($maxSph > 4) {
            $recommendations[] = [
                'type'   => 'photochromic',
                'label'  => 'Lensa Photochromic',
                'reason' => 'Minus tinggi — lensa photochromic nyaman untuk aktivitas dalam dan luar ruangan.',
            ];
        }

        // ── Completeness score ──────────────────────────────────────────────
        $fields = [
            $odSph !== null,
            $osSph !== null,
            $odCyl !== null,
            $osCyl !== null,
            $odAxis !== null || ($odCyl === null || $odCyl == 0),
            $osAxis !== null || ($osCyl === null || $osCyl == 0),
            $pdSingle !== null || ($pdRight !== null && $pdLeft !== null),
        ];
        $completenessScore = (int) round(
            (count(array_filter($fields)) / count($fields)) * 100
        );

        return [
            'errors'             => $errors,
            'warnings'           => $warnings,
            'recommendations'    => $recommendations,
            'completeness_score' => $completenessScore,
            'is_valid'           => empty($errors),
        ];
    }
}
