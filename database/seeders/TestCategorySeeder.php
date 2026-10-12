<?php

namespace Database\Seeders;

use App\Models\TestCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Starter set only — admins can add/edit/deactivate later from
        // Admin → Tests → Categories tab. Seeder is idempotent (matched by
        // slug) so re-running never duplicates or wipes manual edits.
        $categories = [
            ['Blood Tests', 'Routine hematology and blood chemistry profiles.'],
            ['Full Body Checkups', 'Comprehensive multi-organ wellness packages.'],
            ['Diabetes Screening', 'Glucose control and metabolic tracking.'],
            ['Cardiac & Lipid', 'Heart risk, cholesterol and lipid panels.'],
            ['Thyroid & Hormones', 'Endocrine and hormonal balance testing.'],
            ['Vitamin Deficiency', 'Essential nutrient and deficiency screening.'],
            ['Kidney & Liver Function', 'Renal and hepatic performance markers.'],
            ['Hematology', 'Blood counts, coagulation, anemia and blood disorders.'],
            ['Biochemistry', 'Metabolic, electrolyte, enzyme and organ-function chemistry.'],
            ['Microbiology & Culture', 'Bacterial, fungal and culture-sensitivity testing.'],
            ['Serology & Immunology', 'Antibodies, antigens, allergy and autoimmune markers.'],
            ['Molecular & PCR Diagnostics', 'DNA/RNA, gene mutation, cytogenetic and PCR testing.'],
            ['Cytology & Histopathology', 'FNAC, Pap smear, biopsy and tissue examination.'],
            ['Health Packages & Profiles', 'Multi-test wellness profiles, panels and packages.'],
        ];

        foreach ($categories as [$name, $description]) {
            TestCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'status' => 'active']
            );
        }
    }
}
