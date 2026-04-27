<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreMemberCompanyRequest;
use App\Http\Requests\Api\UpdateMemberCompanyRequest;
use App\Http\Resources\DoanhNghiepResource;
use App\Http\Resources\MemberCompanyResource;
use App\Http\Resources\MemberResource;
use App\Models\DoanhNghiep;
use App\Models\Member;
use App\Models\MemberCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MemberCompanyController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = MemberCompany::query()
            ->with(['member', 'doanhNghiep'])
            ->when(request('memberId'), function ($query, $memberId) {
                $query->where('member_id', $memberId);
            })
            ->when(request('doanhNghiepId'), function ($query, $doanhNghiepId) {
                $query->where('doanh_nghiep_id', $doanhNghiepId);
            })
            ->orderBy('created_at', 'desc');

        $perPage = request('perPage', 15);
        $memberCompanies = $query->paginate($perPage);

        return MemberCompanyResource::collection($memberCompanies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberCompanyRequest $request): JsonResponse
    {
        $data = [
            'member_id' => $request->input('memberId'),
            'doanh_nghiep_id' => $request->input('doanhNghiepId'),
        ];

        $memberCompany = MemberCompany::create($data);
        $memberCompany->load(['member', 'doanhNghiep']);

        return $this->success(
            new MemberCompanyResource($memberCompany),
            'Member-Company association created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(MemberCompany $memberCompany): JsonResponse
    {
        $memberCompany->load(['member', 'doanhNghiep']);

        return $this->success(new MemberCompanyResource($memberCompany));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberCompanyRequest $request, MemberCompany $memberCompany): JsonResponse
    {
        $data = [];
        if ($request->has('memberId')) {
            $data['member_id'] = $request->input('memberId');
        }
        if ($request->has('doanhNghiepId')) {
            $data['doanh_nghiep_id'] = $request->input('doanhNghiepId');
        }

        $memberCompany->update($data);
        $memberCompany->load(['member', 'doanhNghiep']);

        return $this->success(
            new MemberCompanyResource($memberCompany->fresh()),
            'Member-Company association updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MemberCompany $memberCompany): JsonResponse
    {
        $memberCompany->delete();

        return $this->success(null, 'Member-Company association deleted successfully');
    }

    /**
     * Attach companies to a member (bulk).
     */
    public function attachCompanies(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'doanhNghiepIds' => ['required', 'array'],
            'doanhNghiepIds.*' => ['integer', 'exists:doanh_nghieps,id'],
        ]);

        $member->doanhNghieps()->syncWithoutDetaching($validated['doanhNghiepIds']);
        $member->load('doanhNghieps');

        return $this->success(
            new MemberResource($member),
            'Companies attached successfully'
        );
    }

    /**
     * Detach companies from a member (bulk).
     */
    public function detachCompanies(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'doanhNghiepIds' => ['required', 'array'],
            'doanhNghiepIds.*' => ['integer', 'exists:doanh_nghieps,id'],
        ]);

        $member->doanhNghieps()->detach($validated['doanhNghiepIds']);
        $member->load('doanhNghieps');

        return $this->success(
            new MemberResource($member),
            'Companies detached successfully'
        );
    }

    /**
     * Sync companies for a member (bulk).
     */
    public function syncCompanies(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'doanhNghiepIds' => ['required', 'array'],
            'doanhNghiepIds.*' => ['integer', 'exists:doanh_nghieps,id'],
        ]);

        $member->doanhNghieps()->sync($validated['doanhNghiepIds']);
        $member->load('doanhNghieps');

        return $this->success(
            new MemberResource($member),
            'Companies synced successfully'
        );
    }

    /**
     * Attach members to a company (bulk).
     */
    public function attachMembers(Request $request, DoanhNghiep $doanhNghiep): JsonResponse
    {
        $validated = $request->validate([
            'memberIds' => ['required', 'array'],
            'memberIds.*' => ['integer', 'exists:members,id'],
        ]);

        $doanhNghiep->members()->syncWithoutDetaching($validated['memberIds']);
        $doanhNghiep->load('members');

        return $this->success(
            new DoanhNghiepResource($doanhNghiep),
            'Members attached successfully'
        );
    }

    /**
     * Detach members from a company (bulk).
     */
    public function detachMembers(Request $request, DoanhNghiep $doanhNghiep): JsonResponse
    {
        $validated = $request->validate([
            'memberIds' => ['required', 'array'],
            'memberIds.*' => ['integer', 'exists:members,id'],
        ]);

        $doanhNghiep->members()->detach($validated['memberIds']);
        $doanhNghiep->load('members');

        return $this->success(
            new DoanhNghiepResource($doanhNghiep),
            'Members detached successfully'
        );
    }

    /**
     * Sync members for a company (bulk).
     */
    public function syncMembers(Request $request, DoanhNghiep $doanhNghiep): JsonResponse
    {
        $validated = $request->validate([
            'memberIds' => ['required', 'array'],
            'memberIds.*' => ['integer', 'exists:members,id'],
        ]);

        $doanhNghiep->members()->sync($validated['memberIds']);
        $doanhNghiep->load('members');

        return $this->success(
            new DoanhNghiepResource($doanhNghiep),
            'Members synced successfully'
        );
    }
}
