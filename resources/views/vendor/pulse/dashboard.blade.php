@extends('app')

@section('content')
<div id="developer-app" class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div id="developer-navbar" data-user="{{ json_encode(auth()->user()) }}"></div>
    
    <main class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-6">Infrastructure Performance</h1>
            
            <iframe src="/pulse/view" class="w-full min-h-[80vh] border-0 rounded-lg" id="pulse-frame"></iframe>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const navbarTarget = document.getElementById('developer-navbar');
        if (navbarTarget && window.renderDeveloperNavbar) {
            const userData = JSON.parse(navbarTarget.getAttribute('data-user'));
            window.renderDeveloperNavbar(navbarTarget, userData);
        }
    });
</script>
@endsection