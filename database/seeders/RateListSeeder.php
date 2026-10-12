<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the real lab catalog from rate list.xlsx (Table 2–43).
 *
 * Source: database/seeders/data/rate_list.json (1161 tests, generated
 * from "rate list.xlsx" with merged-cell dedup; no test skipped).
 *
 * Excel columns preserved: S.No., Test Code, Investigations, Price MRP 2022,
 * Sample, TEMP, Method, Schedule, Reporting, General Instructions (+ NABL "*"
 * and Outsourced "#" flags).
 *
 * Duplicate source codes (B026, M060, A146, P421) are stored with a "-2"
 * suffix so the unique constraint holds; original_code is kept in the
 * description for traceability.
 *
 * Categories: each test gets a best-guess department from its name
 * (keyword map below); anything unmatched lands in "Rate List 2022".
 * Re-running the seeder NEVER moves a test you manually assigned to a
 * different category, and NEVER overwrites an admin's Active/Inactive
 * choice — only tests still sitting in the "Rate List 2022"
 * bucket get remapped. So: seed once, then freely add/edit categories and
 * reassign tests from Admin → Tests.
 */
class RateListSeeder extends Seeder
{
    /** slug => [name, description] for every category this seeder may use. */
    private const CATEGORIES = [
        'rate-list-2022' => ['Rate List 2022', 'Unclassified imports from rate list 2022 (Table 2–43).'],
        'health-packages-profiles' => ['Health Packages & Profiles', 'Multi-test wellness profiles, panels and packages.'],
        'microbiology-culture' => ['Microbiology & Culture', 'Bacterial, fungal and culture-sensitivity testing.'],
        'molecular-pcr-diagnostics' => ['Molecular & PCR Diagnostics', 'DNA/RNA, gene mutation, cytogenetic and PCR testing.'],
        'cytology-histopathology' => ['Cytology & Histopathology', 'FNAC, Pap smear, biopsy and tissue examination.'],
        'thyroid-hormones' => ['Thyroid & Hormones', 'Endocrine and hormonal balance testing.'],
        'vitamin-deficiency' => ['Vitamin Deficiency', 'Essential nutrient and deficiency screening.'],
        'cardiac-lipid' => ['Cardiac & Lipid', 'Heart risk, cholesterol and lipid panels.'],
        'kidney-liver-function' => ['Kidney & Liver Function', 'Renal and hepatic performance markers.'],
        'diabetes-screening' => ['Diabetes Screening', 'Glucose control and metabolic tracking.'],
        'hematology' => ['Hematology', 'Blood counts, coagulation, anemia and blood disorders.'],
        'serology-immunology' => ['Serology & Immunology', 'Antibodies, antigens, allergy and autoimmune markers.'],
        'biochemistry' => ['Biochemistry', 'Metabolic, electrolyte, enzyme and organ-function chemistry.'],
    ];

    /** Ordered keyword map: first matching bucket wins. */
    private const KEYWORDS = [
        'health-packages-profiles' => ['swasthya', 'arogyam', 'nirogyam', 'checkup', 'package', 'master health', 'profile', 'panel'],
        'microbiology-culture' => ['culture', 'sensitivity', 'acid fast', 'afb', 'gram stain', 'koh', 'fungus', 'mycobacteri', 'bactec', 'mgit'],
        'molecular-pcr-diagnostics' => ['pcr', 'dna', 'rna', 'mutation', 'sequencing', 'hla', 'fish', 'karyotyp', 'chromosome', 'cytogenetic', 'microdeletion', 'translocation', 'gene'],
        'cytology-histopathology' => ['biopsy', 'fnac', 'pap smear', 'cytology', 'histopath', 'ihc marker', 'immunohistochemistry'],
        'thyroid-hormones' => ['thyroid', 'tsh', 'testosterone', 'prolactin', 'cortisol', 'estrogen', 'progesterone', 'amh', 'fsh', ' lh '],
        'vitamin-deficiency' => ['vitamin'],
        'cardiac-lipid' => ['cardiac', 'troponin', 'ck-mb', 'bnp', 'homocysteine', 'apolipoprotein', 'ldl', 'hdl', 'lipid', 'cholesterol', 'triglyceride'],
        'kidney-liver-function' => ['kidney', 'liver', 'kft', 'lft', 'creatinine', 'bilirubin', 'sgpt', 'sgot'],
        'diabetes-screening' => ['glucose', 'hba1c', 'fructosamine', 'c-peptide', 'insulin'],
        'hematology' => ['cbc', 'hemoglobin', 'esr', 'platelet', 'anemia', 'thalassemia', 'coagulation', 'prothrombin', 'aptt', 'd-dimer', 'g6pd', 'reticulocyte', 'bone marrow', 'ferritin'],
        'serology-immunology' => ['antibody', 'antibodies', 'antigen', 'hiv', 'hbsag', 'hbv', 'hcv', 'hepatitis', 'dengue', 'malaria', 'typhoid', 'widal', 'vdrl', 'torch', 'allergy', 'ige', 'ana ', 'anca'],
        'biochemistry' => ['urea', 'electrolyte', 'sodium', 'potassium', 'calcium', 'magnesium', 'phosphorus', 'uric acid', 'amylase', 'lipase', 'enzyme', 'iron', 'selenium', 'zinc', 'osmolality'],
    ];

