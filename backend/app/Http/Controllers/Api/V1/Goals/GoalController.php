<?php

namespace App\Http\Controllers\Api\V1\Goals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Goals\ContributeGoalRequest;
use App\Http\Requests\Api\V1\Goals\StoreGoalRequest;
use App\Http\Requests\Api\V1\Goals\UpdateGoalRequest;
use App\Http\Resources\Api\V1\GoalResource;
use App\Models\Goal;
use App\Services\Goals\GoalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GoalController extends Controller
{
    public function __construct(
        protected GoalService $goalService
    ) {}

    /**
     * Display a listing of user goals with progress & pacing analytics.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);
        $progress = $this->goalService->getGoalsWithProgress($request->user(), $filters);

        $formattedGoals = array_map(function ($item) {
            return [
                'goal' => new GoalResource($item['goal']),
                'target_amount' => $item['target_amount'],
                'target_amount_formatted' => $item['target_amount_formatted'],
                'current_amount' => $item['current_amount'],
                'current_amount_formatted' => $item['current_amount_formatted'],
                'remaining_amount' => $item['remaining_amount'],
                'remaining_amount_formatted' => $item['remaining_amount_formatted'],
                'percent_achieved' => $item['percent_achieved'],
                'days_remaining' => $item['days_remaining'],
                'months_remaining' => $item['months_remaining'],
                'monthly_required' => $item['monthly_required'],
                'monthly_required_formatted' => $item['monthly_required_formatted'],
                'is_achieved' => $item['is_achieved'],
            ];
        }, $progress['goals']);

        return response()->json([
            'goals' => $formattedGoals,
            'summary' => $progress['summary'],
        ]);
    }

    /**
     * Store a newly created goal.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        Gate::authorize('create', Goal::class);

        $goal = $this->goalService->createGoal(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Financial savings goal created successfully.',
            'goal' => new GoalResource($goal->load(['account.institution'])),
        ], 201);
    }

    /**
     * Display the specified goal.
     */
    public function show(Goal $goal): JsonResponse
    {
        Gate::authorize('view', $goal);

        return response()->json([
            'goal' => new GoalResource($goal->load(['account.institution'])),
        ]);
    }

    /**
     * Update the specified goal.
     */
    public function update(UpdateGoalRequest $request, Goal $goal): JsonResponse
    {
        Gate::authorize('update', $goal);

        $updated = $this->goalService->updateGoal(
            $goal,
            $request->validated()
        );

        return response()->json([
            'message' => 'Financial goal updated successfully.',
            'goal' => new GoalResource($updated),
        ]);
    }

    /**
     * Remove the specified goal.
     */
    public function destroy(Goal $goal): JsonResponse
    {
        Gate::authorize('delete', $goal);

        $this->goalService->deleteGoal($goal);

        return response()->json([
            'message' => 'Financial goal deleted successfully.',
        ]);
    }

    /**
     * Contribute savings funds toward the goal.
     */
    public function contribute(ContributeGoalRequest $request, Goal $goal): JsonResponse
    {
        Gate::authorize('update', $goal);

        $updated = $this->goalService->contributeToGoal(
            $goal,
            $request->validated('amount')
        );

        return response()->json([
            'message' => 'Milestone savings contribution recorded successfully.',
            'goal' => new GoalResource($updated),
        ]);
    }
}
