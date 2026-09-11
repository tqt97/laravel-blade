<?php

it('renders a translated fallback page for web 404 responses', function (): void {
    $this->get('/route-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Back to home');
});
