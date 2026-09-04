<?php

namespace Database\Seeders;

use App\Models\BookableResource;
use Illuminate\Database\Seeder;

class BookableResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            ['slug' => 'meeting-room-a', 'name' => 'Meeting Room A', 'description' => 'A quiet room for focused meetings and small team sessions.', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => true],
            ['slug' => 'meeting-room-b', 'name' => 'Meeting Room B', 'description' => 'A flexible room for workshops and larger team meetings.', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => true],
            ['slug' => 'training-room', 'name' => 'Training Room', 'description' => 'A larger space for training sessions and presentations.', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => true],
            ['slug' => 'recording-studio', 'name' => 'Recording Studio', 'description' => 'A bookable studio for recording product demos and lessons.', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_active' => false],
        ];

        foreach ($resources as $resource) {
            BookableResource::query()->updateOrCreate(['slug' => $resource['slug']], $resource);
        }
    }
}
