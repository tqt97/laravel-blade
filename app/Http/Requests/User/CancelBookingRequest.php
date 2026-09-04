<?php

namespace App\Http\Requests\User;

use App\Booking\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking && $this->user()?->can('cancel', $booking) === true;
    }

    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }
}
