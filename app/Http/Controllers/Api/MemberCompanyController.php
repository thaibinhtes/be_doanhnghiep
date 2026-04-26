<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DoanhNghiepResource;
use App\Http\Resources\MemberResource;
use App\Models\DoanhNghiep;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberCompanyController extends ApiController
{
    /**
     * Attach companies to a member.
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
     * Detach companies from a member.
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
     * Sync companies for a member.
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
     * Attach members to a company.
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
     * Detach members from a company.
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
     * Sync members for a company.
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
