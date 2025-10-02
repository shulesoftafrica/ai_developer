<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Kudos Orchestrator')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-blue-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-xl font-bold">
                        <i class="fas fa-robot mr-2"></i>
                        Kudos AI Orchestrator
                    </h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">
                        <i class="fas fa-clock mr-1"></i>
                        {{ now()->format('Y-m-d H:i:s') }}
                    </span>
                    <div id="connection-status" class="flex items-center">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                        <span class="text-sm">Connected</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white text-center py-4 mt-8">
        <p>&copy; 2024 Kudos AI Development Orchestrator. Built with Laravel & AI.</p>
    </footer>

    <!-- Scripts -->
    <script>
        // Auto-refresh page data every 30 seconds
        setInterval(function() {
            if (typeof refreshData === 'function') {
                refreshData();
            }
        }, 30000);

        // Check connection status
        function checkConnection() {
            axios.get('/health')
                .then(function(response) {
                    document.getElementById('connection-status').innerHTML = 
                        '<div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div><span class="text-sm">Connected</span>';
                })
                .catch(function(error) {
                    document.getElementById('connection-status').innerHTML = 
                        '<div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div><span class="text-sm">Disconnected</span>';
                });
        }

        // Check connection every 10 seconds
        setInterval(checkConnection, 10000);
    </script>

    @yield('scripts')
</body>
</html>