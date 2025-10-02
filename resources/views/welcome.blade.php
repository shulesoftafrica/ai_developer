@extends('layouts.app')

@section('title', 'Welcome - Kudos Orchestrator')

@section('content')
<div class="text-center py-12">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-4xl font-bold text-gray-900 mb-6">
            <i class="fas fa-robot text-blue-600 mr-3"></i>
            Welcome to Kudos AI Development Orchestrator
        </h1>
        
        <p class="text-xl text-gray-600 mb-8">
            An intelligent system that orchestrates AI-powered development workflows, 
            breaking down tasks into milestones and coordinating multiple AI agents 
            to deliver comprehensive software solutions.
        </p>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <i class="fas fa-brain text-3xl text-blue-600 mb-4"></i>
                <h3 class="text-lg font-semibold mb-2">AI-Powered</h3>
                <p class="text-gray-600">7 specialized AI agents working together</p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <i class="fas fa-cogs text-3xl text-green-600 mb-4"></i>
                <h3 class="text-lg font-semibold mb-2">Automated</h3>
                <p class="text-gray-600">End-to-end development automation</p>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <i class="fas fa-shield-alt text-3xl text-purple-600 mb-4"></i>
                <h3 class="text-lg font-semibold mb-2">Secure</h3>
                <p class="text-gray-600">Sandboxed execution and validation</p>
            </div>
        </div>
        
        <div class="space-x-4">
            <a href="{{ route('dashboard') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <i class="fas fa-tachometer-alt mr-2"></i>
                View Dashboard
            </a>
            
            <a href="/api/dashboard/stats" class="bg-gray-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-700 transition-colors">
                <i class="fas fa-chart-bar mr-2"></i>
                API Stats
            </a>
        </div>
    </div>
</div>
@endsection