<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Helpers\ChartHelper;

class BranchTargetController extends Controller
{
    public function showInputForm(Request $request)
    {
        $month = $request->get('month', date('n'));
        $year = $request->get('year', date('Y'));
        $category = $request->get('category', 'MIKA');

        // Get all branches
        $branches = ChartHelper::getLocations();
        
        // Get existing targets if any
        $existingTargets = DB::table('branch_targets')
            ->where('month', $month)
            ->where('year', $year)
            ->where('category', $category)
            ->pluck('target_amount', 'branch_name')
            ->toArray();

        return view('branch-target-input', compact('month', 'year', 'category', 'branches', 'existingTargets'));
    }

    public function saveTargets(Request $request)
    {
        try {
            $month = $request->input('month');
            $year = $request->input('year');
            $category = $request->input('category');
            $targets = $request->input('targets', []);

            // Validation rules
            $validator = Validator::make($request->all(), [
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer|between:2021,2025',
                'category' => 'required|string|in:MIKA,SPARE PART',
                'targets' => 'required|array|min:17',
                'targets.*' => 'required|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get all expected branches
            $expectedBranches = ChartHelper::getLocations()->toArray();
            
            // Check if all branches have targets
            $missingBranches = [];
            foreach ($expectedBranches as $branch) {
                if (!isset($targets[$branch]) || empty($targets[$branch])) {
                    $missingBranches[] = $branch;
                }
            }

            if (!empty($missingBranches)) {
                return response()->json([
                    'success' => false,
                    'message' => 'All branch targets must be filled',
                    'missing_branches' => $missingBranches
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Delete existing targets for this period
                DB::table('branch_targets')
                    ->where('month', $month)
                    ->where('year', $year)
                    ->where('category', $category)
                    ->delete();

                // Insert new targets
                $insertData = [];
                foreach ($targets as $branchName => $targetAmount) {
                    if (in_array($branchName, $expectedBranches)) {
                        $insertData[] = [
                            'month' => $month,
                            'year' => $year,
                            'category' => $category,
                            'branch_name' => $branchName,
                            'target_amount' => $targetAmount,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }

                if (!empty($insertData)) {
                    DB::table('branch_targets')->insert($insertData);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Targets saved successfully',
                    'redirect_url' => route('dashboard') . '#target-revenue-section'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('BranchTargetController saveTargets error: ' . $e->getMessage(), [
                'month' => $request->input('month'),
                'year' => $request->input('year'),
                'category' => $request->input('category'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save targets. Please try again.'
            ], 500);
        }
    }

    public function getMonthName($month)
    {
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        return $months[$month] ?? 'Unknown';
    }
}
