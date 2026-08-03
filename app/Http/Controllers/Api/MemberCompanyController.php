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
use App\Models\User;
use App\Support\DoanhNghiepScopeHelper;
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
        $user = request()->user();

        $query = MemberCompany::query()
            ->with([
                'member:id,full_name',
                'doanhNghiep:id,ma_so_doanh_nghiep,ten_doanh_nghiep,don_vi_id',
            ])
            ->whereHas('doanhNghiep', fn ($q) => DoanhNghiepScopeHelper::applyScope($q, $user))
            ->when(request('memberId'), function ($query, $memberId) {
                $query->where('member_id', $memberId);
            })
            ->when(request('doanhNghiepId'), function ($query, $doanhNghiepId) use ($user) {
                $accessibleIds = DoanhNghiepScopeHelper::filterAccessibleCompanyIds($user, [(int) $doanhNghiepId]);
                if ($accessibleIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('doanh_nghiep_id', $accessibleIds[0]);
                }
            })
            ->orderBy('created_at', 'desc');

        $perPage = request('perPage', 15);
        $perPage = min(max((int) $perPage, 1), 100);
        $memberCompanies = $query->paginate($perPage);

        return MemberCompanyResource::collection($memberCompanies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMemberCompanyRequest $request): JsonResponse
    {
        $company = DoanhNghiep::query()->find($request->input('doanhNghiepId'));
        if (!$company || !DoanhNghiepScopeHelper::userCanAccess($request->user(), $company)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

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
        if (!$this->userCanAccessMemberCompany($memberCompany)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $memberCompany->load(['member', 'doanhNghiep']);

        return $this->success(new MemberCompanyResource($memberCompany));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMemberCompanyRequest $request, MemberCompany $memberCompany): JsonResponse
    {
        if (!$this->userCanAccessMemberCompany($memberCompany)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

        $data = [];
        if ($request->has('memberId')) {
            $data['member_id'] = $request->input('memberId');
        }
        if ($request->has('doanhNghiepId')) {
            $company = DoanhNghiep::query()->find($request->input('doanhNghiepId'));
            if (!$company || !DoanhNghiepScopeHelper::userCanAccess($request->user(), $company)) {
                return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
            }
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
        if (!$this->userCanAccessMemberCompany($memberCompany)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

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

        $accessibleIds = $this->filterAccessibleCompanyIds($request->user(), $validated['doanhNghiepIds']);
        if (count($accessibleIds) !== count($validated['doanhNghiepIds'])) {
            return $this->error('Một hoặc nhiều doanh nghiệp không thuộc phạm vi đơn vị của bạn.', 403);
        }

        $member->doanhNghieps()->syncWithoutDetaching($accessibleIds);
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

        $accessibleIds = $this->filterAccessibleCompanyIds($request->user(), $validated['doanhNghiepIds']);
        if (count($accessibleIds) !== count($validated['doanhNghiepIds'])) {
            return $this->error('Một hoặc nhiều doanh nghiệp không thuộc phạm vi đơn vị của bạn.', 403);
        }

        $member->doanhNghieps()->detach($accessibleIds);
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

        $accessibleIds = $this->filterAccessibleCompanyIds($request->user(), $validated['doanhNghiepIds']);
        if (count($accessibleIds) !== count($validated['doanhNghiepIds'])) {
            return $this->error('Một hoặc nhiều doanh nghiệp không thuộc phạm vi đơn vị của bạn.', 403);
        }

        $member->doanhNghieps()->sync($accessibleIds);
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
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

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
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

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
        if (!$this->userCanAccessCompany($doanhNghiep)) {
            return $this->error('Không có quyền truy cập doanh nghiệp này.', 403);
        }

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

    /**
     * @param  array<int, int>  $companyIds
     * @return array<int, int>
     */
    private function filterAccessibleCompanyIds(?User $user, array $companyIds): array
    {
        return DoanhNghiepScopeHelper::filterAccessibleCompanyIds($user, $companyIds);
    }

    private function userCanAccessCompany(DoanhNghiep $doanhNghiep): bool
    {
        return DoanhNghiepScopeHelper::userCanAccess(request()->user(), $doanhNghiep);
    }

    private function userCanAccessMemberCompany(MemberCompany $memberCompany): bool
    {
        $memberCompany->loadMissing('doanhNghiep');

        if ($memberCompany->doanhNghiep === null) {
            return false;
        }

        return $this->userCanAccessCompany($memberCompany->doanhNghiep);
    }
}