    public function run(): void
    {
        $ids = [];
        foreach (self::CATEGORIES as $slug => [$name, $description]) {
            $ids[$slug] = TestCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $description, 'status' => 'active']
            )->id;
        }
        $bucketId = $ids['rate-list-2022'];

        $path = database_path('seeders/data/rate_list.json');
        if (! file_exists($path)) {
            $this->command?->error("rate_list.json missing at {$path}");

            return;
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $created = 0;
        $updated = 0;
        foreach ($rows as $row) {
            $sampleDetail = $row['sample_detail'] ?? null;
            $attrs = [
                'serial_no' => $row['serial_no'] ?? null,
                'name' => $row['name'],
                'description' => $this->description($row),
                'preparation_instructions' => $row['general_instructions'] ?: null,
                'general_instructions' => $row['general_instructions'] ?: null,
                'sample_type' => $sampleDetail ? mb_substr($sampleDetail, 0, 64) : 'Blood',
                'sample_detail' => $sampleDetail ?: null,
                'storage_temp' => $row['storage_temp'] ?: null,
                'method' => $row['method'] ?: null,
                'schedule' => $row['schedule'] ?: null,
                'reporting' => $row['reporting'] ?: null,
                'source_sheet' => $row['source_sheet'] ?? null,
                'code_prefix' => $row['code_prefix'] ?? null,
                'price' => $row['price'],
                'mrp' => $row['price'],
                'turnaround_hours' => (int) ($row['turnaround_hours'] ?? 24),
                'parameter_count' => 1,
                'home_collection_available' => true,
                'is_nabl' => (bool) ($row['is_nabl'] ?? false),
                'is_outsourced' => (bool) ($row['is_outsourced'] ?? false),
                'is_discontinued' => (bool) ($row['is_discontinued'] ?? false),
            ];

            $existing = LabTest::where('code', $row['code'])->first();
            if ($existing) {
                // Preserve manual category moves: only remap tests still in the import bucket.
                if ($existing->category_id === $bucketId) {
                    $attrs['category_id'] = $ids[$this->mapCategory($row['name'])];
                }
                // Never overwrite an admin's Active/Inactive choice on re-seed.
                $existing->fill($attrs);
                $existing->save();
                $updated++;
            } else {
                LabTest::create([
                    'uuid' => (string) Str::uuid(),
                    'code' => $row['code'],
                    'category_id' => $ids[$this->mapCategory($row['name'])],
                    'status' => ($row['is_discontinued'] ?? false) ? 'inactive' : 'active',
                    ...$attrs,
                ]);
                $created++;
            }
        }

        $this->command?->info("RateListSeeder: {$created} created, {$updated} updated (".count($rows).' total).');
    }

    private function mapCategory(string $name): string
    {
        $hay = ' '.strtolower($name).' ';
        foreach (self::KEYWORDS as $slug => $words) {
            foreach ($words as $w) {
                if (str_contains($hay, $w)) {
                    return $slug;
                }
            }
        }

        return 'rate-list-2022';
    }

    private function description(array $row): ?string
    {
        $parts = [];
        if (! empty($row['original_code'])) {
            $parts[] = "Original code: {$row['original_code']} (duplicate in source file).";
        }
        if (! empty($row['sample_detail'])) {
            $parts[] = "Sample: {$row['sample_detail']}.";
        }
        if (! empty($row['method'])) {
            $parts[] = "Method: {$row['method']}.";
        }
        if (! empty($row['schedule'])) {
            $parts[] = "Schedule: {$row['schedule']}.";
        }
        if (! empty($row['reporting'])) {
            $parts[] = "Reporting: {$row['reporting']}.";
        }
        if (! empty($row['storage_temp'])) {
            $parts[] = "Storage: {$row['storage_temp']}.";
        }

        return $parts ? implode(' ', $parts) : null;
    }
}
