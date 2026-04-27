<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreMemberRequest;
use App\Http\Requests\Api\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Member::query()
            ->when(request('cccd'), function ($query, $cccd) {
                $query->where('cccd', $cccd);
            })
            ->when(request('gender'), function ($query, $gender) {
                $query->where('gender', $gender);
            })
            ->when(request('status') !== null, function ($query) {
                $query->where('status', request('status'));
            })
            ->when(request('sortBy'), function ($query, $sortBy) {
                $direction = request('sortDirection', 'asc');
                $allowedSorts = ['full_name', 'status', 'cccd', 'created_at'];
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $direction);
                }
            }, function ($query) {
                $query->orderBy('created_at', 'desc');
            });

        $perPage = request('perPage', 15);
        $members = $query->paginate($perPage);

        return MemberResource::collection($members);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        $data = $this->mapCamelToSnake($request->validated());
        $member = Member::create($data);

        return $this->success(
            new MemberResource($member),
            'Member created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Member $member): JsonResponse
    {
        $member->load('doanhNghieps');

        return $this->success(new MemberResource($member));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberRequest $request, Member $member): JsonResponse
    {
        $data = $this->mapCamelToSnake($request->validated());
        $member->update($data);
        $member->load('doanhNghieps');

        return $this->success(
            new MemberResource($member->fresh()),
            'Member updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Member $member): JsonResponse
    {
        $member->delete();

        return $this->success(null, 'Member deleted successfully');
    }

    /**
     * Map camelCase keys to snake_case for database storage.
     */
    private function mapCamelToSnake(array $data): array
    {
        $mapping = [
            'fullName' => 'full_name',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            $result[$mapping[$key] ?? $key] = $value;
        }

        return $result;
    }
}
