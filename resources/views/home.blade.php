<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventarisasi BI Kalsel</title>
    
    <script src="https://cdn.tailwindcss.com?config={%22darkMode%22:%22class%22}"></script>

    <script>
        // Logika FOUC minimal untuk menghindari flicker saat memuat
        (function() {
            const theme = localStorage.getItem('color-theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <style>
        /* CSS Dibiarkan Sama */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100vh; }
        body { overflow-x: hidden; transition: color 0.3s, background-color 0.3s; }
        html { transition: color 0.3s, background-color 0.3s; }
        
        .image-hero-container { position: relative; width: 100%; height: 60vh; overflow: hidden; }
        .image-hero-container img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .image-hero-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); z-index: 1; }
        .image-hero-content { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 2; text-align: center; padding: 0 5%; }
        
        .image-hero-content h5 { font-size: 2rem; color: white; margin-bottom: 1rem; font-weight: bold; }
        .image-hero-content p { font-size: 1rem; color: white; margin-bottom: 1.5rem; max-width: 600px; }
        
        @media (min-width: 768px) {
            .image-hero-content h5 { font-size: 3.5rem; }
            .image-hero-content p { font-size: 1.5rem; }
        }
    </style>
</head>
<body class="bg-white dark:bg-gray-900 transition-colors duration-500 flex flex-col min-h-screen">
    
    <nav class="fixed top-0 z-10 w-full bg-gray-100 dark:bg-gray-800 shadow-md">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            
            <div class="flex items-center space-x-6">
                <a class="flex items-center" href="#">
                    <img src="/images/bi-logo.png" alt="Logo BI Kalsel" class="h-12"> 
                </a>
                
                <div class="hidden md:flex space-x-4">
                    <a class="text-gray-900 dark:text-gray-200 hover:text-[#045498] dark:hover:text-[#045498] transition" href="#top">Home</a>
                    <a class="text-gray-900 dark:text-gray-200 hover:text-[#045498] dark:hover:text-[#045498] transition" href="https://linktr.ee/BIKalsel" target="_blank">Sosmed</a>
                </div>
            </div>

            <div class="flex items-center space-x-4">
                
                <div class="hidden sm:flex">
                    @auth
                        <a href="{{ route('filament.admin.pages.dashboard') }}"
                        class="px-3 py-1.5 bg-[#045498] text-white rounded-md hover:bg-[#034479] transition font-semibold">
                            Lanjut ke Dashboard
                        </a>
                    @endauth
                    @guest
                        <a href="{{ route('filament.admin.auth.login') }}"
                        class="px-3 py-1.5 bg-[#045498] text-white rounded-md hover:bg-[#034479] transition font-semibold">
                            Masuk ke Dashboard
                        </a>
                    @endguest
                </div>

                <!-- <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-[#045498] focus:ring-opacity-50">
                    <svg id="sun-icon" class="h-6 w-6 text-yellow-500 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg id="moon-icon" class="h-6 w-6 text-gray-700 dark:text-gray-300 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
                
                <button id="mobile-menu-toggle" class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-[#045498] focus:ring-opacity-50">
                    <svg class="h-6 w-6 text-gray-800 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button> -->
            </div>
        </div>
        
        <div id="mobile-menu" class="hidden md:hidden px-4 pt-2 pb-3 space-y-1 bg-gray-200 dark:bg-gray-700">
            <a href="#" class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600">Home</a>
            <a href="https://linktr.ee/BIKalsel" target="_blank" class="block px-3 py-2 rounded-md text-base font-medium text-gray-900 dark:text-white hover:bg-gray-300 dark:hover:bg-gray-600">Sosmed</a>

            @auth
                <a href="{{ route('filament.admin.pages.dashboard') }}"
                class="block w-full text-center px-3 py-2 rounded-md text-base font-medium bg-[#045498] text-white hover:bg-[#034479] transition mt-2">
                    Lanjut ke Dashboard
                </a>
            @endauth
            @guest
                <a href="{{ route('filament.admin.auth.login') }}"
                class="block w-full text-center px-3 py-2 rounded-md text-base font-medium bg-[#045498] text-white hover:bg-[#034479] transition mt-2">
                    Masuk ke Dashboard
                </a>
            @endguest
        </div>
    </nav>
    
    <main class="flex-grow pt-16"> 
        <div class="image-hero-container mb-12">
            <img src="/images/carousel.png" alt="Bank Indonesia Kalimantan Selatan" loading="lazy">
            <div class="image-hero-overlay"></div> 
            
            <div class="image-hero-content">
                <h5>Sistem Manajemen Inventarisasi BI Kalsel</h5>
                <p class="text-sm leading-normal">Kelola, pantau, dan laporkan semua aset inventaris Bank Indonesia Kalimantan Selatan secara terpusat. Akses data terkini kini lebih mudah.</p>
            @auth
                <a href="{{ route('filament.admin.pages.dashboard') }}"
                class="px-6 py-3 bg-[#045498] hover:bg-[#034479] text-white font-semibold rounded-lg transition duration-300 shadow-lg inline-block text-center">
                    Lanjut ke Dashboard
                </a>
            @endauth
            @guest
                <a href="{{ route('filament.admin.auth.login') }}"
                class="px-6 py-3 bg-[#045498] hover:bg-[#034479] text-white font-semibold rounded-lg transition duration-300 shadow-lg inline-block text-center">
                    Masuk ke Dashboard
                </a>
            @endguest
            </div>
        </div>

        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Latar Belakang</h2>
            
            <div class="w-full">
                <div class="lg:flex bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-md overflow-hidden">
                    <div class="h-48 lg:h-auto lg:w-1/3 flex-none bg-cover text-center overflow-hidden" 
                        style="background-image: url('/images/card1.png')" 
                        title="Woman holding a mug">
                    </div>
                    
                    <div class="p-6 lg:w-2/3 flex flex-col justify-between leading-normal text-gray-900 dark:text-gray-300">
                        <div class="mb-8">
                            <div class="text-xl font-bold mb-2 text-gray-900 dark:text-white">Pengelolaan Gudang & ATK BI Kalsel: </div>
                            <p class="text-base text-gray-700 dark:text-gray-400">Sistem manual gudang dan ATK di Bank Indonesia Provinsi Kalimantan Selatan kurang efektif. Oleh karena itu, dibutuhkan website untuk mengelola stok, permintaan, dan laporan secara real-time guna meningkatkan efisiensi, transparansi, dan akuntabilitas operasional.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-100 dark:bg-gray-800 mt-12 py-6">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center text-gray-600 dark:text-gray-400 text-sm">
            &copy; 2025 Bank Indonesia Provinsi Kalimantan Selatan. All rights reserved. <br>
            <u><a href="https://linktr.ee/BIKalsel" target="_blank">https://linktr.ee/BIKalsel</a></u>
        </div>
    </footer>

    <script>
        // Pastikan script dijalankan setelah DOM loaded
        document.addEventListener('DOMContentLoaded', function() {
            const html = document.documentElement;
            const themeToggle = document.getElementById('theme-toggle');
            const sunIcon = document.getElementById('sun-icon');
            const moonIcon = document.getElementById('moon-icon');
            const themeKey = 'color-theme'; 

            // Fungsi untuk update tampilan icon
            function updateThemeIcons() {
                const isDark = html.classList.contains('dark');
                if (isDark) {
                    // Di dark mode: tampilkan moon icon
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                } else {
                    // Di light mode: tampilkan sun icon
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                }
            }

            // Inisialisasi icon saat pertama kali load
            updateThemeIcons();

            // Event listener untuk toggle theme (dengan pencegahan event bubbling jika diperlukan)
            if (themeToggle) {
                themeToggle.addEventListener('click', function(e) {
                    e.preventDefault(); // Cegah default behavior jika ada
                    e.stopPropagation(); // Cegah bubbling jika konflik dengan elemen lain
                    
                    const isCurrentlyDark = html.classList.contains('dark');

                    if (isCurrentlyDark) {
                        // Switch ke light mode
                        html.classList.remove('dark');
                        localStorage.setItem(themeKey, 'light');
                    } else {
                        // Switch ke dark mode
                        html.classList.add('dark');
                        localStorage.setItem(themeKey, 'dark');
                    }
                    
                    // Update icons setelah toggle (dengan delay kecil untuk transisi smooth)
                    setTimeout(updateThemeIcons, 50);
                });
            }

            // Mobile Menu Toggle
            const mobileToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileToggle && mobileMenu) {
                mobileToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('hidden');
                });
            }

            // Tutup mobile menu jika klik di luar (opsional, untuk UX lebih baik)
            document.addEventListener('click', function(e) {
                if (!mobileMenu.contains(e.target) && !mobileToggle.contains(e.target)) {
                    mobileMenu.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>
