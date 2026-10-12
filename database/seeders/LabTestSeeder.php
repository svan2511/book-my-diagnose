<?php

namespace Database\Seeders;

use App\Models\LabTest;
use App\Models\TestCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catalog mirrors the launch test directory (8 profiles).
 */
class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        $tests = [
            [
                'category' => 'blood-tests', 'name' => 'Complete Blood Count (CBC)', 'code' => 'CBC001',
                'description' => 'Evaluates overall cellular health: Hemoglobin, RBC, WBC count, and Platelet levels.',
                'preparation_instructions' => 'No overnight fasting is required. Drink 1 glass of water 15 minutes before phlebotomy.',
                'sample_type' => 'Whole Blood (EDTA)', 'price' => 399, 'mrp' => 650,
                'turnaround_hours' => 12, 'parameter_count' => 24,
            ],
            [
                'category' => 'kidney-liver-function', 'name' => 'Liver Function Test (LFT)', 'code' => 'LFT002',
                'description' => 'Evaluates hepatic enzyme markers: SGPT, SGOT, Total Bilirubin, Direct Bilirubin, and Alkaline Phosphatase.',
                'preparation_instructions' => 'Strict 10-12 hours overnight fasting mandatory. Avoid alcohol for 48 hours prior.',
                'sample_type' => 'Blood (Serum)', 'price' => 699, 'mrp' => 1100,
                'turnaround_hours' => 12, 'parameter_count' => 11,
            ],
            [
                'category' => 'kidney-liver-function', 'name' => 'Kidney Function Test (KFT)', 'code' => 'KFT003',
                'description' => 'Measures glomerular filtration & metabolic waste clearance: Serum Creatinine, Urea, Uric Acid, and BUN.',
                'preparation_instructions' => 'Fasting not required unless combined with glucose screening. Stay hydrated.',
                'sample_type' => 'Blood (Serum)', 'price' => 649, 'mrp' => 950,
                'turnaround_hours' => 12, 'parameter_count' => 8,
            ],
            [
                'category' => 'diabetes-screening', 'name' => 'HbA1c Glycated Hemoglobin', 'code' => 'DIA102',
                'description' => 'Reliable 3-month rolling snapshot of blood glucose with Estimated Average Glucose (eAG).',
                'preparation_instructions' => 'No fasting required. Take morning diabetic medication as usual.',
                'sample_type' => 'Whole Blood', 'price' => 499, 'mrp' => 850,
                'turnaround_hours' => 8, 'parameter_count' => 2,
            ],
            [
                'category' => 'thyroid-hormones', 'name' => 'Complete Thyroid Care (T3, T4, TSH)', 'code' => 'THY301',
                'description' => 'Full endocrine panel detecting hyperthyroidism, hypothyroidism, and pituitary hormonal balance.',
                'preparation_instructions' => 'Morning draw recommended (07:00-10:00). Take thyroid medication after the draw.',
                'sample_type' => 'Blood (Serum)', 'price' => 549, 'mrp' => 890,
                'turnaround_hours' => 10, 'parameter_count' => 3,
            ],
            [
                'category' => 'cardiac-lipid', 'name' => 'Lipid Profile Heart Risk', 'code' => 'LIP204',
                'description' => 'Cardiovascular assessment: Total Cholesterol, HDL, LDL, VLDL, and Triglycerides ratio.',
                'preparation_instructions' => 'Mandatory 12-hour strict overnight fasting. Water permitted.',
                'sample_type' => 'Blood (Serum)', 'price' => 799, 'mrp' => 1350,
                'turnaround_hours' => 12, 'parameter_count' => 9,
            ],
            [
                'category' => 'vitamin-deficiency', 'name' => 'Vitamin D (25-OH) & B12 Duo', 'code' => 'VIT405',
                'description' => 'Essential bone & neurological health biomarkers for fatigue, immunity, and nerve health.',
                'preparation_instructions' => 'No fasting required. Sample shielded from light during transport.',
                'sample_type' => 'Serum (Protected)', 'price' => 1199, 'mrp' => 2400,
                'turnaround_hours' => 24, 'parameter_count' => 2,
            ],
            [
                'category' => 'full-body-checkups', 'name' => 'Executive Master Health Checkup', 'code' => 'FBP501',
                'description' => 'All-in-one 84-parameter check: CBC, LFT, KFT, Lipid, HbA1c, Thyroid, Urine Routine, Iron.',
                'preparation_instructions' => 'Strict 10-12 hours fasting required. Free phlebotomist home visit included.',
                'sample_type' => 'Blood + Urine', 'price' => 1899, 'mrp' => 4500,
                'turnaround_hours' => 24, 'parameter_count' => 84,
            ],
        ];

        foreach ($tests as $data) {
            $category = TestCategory::where('slug', $data['category'])->firstOrFail();
            unset($data['category']);

            LabTest::updateOrCreate(
                ['code' => $data['code']],
                [...$data, 'category_id' => $category->id, 'uuid' => (string) Str::uuid(), 'status' => 'active', 'home_collection_available' => true]
            );
        }
    }
}
