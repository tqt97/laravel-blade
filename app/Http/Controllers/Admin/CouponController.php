<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Commerce\Coupons\CreateCoupon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Models\Commerce\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class CouponController extends Controller
{
    public function index(): View
    {
        return view('admin.cinema.coupons', [
            'coupons' => Coupon::query()->latest()->paginate((int) config('booking.listing.admin_page_size')),
        ]);
    }

    public function store(SaveCouponRequest $request, CreateCoupon $createCoupon): RedirectResponse
    {
        $createCoupon->execute($request->validated());

        return back()->with('status', 'cinema.admin.coupon_created');
    }
}
