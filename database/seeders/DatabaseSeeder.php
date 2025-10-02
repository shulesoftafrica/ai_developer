<?php

namespace Database\Seeders;

use App\Models\Sprint;
use App\Models\Task;
use App\Models\Milestone;
use App\Models\AiLog;
use App\Enums\TaskType;
use App\Enums\TaskStatus;
use App\Enums\MilestoneStatus;
use App\Enums\AgentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SprintSeeder::class,
            TaskSeeder::class,
            MilestoneSeeder::class,
            // AiLogSeeder::class, // Commented out due to schema mismatch
        ]);
    }
}

class SprintSeeder extends Seeder
{
    public function run(): void
    {
        // Active sprint
        Sprint::create([
            'name' => 'Portifolio Platform Development',
            'description' => 'Build a comprehensive portfolio platform with user authentication, project catalog, and file uploads.',
            'status' => 'active',
            'start_date' => Carbon::now()->subDays(5),
            'end_date' => Carbon::now()->addDays(25),
            'metadata' => json_encode([
                'priority' => 'high',
                'team_size' => 3,
                'budget' => 50000,
                'goals' => [
                    'Implement user registration and authentication',
                    'Create project catalog with search and filtering',
                    'Build file upload functionality',
                    'Integrate payment processing',
                    'Add admin dashboard for project management'
                ]
            ])
        ]);

        // // Planning sprint
        // Sprint::create([
        //     'name' => 'Mobile App Development',
        //     'description' => 'Create a companion mobile app for the e-commerce platform with React Native.',
        //     'status' => 'planning',
        //     'start_date' => Carbon::now()->addDays(30),
        //     'end_date' => Carbon::now()->addDays(60),
        //     'metadata' => json_encode([
        //         'priority' => 'medium',
        //         'platform' => 'React Native',
        //         'target_platforms' => ['iOS', 'Android'],
        //         'goals' => [
        //             'Set up React Native development environment',
        //             'Implement authentication flow',
        //             'Create product browsing interface',
        //             'Add shopping cart functionality',
        //             'Implement push notifications'
        //         ]
        //     ])
        // ]);

        // // Completed sprint
        // Sprint::create([
        //     'name' => 'Database Design & Setup',
        //     'description' => 'Design and implement the database schema for the e-commerce platform.',
        //     'status' => 'completed',
        //     'start_date' => Carbon::now()->subDays(35),
        //     'end_date' => Carbon::now()->subDays(6),
        //     'metadata' => json_encode([
        //         'priority' => 'high',
        //         'database' => 'PostgreSQL',
        //         'completion_rate' => 100,
        //         'goals' => [
        //             'Design entity relationship diagram',
        //             'Create database migrations',
        //             'Set up user authentication tables',
        //             'Implement product and order tables',
        //             'Add indexes for performance optimization'
        //         ]
        //     ])
        // ]);
    }
}

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $activeSprint = Sprint::where('status', 'active')->first();
        // $planningSprint = Sprint::where('status', 'planning')->first();
        // $completedSprint = Sprint::where('status', 'completed')->first();

        // Tasks for active sprint
        $tasks = [
            [
                'sprint_id' => $activeSprint->id,
                'uuid' => (string) Str::uuid(),
                'title' => 'Implement User Authentication System',
                'description' => 'Create a complete portifolio system with user authentication to admin with registration, login, password reset, and email verification functionality and other functionalities. consider using Laravel 12.',
                'type' => TaskType::FEATURE,
                'status' => TaskStatus::PENDING,
                'priority' => 1,
                'content' => json_encode([
                    'requirements' => [
                        'Laravel Sanctum for API authentication',
                        'Email verification system',
                        'Password reset functionality',
                        'Role-based access control',
                        'Rate limiting for login attempts'
                    ],
                    'acceptance_criteria' => [
                        'Users can register with email and password',
                        'Email verification works correctly',
                        'Password reset via email functions',
                        'Login rate limiting prevents brute force',
                        'Admin and user roles are properly separated'
                    ],
                    'estimated_hours' => 16
                ])
            ],
            
        ];

        foreach ($tasks as $taskData) {
            Task::create($taskData);
        }

        // Task for planning sprint
        // Task::create([
        //     'sprint_id' => $planningSprint->id,
        //     'uuid' => (string) Str::uuid(),
        //     'title' => 'Setup React Native Development Environment',
        //     'description' => 'Configure development environment for React Native app development including tooling, dependencies, and initial project structure.',
        //     'type' => TaskType::FEATURE,
        //     'status' => TaskStatus::PENDING,
        //     'priority' => 2,
        //     'content' => json_encode([
        //         'requirements' => [
        //             'React Native CLI setup',
        //             'Android Studio configuration',
        //             'Xcode setup for iOS development',
        //             'Metro bundler configuration',
        //             'ESLint and Prettier setup'
        //         ],
        //         'acceptance_criteria' => [
        //             'Project builds successfully on both platforms',
        //             'Hot reload works correctly',
        //             'Code linting is enforced',
        //             'Development scripts are documented'
        //         ],
        //         'estimated_hours' => 12
        //     ])
        // ]);

        // // Completed task
        // Task::create([
        //     'sprint_id' => $completedSprint->id,
        //     'uuid' => (string) Str::uuid(),
        //     'title' => 'Design Database Schema',
        //     'description' => 'Create comprehensive database schema for e-commerce platform including all necessary tables, relationships, and constraints.',
        //     'type' => TaskType::FEATURE,
        //     'status' => TaskStatus::PENDING,
        //     'priority' => 1,
        //     'content' => json_encode([
        //         'requirements' => [
        //             'User management tables',
        //             'Product catalog structure',
        //             'Order processing tables',
        //             'Payment tracking',
        //             'Audit logging'
        //         ],
        //         'acceptance_criteria' => [
        //             'All tables created with proper constraints',
        //             'Foreign key relationships established',
        //             'Indexes added for performance',
        //             'Data migration scripts ready'
        //         ],
        //         'estimated_hours' => 20
        //     ])
        // ]);
    }
}

