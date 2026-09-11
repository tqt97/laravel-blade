<?php

namespace App\Http\Requests\Admin;

use App\Models\Movie\Booking;
use Illuminate\Foundation\Http\FormRequest;

final class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refund-bookings') === true
            && $this->route('booking') instanceof Booking;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}
