<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Denver Pub Finder with MongoDB</title>
    <link href="https://fonts.googleapis.com/css?family=Arial" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans">

    <div class="container mx-auto p-6 max-w-6xl">
        <!-- Header -->
        <div class="flex justify-center items-center mb-8">
            <img src="{{ asset('laravelimage.png') }}" class="h-16 mr-4" alt="Laravel">
            <h1 class="text-4xl font-bold text-red-600">DENVER PUB FINDER WITH</h1>
            <img src="{{ asset('mongodbImage.png') }}" class="h-12 ml-4" alt="MongoDB">
        </div>

        <!-- Search Bar -->
        <div class="flex justify-center mb-10">
            <form method="POST" action="{{ route('search.perform') }}" class="flex w-full max-w-2xl">
                @csrf
                <input type="text" name="query" placeholder="Guinness with outdoor seating"
                    value="{{ $query ?? '' }}"
                    class="flex-1 border-2 border-gray-300 rounded-l-lg px-6 py-3 text-lg focus:outline-none focus:border-red-500">
                <button type="submit" class="bg-red-500 text-white px-8 py-3 rounded-r-lg hover:bg-red-600 font-semibold text-lg">
                    SEARCH
                </button>
            </form>
        </div>

        <!-- Search Results -->
        @if (!empty($results))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach ($results as $result)
                    <div class="bg-white border border-gray-200 rounded-lg p-6 shadow-lg hover:shadow-xl transition-shadow">
                        <!-- Pub Name -->
                        <h2 class="text-2xl font-bold text-gray-800 mb-3">{{ $result['name'] }}</h2>
                        
                        <!-- Rating Stars -->
                        <div class="flex items-center mb-3">
                            @for ($i = 1; $i <= 5; $i++)
                                @if ($i <= floor($result['rating']))
                                    <span class="text-yellow-400 text-xl">★</span>
                                @else
                                    <span class="text-gray-300 text-xl">★</span>
                                @endif
                            @endfor
                        </div>

                        <!-- Distance -->
                        <div class="flex items-center text-green-600 mb-4">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="font-medium">{{ $result['distance'] }}</span>
                        </div>

                        

                        <!-- Similarity Score -->
                        <div class="text-xs text-gray-500 pt-3 border-t border-gray-100">
                            <span class="font-medium">Similarity Score: {{ $result['similarityScore'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- No Results Message -->
        @if (empty($results) && isset($query))
            <div class="text-center py-12">
                <div class="text-gray-500 text-xl mb-4">No pubs found matching your search.</div>
                <div class="text-gray-400">Try different keywords or search terms.</div>
            </div>
        @endif

        <!-- Error Handling -->
        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>