class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = Task::all();

        // foreach ($tasks as $task) {
        //     if ($task->status === TaskStatus::COMPLETED) {
        //         // Completed task - all milestones completed
        //         $this->createMilestonesForTask($task, MilestoneStatus::COMPLETED);
        //     } elseif ($task->status === TaskStatus::IN_PROGRESS) {
        //         // In progress task - some milestones completed, some in progress
        //         $this->createMilestonesForTask($task, MilestoneStatus::IN_PROGRESS);
        //     } else {
        //         // Pending task - all milestones pending
        //         $this->createMilestonesForTask($task, MilestoneStatus::PENDING);
        //     }
        // }
    }

    private function createMilestonesForTask(Task $task, MilestoneStatus $defaultStatus): void
    {
        $agentTypes = [
            AgentType::PM,
            AgentType::BA,
            AgentType::UX,
            AgentType::ARCH,
            AgentType::DEV,
            AgentType::QA,
            AgentType::DOC
        ];

        foreach ($agentTypes as $index => $agentType) {
            $status = $defaultStatus;
            
            // For in-progress tasks, make some milestones completed and some pending
            if ($defaultStatus === MilestoneStatus::IN_PROGRESS) {
                if ($index < 3) {
                    $status = MilestoneStatus::COMPLETED;
                } elseif ($index === 3) {
                    $status = MilestoneStatus::IN_PROGRESS;
                } else {
                    $status = MilestoneStatus::PENDING;
                }
            }

            Milestone::create([
                'task_id' => $task->id,
                'title' => $this->getMilestoneName($agentType, $task),
                'description' => $this->getMilestoneDescription($agentType, $task),
                'agent_type' => $agentType,
                'sequence' => $index + 1,
                'status' => $status,
                'output_data' => $status === MilestoneStatus::COMPLETED ? json_encode(['output' => $this->getMilestoneOutput($agentType, $task)]) : null,
                'completed_at' => $status === MilestoneStatus::COMPLETED ? Carbon::now()->subDays(rand(1, 10)) : null
            ]);
        }
    }

    private function getMilestoneName(AgentType $agentType, Task $task): string
    {
        return match($agentType) {
            AgentType::PM => "Project Planning for {$task->title}",
            AgentType::BA => "Requirements Analysis for {$task->title}",
            AgentType::UX => "User Experience Design for {$task->title}",
            AgentType::ARCH => "Technical Architecture for {$task->title}",
            AgentType::DEV => "Development Implementation for {$task->title}",
            AgentType::QA => "Quality Assurance for {$task->title}",
            AgentType::DOC => "Documentation for {$task->title}",
        };
    }

    private function getMilestoneDescription(AgentType $agentType, Task $task): string
    {
        return match($agentType) {
            AgentType::PM => "Break down the task into manageable sub-tasks, estimate effort, and create project timeline.",
            AgentType::BA => "Analyze business requirements, define user stories, and create acceptance criteria.",
            AgentType::UX => "Design user interface mockups, create user flow diagrams, and define interaction patterns.",
            AgentType::ARCH => "Design technical architecture, choose appropriate technologies, and define implementation approach.",
            AgentType::DEV => "Implement the functionality according to specifications and architectural guidelines.",
            AgentType::QA => "Create test cases, perform testing, and validate that all requirements are met.",
            AgentType::DOC => "Create technical documentation, user guides, and API documentation.",
        };
    }

    private function getMilestoneOutput(AgentType $agentType, Task $task): string
    {
        return match($agentType) {
            AgentType::PM => "Project plan created with 5 sub-tasks, estimated 16 hours total effort. Timeline: 2 weeks with 2 developers.",
            AgentType::BA => "Requirements document created with 8 user stories and detailed acceptance criteria. Identified 3 edge cases.",
            AgentType::UX => "UI mockups created for 4 screens. User flow diagram shows 3 primary paths. Accessibility guidelines defined.",
            AgentType::ARCH => "Architecture diagram created. Chosen Laravel + Vue.js stack. Identified 3 microservices and database schema.",
            AgentType::DEV => "Implementation completed with 15 files modified. All unit tests passing. Code coverage at 85%.",
            AgentType::QA => "Test suite created with 25 test cases. All tests passing. Performance benchmarks met. Security scan clean.",
            AgentType::DOC => "Technical documentation created. API documentation generated. User guide with 10 sections completed.",
        };
    }
}

