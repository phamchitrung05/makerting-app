<?php

namespace Modules\Taxonomy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller điều phối HTTP của module Taxonomy (khung ban đầu).
 * =====================================================================
 *
 * Phân loại dùng chung: categories, tags, categorizables, taggables và trait HasTaxonomy.
 *
 * Controller chỉ điều phối HTTP: Route → Middleware → FormRequest → Controller
 * → Action/Service → JsonResource/BaseResponse; không chứa business rule dài.
 * Các method resource là khung mặc định, sẽ thay bằng endpoint thực ở Phase sau.
 *
 * CÁC HÀM/METHOD TRONG FILE:
 * - index/create/store/show/edit/update/destroy: khung resource mặc định
 * INPUT/OUTPUT CỦA CLASS (tổng thể):
 * - INPUT : Illuminate\Http\Request và route params
 * - OUTPUT: view/response placeholder (đổi sang BaseResponse)
 * =====================================================================
 */
class TaxonomyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('taxonomy::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('taxonomy::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('taxonomy::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('taxonomy::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
