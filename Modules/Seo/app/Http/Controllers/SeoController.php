<?php

namespace Modules\Seo\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * =====================================================================
 * CHỨC NĂNG FILE: Controller điều phối HTTP của module Seo (khung ban đầu).
 * =====================================================================
 *
 * Siêu dữ liệu SEO đa hình qua trait HasSeoMeta (title, description, OpenGraph, Canonical).
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
class SeoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('seo::index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('seo::create');
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
        return view('seo::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('seo::edit');
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