class AiLogSeeder extends Seeder
{
//     public function run(): void
//     {
//         $milestones = Milestone::where('status', MilestoneStatus::COMPLETED)->get();
        
//         foreach ($milestones as $milestone) {
//             // Create multiple AI logs for each completed milestone
//             for ($i = 0; $i < rand(2, 5); $i++) {
//                 AiLog::create([
//                     'agent_type' => $milestone->agent_type,
//                     'prompt' => $this->generatePrompt($milestone),
//                     'response' => $this->generateResponse($milestone),
//                     'success' => rand(0, 10) > 1, // 90% success rate
//                     'execution_time_ms' => rand(1500, 8000),
//                     'tokens_used' => rand(500, 3000),
//                     'run_id' => 'run_' . uniqid(),
//                     'metadata' => json_encode([
//                         'model' => 'claude-3-5-sonnet-20241022',
//                         'temperature' => 0.1,
//                         'max_tokens' => 4096,
//                         'milestone_id' => $milestone->id
//                     ]),
//                     'created_at' => Carbon::now()->subDays(rand(1, 30))
//                 ]);
//             }
//         }

//         // Add some recent logs for today
//         for ($i = 0; $i < 10; $i++) {
//             AiLog::create([
//                 'agent_type' => AgentType::cases()[array_rand(AgentType::cases())],
//                 'prompt' => 'System health check and performance monitoring request.',
//                 'response' => 'System is operating normally. All services are healthy.',
//                 'success' => true,
//                 'execution_time_ms' => rand(500, 2000),
//                 'tokens_used' => rand(100, 500),
//                 'run_id' => 'run_' . uniqid(),
//                 'metadata' => json_encode([
//                     'model' => 'claude-3-5-sonnet-20241022',
//                     'temperature' => 0.1,
//                     'type' => 'health_check'
//                 ]),
//                 'created_at' => Carbon::now()->subHours(rand(1, 12))
//             ]);
//         }
//     }

    // private function generatePrompt(Milestone $milestone): string
    // {
    //     return "Please {$milestone->description} for the task: {$milestone->task->title}. 
        
    //     Task Description: {$milestone->task->description}
        
    //     Please analyze the requirements and provide detailed recommendations for this milestone.";
    // }

    // private function generateResponse(Milestone $milestone): string
    // {
    //     $agentTypeValue = is_string($milestone->agent_type) ? $milestone->agent_type : $milestone->agent_type->value;
        
    //     return "I have successfully completed the {$agentTypeValue} milestone for {$milestone->task->title}. 
        
    //     Key deliverables:
    //     - Analyzed requirements and created detailed specifications
    //     - Identified potential risks and mitigation strategies  
    //     - Provided implementation recommendations
    //     - Created necessary documentation
        
    //     The milestone has been completed according to the defined acceptance criteria and is ready for the next phase.";
    // }
}