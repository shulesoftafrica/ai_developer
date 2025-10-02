<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Sprint;
use App\Enums\TaskType;
use App\Enums\TaskStatus;
use Illuminate\Database\Seeder;

class PendingTaskSeeder extends Seeder
{
    public function run(): void
    {
        // Create a demo sprint first
        $sprint = Sprint::firstOrCreate([
            'name' => 'Demo Sprint',
            'description' => 'Demo sprint for testing purposes',
            'start_date' => now(),
            'end_date' => now()->addWeeks(2),
        ]);

        // Create React Native app task
        Task::create([
            'title' => 'Build React Native Shopping App',
            'description' => 'Create a complete React Native e-commerce mobile application',
            'type' => TaskType::FEATURE,
            'status' => TaskStatus::PENDING,
            'priority' => 1,
            'sprint_id' => $sprint->id,
            'content' => [
                'description' => 'Build a React Native shopping app with authentication, product catalog, cart, and payment',
                'requirements' => [
                    'React Native setup with TypeScript',
                    'User authentication',
                    'Product listing and details', 
                    'Shopping cart functionality',
                    'Payment integration',
                    'Order history'
                ]
            ]
        ]);

        // Create CI/CD task
        Task::create([
            'title' => 'Setup CI/CD Pipeline',
            'description' => 'Configure automated testing and deployment pipeline',
            'type' => TaskType::FEATURE, 
            'status' => TaskStatus::PENDING,
            'priority' => 2,
            'sprint_id' => $sprint->id,
            'content' => [
                'description' => 'Setup CI/CD pipeline for automated testing and deployment',
                'requirements' => [
                    'GitHub Actions workflow',
                    'Automated testing',
                    'Code quality checks', 
                    'Deployment automation'
                ]
            ]
        ]);

        $this->command->info('Created 2 pending tasks for queue testing');
    }
}