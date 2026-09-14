<?php

it('renders a translated fallback page for web 404 responses', function (): void {
    $this->get('/route-does-not-exist')
        ->assertNotFound()
        ->assertSee(__('booking.errors.not_found_title'))
        ->assertSee(__('booking.errors.back_home'));
});
